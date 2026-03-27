<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use App\Models\RevealedTile;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GameServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GameService::class);
    }

    public function test_creates_new_game_if_none_exists()
    {
        $campaign = Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        $game = $this->service->handle($campaign, "account1", "low");

        $this->assertDatabaseHas("games", [
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
            "finished_at" => null,
        ]);

        $this->assertNotNull($game->id);
    }

    public function test_returns_existing_unfinished_game()
    {
        $campaign = Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        $existingGame = Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
            "finished_at" => null,
        ]);

        $game = $this->service->handle($campaign, "account1", "low");

        $this->assertEquals($existingGame->id, $game->id);
        $this->assertDatabaseCount("games", 1);
    }

    public function test_creates_new_game_if_previous_finished()
    {
        $campaign = Campaign::query()->create([
            "timezone" => "Europe/London",
            "name" => "Test Campaign",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
            "finished_at" => now(),
        ]);

        $game = $this->service->handle($campaign, "account1", "low");

        $this->assertDatabaseCount("games", 2);
        $this->assertNull($game->finished_at);
    }

    public function test_throws_if_campaign_not_started()
    {
        $campaign = Campaign::query()->create([
            "name" => "Future Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->addDay(),
            "ends_at" => now()->addDays(2),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Campaign has not started");

        $this->service->handle($campaign, "account1", "low");
    }

    public function test_throws_if_campaign_ended()
    {
        $campaign = Campaign::query()->create([
            "name" => "Past Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->subDays(3),
            "ends_at" => now()->subDay(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Campaign has ended");

        $this->service->handle($campaign, "account1", "low");
    }

    public function test_loads_revealed_tiles_with_prizes()
    {
        $campaign = Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "Europe/London",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        $game = Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
            "finished_at" => null,
        ]);

        $prize = Prize::query()->create([
            "campaign_id" => $campaign->id,
            "name" => "Test Prize",
            "segment" => "low",
            "weight" => 1,
            "daily_cap" => 10,
            "image" => "assets/test.png",
        ]);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 5,
            "prize_id" => $prize->id,
        ]);

        $game = $this->service->handle($campaign, "account1", "low");

        $this->assertTrue($game->relationLoaded("revealedTiles"));
        $this->assertTrue($game->revealedTiles->first()->relationLoaded("prize"));
        $this->assertEquals(5, $game->revealedTiles->first()->tile_index);
    }
}