<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $image
 * @property int $id
 * @property int $daily_cap
 * @property float $weight
 */
class Prize extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'segment',
        'weight',
        'image',
        'starts_at',
        'ends_at',
        'daily_cap',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function search($query)
    {
        return empty($query) ? static::query()
            : static::where('name', 'like', '%'.$query.'%');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function winsToday() : HasMany
    {
        return $this->hasMany(Game::class)->whereDate("finished_at", Carbon::today());
    }
}
