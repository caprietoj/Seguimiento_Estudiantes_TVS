<?php

namespace Database\Seeders;

use App\Enums\CommitmentStatus;
use App\Enums\CommitmentType;
use App\Enums\CommitteeDecision;
use App\Enums\ContributionField;
use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryCaseType;
use App\Enums\RoleName;
use App\Enums\StrategyResult;
use App\Enums\StrategyType;
use App\Models\Commitment;
use App\Models\CommitteeDecision as CommitteeDecisionModel;
use App\Models\Contribution;
use App\Models\DisciplinaryCase;
use App\Models\Enrollment;
use App\Models\ExternalSupport;
use App\Models\FollowUp;
use App\Models\Grade;
use App\Models\Group;
use App\Models\GroupMeeting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strategy;
use App\Models\StrategyReview;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo FICTICIOS (sección 11 del encargo): año escolar 2026–2027
 * (Calendario B), tres secciones del Continuo IB —PEP (Preescolar–4.°), PAI
 * (5.°–9.°) y DP (10.°–11.°)—, dos grupos con matrículas dentro del rango real
 * de 15–24 estudiantes (9B en PAI y 10A en DP; PEP queda sin grupos porque la
 * app cubre los grados 6–11), y un usuario por rol (más la EMC limitada a DP).
 * Asignaturas, apoyos y vocabulario basados en la información pública del
 * colegio (thevictoriaschool.edu.co). Ningún dato es real.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Permite ejecutar el seeder varias veces (por ejemplo, en cada arranque de
        // desarrollo con AUTORUN_LARAVEL_MIGRATION_SEED) sin duplicar los datos de ejemplo.
        if (SchoolYear::where('name', '2026–2027')->exists()) {
            return;
        }

        $schoolYear = SchoolYear::create([
            'name' => '2026–2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-06-13',
            'is_active' => true,
            'is_read_only' => false,
        ]);

        $pep = Section::create(['name' => 'PEP (Preescolar–4.°)']);
        $pai = Section::create(['name' => 'PAI (5.°–9.°)']);
        $dp = Section::create(['name' => 'DP (10.°–11.°)']);

        // Asignaturas PAI (Programa de Años Intermedios) para los grados 6–9 y del
        // Programa del Diploma para 10–11, según la oferta pública del colegio.
        // grade_subject limita cada asignatura a sus grados.
        $paiSubjects = collect([
            'Lengua y Literatura', 'Inglés (lengua adicional)', 'Francés (lengua adicional)',
            'Individuos y Sociedades', 'Matemáticas', 'Ciencias', 'Artes',
            'Diseño', 'Educación Física y Salud',
        ])->map(fn ($name) => Subject::create(['name' => $name, 'active' => true]));
        foreach ($paiSubjects as $subject) {
            $subject->syncGradeLevels([6, 7, 8, 9]);
        }

        $pdSubjects = collect([
            'Español A · Lengua y Literatura', 'English A · Lengua y Literatura', 'Francés B',
            'Historia', 'Economía', 'Psicología', 'Biología', 'Física', 'Química',
            'Matemáticas: Análisis y Enfoques', 'Artes Visuales',
            'Ciencias del Deporte y el Ejercicio',
        ])->map(fn ($name) => Subject::create(['name' => $name, 'active' => true]));
        foreach ($pdSubjects as $subject) {
            $subject->syncGradeLevels([10, 11]);
        }

        $admin = User::factory()->create([
            'name' => 'Administrador TVS',
            'email' => 'admin@tvs.edu.co',
        ]);
        $admin->assignRole(RoleName::Admin->value);

        $andres = User::factory()->create([
            'name' => 'Andrés Castaño',
            'email' => 'andres.castano@tvs.edu.co',
        ]);
        $andres->assignRole(RoleName::Profesor->value);

        $diana = User::factory()->create([
            'name' => 'Diana Ríos',
            'email' => 'diana.rios@tvs.edu.co',
        ]);
        $diana->assignRole(RoleName::Profesor->value);

        $laura = User::factory()->create([
            'name' => 'Laura Restrepo',
            'email' => 'laura.restrepo@tvs.edu.co',
        ]);
        $laura->assignRole(RoleName::Profesor->value);

        $catalina = User::factory()->create([
            'name' => 'Catalina Mejía',
            'email' => 'catalina.mejia@tvs.edu.co',
        ]);
        $catalina->assignRole(RoleName::Psicologia->value);

        $felipe = User::factory()->create([
            'name' => 'Felipe Uribe',
            'email' => 'felipe.uribe@tvs.edu.co',
            'emc_section_id' => null, // sin sección: ve todo el colegio
        ]);
        $felipe->assignRole(RoleName::Emc->value);

        $natalia = User::factory()->create([
            'name' => 'Natalia Serna',
            'email' => 'natalia.serna@tvs.edu.co',
            'emc_section_id' => $dp->id, // limitada a la sección DP
        ]);
        $natalia->assignRole(RoleName::Emc->value);

        $group10A = Group::create([
            'code' => '10A',
            'grade' => 10,
            'section_id' => $dp->id,
            'school_year_id' => $schoolYear->id,
            'director_id' => $andres->id,
        ]);

        $group9B = Group::create([
            'code' => '9B',
            'grade' => 9,
            'section_id' => $pai->id,
            'school_year_id' => $schoolYear->id,
            'director_id' => null, // dirección de grupo aún sin asignar (caso real frecuente)
        ]);

        $historia = $pdSubjects[3];
        $englishA = $pdSubjects[1];
        $francesB = $pdSubjects[2];
        $matematicasAA = $pdSubjects[9];
        $inglesPai = $paiSubjects[1];
        $matematicasPai = $paiSubjects[4];

        TeachingAssignment::create(['user_id' => $andres->id, 'group_id' => $group10A->id, 'subject_id' => $historia->id, 'school_year_id' => $schoolYear->id]);
        TeachingAssignment::create(['user_id' => $diana->id, 'group_id' => $group10A->id, 'subject_id' => $matematicasAA->id, 'school_year_id' => $schoolYear->id]);
        TeachingAssignment::create(['user_id' => $diana->id, 'group_id' => $group9B->id, 'subject_id' => $matematicasPai->id, 'school_year_id' => $schoolYear->id]);
        TeachingAssignment::create(['user_id' => $laura->id, 'group_id' => $group10A->id, 'subject_id' => $englishA->id, 'school_year_id' => $schoolYear->id]);
        TeachingAssignment::create(['user_id' => $laura->id, 'group_id' => $group10A->id, 'subject_id' => $francesB->id, 'school_year_id' => $schoolYear->id]);
        TeachingAssignment::create(['user_id' => $laura->id, 'group_id' => $group9B->id, 'subject_id' => $inglesPai->id, 'school_year_id' => $schoolYear->id]);

        // Matrículas reales: grupos de 15 a 24 estudiantes (FAQ del colegio).
        $names10A = [
            'Valentina Ospina', 'Santiago Restrepo', 'Mariana Cárdenas', 'Juan Esteban Rojas',
            'Isabella Quintero', 'Tomás Arango', 'Sara Montoya', 'Nicolás Pardo',
            'Camila Delgado', 'Sebastián Peláez', 'Emilia Vargas', 'Mateo Cardona',
            'Daniela Prieto', 'Samuel Guzmán', 'Luisa Gutiérrez', 'Joaquín Torres',
        ];
        $names9B = [
            'Luciana Herrera', 'Samuel Duque', 'Antonia Lozano', 'Martín Salazar',
            'María José Rincón', 'Gabriel Ossa', 'Paula Cifuentes', 'Diego Marín',
            'Sofía Naranjo', 'Juan Pablo Bernal', 'Mariana Zuluaga', 'Benjamín Correa',
            'Valeria Aponte', 'Esteban Cardozo', 'Manuela Sandoval',
        ];

        /** @var array<int, Student> $students */
        $students = [];
        $n = 1;
        foreach ($names10A as $fullName) {
            $students[$n] = $s = Student::factory()->create(['full_name' => $fullName]);
            Enrollment::create(['student_id' => $s->id, 'group_id' => $group10A->id, 'school_year_id' => $schoolYear->id]);
            $n++;
        }
        foreach ($names9B as $fullName) {
            $students[$n] = $s = Student::factory()->create(['full_name' => $fullName]);
            Enrollment::create(['student_id' => $s->id, 'group_id' => $group9B->id, 'school_year_id' => $schoolYear->id]);
            $n++;
        }

        // Seguimientos del año: 3 periodos x 2 seguimientos.
        $followUps = [];
        for ($p = 1; $p <= 3; $p++) {
            for ($s = 1; $s <= 2; $s++) {
                $followUps["P{$p}S{$s}"] = FollowUp::create(['school_year_id' => $schoolYear->id, 'period' => $p, 'number' => $s]);
            }
        }
        $p1s1 = $followUps['P1S1'];

        GroupMeeting::create(['group_id' => $group10A->id, 'follow_up_id' => $p1s1->id, 'held_on' => '2026-09-16']);
        GroupMeeting::create(['group_id' => $group9B->id, 'follow_up_id' => $p1s1->id, 'held_on' => '2026-09-17']);

        // Desempeño del Periodo 1 (niveles 1–7 del IB), en el orden de cada lista
        // de asignaturas: posiciones 1–16 son 10A (PD) y 17–31 son 9B (PAI).
        $levels10A = [
            [4, 5, 4, 4, 5, 4, 5, 4, 4, 5, 6, 5],
            [5, 5, 5, 5, 6, 4, 5, 5, 5, 6, 5, 6],
            [4, 4, 5, 4, 3, 4, 6, 5, 6, 4, 6, 5],
            [3, 5, 4, 4, 5, 5, 5, 6, 5, 4, 5, 7],
            [3, 3, 5, 4, 3, 4, 5, 4, 5, 3, 5, 5],
            [5, 4, 4, 5, 4, 5, 4, 5, 4, 5, 5, 6],
            [5, 6, 3, 5, 4, 4, 6, 5, 6, 5, 6, 6],
            [4, 4, 4, 3, 4, 4, 5, 4, 5, 2, 4, 5],
            [5, 4, 5, 5, 3, 4, 6, 3, 5, 4, 6, 6],
            [4, 4, 4, 4, 4, 4, 5, 5, 6, 3, 5, 6],
            [4, 4, 5, 4, 4, 5, 5, 5, 5, 5, 5, 5],
            [4, 3, 4, 4, 5, 4, 6, 5, 7, 5, 5, 7],
            [5, 5, 4, 5, 5, 4, 5, 4, 4, 5, 5, 5],
            [4, 4, 4, 3, 4, 4, 5, 4, 4, 3, 4, 5],
            [4, 5, 5, 4, 5, 5, 5, 5, 5, 4, 6, 5],
            [4, 4, 4, 4, 4, 4, 5, 5, 3, 4, 4, 5],
        ];
        foreach ($levels10A as $i => $row) {
            $student = $students[$i + 1];
            foreach ($row as $j => $level) {
                Grade::create([
                    'student_id' => $student->id,
                    'subject_id' => $pdSubjects[$j]->id,
                    'school_year_id' => $schoolYear->id,
                    'period' => 1,
                    'level' => $level,
                ]);
            }
        }

        $levels9B = [
            [5, 6, 5, 5, 5, 5, 6, 5, 6],
            [4, 5, 4, 5, 5, 4, 5, 5, 5],
            [4, 4, 5, 4, 4, 5, 5, 4, 5],
            [5, 5, 5, 6, 5, 5, 5, 6, 6],
            [3, 4, 4, 3, 4, 4, 5, 4, 5],
            [4, 5, 4, 4, 5, 5, 6, 5, 5],
            [5, 6, 5, 5, 5, 5, 6, 5, 6],
            [3, 3, 4, 4, 3, 4, 5, 4, 5],
            [4, 5, 3, 4, 4, 5, 5, 4, 5],
            [5, 5, 4, 5, 5, 4, 5, 5, 6],
            [4, 4, 4, 3, 4, 4, 4, 4, 5],
            [3, 4, 5, 4, 3, 5, 5, 5, 5],
            [5, 6, 5, 5, 6, 5, 5, 6, 6],
            [4, 3, 4, 4, 4, 3, 4, 4, 5],
            [4, 5, 4, 5, 4, 5, 5, 4, 5],
        ];
        foreach ($levels9B as $i => $row) {
            $student = $students[$i + 17];
            foreach ($row as $j => $level) {
                Grade::create([
                    'student_id' => $student->id,
                    'subject_id' => $paiSubjects[$j]->id,
                    'school_year_id' => $schoolYear->id,
                    'period' => 1,
                    'level' => $level,
                ]);
            }
        }

        $contribute = function (int $studentNumber, ContributionField $field, string $body, User $author) use ($students, $p1s1) {
            Contribution::create([
                'student_id' => $students[$studentNumber]->id,
                'follow_up_id' => $p1s1->id,
                'field' => $field->value,
                'body' => $body,
                'author_id' => $author->id,
            ]);
        };

        $contribute(1, ContributionField::Strength, 'Buena disposición en clase y participación constante.', $andres);
        $contribute(1, ContributionField::Improvement, 'Su escritura es muy descriptiva: debe fundamentar argumentos y hacer síntesis.', $diana);
        $contribute(1, ContributionField::SupportCoordination, 'Revisar con los docentes de Español A, English A y Matemáticas.', $natalia);
        $contribute(1, ContributionField::Observation, 'Se pedirá a los docentes revisar cómo está desarrollando análisis y síntesis.', $andres);
        $contribute(3, ContributionField::SupportOt, 'Seguimiento de organización de materiales y funciones ejecutivas.', $catalina);
        $contribute(4, ContributionField::Improvement, 'Profundizar el análisis escrito y las habilidades de transferencia en Español A.', $andres);
        $contribute(4, ContributionField::SupportPsychology, 'Acompañamiento por ansiedad en evaluaciones. Cita con la familia pendiente.', $catalina);
        $contribute(5, ContributionField::Improvement, 'Mejorar el manejo del tiempo en el aula.', $diana);
        $contribute(5, ContributionField::SupportNeuro, 'Observación en aula de habilidades de autogestión.', $catalina);
        $contribute(6, ContributionField::SupportSpeech, 'Participa en el grupo de habilidades de comunicación oral.', $catalina);
        $contribute(7, ContributionField::Strength, 'Buenas habilidades de comunicación oral.', $andres);
        $contribute(7, ContributionField::Improvement, 'Rigor en la comprensión escrita. Habla mucho en clase.', $diana);
        $contribute(7, ContributionField::SupportCoordination, 'Plan de acción en Francés B con entregas semanales cortas.', $natalia);
        $contribute(8, ContributionField::Improvement, 'Nivel bajo en Matemáticas: Análisis y Enfoques e Historia.', $diana);
        $contribute(8, ContributionField::SupportCoordination, 'Cita desde Coordinación con la familia.', $natalia);
        $contribute(9, ContributionField::Strength, 'Liderazgo positivo en trabajos de grupo.', $diana);
        $contribute(10, ContributionField::Improvement, 'Entrega tardía de tareas en Matemáticas y Español A.', $diana);
        $contribute(10, ContributionField::SupportPsychology, 'Seguimiento de hábitos de estudio con la familia.', $catalina);
        $contribute(12, ContributionField::Improvement, 'Se le ve inseguro al hablar en English A; necesita más práctica oral.', $laura);
        $contribute(13, ContributionField::Strength, 'Participación constante en los intercambios orales de English A.', $laura);
        $contribute(14, ContributionField::Improvement, 'Debe organizar mejor el tiempo entre ensayos y exámenes; avanza despacio con el borrador de TdC.', $andres);
        $contribute(15, ContributionField::SupportCoordination, 'Revisión de carga académica con la consejería: elegir entre Matemáticas AA y AI.', $natalia);
        $contribute(16, ContributionField::Improvement, 'Química: necesita reforzar la base matemática de los laboratorios.', $diana);
        $contribute(17, ContributionField::Strength, 'Liderazgo positivo en la organización del Proyecto Comunitario.', $diana);
        $contribute(19, ContributionField::Improvement, 'Debe cumplir el cronograma del Proyecto Personal; ha entregado tarde dos versiones.', $diana);
        $contribute(22, ContributionField::SupportCoordination, 'Conversación con la familia sobre puntualidad y uso del celular en el aula.', $felipe);
        $contribute(23, ContributionField::Strength, 'Buenas habilidades de comunicación oral en inglés.', $laura);
        $contribute(25, ContributionField::Improvement, 'Entrega incompleta de tareas de Francés; necesita acompañamiento en la agenda.', $laura);
        $contribute(30, ContributionField::Improvement, 'Participa poco en clase; se le invitó a exponer en Inglés y Ciencias.', $laura);

        // Asignaturas para tener en cuenta (observaciones pedagógicas docentes).
        $contribute(1, ContributionField::SubjectAttention, 'Matemáticas: Errores en notación científica, redondeo y desarrollo de procedimientos; necesita practicar el paso a paso explícito.', $diana);
        $contribute(1, ContributionField::SubjectAttention, 'Español A: Transicionar del análisis descriptivo al explicativo (efecto del recurso en el lector).', $andres);
        $contribute(2, ContributionField::SubjectAttention, 'Francés B: Requiere planificar con borradores y revisión apoyada en Classroom y El Principito.', $laura);
        $contribute(4, ContributionField::SubjectAttention, 'Biología: Pasar de la memorización al análisis práctico, conectando mejor las observaciones con las conclusiones.', $diana);
        $contribute(7, ContributionField::SubjectAttention, 'Francés B: Plan de acción con entregas semanales cortas para afianzar estructuras gramaticales.', $laura);
        $contribute(8, ContributionField::SubjectAttention, 'Matemáticas: Resolver vacíos en álgebra básica e identificación de procedimientos mediante ejercicios guiados.', $diana);
        $contribute(10, ContributionField::SubjectAttention, 'Química: Necesita reforzar la base matemática de los cálculos de laboratorio.', $diana);

        // Estrategias grupales.
        $groupStrategy10A = Strategy::create([
            'type' => StrategyType::Group->value,
            'group_id' => $group10A->id,
            'follow_up_id' => $p1s1->id,
            'body' => 'Profundizar el análisis escrito y la síntesis en Español A, Historia y Matemáticas.',
            'responsible' => 'Todos los docentes',
            'author_id' => $andres->id,
        ]);
        StrategyReview::create([
            'strategy_id' => $groupStrategy10A->id,
            'reviewed_on' => '2026-09-26',
            'body' => 'Aplicado en Español A e Historia con rúbrica común.',
            'result' => StrategyResult::Partially->value,
            'author_id' => $andres->id,
        ]);
        Strategy::create([
            'type' => StrategyType::Group->value,
            'group_id' => $group9B->id,
            'follow_up_id' => $p1s1->id,
            'body' => 'Revisar agenda semanal y cronograma del Proyecto Comunitario los lunes en dirección de grupo.',
            'responsible' => 'Dirección de grupo',
            'author_id' => $felipe->id,
        ]);

        // Estrategias individuales.
        $strategyS1 = Strategy::create([
            'type' => StrategyType::Individual->value,
            'student_id' => $students[1]->id,
            'follow_up_id' => $p1s1->id,
            'body' => 'Organizador gráfico tesis–evidencia–análisis antes de cada texto escrito.',
            'responsible' => 'Docentes de Español A e Historia',
            'author_id' => $andres->id,
        ]);
        StrategyReview::create([
            'strategy_id' => $strategyS1->id,
            'reviewed_on' => '2026-09-25',
            'body' => 'Lo usa en Historia; en Español A todavía no.',
            'result' => StrategyResult::Partially->value,
            'author_id' => $andres->id,
        ]);
        Strategy::create([
            'type' => StrategyType::Individual->value,
            'student_id' => $students[5]->id,
            'follow_up_id' => $p1s1->id,
            'body' => 'Temporizador visible y metas por bloque de 15 minutos en clase.',
            'responsible' => 'Docentes de English A, Español A y Matemáticas',
            'author_id' => $catalina->id,
        ]);
        $strategyS8 = Strategy::create([
            'type' => StrategyType::Individual->value,
            'student_id' => $students[8]->id,
            'follow_up_id' => $p1s1->id,
            'body' => 'Tutoría entre pares en Matemáticas: Análisis y Enfoques dos veces por semana.',
            'responsible' => 'Diana Ríos',
            'author_id' => $diana->id,
        ]);
        StrategyReview::create([
            'strategy_id' => $strategyS8->id,
            'reviewed_on' => '2026-09-29',
            'body' => 'Asistió a 3 de 4 sesiones.',
            'result' => StrategyResult::Works->value,
            'author_id' => $diana->id,
        ]);

        // Compromisos (al menos uno vencido, como pide la sección 11).
        Commitment::create(['student_id' => $students[1]->id, 'type' => CommitmentType::School->value, 'body' => 'Retroalimentación escrita quincenal en Español A.', 'responsible' => 'Docente de Español A', 'due_on' => now()->addDays(16)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $andres->id]);
        Commitment::create(['student_id' => $students[1]->id, 'type' => CommitmentType::Family->value, 'body' => 'Acompañar la lectura en inglés 20 minutos diarios.', 'responsible' => 'Madre', 'due_on' => now()->addDays(30)->toDateString(), 'status' => CommitmentStatus::InProgress->value, 'author_id' => $andres->id]);
        Commitment::create(['student_id' => $students[4]->id, 'type' => CommitmentType::Family->value, 'body' => 'Asistir a la cita con Psicología.', 'responsible' => 'Padre y madre', 'due_on' => now()->subDays(5)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $catalina->id]);
        Commitment::create(['student_id' => $students[8]->id, 'type' => CommitmentType::School->value, 'body' => 'Citar a la familia para plan de mejoramiento.', 'responsible' => 'Coordinación', 'due_on' => now()->subDays(3)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $natalia->id]);
        Commitment::create(['student_id' => $students[7]->id, 'type' => CommitmentType::School->value, 'body' => 'Conversación con Coordinación sobre el uso del lenguaje en clase de Francés.', 'responsible' => 'Coordinación', 'due_on' => now()->subDays(10)->toDateString(), 'status' => CommitmentStatus::Done->value, 'author_id' => $natalia->id]);
        Commitment::create(['student_id' => $students[10]->id, 'type' => CommitmentType::Family->value, 'body' => 'Horario fijo de estudio en casa.', 'responsible' => 'Madre', 'due_on' => now()->addDays(20)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $diana->id]);
        Commitment::create(['student_id' => $students[13]->id, 'type' => CommitmentType::School->value, 'body' => 'Entregar el borrador del ensayo de TdC a la consejería.', 'responsible' => 'Estudiante', 'due_on' => now()->addDays(12)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $andres->id]);
        Commitment::create(['student_id' => $students[14]->id, 'type' => CommitmentType::School->value, 'body' => 'Registro semanal de actividades en el portafolio CAS.', 'responsible' => 'Estudiante y tutor de CAS', 'due_on' => now()->addDays(10)->toDateString(), 'status' => CommitmentStatus::InProgress->value, 'author_id' => $andres->id]);
        Commitment::create(['student_id' => $students[19]->id, 'type' => CommitmentType::School->value, 'body' => 'Cumplir el cronograma del Proyecto Personal de la semana.', 'responsible' => 'Estudiante', 'due_on' => now()->addDays(14)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $diana->id]);
        Commitment::create(['student_id' => $students[25]->id, 'type' => CommitmentType::Family->value, 'body' => 'Revisar la agenda de tareas de Francés con el estudiante.', 'responsible' => 'Madre', 'due_on' => now()->addDays(7)->toDateString(), 'status' => CommitmentStatus::Pending->value, 'author_id' => $laura->id]);

        // Apoyos externos (especialidades del Dpto. de apoyo: psicología,
        // fonoaudiología, terapia ocupacional y neuropediatría).
        ExternalSupport::create(['student_id' => $students[3]->id, 'provider' => 'Centro de Terapias Integrales (ficticio)', 'specialty' => 'Terapia ocupacional', 'frequency' => 'Semanal', 'contact' => 'A través de la familia', 'notes' => 'Trabaja organización y funciones ejecutivas.', 'author_id' => $catalina->id]);
        ExternalSupport::create(['student_id' => $students[5]->id, 'provider' => 'Neuropediatra particular (ficticio)', 'specialty' => 'Neuropediatría', 'frequency' => 'Trimestral', 'contact' => 'Informe enviado por la familia', 'notes' => null, 'author_id' => $catalina->id]);
        ExternalSupport::create(['student_id' => $students[6]->id, 'provider' => 'Centro de Comunicación y Lenguaje (ficticio)', 'specialty' => 'Fonoaudiología', 'frequency' => 'Semanal', 'contact' => 'A través de la familia', 'notes' => 'Fortalece comunicación oral; coordinado con el Dpto. de apoyo.', 'author_id' => $catalina->id]);

        // Comité evaluador.
        CommitteeDecisionModel::create(['student_id' => $students[7]->id, 'decided_on' => '2026-09-16', 'decision' => CommitteeDecision::ImprovementPlan->value, 'notes' => 'Francés B en nivel 3. Revisar en el Seguimiento 2.', 'author_id' => $natalia->id]);
        CommitteeDecisionModel::create(['student_id' => $students[8]->id, 'decided_on' => '2026-09-16', 'decision' => CommitteeDecision::FamilyMeeting->value, 'notes' => 'Historia y Matemáticas en nivel 3 o menos.', 'author_id' => $natalia->id]);

        // Proceso disciplinario.
        DisciplinaryCase::create([
            'student_id' => $students[2]->id,
            'occurred_on' => '2026-09-12',
            'type' => DisciplinaryCaseType::TypeI->value,
            'description' => 'Comentarios inapropiados durante la convivencia de principios de año (ejemplo ficticio).',
            'action_taken' => 'Diálogo reflexivo con Coordinación y compromiso verbal según el protocolo Tolerancia Cero.',
            'status' => DisciplinaryCaseStatus::Closed->value,
            'author_id' => $natalia->id,
        ]);
    }
}
