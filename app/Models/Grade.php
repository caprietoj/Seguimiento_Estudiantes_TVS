<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_year_id',
        'period',
        'level',
        'score',
        'scale_max',
        'performance',
        'recorded_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'score' => 'float',
        'scale_max' => 'integer',
    ];

    public const BAJO = 'Desempeño Bajo';

    public const BASICO = 'Desempeño Básico';

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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

    /**
     * Desempeño de Phidias para un promedio en la escala de la sección (5, 7 u 8), con los
     * rangos de `seguimiento.phidias_escalas` (sacados de cómo Phidias rotula cada valor).
     */
    public static function performanceFor(float $score, int $scaleMax): string
    {
        $ranges = config("seguimiento.phidias_escalas.{$scaleMax}") ?? config('seguimiento.phidias_escalas.8');

        foreach ($ranges as [$label, $operator, $min]) {
            if ($operator === '>' ? $score > $min : $score >= $min) {
                return $label;
            }
        }

        return self::BAJO;
    }

    /** Nota para mostrar: el promedio de Phidias con su escala («7,3/8») o el nivel 1–7. */
    public function display(): ?string
    {
        if ($this->score !== null) {
            return rtrim(rtrim(number_format($this->score, 1, ',', ''), '0'), ',').($this->scale_max ? '/'.$this->scale_max : '');
        }

        return $this->level !== null ? (string) $this->level : null;
    }

    /** Desempeño: el de Phidias si la nota vino de allá; si no, el equivalente del nivel 1–7. */
    public function performanceText(): ?string
    {
        return $this->performance ?? self::performanceLabel($this->level);
    }

    /** Asignatura a tener en cuenta: desempeño Bajo o Básico (Phidias) o nivel ≤ umbral (1–7). */
    public function needsAttention(?int $threshold = null): bool
    {
        if ($this->performance !== null) {
            return in_array($this->performance, [self::BAJO, self::BASICO], true);
        }

        return $this->level !== null && $this->level <= ($threshold ?? (int) config('seguimiento.nivel_atencion', 3));
    }

    /** Desempeño bajo (rojo en las alertas). */
    public function isLow(): bool
    {
        return $this->performance !== null ? $this->performance === self::BAJO : ($this->level !== null && $this->level <= 2);
    }

    /** Indicador cualitativo para un nivel 1–7 (1-2 Bajo, 3 Básico, 4 Alto, 5-7 Superior). */
    public static function performanceLabel(?int $level): ?string
    {
        return match (true) {
            $level === null => null,
            $level <= 2 => 'Desempeño Bajo',
            $level === 3 => 'Desempeño Básico',
            $level === 4 => 'Desempeño Alto',
            default => 'Desempeño Superior',
        };
    }

    /** Nivel 1-2 bajo, 3 en riesgo, 4 adecuado, 5-7 bueno a excelente (sección 6). */
    public function band(): string
    {
        if ($this->performance !== null) {
            return match ($this->performance) {
                self::BAJO => 'low',
                self::BASICO => 'risk',
                'Desempeño Alto' => 'adequate',
                default => 'good',
            };
        }

        return match (true) {
            $this->level === null => 'none',
            $this->level <= 2 => 'low',
            $this->level === 3 => 'risk',
            $this->level === 4 => 'adequate',
            default => 'good',
        };
    }
}
