<?php

namespace App\Models;

use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use HasFactory;

    protected $fillable = [
        'school_year_id',
        'period',
        'number',
    ];

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return HasMany<GroupMeeting, $this> */
    public function groupMeetings(): HasMany
    {
        return $this->hasMany(GroupMeeting::class);
    }

    /** @return HasMany<Contribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** @return HasMany<Strategy, $this> */
    public function strategies(): HasMany
    {
        return $this->hasMany(Strategy::class);
    }

    /** Etiqueta "Periodo 1 · Seguimiento 1". */
    public function label(): string
    {
        return "Periodo {$this->period} · Seguimiento {$this->number}";
    }

    /** Etiqueta corta "P1·S1". */
    public function shortLabel(): string
    {
        return "P{$this->period}·S{$this->number}";
    }
}
