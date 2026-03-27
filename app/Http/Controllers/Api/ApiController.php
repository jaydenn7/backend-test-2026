<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Prize;
use App\Services\FlipService;
use App\Services\PrizeSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{
    public function flip(Request $request, FlipService $flipService, PrizeSelector $prizeSelector) : JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "gameId" => ["required", "exists:games,id"],
            "tileIndex" => ["required", "integer", "min:0", "max:24"],
        ]);

        if ($validator->fails()) {
            return $this->response(message: $validator->errors()->first(), code: 400);
        }

        $validated = $validator->validated();

        /** @var Game $game */
        $game = Game::query()->findOrFail($validated["gameId"]);

        $result = $flipService->handle(
            game: $game,
            prizeSelector: $prizeSelector,
            tileIndex: $validated["tileIndex"]
        );

        return $this->response($result->prize, $result->message);
    }

    private function response(Prize|null $prize = null, string|null $message = null, int $code = 200) : JsonResponse
    {
        return response()->json([
            "tileImage" => $prize?->image,
            "message" => $message,
        ], $code);
    }
}