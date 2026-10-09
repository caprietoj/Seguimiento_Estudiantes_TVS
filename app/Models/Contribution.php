<?php

namespace App\Models;

use App\Enums\ContributionField;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contribution extends Model
{
    /** @use HasFactory<ContributionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'follow_up_id',
        'field',
        'body',
        'author_id',
    ];

    protected $casts = ['field' => ContributionField::class];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
}
