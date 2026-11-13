# Decisiones tomadas durante la construcción

Este archivo documenta, como pide la sección 14 del encargo, las decisiones que se tomaron
sobre puntos que el encargo dejaba abiertos o que resultaron distintos en la práctica al
construir. Se actualiza en cada fase.

## Fase 1 — Base

1. **Autenticación: controladores propios del starter kit, no Fortify.** El encargo (sección
   2) pide Laravel Fortify para el login. El starter kit oficial `laravel/react-starter-kit`
   para Laravel 12 ya no usa Fortify: trae sus propios controladores de autenticación
   (`app/Http/Controllers/Auth/*`), muy parecidos a Laravel Breeze. Se mantienen esos
   controladores y se adaptan en la Fase 2 a las reglas de la sección 3 (sin registro
   público, contraseña temporal, cambio obligatorio en el primer ingreso, bloqueo por
   intentos), en vez de instalar Fortify encima y duplicar la lógica de sesión.

2. **Imagen Docker: `serversideup/php:8.3-fpm-nginx`, no FrankenPHP.** Motivos: ya corre
   NGINX + PHP-FPM supervisados por S6 Overlay en una sola imagen (el formato que pide la
   sección 9), ya usa un usuario sin privilegios y el puerto 8080 por defecto, trae un
   script de automatización para Laravel (variables `AUTORUN_*`) que hace exactamente lo
   que pide la sección 9 al arrancar (migrate, config:cache, route:cache, view:cache,
   storage:link) sin un entrypoint propio, y expone health checks nativos
   (`healthcheck`, `healthcheck-queue`, `healthcheck-schedule`). FrankenPHP en modo worker
   exige más cuidado con el estado entre peticiones (memory leaks de estado global); para
   una aplicación Inertia+React con sesiones y colas clásicas, FPM es la opción más simple
   y más probada en producción.

3. **Dirección de grupo de 9B sin asignar en el seeder de ejemplo.** La sección 11 solo
   pide "2 profesores, uno de ellos director de 10A". El segundo profesor (Diana Ríos) no
   se nombra director de ningún grupo, así que 9B queda sin director(a) de grupo asignado
   en los datos de ejemplo — es un estado real y frecuente (grupo recién creado) que sirve
   para probar que la aplicación no falla sin ese dato. El ADMIN puede asignarlo desde
   /admin (Fase 4).

4. **EMC de ejemplo: uno sin sección, otro limitado a Media.** Así lo pide literalmente la
   sección 11. El boceto aprobado (`seguimiento-estudiantes.html`) tenía la asignación al
   revés (una coordinadora en Media, otro coordinador en Secundaria, ambos con sección);
   donde el boceto y este encargo no coinciden, manda el encargo (sección 1), así que se
   siguió el texto de la sección 11 y no el del boceto.

5. **Pruebas (Pest) corren contra SQLite en memoria**, como en cualquier proyecto Laravel
   (`phpunit.xml`), y no contra MySQL. Es la configuración por defecto del starter kit y
   ninguna consulta de la aplicación usa SQL específico de MySQL (todo pasa por el query
   builder / Eloquent), así que no hay riesgo de falsos positivos. MySQL 8 sigue siendo la
   base de datos real en desarrollo (XAMPP o el contenedor `mysql` de `docker-compose.yml`)
   y en producción.

6. **Sin baseline de Larastan: los modelos quedaron con genéricos completos y `$casts` como
   propiedad.** La Fase 1 arrancó con un baseline que congelaba ~93 avisos de
   `missingType.generics` en las relaciones de Eloquent. Al construir la primera vista real
   (Fase 3, Reunión de grupo) ese hueco se volvió un problema de verdad: sin los genéricos,
   Larastan no puede saber qué modelo devuelve cada relación y empieza a reportar
   "propiedad no encontrada" en cascada sobre cualquier código que las use. Encima,
   se confirmó que Larastan **no infiere los `casts()` declarados como método** (la forma
   que trae Laravel 12 por defecto) y los trata como `string`, no como el Enum real. Se
   corrigió de raíz en los ~20 modelos: cada relación (`BelongsTo`, `HasMany`,
   `HasManyThrough`, `MorphTo`) lleva su docblock `@return Tipo<Relacionado, $this>`, y
   todos los `casts()` volvieron a la forma `protected $casts = [...]`. El baseline ya no
   existe: `phpstan.neon` no lo incluye y Larastan corre en 0 errores sin excepciones.

7. **`npm audit fix` aplicado de una vez.** El `package-lock.json` del starter kit traía 33
   vulnerabilidades, todas en herramientas de construcción (`postcss`, `rollup`, `qs`,
   `shell-quote`, `source-map-js`) que corren solo en la máquina que compila los assets,
   nunca en el navegador de quien usa la aplicación. `npm audit fix` las resolvió sin
   cambios de versión mayor; quedaron 0 vulnerabilidades.

8. **`CrearAdmin` (`php artisan app:crear-admin correo@dominio`) ya existe desde la Fase 1**,
   aunque la administración completa de usuarios es tema de la Fase 4, porque el README de
   despliegue (sección 9) y `docker-compose.prod.yml` la necesitan desde el primer arranque
   en un servidor limpio.

9. **Copia de seguridad (`app:respaldar-base-datos`).** Usa `mysqldump` (vía
   `Illuminate\Support\Facades\Process`) y comprime el resultado con gzip. Se programa en
   `routes/console.php` a las 02:00 hora de Bogotá y la ejecuta el contenedor `scheduler` de
   `docker-compose.prod.yml` con `php artisan schedule:work`. Conserva 14 días de
   respaldos (`--keep-days`, configurable).

10. **`healthcheck` del contenedor `app` extendido a MySQL mediante el propio mecanismo de
    Laravel**: un listener de `Illuminate\Foundation\Events\DiagnosingHealth`
    (`app/Listeners/CheckDatabaseHealth.php`) abre la conexión PDO; si falla, la ruta `/up`
    responde con error y el `HEALTHCHECK` de Docker (que apunta a `/up` vía
    `HEALTHCHECK_PATH`) lo refleja. No hace falta un script de salud aparte.

11. **Secciones del Continuo IB: PEP, PAI y DP.** El boceto y la sección 11 del encargo
    hablaban de dos secciones ("Secundaria (6.° a 9.°)" y "Media (10.° y 11.°)"), pero la
    estructura real del colegio es **PEP (Preescolar–4.°), PAI (5.°–9.°) y DP (10.°–11.°)**,
    así que el seeder crea las tres: 9B queda en PAI, 10A en DP y PEP queda sin grupos (la
    app cubre los grados 6–11; preescolar y primaria hasta 4.° no aplican). La EMC limitada
    pasó de "sección Media" a "sección DP" (es la misma sección, renombrada). El rango de
    grados de la app sigue en 6–11: habilitar el grado 5 quedó como decisión consciente de
    no hacer (el encargo y el boceto están para secundaria y media).

12. **Catálogo de grados en base de datos (pantalla Administración · Grados).** Los
    grados dejaron de estar hardcodeados: la tabla `grade_levels` nace con las 12 filas
    Preescolar (valor 0) a 11.° y se gestiona desde `/admin/grados` (nombre, activo/inactivo,
    eliminación bloqueada si hay grupos o `grade_subject` usándolo). `GroupController` ya no
    valida `between:6,11` ni la asignatura lee `config('seguimiento.grado_*')`: ambas
    consultan el catálogo (los grupos se crean solo con grados activos). El rango **por
    defecto de los datos sembrados sigue en 6–11** (el encargo es para secundaria y media);
    habilitar el grado 5 u otro más es ahora operación de la pantalla, sin tocar código.
    Las etiquetas mostradas en grupos, ficha y PDF salen del catálogo
    (`grade_label` / `GradeLevel::labelFor`), no de `{grado}.°`.

## Fase 4 y 5 — Asignaciones e importación

13. **Simulación = importación revertida.** En vez de un validador aparte, la simulación ejecuta
    el mismo importador dentro de una transacción y la revierte. Así no hay dos lógicas que
    puedan diferir: lo que el ADMIN ve en la simulación es lo que se guarda al confirmar. La
    importación es síncrona (no Job en cola): los archivos del colegio son de cientos de filas.

14. **Filas con error no bloquean el lote.** Se importan las filas válidas y las que tienen
    error quedan listadas en el reporte. El ADMIN decide en la simulación si confirma así o
    corrige el archivo.

15. **Formato actual: autor de los aportes = quien importa.** El Excel no dice qué profesor
    escribió cada celda, así que los aportes y estrategias importados quedan a nombre del
    ADMIN que importa, marcados con lote, archivo y fila de origen (`source_file`,
    `source_row`).

16. **"Observación" del Excel → aporte de campo Observación**, que es una columna de la
    Reunión de grupo y no una sección de la Ficha (decisión 4 de `CONTEXTO.md`).

17. **Estudiantes no encontrados se crean por defecto** (casilla en la pantalla), con aviso
    en la simulación. Un estudiante que el archivo pone en un grupo distinto al de su
    matrícula del año es error en el formato actual y cambio de grupo (revertible) en la
    plantilla de estudiantes.

18. **Roles de la intranet por alias.** `User::role('profesor')` de spatie no encontraba a
    los docentes cuyo rol en la intranet se llama distinto ("Docente"), así que no se podían
    elegir como director(a). Se agregó `RoleName::aliases()` y el scope
    `User::withRoleName()`, que usan la misma regla que `Scope`.

19. **Phidias: solo los campos que la aplicación ya tiene.** De las ~56 columnas del export se
    usan código, nombre, curso, estado y foto. Documento, dirección, EPS, rutas, familia, etc.
    no se guardan (no existen en el modelo y son datos sensibles que la app no necesita).

20. **Fotos de Phidias en cola, no en la petición.** Descargar cientos de fotos dentro de la
    confirmación superaría el tiempo máximo de PHP. Se importan los datos y un job baja las
    fotos. Nunca reemplaza una foto subida a mano y la reversión solo quita las que trajo.

21. **Retirados en Phidias:** si ya existen en la app quedan inactivos (revertible); si no
    existen, no se crean.

## Ficha en PDF, respaldos y grados (13 oct 2026)

22. **PDF con Public Sans incrustada y sin sección de acudientes.** Se rediseñó la ficha
    (foto de 116 pt, indicadores sin duplicar, secciones vacías en una línea). La fuente de
    la app se incluye como TTF en `resources/fonts/pdf/` (OFL) porque DomPDF no lee woff;
    con subconjuntos el PDF pasó de ~1,3 MB a ~100 KB. La antigua sección «Padres de familia
    y acudientes» mostraba en realidad los responsables de estrategias/compromisos: se quitó.

23. **Respaldos en formato propio y solo de tablas del seguimiento.** No se usa mysqldump
    para respaldar/restaurar desde la pantalla: un .zip con JSON por tabla permite restaurar
    todo dentro de una transacción, funciona igual en Docker y XAMPP y garantiza que nunca
    se toquen las tablas compartidas con la intranet (usuarios, roles, permisos, sesiones).
    Cada restauración crea antes un respaldo «previo a restauración».

24. **Grados por valor de catálogo, no por número.** Desde que `GradeLevelSeeder` numera
    desde Prekinder (10.° = 12), el número de un código de grupo ("10A") se traduce al valor
    del catálogo por el nombre del grado ("10.°"); los importadores funcionan con la
    numeración antigua y con la nueva.

## Dpto. de Apoyo: estadística general e importación 2025-2026 (13 nov 2026)

25. **La estadística sale de un registro de actividades, no de los aportes.** La hoja
    «ESTADÍSTICA GRAL» del informe de gestión cuenta estudiantes en seguimiento, familias
    atendidas, reuniones de seguimiento, PIAR y crisis por grado y área. Nada de eso se
    puede deducir de los aportes de la ficha (el Dpto. escribe sobre *todos* los
    estudiantes del grupo en cada reunión, no solo sobre su caseload). Por eso se creó la
    tabla `support_activities` (área × tipo × grupo × estudiante opcional × cantidad ×
    fecha/semestre) y la estadística se calcula de ahí. `quantity` permite registrar cifras
    agregadas («3 reuniones en 7A») cuando no hay registro uno a uno.

26. **«Estudiantes con seguimiento» cuenta personas distintas.** Por área, un estudiante
    cuenta una vez; en el total, una vez aunque lo acompañen varias áreas. Los registros sin
    estudiante suman su cantidad. Las actividades sin grupo van en la fila «Todo el
    colegio» y en los totales, no en las gráficas por curso.

27. **Semestre escolar: I = julio–diciembre, II = enero–junio** (calendario B). Se deduce de
    la fecha si no se indica. El informe de gestión del colegio es por semestre; los
    seguimientos de la app son por periodo (3 × 2), así que el filtro de la estadística es
    por semestre.

28. **Quién ve qué.** Ven la estadística Admin, Dpto. de Apoyo y EMC (EMC con sección solo
    los grupos de su sección); registran actividades solo Admin y Dpto. de Apoyo
    (`Scope::supportStatsSee`, `Scope::supportActivitiesWrite`, gates `apoyo-ver` y
    `apoyo-registrar`). Los profesores no tienen la pestaña.

29. **El seguimiento del Dpto. (habilidades / acuerdos / seguimiento a las estrategias) entra
    como un solo aporte del área, con las partes rotuladas**, no como estrategias. En el
    Excel del colegio muchos «acuerdos» son «No requiere estrategias desde el área»;
    convertirlos en estrategias individuales llenaría la reunión de grupo de ruido. Se
    conservan íntegros en la ficha (Apoyos internos → área).

30. **Fecha del Excel → seguimiento de la app.** El Excel del Dpto. tiene bloques por fecha,
    no por periodo. El convertidor asigna por mes (ago–oct P1·S1, nov–dic P1·S2, ene–feb
    P2·S1, mar P2·S2, abr–may P3·S1, jun P3·S2) y, si dos bloques caen en el mismo
    seguimiento, corre el segundo al siguiente. La tabla de asignación queda en la hoja
    «LEER - fechas y seguimientos» del archivo, y el archivo plano permite corregir
    «Periodo»/«Seguimiento» antes de importar.

31. **Hermanos no se emparejan solos.** `NameMatcher` ya separaba Daniel/Daniela; ahora
    también Olivia/Sofía Manfriani Collazos: mismos dos apellidos y un nombre que no se
    parece (ni por edición ni por letras comunes, umbral 0,7) ⇒ 70 %, por debajo del
    emparejamiento automático. Los errores de digitación (Jhon/John) sí se emparejan.

32. **Preescolar: TA = Transición A = TR**, KA = Kinder A = Kº, PKA = Prekinder A.
    `normalizeGroupCode` los trata como el mismo grupo (resuelve el problema conocido del
    código de Transición) y `resolveGroup` deduce su grado por el nombre del catálogo.

## Pendiente de confirmar con el colegio (no bloquea el desarrollo)

- Si además de las 6 asignaciones docentes de ejemplo (Historia, Matemáticas AA y
  Matemáticas, English A y Francés B, Inglés) conviene sembrar más asignaciones por
  asignatura.
- El dominio real y el proveedor de hosting (sección "Decisiones ya tomadas", punto 4 del
  encargo): todo depende de `APP_URL`, así que no bloquea nada.
- Estadística del Dpto.: si las «Reuniones de seguimiento» del I semestre 2025-2026 se
  deben cargar (el informe recibido es solo del II semestre) y qué cifra vale cuando el
  informe trae dos distintas para lo mismo (p. ej. Psicología 7.°–9.°: 18 en el total y 25
  en el detalle por curso; se usó el detalle).
