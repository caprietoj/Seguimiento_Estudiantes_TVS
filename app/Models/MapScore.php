<?php

namespace App\Models;

use App\Enums\MapPerformanceLevel;
use App\Enums\MapSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $school_year_id
 * @property string $subject
 * @property int $period
 * @property int|null $rit_score
 * @property int|null $percentile
 * @property MapPerformanceLevel|null $performance_level
 * @property int|null $recorded_by
 * @property int|null $import_batch_id
 * @property string|null $source_file
 * @property int|null $source_row
 */
class MapScore extends Model
{
    protected $fillable = [
        'student_id',
        'school_year_id',
        'subject',
        'period',
        'rit_score',
        'percentile',
        'performance_level',
        'recorded_by',
        'import_batch_id',
        'source_file',
        'source_row',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'subject' => 'string',
        'period' => 'integer',
        'rit_score' => 'integer',
        'percentile' => 'integer',
        'performance_level' => MapPerformanceLevel::class,
    ];

    /**
     * Retorna la etiqueta legible del área evaluada según la configuración activa.
     */
    public function subjectLabel(): string
    {
        $areas = Setting::mapAreas(false);
        foreach ($areas as $area) {
            if ($area['key'] === $this->subject) {
                return $area['name'];
            }
        }

        return MapSubject::tryFrom($this->subject)?->label() ?? ucfirst($this->subject);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
