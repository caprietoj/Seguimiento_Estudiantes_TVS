<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EMC y notas clínicas
    |--------------------------------------------------------------------------
    |
    | Por defecto, EMC (Coordinación) lee las notas de Psicología, Fonoaudiología,
    | Terapia ocupacional y Neuropsicología, pero no las escribe: solo Psicología
    | las escribe. Ver sección 4 del encargo.
    |
    */
    'emc_escribe_notas_clinicas' => env('SEGUIMIENTO_EMC_ESCRIBE_NOTAS_CLINICAS', false),

    /*
    |--------------------------------------------------------------------------
    | SSO desde la intranet
    |--------------------------------------------------------------------------
    |
    | Secreto compartido (HMAC-SHA256) para aceptar el inicio de sesión único
    | proveniente de intranet.tvs.edu.co. Debe coincidir con el
    | SEGUIMIENTO_SSO_SECRET configurado en la intranet.
    |
    */
    'intranet_sso_secret' => env('INTRANET_SSO_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Alcance de EMC por sección
    |--------------------------------------------------------------------------
    |
    | Si un usuario EMC tiene una sección asignada (users.emc_section_id), su
    | alcance se limita a esa sección. Sin sección asignada, ve todo el colegio.
    | Esta bandera permite desactivar la limitación sin tocar código.
    |
    */
    'emc_limita_por_seccion' => env('SEGUIMIENTO_EMC_LIMITA_POR_SECCION', true),

    /*
    |--------------------------------------------------------------------------
    | Migración de datos
    |--------------------------------------------------------------------------
    */
    'migracion' => [
        'max_mb' => (int) env('MIGRACION_MAX_MB', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Respaldos
    |--------------------------------------------------------------------------
    |
    | Carpeta de los respaldos (.zip) de Administración · Respaldos y del respaldo
    | automático nocturno. En producción vive en el volumen de storage/app.
    |
    */
    'respaldos' => [
        'directorio' => env('RESPALDOS_DIRECTORIO', storage_path('app/backups')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seguimientos por periodo
    |--------------------------------------------------------------------------
    |
    | Tres periodos académicos, dos seguimientos por periodo (como en el Excel
    | actual del colegio). Ver sección 5 y el supuesto 6 del boceto aprobado.
    |
    */
    'periodos' => 3,
    'seguimientos_por_periodo' => 2,

    /*
    |--------------------------------------------------------------------------
    | Escala de desempeño académico (IB)
    |--------------------------------------------------------------------------
    */
    'nivel_minimo' => 1,
    'nivel_maximo' => 7,
    // Un nivel igual o menor a este valor marca la asignatura como "a tener en cuenta".
    'nivel_atencion' => 3,

    /*
    |--------------------------------------------------------------------------
    | Semestres de la estadística del Dpto. de Apoyo (calendario B)
    |--------------------------------------------------------------------------
    | Periodos que forman cada semestre: I = agosto a diciembre, II = enero a junio.
    */
    /*
    |--------------------------------------------------------------------------
    | Escalas de calificación de Phidias
    |--------------------------------------------------------------------------
    | Tope de la escala por sección y rangos de desempeño para el promedio de los criterios o
    | indicadores de cada asignatura en el trimestre. Se sacaron de cómo Phidias rotula cada
    | valor en su exportación 2025-2026. Cada rango: [desempeño, operador, mínimo], en orden.
    */
    'phidias_escalas' => [
        // Preescolar y Primaria: 2 Bajo, 3 Básico, 4 Alto, 5 Superior (3,5 y 4,5 son Alto).
        5 => [
            ['Desempeño Superior', '>', 4.5],
            ['Desempeño Alto', '>=', 3.5],
            ['Desempeño Básico', '>', 2.5],
        ],
        // DP (10.° y 11.°): 1,5–3 Bajo, 3,5–4 Básico, 5–6 Alto, 7 Superior.
        7 => [
            ['Desempeño Superior', '>=', 6.5],
            ['Desempeño Alto', '>=', 4.5],
            ['Desempeño Básico', '>=', 3.5],
        ],
        // PAI (5.° a 9.°), criterios A–D de 1 a 8: 1–3 Bajo, 4 Básico, 5–6 Alto, 7–8 Superior.
        8 => [
            ['Desempeño Superior', '>=', 6.5],
            ['Desempeño Alto', '>=', 4.5],
            ['Desempeño Básico', '>=', 3.5],
        ],
    ],

    // Cursos de Bachillerato que en Phidias se califican en la escala del DP (1–7).
    'phidias_cursos_dp' => ['decimo', 'undecimo'],

    'semestres' => [
        1 => [1],
        2 => [2, 3],
    ],

];
