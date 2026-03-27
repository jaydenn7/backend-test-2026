<?php

namespace App\Services;

use App\Models\Game;
use App\Models\RevealedTile;
use Carbon\Carbon;
use InvalidArgumentException;

class FlipService
{
    public function handle(Game $game, PrizeSelector $prizeSelector, int $tileIndex): FlipResult
    {
        if ($game->finished_at) {
            throw new InvalidArgumentException("Game already finished");
        }

        $existingTile = RevealedTile::query()
            ->with("prize")
            ->where("game_id", $game->id)
            ->where("tile_index", $tileIndex)
            ->first();

        if ($existingTile) {
            return new FlipResult($existingTile->prize);
        }

        $prize = $prizeSelector->handle($game);

        if (!$prize) {
            return new FlipResult(message: "No prize available");
        }

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => $tileIndex,
            "prize_id" => $prize->id,
        ]);

        $matches = RevealedTile::query()
            ->where("game_id", $game->id)
            ->where("prize_id", $prize->id)
            ->count();

        if ($matches >= 3) {
            $game->finished_at = Carbon::now();
            $game->prize_id = $prize->id;
            $game->save();

            return new FlipResult($prize, "You won a prize!");
        }

        $totalTiles = RevealedTile::query()
            ->where("game_id", $game->id)
            ->count();

        if ($totalTiles >= 25) {
            $game->finished_at = Carbon::now();
            $game->save();

            return new FlipResult($prize, "Better luck next time");
        }

        return new FlipResult($prize);
    }
}