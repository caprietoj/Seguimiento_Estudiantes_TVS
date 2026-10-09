<?php

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Matrícula de un estudiante en un grupo para un año escolar. Es la tabla intermedia de
 * Group::students() (belongsToMany), pero también un modelo de primera clase: tiene su
 * propio id autoincremental y columnas de origen para la migración (sección 7).
 */
class Enrollment extends Pivot
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'enrollments';

    protected $fillable = [
        'student_id',
        'group_id',
        'school_year_id',
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

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
