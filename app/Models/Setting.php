<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Obtiene el valor de una configuración o su valor por defecto.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if ($setting === null || $setting->value === null) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    /**
     * Guarda o actualiza el valor de una configuración.
     */
    public static function set(string $key, mixed $value): void
    {
        $encoded = is_array($value) || is_object($value) || is_bool($value) || is_numeric($value)
            ? json_encode($value)
            : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $encoded]
        );
    }

    /**
     * Áreas evaluadas por defecto para pruebas estandarizadas MAP Growth (NWEA).
     *
     * @return list<array{key: string, name: string, active: bool}>
     */
    public static function defaultMapAreas(): array
    {
        return [
            ['key' => 'math', 'name' => 'Matemáticas (Math)', 'active' => true],
            ['key' => 'reading', 'name' => 'Lectura (Reading)', 'active' => true],
            ['key' => 'language', 'name' => 'Uso del Lenguaje (Language Usage)', 'active' => true],
            ['key' => 'science', 'name' => 'Ciencias (Science)', 'active' => true],
        ];
    }

    /**
     * Retorna las áreas evaluadas de MAP configuradas (o las por defecto).
     *
     * @return list<array{key: string, name: string, active: bool}>
     */
    public static function mapAreas(bool $onlyActive = true): array
    {
        $defaults = static::defaultMapAreas();

        /** @var mixed $stored */
        $stored = static::get('map_areas', $defaults);

        if (! is_array($stored) || empty($stored)) {
            $stored = $defaults;
        }

        $areas = [];
        foreach ($stored as $item) {
            if (! is_array($item) || empty($item['key']) || empty($item['name'])) {
                continue;
            }
            $active = isset($item['active']) ? (bool) $item['active'] : true;
            if ($onlyActive && ! $active) {
                continue;
            }
            $areas[] = [
                'key' => (string) $item['key'],
                'name' => (string) $item['name'],
                'active' => $active,
            ];
        }

        return ! empty($areas) || ! $onlyActive ? $areas : $defaults;
    }
}
