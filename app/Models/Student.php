<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'institutional_code',
        'full_name',
        'photo_path',
        'active',
    ];

    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Contribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** @return HasMany<Strategy, $this> */
    public function strategies(): HasMany
    {
        return $this->hasMany(Strategy::class);
    }

    /** @return HasMany<Commitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    /** @return HasMany<ExternalSupport, $this> */
    public function externalSupports(): HasMany
    {
        return $this->hasMany(ExternalSupport::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<CommitteeDecision, $this> */
    public function committeeDecisions(): HasMany
    {
        return $this->hasMany(CommitteeDecision::class);
    }

    /** @return HasMany<DisciplinaryCase, $this> */
    public function disciplinaryCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class);
    }

    /** Matrícula del estudiante en un año escolar dado (por defecto, el activo). */
    public function enrollmentFor(?SchoolYear $schoolYear = null): ?Enrollment
    {
        $schoolYear ??= SchoolYear::where('is_active', true)->first();

        if (! $schoolYear) {
            return null;
        }

        return $this->enrollments()->where('school_year_id', $schoolYear->id)->first();
    }

    public function currentGroup(): ?Group
    {
        return $this->enrollmentFor()?->group;
    }

    /**
     * Asignaturas a tener en cuenta en un periodo: toda asignatura con nivel <= al umbral
     * configurado (config('seguimiento.nivel_atencion')). No se almacena: se calcula.
     *
     * @return Collection<int, Grade>
     */
    public function subjectsNeedingAttention(int $schoolYearId, int $period)
    {
        $threshold = config('seguimiento.nivel_atencion', 3);

        return $this->grades()
            ->where('school_year_id', $schoolYearId)
            ->where('period', $period)
            ->whereNotNull('level')
            ->where('level', '<=', $threshold)
            ->with('subject')
            ->get();
    }

    /**
     * Responsables de la ficha: se arma a partir de las estrategias y los compromisos
     * activos/abiertos del estudiante (sección 5).
     *
     * @return Collection<int, string>
     */
    public function responsibles()
    {
        $fromStrategies = $this->strategies()->whereNotNull('responsible')->where('responsible', '!=', '')->pluck('responsible');
        $fromCommitments = $this->commitments()->whereNotNull('responsible')->where('responsible', '!=', '')->pluck('responsible');

        return $fromStrategies->merge($fromCommitments)->unique()->values();
    }
}
