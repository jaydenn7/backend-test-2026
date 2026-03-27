<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\RevealedTile;
use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class FrontendController extends Controller
{
    public function loadCampaign(Request $request, GameService $gameService, Campaign $campaign) : View
    {
        $validator = Validator::make($request->all(), [
            "a" => ["required", "string"],
            "segment" => ["required", "in:low,medium,high"],
        ]);

        if ($validator->fails()) {
            return $this->response(message: $validator->errors()->first());
        }

        $validated = $validator->validated();

        try {
            $game = $gameService->handle(
                campaign: $campaign,
                account: $validated["a"],
                segment: $validated["segment"],
            );
        } catch (InvalidArgumentException $exception) {
            return $this->response(message: $exception->getMessage());
        } catch (Throwable $throwable) {
            report($throwable);
            return $this->response(message: "Something went wrong!");
        }

        return $this->response($game);
    }

    public function placeholder() : View
    {
        return view("frontend.placeholder");
    }

    private function response(Game|null $game = null, string|null $message = null) : View
    {
        $revealedTiles = $game
            ? $game->revealedTiles
                ->map(fn (RevealedTile $tile) => [
                    "index" => $tile->tile_index,
                    "image" => $tile->prize->image,
                ])
                ->values()
                ->all()
            : [];

        return view("frontend.index", [
            "config" => json_encode([
                "apiPath" => "/api/flip",
                "gameId" => $game?->id,
                "revealedTiles" => $revealedTiles,
                "message" => $message,
            ]),
        ]);
    }
}