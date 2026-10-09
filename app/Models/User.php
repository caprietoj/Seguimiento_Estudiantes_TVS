<?php

namespace App\Models;

use App\Enums\LoginMethod;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'must_change_password',
        'external_id',
        'login_method',
        'emc_section_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'must_change_password' => 'boolean',
        'login_method' => LoginMethod::class,
    ];

    /**
     * Sección a la que un usuario EMC limita su alcance. Null = ve todo el colegio.
     *
     * @return BelongsTo<Section, $this>
     */
    public function emcSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'emc_section_id');
    }

    /**
     * Grupos de los que este usuario es director(a). La dirección de grupo no es un rol.
     *
     * @return HasMany<Group, $this>
     */
    public function directedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'director_id');
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /**
     * Grupos en los que este usuario tiene al menos una asignación docente.
     *
     * @return Builder<Group>
     */
    public function teachingGroups(): Builder
    {
        return Group::whereIn('id', $this->teachingAssignments()->pluck('group_id'));
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
