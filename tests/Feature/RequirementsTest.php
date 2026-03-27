<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use App\Models\RevealedTile;
use App\Services\FlipService;
use App\Services\GameService;
use App\Services\PrizeSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function createCampaign(): Campaign
    {
        return Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "UTC",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);
    }

    private function createGame(Campaign $campaign, string $segment = "low"): Game
    {
        return Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => $segment,
        ]);
    }

    private function createPrize(Game $game, array $overrides = []): Prize
    {
        return Prize::create(array_merge([
            "campaign_id" => $game->campaign_id,
            "name" => "Test Prize",
            "segment" => $game->segment,
            "weight" => 1,
            "daily_cap" => null,
            "image" => "assets/test.png",
        ], $overrides));
    }

    /** @test */
    public function it_creates_a_new_game_if_none_exists()
    {
        $campaign = $this->createCampaign();
        $service = app(GameService::class);

        $game = $service->handle($campaign, "account1", "low");

        $this->assertDatabaseHas("games", [
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
        ]);
    }

    /** @test */
    public function it_returns_existing_unfinished_game()
    {
        $campaign = $this->createCampaign();

        $existing = Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => "low",
        ]);

        $service = app(GameService::class);
        $game = $service->handle($campaign, "account1", "low");

        $this->assertEquals($existing->id, $game->id);
    }

    /** @test */
    public function it_throws_if_campaign_not_started()
    {
        $campaign = Campaign::query()->create([
            "name" => "Test",
            "timezone" => "UTC",
            "starts_at" => now()->addDay(),
            "ends_at" => now()->addDays(2),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(GameService::class)->handle($campaign, "account1", "low");
    }

    /** @test */
    public function revealed_tiles_are_persisted()
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame($campaign);

        $prize = $this->createPrize($game);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 1,
            "prize_id" => $prize->id,
        ]);

        $game->load("revealedTiles");

        $this->assertCount(1, $game->revealedTiles);
    }

    /** @test */
    public function flipping_same_tile_returns_same_prize()
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame($campaign);
        $prize = $this->createPrize($game);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 2,
            "prize_id" => $prize->id,
        ]);

        $flipService = app(FlipService::class);

        $result = $flipService->handle(
            $game,
            app(PrizeSelector::class),
            2
        );

        $this->assertEquals($prize->id, $result->prize->id);
    }

    /** @test */
    public function game_finishes_on_third_match()
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame($campaign);
        $prize = $this->createPrize($game);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 1,
            "prize_id" => $prize->id,
        ]);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => 2,
            "prize_id" => $prize->id,
        ]);

        $flipService = app(FlipService::class);

        $flipService->handle(
            $game,
            app(PrizeSelector::class),
            3
        );

        $this->assertNotNull($game->fresh()->finished_at);
        $this->assertEquals($prize->id, $game->fresh()->prize_id);
    }

    /** @test */
    public function daily_cap_prevents_prize_from_being_selected()
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame($campaign);

        $cappedPrize = $this->createPrize($game, [
            "daily_cap" => 1,
        ]);

        // Simulate prize already won today
        Game::query()->create([
            "campaign_id" => $game->campaign_id,
            "account" => "other",
            "segment" => $game->segment,
            "prize_id" => $cappedPrize->id,
            "finished_at" => now(),
        ]);

        $otherPrize = $this->createPrize($game);

        $selector = app(PrizeSelector::class);
        $prize = $selector->handle($game);

        $this->assertEquals($otherPrize->id, $prize->id);
    }

    /** @test */
    public function only_segment_prizes_are_selected()
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame($campaign, "low");

        Prize::query()->create([
            "campaign_id" => $game->campaign_id,
            "name" => "Wrong Segment Prize",
            "segment" => "high",
            "weight" => 1,
            "daily_cap" => null,
            "image" => "assets/test.png",
        ]);

        $validPrize = $this->createPrize($game);

        $selector = app(PrizeSelector::class);
        $prize = $selector->handle($game);

        $this->assertEquals($validPrize->id, $prize->id);
    }
}