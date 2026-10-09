<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = ['name', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return int[] Grados (6-11) asignados en grade_subject. Vacío = aplica a todos. */
    public function gradeLevels(): array
    {
        return DB::table('grade_subject')->where('subject_id', $this->id)->pluck('grade')
            ->map(fn ($g) => (int) $g)->all();
    }

    /** @param  int[]  $grades */
    public function syncGradeLevels(array $grades): void
    {
        DB::table('grade_subject')->where('subject_id', $this->id)->delete();
        if ($grades !== []) {
            DB::table('grade_subject')->insert(
                array_map(fn ($g) => ['subject_id' => $this->id, 'grade' => $g], $grades)
            );
        }
    }

    /** Una asignatura sin grados asignados en grade_subject aplica a todos los grados. */
    public function appliesToGrade(int $grade): bool
    {
        $levels = $this->gradeLevels();

        return $levels === [] || in_array($grade, $levels, true);
    }

    /**
     * @param  Builder<Subject>  $query
     * @return Builder<Subject>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Asignaturas activas que aplican al grado dado (sin filas en grade_subject, o con una fila para ese grado).
     *
     * @param  Builder<Subject>  $query
     * @return Builder<Subject>
     */
    public function scopeForGrade(Builder $query, int $grade): Builder
    {
        return $query->where(function (Builder $q) use ($grade) {
            $q->whereNotIn('id', DB::table('grade_subject')->select('subject_id'))
                ->orWhereIn('id', DB::table('grade_subject')->select('subject_id')->where('grade', $grade));
        });
    }
}
