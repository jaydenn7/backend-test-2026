<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Prize $prize
 * @property int $tile_index
 */
class RevealedTile extends Model
{
    protected $fillable = [
        "game_id",
        "tile_index",
        "prize_id",
    ];

    public function prize() : BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function game() : BelongsTo
    {
        return $this->BelongsTo(Game::class);
    }
}
