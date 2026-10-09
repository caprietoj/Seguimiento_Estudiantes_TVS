<?php

namespace App\Models;

use App\Enums\CommitteeDecision as CommitteeDecisionEnum;
use Database\Factories\CommitteeDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommitteeDecision extends Model
{
    /** @use HasFactory<CommitteeDecisionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'decided_on',
        'decision',
        'notes',
        'author_id',
    ];

    protected $casts = [
        'decided_on' => 'date',
        'decision' => CommitteeDecisionEnum::class,
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
}
