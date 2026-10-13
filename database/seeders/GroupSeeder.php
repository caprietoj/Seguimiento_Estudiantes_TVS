<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crea los grupos reales del colegio por grado y les asigna el/la director(a)
 * de grupo. Los grupos marcados como "N.A." en el listado no existen y por eso
 * no se crean. Es idempotente: usa `updateOrCreate` por (code, school_year_id),
 * así que se puede volver a correr sin duplicar.
 *
 * Los directores se buscan por nombre en la tabla `users` (compartida con la
 * intranet): el listado viene como "APELLIDOS, NOMBRES" y `users.name` como
 * "Nombres Apellidos", por eso se compara por tokens (sin tildes ni mayúsculas).
 */
class GroupSeeder extends Seeder
{
    /**
     * Cada grupo: código, grado (valor de `grade_levels`), prefijo de la sección
     * y nombre del director(a) de grupo tal como aparece en el listado del colegio
     * (`null` si aún no tiene director asignado).
     *
     * @var list<array{code: string, grade: int, section: string, director: ?string}>
     */
    private const GROUPS = [
        ['code' => 'PKA',          'grade' => 0,  'section' => 'PEP', 'director' => null],
        ['code' => 'KA',           'grade' => 0,  'section' => 'PEP', 'director' => null],
        ['code' => 'Transición A', 'grade' => 0,  'section' => 'PEP', 'director' => 'Hurtado Veloz, Paola Estefanía'],
        ['code' => '1A',           'grade' => 1,  'section' => 'PEP', 'director' => 'CANO VALDES, GISELA AMALIA'],
        ['code' => '2A',           'grade' => 2,  'section' => 'PEP', 'director' => 'PEÑARANDA SUAREZ, CARMEN JULIANA'],
        ['code' => '3A',           'grade' => 3,  'section' => 'PEP', 'director' => 'YAÑEZ, JUAN FELIPE'],
        ['code' => '4A',           'grade' => 4,  'section' => 'PEP', 'director' => 'CARDENAS ARIAS, ANGELICA MARIA'],
        ['code' => '4B',           'grade' => 4,  'section' => 'PEP', 'director' => 'CARDENAS ARIAS, ANGELICA MARIA'],
        ['code' => '5A',           'grade' => 5,  'section' => 'PAI', 'director' => 'BELTRAN GARZÓN, MARTHA'],
        ['code' => '6A',           'grade' => 6,  'section' => 'PAI', 'director' => 'Ayala, ERIKA ALEJANDRA'],
        ['code' => '7A',           'grade' => 7,  'section' => 'PAI', 'director' => 'MARTÍNEZ, MARCELL'],
        ['code' => '7B',           'grade' => 7,  'section' => 'PAI', 'director' => 'FERNANDEZ PANQUEVA, ADRIANA'],
        ['code' => '8A',           'grade' => 8,  'section' => 'PAI', 'director' => 'REINOSO ESPINOSA, JULIAN DAVID'],
        ['code' => '8B',           'grade' => 8,  'section' => 'PAI', 'director' => 'RODRIGUEZ BARRERA, CLAUDIA MARCELA'],
        ['code' => '9A',           'grade' => 9,  'section' => 'PAI', 'director' => 'LAGOS, MARIA LUISA'],
        ['code' => '9B',           'grade' => 9,  'section' => 'PAI', 'director' => 'JIMENEZ CASTRO, CARLOS SAMUEL'],
        ['code' => '10A',          'grade' => 10, 'section' => 'DP',  'director' => 'BARRIOS DIAZ, GONZALO ERNESTO'],
        ['code' => '10B',          'grade' => 10, 'section' => 'DP',  'director' => 'FRANCESCONI ARANGO, GINA PAOLA'],
        ['code' => '11A',          'grade' => 11, 'section' => 'DP',  'director' => 'TORRES RUEDA, DIANA'],
        ['code' => '11B',          'grade' => 11, 'section' => 'DP',  'director' => 'SILVA MARTINEZ, MARIA DEL PILAR'],
    ];

    public function run(): void
    {
        $schoolYear = SchoolYear::query()->where('is_active', true)->first()
            ?? SchoolYear::query()->orderByDesc('starts_on')->first();

        if ($schoolYear === null) {
            throw new RuntimeException('No hay ningún año escolar. Créalo en /admin/anios antes de sembrar los grupos.');
        }

        $sections = Section::query()->get();

        foreach (self::GROUPS as $spec) {
            $section = $sections->first(
                fn (Section $s) => str_starts_with(Str::upper($s->name), $spec['section'])
            );

            if ($section === null) {
                throw new RuntimeException("No se encontró la sección '{$spec['section']}' para el grupo {$spec['code']}.");
            }

            $directorId = null;
            $directorName = 'Sin asignar';

            if ($spec['director'] !== null) {
                $director = $this->findDirector($spec['director']);
                $directorId = $director->id;
                $directorName = $director->name;
            }

            Group::query()->updateOrCreate(
                ['code' => $spec['code'], 'school_year_id' => $schoolYear->id],
                ['grade' => $spec['grade'], 'section_id' => $section->id, 'director_id' => $directorId],
            );

            $this->command->info("Grupo {$spec['code']} ({$section->name}) → director(a): {$directorName}");
        }
    }

    /**
     * Busca una persona por nombre. El listado viene como "APELLIDOS, NOMBRES" y
     * `users.name` como "Nombres Apellidos": se comparan tokens normalizados y se
     * exige que todos los del listado aparezcan en el nombre del usuario y que el
     * resultado sea único.
     */
    private function findDirector(string $name): User
    {
        $tokens = $this->tokens($name);

        $matches = User::query()->get()->filter(function (User $user) use ($tokens) {
            $candidate = $this->normalize((string) $user->name);

            return $tokens !== [] && collect($tokens)->every(fn (string $t) => str_contains($candidate, $t));
        });

        if ($matches->isEmpty()) {
            throw new RuntimeException("No se encontró un usuario para el director '{$name}'.");
        }

        if ($matches->count() > 1) {
            $ids = $matches->pluck('id')->implode(', ');

            throw new RuntimeException("Varios usuarios coinciden con '{$name}' (ids: {$ids}). Usa un nombre más específico.");
        }

        return $matches->first();
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        return array_values(array_filter(
            explode(' ', $this->normalize(str_replace(',', ' ', $name))),
            fn (string $token) => $token !== '',
        ));
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));

        return trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', $value));
    }
}
