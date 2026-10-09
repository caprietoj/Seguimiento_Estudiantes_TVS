<?php

namespace App\Models;

use App\Enums\CommitmentStatus;
use App\Enums\CommitmentType;
use Database\Factories\CommitmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commitment extends Model
{
    /** @use HasFactory<CommitmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'type',
        'body',
        'responsible',
        'due_on',
        'status',
        'due_on_estimated',
        'author_id',
    ];

    protected $casts = [
        'type' => CommitmentType::class,
        'status' => CommitmentStatus::class,
        'due_on' => 'date',
        'due_on_estimated' => 'boolean',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** Vencido: abierto y con fecha límite ya pasada. */
    public function isLate(): bool
    {
        return $this->isOpen() && $this->due_on !== null && $this->due_on->isPast();
    }

    /**
     * @param  Builder<Commitment>  $query
     * @return Builder<Commitment>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [CommitmentStatus::Pending, CommitmentStatus::InProgress]);
    }

    /**
     * @param  Builder<Commitment>  $query
     * @return Builder<Commitment>
     */
    public function scopeLate(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_on')->where('due_on', '<', now()->toDateString());
    }
}
