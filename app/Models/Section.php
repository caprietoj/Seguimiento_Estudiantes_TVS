<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /** @return HasMany<Group, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Usuarios EMC cuyo alcance está limitado a esta sección.
     *
     * @return HasMany<User, $this>
     */
    public function emcUsers(): HasMany
    {
        return $this->hasMany(User::class, 'emc_section_id');
    }
}
