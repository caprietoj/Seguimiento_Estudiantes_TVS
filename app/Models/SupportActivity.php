<?php

namespace App\Models;

use App\Enums\SupportActivityType;
use App\Enums\SupportArea;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una actividad del Departamento de Apoyo: un estudiante en seguimiento, una familia atendida,
 * una reunión, una intervención en crisis… `quantity` permite registrar cifras agregadas (p. ej.
 * «3 reuniones de seguimiento en 7A») cuando no se registra una por una.
 *
 * @property int $id
 * @property int $school_year_id
 * @property SupportArea $area
 * @property SupportActivityType $type
 * @property int|null $group_id
 * @property int|null $student_id
 * @property CarbonInterface|null $occurred_on
 * @property int|null $semester
 * @property int $quantity
 * @property string|null $description
 * @property int $author_id
 * @property int|null $import_batch_id
 */
class SupportActivity extends Model
{
    protected $fillable = [
        'school_year_id',
        'area',
        'type',
        'group_id',
        'student_id',
        'occurred_on',
        'semester',
        'quantity',
        'description',
        'author_id',
    ];

    protected $casts = [
        'area' => SupportArea::class,
        'type' => SupportActivityType::class,
        'occurred_on' => 'date',
        'semester' => 'integer',
        'quantity' => 'integer',
    ];

    /** Semestre escolar de una fecha: julio–diciembre = I, enero–junio = II. */
    public static function semesterFor(?CarbonInterface $date): ?int
    {
        if ($date === null) {
            return null;
        }

        return $date->month >= 7 ? 1 : 2;
    }

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

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
