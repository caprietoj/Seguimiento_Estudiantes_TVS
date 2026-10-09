<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de grados del colegio (Preescolar = valor 0 hasta 11.°), gestionado
 * desde /admin/grados. Los grupos y el pivot grade_subject guardan el `value`
 * como entero; la etiqueta (`name`) es la que se muestra en la interfaz y en la
 * ficha/PDF.
 */
class GradeLevel extends Model
{
    protected $fillable = ['value', 'name', 'active'];

    /** @var array<string, string> */
    protected $casts = ['value' => 'integer', 'active' => 'boolean'];

    /**
     * @param  Builder<GradeLevel>  $query
     * @return Builder<GradeLevel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('value');
    }

    /** @return array<int, string> value => name de todos los grados, en orden. */
    public static function options(): array
    {
        return self::query()->orderBy('value')->pluck('name', 'value')->all();
    }

    /** @return array<int, string> value => name solo de los grados activos. */
    public static function activeOptions(): array
    {
        return self::query()->active()->pluck('name', 'value')->all();
    }

    /** Etiqueta de un valor (10 → "10.°", 0 → "Preescolar"; fuera del catálogo → genérica). */
    public static function labelFor(int $value): string
    {
        $name = self::query()->where('value', $value)->value('name');

        if (is_string($name)) {
            return $name;
        }

        return $value === 0 ? 'Preescolar' : $value.'.°';
    }
}
