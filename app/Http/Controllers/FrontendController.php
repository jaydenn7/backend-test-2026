<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\RevealedTile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class FrontendController extends Controller
{
    public function loadCampaign(Request $request, Campaign $campaign) : View
    {
        $validator = Validator::make($request->all(), [
            "a" => ["required", "string"],
            "segment" => ["required", "in:low,medium,high"],
        ]);

        if ($validator->fails()) {
            return $this->response(message: $validator->errors()->first());
        }

        if (now()->lt($campaign->starts_at)) {
            return $this->response(message: "Campaign has not started");
        }

        if (now()->gt($campaign->ends_at)) {
            return $this->response(message: "Campaign has ended");
        }

        $data = $validator->validated();

        $game = Game::query()
            ->with("revealed_tiles.prize")
            ->where("campaign_id", $campaign->id)
            ->where("account", $data["a"])
            ->where("segment", $data["segment"])
            ->whereNull("finished_at")
            ->first();

        if (!$game) {
            $game = Game::query()->create([
                "campaign_id" => $campaign->id,
                "account" => $data["a"],
                "segment" => $data["segment"],
            ]);

            $game->load(["revealed_tiles.prize"]);
        }

        return $this->response(game: $game);
    }

    public function placeholder() : View
    {
        return view("frontend.placeholder");
    }

    private function response(?Game $game = null, ?string $message = null) : View
    {
        return view("frontend.index", [
            "config" => json_encode([
                "apiPath" => "/api/flip",
                "gameId" => $game?->id,
                "revealedTiles" => $game?->revealed_tiles?->map(fn (RevealedTile $revealedTile) => [
                        "index" => $revealedTile->tile_index,
                        "image" => $revealedTile->prize->image,
                    ])->values()->all() ?? [],
                "message" => $message,
            ]),
        ]);
    }
}