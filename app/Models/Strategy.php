<?php

namespace App\Models;

use App\Enums\StrategyType;
use Database\Factories\StrategyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Strategy extends Model
{
    /** @use HasFactory<StrategyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'student_id',
        'group_id',
        'follow_up_id',
        'body',
        'responsible',
        'author_id',
        'is_active',
    ];

    protected $casts = [
        'type' => StrategyType::class,
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<FollowUp, $this> */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return HasMany<StrategyReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(StrategyReview::class)->orderBy('reviewed_on');
    }

    public function lastReview(): ?StrategyReview
    {
        return $this->reviews()->latest('reviewed_on')->first();
    }
}
