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

## Pendiente de confirmar con el colegio (no bloquea el desarrollo)

- Si además de las 6 asignaciones docentes de ejemplo (Historia, Matemáticas AA y
  Matemáticas, English A y Francés B, Inglés) conviene sembrar más asignaciones por
  asignatura.
- El dominio real y el proveedor de hosting (sección "Decisiones ya tomadas", punto 4 del
  encargo): todo depende de `APP_URL`, así que no bloquea nada.
