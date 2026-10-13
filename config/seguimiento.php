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

];
