<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'grade',
        'section_id',
        'school_year_id',
        'director_id',
    ];

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Estudiantes matriculados en este grupo. "enrollments" no es una tabla pivote pura
     * (tiene su propio modelo con school_year_id), pero la relación entre Group y Student
     * es N a N a través de ella: un grupo tiene muchos estudiantes y, a lo largo de los
     * años, un estudiante pasa por muchos grupos.
     *
     * @return BelongsToMany<Student, $this, Enrollment>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'group_id', 'student_id')
            ->using(Enrollment::class)
            ->withPivot('school_year_id');
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @return HasMany<Strategy, $this> */
    public function strategies(): HasMany
    {
        return $this->hasMany(Strategy::class);
    }

    /** @return HasMany<GroupMeeting, $this> */
    public function groupMeetings(): HasMany
    {
        return $this->hasMany(GroupMeeting::class);
    }

    public function isDirectedBy(User $user): bool
    {
        return $this->director_id === $user->id;
    }
}
