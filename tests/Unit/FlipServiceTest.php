<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use App\Models\RevealedTile;
use App\Services\FlipService;
use App\Services\PrizeSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FlipServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FlipService::class);
    }

    private function createGame(): Game
    {
        $campaign = Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        return Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
        ]);
    }

    private function createPrize(Game $game, string $name = "Prize"): Prize
    {
        return Prize::query()->create([
            "campaign_id" => $game->campaign_id,
            "name" => $name,
            "segment" => "low",
            "weight" => 1,
            "daily_cap" => 10,
            "image" => "assets/test.png",
        ]);
    }

    public function test_revealing_same_tile_returns_same_prize()
    {
        $game = $this->createGame();
        $prize = $this->createPrize($game);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 1,
            "prize_id" => $prize->id,
        ]);

        $selector = $this->mock(PrizeSelector::class);
        $selector->shouldNotReceive("handle");

        $result = $this->service->handle($game, $selector, 1);

        $this->assertEquals($prize->id, $result->prize->id);
    }

    public function test_revealing_new_tile_creates_tile()
    {
        $game = $this->createGame();
        $prize = $this->createPrize($game);

        $selector = $this->mock(PrizeSelector::class);
        $selector->shouldReceive("handle")->once()->andReturn($prize);

        $this->service->handle($game, $selector, 2);

        $this->assertDatabaseHas("revealed_tiles", [
            "game_id" => $game->id,
            "tile_index" => 2,
            "prize_id" => $prize->id,
        ]);
    }

    public function test_three_matching_tiles_wins_game()
    {
        $game = $this->createGame();
        $prize = $this->createPrize($game);

        RevealedTile::query()->create(["game_id" => $game->id, "tile_index" => 1, "prize_id" => $prize->id]);
        RevealedTile::query()->create(["game_id" => $game->id, "tile_index" => 2, "prize_id" => $prize->id]);

        $selector = $this->mock(PrizeSelector::class);
        $selector->shouldReceive("handle")->once()->andReturn($prize);

        $result = $this->service->handle($game, $selector, 3);

        $this->assertEquals("You won a prize!", $result->message);
        $this->assertNotNull($game->fresh()->finished_at);
        $this->assertEquals($prize->id, $game->fresh()->prize_id);
    }

    public function test_game_cannot_be_flipped_if_finished()
    {
        $game = $this->createGame();
        $game->finished_at = now();
        $game->save();

        $selector = $this->mock(PrizeSelector::class);

        $this->expectException(InvalidArgumentException::class);

        $this->service->handle($game, $selector, 1);
    }

    public function test_flipping_same_tile_twice_is_idempotent()
    {
        $game = $this->createGame();
        $prize = $this->createPrize($game);

        $selector = $this->mock(PrizeSelector::class);
        $selector->shouldReceive("handle")->once()->andReturn($prize);

        $this->service->handle($game, $selector, 5);
        $this->service->handle($game, $selector, 5);

        $this->assertDatabaseCount("revealed_tiles", 1);
    }

    public function test_game_lost_when_board_full_without_three_matches()
    {
        $game = $this->createGame();

        // Fill 24 tiles with different prizes (no matches)
        for ($i = 0; $i < 24; $i++) {
            $prize = $this->createPrize($game, "Prize ".$i);

            RevealedTile::query()->create([
                "game_id" => $game->id,
                "tile_index" => $i,
                "prize_id" => $prize->id,
            ]);
        }

        $lastPrize = $this->createPrize($game, "Last Prize");

        $selector = $this->mock(PrizeSelector::class);
        $selector->shouldReceive("handle")->once()->andReturn($lastPrize);

        $result = $this->service->handle($game, $selector, 24);

        $this->assertEquals("Better luck next time", $result->message);
        $this->assertNotNull($game->fresh()->finished_at);
    }
}