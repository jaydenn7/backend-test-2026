<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Prize;

class PrizeSelector
{
    public function handle(Game $game) : Prize|null
    {
        return Prize::query()
            ->where("campaign_id", $game->campaign_id)
            ->where("segment", $game->segment)
            ->withCount("winsToday")
            ->having(fn ($query) => $query
                ->havingNull("daily_cap")
                ->orHavingRaw("wins_today_count < daily_cap")
            )
            ->orderByRaw("-LOG(RAND()) / weight")
            ->first();
    }
}