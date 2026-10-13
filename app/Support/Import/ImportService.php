<?php

namespace App\Support\Import;

use App\Enums\ImportTemplateType;
use App\Jobs\FetchPhidiasPhotos;
use App\Models\Contribution;
use App\Models\Enrollment;
use App\Models\FollowUp;
use App\Models\Group;
use App\Models\GroupMeeting;
use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Models\Strategy;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportService
{
    /** Plantillas que la pantalla de importación ofrece hoy. */
    public const SUPPORTED = [
        ImportTemplateType::CurrentFormat,
        ImportTemplateType::PhidiasStudents,
        ImportTemplateType::StudentsEnrollments,
        ImportTemplateType::TeachingAssignments,
    ];

    /** @param array<string, mixed> $options */
    public static function importer(ImportTemplateType $type, SchoolYear $year, User $user, array $options = []): Importer
    {
        return match ($type) {
            ImportTemplateType::CurrentFormat => new CurrentFormatImporter($year, $user, $options),
            ImportTemplateType::StudentsEnrollments => new StudentsImporter($year, $user, $options),
            ImportTemplateType::PhidiasStudents => new PhidiasStudentsImporter($year, $user, $options),
            ImportTemplateType::TeachingAssignments => new TeachingAssignmentsImporter($year, $user, $options),
            default => throw new InvalidArgumentException("La plantilla {$type->value} todavía no se puede importar."),
        };
    }

    /** @return list<string>|null Encabezados de la plantilla descargable (null = el formato es el Excel actual). */
    public static function templateHeaders(ImportTemplateType $type): ?array
    {
        return match ($type) {
            ImportTemplateType::StudentsEnrollments => StudentsImporter::TEMPLATE,
            ImportTemplateType::TeachingAssignments => TeachingAssignmentsImporter::TEMPLATE,
            default => null,
        };
    }

    /**
     * Deshace todo lo que hizo un lote: borra lo que creó (marcado con import_batch_id) y
     * devuelve a su estado anterior lo que cambió (direcciones de grupo, cambios de grupo…).
     * Lo que alguien haya agregado a mano después sobre esos estudiantes se conserva.
     *
     * @return array<string, int> Conteo de lo revertido.
     */
    public static function revert(ImportBatch $batch): array
    {
        $done = [];

        DB::transaction(function () use ($batch, &$done) {
            $photos = 0;
            foreach (array_keys($batch->error_report['photos'] ?? []) as $studentId) {
                $photos += FetchPhidiasPhotos::removePhoto((int) $studentId) ? 1 : 0;
            }
            $done['Fotos de Phidias'] = $photos;

            $done['Aportes'] = Contribution::withTrashed()->where('import_batch_id', $batch->id)->forceDelete();
            $done['Estrategias'] = Strategy::withTrashed()->where('import_batch_id', $batch->id)->forceDelete();

            /** @var list<array{type: string, id: int, previous?: int|null}> $undo */
            $undo = array_reverse($batch->error_report['undo'] ?? []);

            foreach ($undo as $change) {
                match ($change['type']) {
                    'enrollment_moved' => Enrollment::whereKey($change['id'])->update(['group_id' => $change['previous'] ?? null]),
                    'student_active' => Student::withTrashed()->whereKey($change['id'])->update(['active' => (bool) ($change['previous'] ?? 1)]),
                    'student_code_set' => Student::withTrashed()->whereKey($change['id'])->update(['institutional_code' => null]),
                    default => null,
                };

                $label = match ($change['type']) {
                    'enrollment_moved' => 'Cambios de grupo',
                    'student_active' => 'Cambios de estado',
                    'student_code_set' => 'Códigos institucionales',
                    default => null,
                };

                if ($label !== null) {
                    $done[$label] = ($done[$label] ?? 0) + 1;
                }
            }

            $done['Matrículas'] = Enrollment::where('import_batch_id', $batch->id)->delete();

            // Estudiantes creados por el lote: se borran solo si no quedó nada suyo.
            $kept = 0;
            $deleted = 0;
            foreach (Student::withTrashed()->where('import_batch_id', $batch->id)->get() as $student) {
                $hasData = $student->enrollments()->exists()
                    || Contribution::withTrashed()->where('student_id', $student->id)->exists()
                    || Strategy::withTrashed()->where('student_id', $student->id)->exists()
                    || $student->commitments()->exists()
                    || $student->grades()->exists();

                if ($hasData) {
                    $student->forceFill(['import_batch_id' => null])->save();
                    $kept++;
                } else {
                    $student->forceDelete();
                    $deleted++;
                }
            }
            $done['Estudiantes'] = $deleted;
            if ($kept > 0) {
                $done['Estudiantes conservados (tienen información nueva)'] = $kept;
            }

            foreach ($undo as $change) {
                switch ($change['type']) {
                    case 'assignment_created':
                        TeachingAssignment::whereKey($change['id'])->delete();
                        $done['Asignaciones'] = ($done['Asignaciones'] ?? 0) + 1;
                        break;
                    case 'director_set':
                        Group::whereKey($change['id'])->update(['director_id' => $change['previous'] ?? null]);
                        $done['Direcciones de grupo'] = ($done['Direcciones de grupo'] ?? 0) + 1;
                        break;
                    case 'meeting_date_set':
                        GroupMeeting::whereKey($change['id'])->update(['held_on' => null]);
                        break;
                    case 'meeting_created':
                        GroupMeeting::whereKey($change['id'])->delete();
                        break;
                    case 'follow_up_created':
                        $inUse = Contribution::withTrashed()->where('follow_up_id', $change['id'])->exists()
                            || Strategy::withTrashed()->where('follow_up_id', $change['id'])->exists()
                            || GroupMeeting::where('follow_up_id', $change['id'])->exists();
                        if (! $inUse) {
                            FollowUp::whereKey($change['id'])->delete();
                        }
                        break;
                    case 'group_created':
                        $group = Group::find($change['id']);
                        if ($group !== null && ! $group->enrollments()->exists() && ! $group->teachingAssignments()->exists() && ! $group->groupMeetings()->exists()) {
                            $group->delete();
                            $done['Grupos'] = ($done['Grupos'] ?? 0) + 1;
                        }
                        break;
                }
            }
        });

        return array_filter($done);
    }
}
