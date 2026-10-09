<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Administración · Secciones (ej. "PEP (Preescolar–4.°)", "PAI (5.°–9.°)", "DP (10.°–11.°)"). */
class SectionController extends Controller
{
    public function index(): Response
    {
        $sections = Section::withCount(['groups', 'emcUsers'])->orderBy('name')->get();

        return Inertia::render('admin/secciones', [
            'sections' => $sections->map(fn (Section $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'groups_count' => $s->groups_count,
                'emc_users_count' => $s->emc_users_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sections,name'],
        ]);

        Section::create($data);

        return back();
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sections,name,'.$section->id],
        ]);

        $section->update($data);

        return back();
    }

    public function destroy(Section $section): RedirectResponse
    {
        if ($section->groups()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar: hay grupos asignados a esta sección.',
            ]);
        }

        $section->delete();

        return back();
    }
}
