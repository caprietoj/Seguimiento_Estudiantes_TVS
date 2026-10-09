<?php

namespace App\Models;

use Database\Factories\ExternalSupportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalSupport extends Model
{
    /** @use HasFactory<ExternalSupportFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'provider',
        'specialty',
        'frequency',
        'contact',
        'notes',
        'author_id',
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
