<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeConfigController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/calificaciones', [
            'config' => [
                'ib_escala_min' => (int) Setting::get('ib_escala_min', config('seguimiento.nivel_minimo', 1)),
                'ib_escala_max' => (int) Setting::get('ib_escala_max', config('seguimiento.nivel_maximo', 7)),
                'ib_nivel_atencion' => (int) Setting::get('ib_nivel_atencion', config('seguimiento.nivel_atencion', 3)),
                'ib_periodos' => (int) Setting::get('ib_periodos', config('seguimiento.periodos', 3)),
                'map_periodos' => (int) Setting::get('map_periodos', 2),
                'map_corte_bajo' => (int) Setting::get('map_corte_bajo', 21),
                'map_corte_promedio_bajo' => (int) Setting::get('map_corte_promedio_bajo', 40),
                'map_corte_promedio' => (int) Setting::get('map_corte_promedio', 60),
                'map_corte_promedio_alto' => (int) Setting::get('map_corte_promedio_alto', 80),
                'map_areas' => Setting::mapAreas(false),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ib_escala_min' => ['required', 'integer', 'min:1', 'max:10'],
            'ib_escala_max' => ['required', 'integer', 'gt:ib_escala_min', 'max:100'],
            'ib_nivel_atencion' => ['required', 'integer', 'gte:ib_escala_min', 'lte:ib_escala_max'],
            'ib_periodos' => ['required', 'integer', 'min:1', 'max:6'],
            'map_periodos' => ['required', 'integer', 'min:1', 'max:4'],
            'map_corte_bajo' => ['required', 'integer', 'min:1', 'max:99'],
            'map_corte_promedio_bajo' => ['required', 'integer', 'gt:map_corte_bajo', 'max:99'],
            'map_corte_promedio' => ['required', 'integer', 'gt:map_corte_promedio_bajo', 'max:99'],
            'map_corte_promedio_alto' => ['required', 'integer', 'gt:map_corte_promedio', 'max:99'],
            'map_areas' => ['required', 'array', 'min:1'],
            'map_areas.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'map_areas.*.name' => ['required', 'string', 'max:100'],
            'map_areas.*.active' => ['required', 'boolean'],
        ]);

        /** @var list<array{key: string, name: string, active: bool}> $areas */
        $areas = $data['map_areas'];
        $keys = array_map(fn (array $a): string => (string) $a['key'], $areas);
        if (count($keys) !== count(array_unique($keys))) {
            return back()->withErrors(['map_areas' => 'Los identificadores de las áreas evaluadas deben ser únicos.']);
        }

        $hasActive = false;
        foreach ($areas as $a) {
            if (! empty($a['active'])) {
                $hasActive = true;
                break;
            }
        }
        if (! $hasActive) {
            return back()->withErrors(['map_areas' => 'Debe haber al menos un área evaluada activa.']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Configuración de calificaciones guardada correctamente.');
    }
}
