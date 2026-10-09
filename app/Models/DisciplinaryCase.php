<?php

namespace App\Models;

use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryCaseType;
use Database\Factories\DisciplinaryCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisciplinaryCase extends Model
{
    /** @use HasFactory<DisciplinaryCaseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'occurred_on',
        'type',
        'description',
        'action_taken',
        'status',
        'author_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'type' => DisciplinaryCaseType::class,
        'status' => DisciplinaryCaseStatus::class,
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
