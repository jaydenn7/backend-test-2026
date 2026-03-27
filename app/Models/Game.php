<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property Collection $revealedTiles
 * @property Carbon|null $finished_at
 * @property int $campaign_id
 * @property string $segment
 * @property int|null $prize_id
 */
class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'prize_id',
        'account',
        'segment',
        'finished_at',
    ];

    public static function filter(?string $account = null, ?int $prizeId = null, ?string $fromDate = null, ?string $tillDate = null)
    {
        $query = self::query();
        $campaign = Campaign::find(session('activeCampaign'));

        // When filtering by dates, keep in mind `finished_at` should be stored in Campaign timezone

        return $query;
    }

    public function campaign() : BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function prize() : BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function revealedTiles() : HasMany
    {
        return $this->hasMany(RevealedTile::class);
    }

    protected function casts() : array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }
}
