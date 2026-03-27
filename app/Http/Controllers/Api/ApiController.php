<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Prize;
use App\Models\RevealedTile;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{
    public function flip(Request $request)
    {
        Validator::validate($request->all(), [
            "gameId" => ["required", "exists:games,id"],
            "tileIndex" => ["required", "integer", "min:0", "max:24"],
        ]);

        $game = Game::query()->findOrFail($request->input("gameId"));

        if ($game->finished_at !== null) {
            throw new Exception("Game already finished");
        }

        $existingTile = RevealedTile::query()
            ->where("game_id", $game->id)
            ->where("tile_index", $request->input("tileIndex"))
            ->first();

        if ($existingTile) {
            return $this->response($existingTile->prize, null);
        }

        $prize = $this->generatePrize($game);

        RevealedTile::query()->create([
            "game_id" => $game->id,
            "tile_index" => $request->input("tileIndex"),
            "prize_id" => $prize->id,
        ]);

        $matches = RevealedTile::query()
            ->where("game_id", $game->id)
            ->where("prize_id", $prize->id)
            ->count();

        if ($matches >= 3) {
            $game->finished_at = now();
            $game->save();

            return $this->response($prize, "You won a prize!");
        }

        return $this->response($prize, null);
    }

    private function response(Prize $prize, ?string $message)
    {
        return response()->json(array_filter([
            "tileImage" => asset($prize->image),
            "message" => $message,
        ]));
    }

    private function generatePrize(Game $game): Prize
    {
        $prize = Prize::query()
            ->where("campaign_id", $game->campaign_id)
            ->where("segment", $game->segment)
            ->orderByRaw("-LOG(RAND()) / weight")
            ->first();

        if (!$prize) {
            throw new Exception("No prize found for game");
        }

        return $prize;
    }
}