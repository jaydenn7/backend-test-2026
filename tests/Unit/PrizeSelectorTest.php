<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use App\Services\PrizeSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrizeSelectorTest extends TestCase
{
    use RefreshDatabase;

    private PrizeSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = app(PrizeSelector::class);
    }

    private function createGame(string $segment = "low"): Game
    {
        $campaign = Campaign::query()->create([
            "name" => "Test Campaign",
            "timezone" => "UTC",
            "starts_at" => now()->subDay(),
            "ends_at" => now()->addDay(),
        ]);

        return Game::query()->create([
            "campaign_id" => $campaign->id,
            "account" => "account1",
            "segment" => $segment,
        ]);
    }

    private function createPrize(Game $game, array $overrides = []): Prize
    {
        return Prize::query()->create(array_merge([
            "campaign_id" => $game->campaign_id,
            "name" => "Test Prize",
            "segment" => $game->segment,
            "weight" => 1,
            "daily_cap" => null,
            "image" => "assets/test.png",
        ], $overrides));
    }

    public function test_selects_prize_for_correct_campaign_and_segment()
    {
        $game = $this->createGame("low");

        $validPrize = $this->createPrize($game);

        Prize::query()->create([
            "campaign_id" => $game->campaign_id,
            "name" => "Wrong Segment Prize",
            "segment" => "high",
            "weight" => 1,
            "daily_cap" => null,
            "image" => "assets/test.png",
        ]);

        $prize = $this->selector->handle($game);

        $this->assertNotNull($prize);
        $this->assertEquals($validPrize->id, $prize->id);
    }

    public function test_does_not_select_prize_if_daily_cap_reached()
    {
        $game = $this->createGame();

        $cappedPrize = $this->createPrize($game, [
            "daily_cap" => 1,
        ]);

        Game::query()->create([
            "campaign_id" => $game->campaign_id,
            "account" => "other",
            "segment" => $game->segment,
            "prize_id" => $cappedPrize->id,
            "finished_at" => now(),
        ]);

        $availablePrize = $this->createPrize($game);

        $prize = $this->selector->handle($game);

        $this->assertNotNull($prize);
        $this->assertEquals($availablePrize->id, $prize->id);
    }

    public function test_returns_null_if_all_prizes_capped()
    {
        $game = $this->createGame();

        $prize = $this->createPrize($game, [
            "daily_cap" => 1,
        ]);

        Game::query()->create([
            "campaign_id" => $game->campaign_id,
            "account" => "other",
            "segment" => $game->segment,
            "prize_id" => $prize->id,
            "finished_at" => now(),
        ]);

        $result = $this->selector->handle($game);

        $this->assertNull($result);
    }

    public function test_selects_prize_if_daily_cap_not_reached()
    {
        $game = $this->createGame();

        $prize = $this->createPrize($game, [
            "daily_cap" => 2,
        ]);

        Game::query()->create([
            "campaign_id" => $game->campaign_id,
            "account" => "other",
            "segment" => $game->segment,
            "prize_id" => $prize->id,
            "finished_at" => now(),
        ]);

        $selectedPrize = $this->selector->handle($game);

        $this->assertNotNull($selectedPrize);
        $this->assertEquals($prize->id, $selectedPrize->id);
    }

    public function test_only_finished_games_count_towards_daily_cap()
    {
        $game = $this->createGame();

        $prize = $this->createPrize($game, [
            "daily_cap" => 1,
        ]);

        Game::query()->create([
            "campaign_id" => $game->campaign_id,
            "account" => "other",
            "segment" => $game->segment,
            "prize_id" => $prize->id,
            "finished_at" => null,
        ]);

        $selectedPrize = $this->selector->handle($game);

        $this->assertNotNull($selectedPrize);
        $this->assertEquals($prize->id, $selectedPrize->id);
    }
}