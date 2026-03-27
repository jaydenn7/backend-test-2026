<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Game;
use InvalidArgumentException;

class GameService
{
    public function handle(Campaign $campaign, string $account, string $segment) : Game
    {
        if ($campaign->starts_at->isFuture()) {
            throw new InvalidArgumentException("Campaign has not started");
        }

        if ($campaign->ends_at->isNowOrPast()) {
            throw new InvalidArgumentException("Campaign has ended");
        }

        $game = Game::query()
            ->with("revealedTiles.prize")
            ->where("campaign_id", $campaign->id)
            ->where("account", $account)
            ->where("segment", $segment)
            ->whereNull("finished_at")
            ->first();

        if (!$game) {
            $game = Game::query()->create([
                "campaign_id" => $campaign->id,
                "account" => $account,
                "segment" => $segment,
            ]);

            $game->load("revealedTiles.prize");
        }

        return $game;
    }
}