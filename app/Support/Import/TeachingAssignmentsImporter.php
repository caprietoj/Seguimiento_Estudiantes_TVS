<?php

namespace App\Support\Import;

use App\Enums\RoleName;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Scope;
use Illuminate\Support\Collection;

/**
 * Plantilla "Asignaciones de profesores": qué docente (de la intranet) dicta qué asignatura en
 * qué grupo, y quién es director(a) de cada grupo. Es lo que limita a cada profesor a ver solo
 * sus grupos (ver App\Support\Scope::inScope).
 */
class TeachingAssignmentsImporter extends Importer
{
    use ResolvesRecords;

    public const TEMPLATE = ['Correo del docente', 'Nombre del docente', 'Grupo', 'Asignatura', 'Director de grupo'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'email' => ['correo del docente', 'correo', 'email', 'correo electronico', 'correo institucional'],
        'name' => ['nombre del docente', 'docente', 'profesor', 'profesora', 'nombre'],
        'group' => ['grupo', 'curso'],
        'subject' => ['asignatura', 'materia'],
        'director' => ['director de grupo', 'director a de grupo', 'director', 'directora de grupo', 'es director'],
    ];

    /** @var Collection<int, User>|null */
    private ?Collection $users = null;

    protected function process(array $sheets, ImportReport $report): void
    {
        $sheet = $sheets[0] ?? null;
        $headerRow = $sheet ? $this->findHeaderRow($sheet, self::HEADERS['group']) : null;

        if ($sheet === null || $headerRow === null) {
            $report->error(null, null, 'No se encontró la columna «Grupo». Usa la plantilla descargable.');

            return;
        }

        $columns = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS)['columns'];

        if (! isset($columns['email']) && ! isset($columns['name'])) {
            $report->error($sheet->name, $headerRow, 'Falta la columna «Correo del docente».');

            return;
        }

        foreach ($sheet->rows as $r => $cells) {
            if ($r <= $headerRow) {
                continue;
            }

            $report->rowsRead++;
            $get = fn (string $concept): string => isset($columns[$concept]) ? trim($cells[$columns[$concept]] ?? '') : '';

            $teacher = $this->resolveTeacher($get('email'), $get('name'), $report, $sheet->name, $r);
            $group = $teacher ? $this->resolveGroup($get('group'), null, false, $report, $sheet->name, $r) : null;

            if ($teacher === null || $group === null) {
                $report->rowsFailed++;

                continue;
            }

            $isDirector = self::yes($get('director'));
            $subjectName = $get('subject');
            $did = [];

            if ($subjectName === '' && ! $isDirector) {
                $report->error($sheet->name, $r, 'Indica la asignatura o marca «Sí» en «Director de grupo».');
                $report->rowsFailed++;

                continue;
            }

            if ($subjectName !== '') {
                $subject = Subject::all()->first(fn (Subject $s) => NameMatcher::normalize($s->name) === NameMatcher::normalize($subjectName));

                if ($subject === null) {
                    $report->error($sheet->name, $r, "La asignatura «{$subjectName}» no existe. Créala en Administración · Asignaturas.");
                    $report->rowsFailed++;

                    continue;
                }

                $assignment = TeachingAssignment::firstOrCreate([
                    'user_id' => $teacher->id,
                    'group_id' => $group->id,
                    'subject_id' => $subject->id,
                    'school_year_id' => $this->schoolYear->id,
                ]);

                if ($assignment->wasRecentlyCreated) {
                    $report->remember(['type' => 'assignment_created', 'id' => $assignment->id]);
                    $report->count('Asignaciones nuevas');
                    $did[] = $subject->name;
                } else {
                    $report->count('Asignaciones que ya existían (omitidas)');
                }

                if (! Scope::hasRoleName($teacher, RoleName::Profesor)) {
                    $report->warning($sheet->name, $r, "{$teacher->name} no tiene el rol Profesor en la intranet: la asignación queda guardada, pero no verá el grupo hasta tener ese rol.");
                }
            }

            if ($isDirector) {
                if ($group->director_id === $teacher->id) {
                    $report->count('Direcciones de grupo que ya existían (omitidas)');
                } else {
                    if ($group->director_id !== null) {
                        $previous = User::whereKey($group->director_id)->value('name');
                        $report->warning($sheet->name, $r, "{$group->code}: la dirección de grupo pasa de {$previous} a {$teacher->name}.");
                    }
                    $report->remember(['type' => 'director_set', 'id' => $group->id, 'previous' => $group->director_id]);
                    $group->update(['director_id' => $teacher->id]);
                    $report->count('Direcciones de grupo');
                    $did[] = 'Director(a)';
                }
            }

            $report->rowsImported++;
            $report->addPreview([
                'fila' => $r,
                'docente' => $teacher->name,
                'correo' => $teacher->email,
                'grupo' => $group->code,
                'asignacion' => $did === [] ? '(sin cambios)' : implode(', ', $did),
            ]);
        }
    }

    private function resolveTeacher(string $email, string $name, ImportReport $report, string $sheet, int $row): ?User
    {
        if ($email !== '') {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

            if ($user === null) {
                $report->error($sheet, $row, "No hay ningún usuario con el correo {$email}. Verifica que el docente exista en la intranet.");

                return null;
            }
        } elseif ($name !== '') {
            $this->users ??= User::where('active', true)->get();
            $result = NameMatcher::best($name, $this->users, fn (User $u) => $u->name);
            $user = $result['score'] >= NameMatcher::AUTO && ! $result['ambiguous'] ? $result['match'] : null;

            if ($user === null) {
                $report->error($sheet, $row, "No se encontró al docente «{$name}»; usa su correo.");

                return null;
            }

            if ($result['score'] < 100) {
                $report->warning($sheet, $row, "«{$name}» se emparejó con {$user->name} ({$result['score']} %).");
            }
        } else {
            $report->error($sheet, $row, 'Falta el correo del docente.');

            return null;
        }

        if (! $user->active) {
            $report->warning($sheet, $row, "{$user->name} está inactivo(a): la asignación se guarda pero no podrá ingresar.");
        }

        return $user;
    }
}
