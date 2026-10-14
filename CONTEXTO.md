# Contexto del proyecto — para quien retome este trabajo

Este archivo es un resumen para otro agente (humano o IA) que no haya visto las
conversaciones anteriores. Léelo primero; para el detalle lee también `README.md` (cómo
correr y desplegar) y `DECISIONES.md` (qué se decidió cuando el encargo no era explícito,
y por qué). Última actualización: 13 de octubre de 2026.

## Qué es esta aplicación

**The Victoria School** (colegio IB en Colombia) lleva el seguimiento académico y de
convivencia de cada estudiante en un formato de Excel llamado "Seguimiento y
acuerdos": una hoja por reunión, donde el director de grupo consolida la información
y cada profesor/coordinador/psicóloga aporta lo suyo.

Esta aplicación **reemplaza ese Excel**. La idea central: cada estudiante tiene una
**ficha** que se alimenta durante todo el año escolar (no solo en la reunión), y la
"reunión de seguimiento" es simplemente el momento en que varias personas aportan al
mismo tiempo sobre los estudiantes de un grupo. Está **en producción** en el colegio.

Archivos de referencia (están en `.gitignore`, **no** se suben a GitHub; pídeselos al
usuario si los necesitas):

- `Seguimiento de Estudiantes.html` — boceto interactivo (HTML + localStorage) aprobado
  por el colegio. Es la referencia visual y de comportamiento más confiable: ábrelo en un
  navegador (selector "Viendo como" para probar roles) antes de inventar un diseño.
- `Seguimiento 10A-2.xlsx` — el Excel real "Seguimiento y acuerdos". Ojo: la copia que
  compartió el colegio trae la columna «Estudiante» **vacía** (se anonimizó).
- `exported.xls` — export de estudiantes de **Phidias** (la plataforma académica del
  colegio). Es una tabla HTML con extensión .xls, ~56 columnas.

El encargo original completo (~14 secciones, en español) no está en el repo; lo esencial
está resumido abajo y en `DECISIONES.md`.

## Quiénes usan la aplicación (roles)

Cuatro roles (`spatie/laravel-permission`, ver `app/Enums/RoleName.php`):

| Rol (slug) | Quiénes son |
|---|---|
| `admin` | Administra la aplicación. Ve y edita todo. |
| `profesor` | Docentes. Aportan en sus grupos/asignatura. |
| `psicologia` | Dpto. de apoyo. Ve todo, escribe las notas clínicas. |
| `emc` | Coordinadores. Ven y editan casi todo (salvo notas clínicas, configurable). |

**Usuarios y roles vienen de la intranet** (intranet.tvs.edu.co). Las tablas `users`,
`roles` y relacionadas se consideran **compartidas con la intranet**: la app no tiene
pantalla de usuarios y ningún respaldo/restauración las toca. El nombre de un rol puede
venir distinto de la intranet ("Docente", "Departamento de Apoyo"…), por eso:

- `RoleName::matches()` / `RoleName::aliases()` reconocen los alias conocidos.
- Para listar usuarios por rol usa **`User::withRoleName(RoleName::Profesor)`**, nunca
  `User::role('profesor')` de spatie (exige el nombre exacto y deja fuera a los docentes
  de la intranet).

**La "dirección de grupo" NO es un rol**: es una asignación (`groups.director_id`).

**Qué ve un profesor:** solo los grupos donde es director(a) o tiene una asignación
docente (`teaching_assignments`, que se gestiona en Administración · Asignaciones o se
importa). Sin ninguna, ve la pantalla `reunion/sin-grupos`. Un docente de 10A que intente
abrir un estudiante, la reunión o el PDF de otro grupo recibe 403.

Toda la lógica de "quién puede ver/escribir qué" está centralizada en
**`app/Support/Scope.php`** (`Scope::inScope`, `clinicalSee`, `writesField`…). **Cualquier
controlador o Policy nueva debe llamar a `Scope::`, nunca reinventar la regla.** `Scope`
memoiza por request las asignaciones docentes (`Scope::flush()` se llama al terminar cada
request, ver `AppServiceProvider`); si cambias asignaciones dentro de un mismo request y
vuelves a consultar permisos, llama `Scope::flush()`.

### Inicio de sesión

- Login institucional propio (`resources/js/pages/auth/login.tsx`), **sin registro
  público** (se quitaron la ruta y la vista de registro). Límite de 5 intentos.
- **SSO desde la intranet**: `GET /sso/intranet?token=…` (`IntranetSsoController`). La
  intranet firma con HMAC-SHA256 (`INTRANET_SSO_SECRET`) un payload con `uid`, `exp`,
  `nonce` y opcionalmente `email`; se valida firma, vigencia y que el nonce no se reuse.
  El ADMIN entra a `/admin`, el resto a `/reunion`.
- `php artisan app:crear-admin correo` crea el primer ADMIN por consola.

## Stack

- Laravel 12 (PHP 8.3), MySQL 8 en producción. Inertia.js + React 19 + TypeScript +
  Vite, Tailwind CSS v4, componentes shadcn/Radix en `resources/js/components/ui/`.
- `spatie/laravel-permission` + Policies + `App\Support\Scope`.
- `phpoffice/phpspreadsheet` (vía `maatwebsite/excel`): importadores y plantillas.
- `barryvdh/laravel-dompdf`: ficha en PDF, con Public Sans incrustada.
- Cola `database` (`QUEUE_CONNECTION=database`): la usan las fotos de Phidias.
- Pest, Pint, Larastan nivel 6 (sin baseline), ESLint + Prettier.
- Docker: `Dockerfile` (`serversideup/php:8.3-fpm-nginx`, incluye cliente MySQL y zip),
  `docker-compose.prod.yml` con contenedores `app`, `queue` y `scheduler`.

## Cómo correr esto (desarrollo local)

```bash
composer install
npm install
php artisan migrate --seed   # datos ficticios (DemoDataSeeder); migrate:fresh --seed para reiniciar
composer run dev             # serve (8000) + vite (5173) + cola + logs
```

Usuarios de prueba (contraseña `password`): `admin@tvs.edu.co` (ADMIN),
`andres.castano@tvs.edu.co` (profesor, director de 10A), `diana.rios@tvs.edu.co`
(Matemáticas en 10A y 9B), `laura.restrepo@tvs.edu.co` (Inglés/Francés),
`catalina.mejia@tvs.edu.co` (Psicología), `felipe.uribe@tvs.edu.co` (EMC todo el
colegio), `natalia.serna@tvs.edu.co` (EMC solo DP).

Seeders con **datos reales del colegio** (no están en `DatabaseSeeder`, se corren a mano en
producción, son idempotentes):

```bash
php artisan db:seed --class=GradeLevelSeeder   # catálogo de grados real (ver «Grados» abajo)
php artisan db:seed --class=GroupSeeder        # grupos reales del año activo con su director
```

Antes de dar por terminado cualquier cambio:

```bash
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
npm run lint && npm run format:check && npm run build
```

**Pruebas:** la carpeta `tests/` está en `.gitignore` a propósito (el CI la salta si no
existe), así que **las pruebas no están en GitHub**; existen en la copia de trabajo de
quien las escribió. Hay pruebas Pest de: importador del formato actual, Phidias,
estudiantes/asignaciones, que un profesor solo ve su grupo, PDF y respaldos (25 pruebas).
Usan SQLite en memoria; `tests/TestCase.php` apunta las páginas de Inertia a
`resources/js/pages` (minúscula).

**Larastan sin baseline:** si una relación nueva da errores en cascada, falta el docblock
genérico (`@return HasMany<Modelo, $this>`) o el modelo usa `casts()` como método — usa
siempre `protected $casts = [...]` (ver `DECISIONES.md`, Fase 1).

**Composer en entornos sin acceso a la API de GitHub:** si `composer install` falla con
"Could not authenticate against github.com", usa `--prefer-source`.

## Qué está construido

### Vistas principales
- **Reunión de grupo** (`/reunion`, `/reunion/{group}/{followUpId}`) —
  `ReunionController` + `pages/reunion/show.tsx`. Cuadrícula por estudiante con
  fortalezas, aspectos de mejora, asignaturas a tener en cuenta, estrategias,
  responsables, Dpto. de apoyo y Observación. Consultas optimizadas (sin N+1).
- **Ficha del estudiante** (`/estudiantes`, `/estudiantes/{student}?anio=&grupo=`) —
  `StudentController` + `pages/estudiantes/show.tsx`. Selector de año, grupo y
  estudiante para navegar entre fichas (solo grupos visibles y con estudiantes).
  Foto (`PhotoController`, recorte cuadrado 480 px vía `App\Support\StudentPhoto`,
  servida por ruta autenticada).
- **Ficha en PDF** (`/estudiantes/{student}/pdf`, `resources/views/pdf/ficha.blade.php`):
  carta, Public Sans incrustada desde `resources/fonts/pdf/` (licencia OFL; DomPDF cachea en
  `storage/fonts`), foto grande, indicadores, secciones numeradas que se compactan si están
  vacías, firmas y aviso legal (Ley 1581/2012). Solo incluye lo que el perfil de quien
  exporta puede ver y lo indica al final. Se descarga como
  **"Ficha de seguimiento - Nombre - Grupo - Año.pdf"** (con respaldo ASCII). No hay
  sección de acudientes: la app no guarda acudientes.
- **Compromisos** (`/compromisos`) — `CompromisosController` + `pages/compromisos/index.tsx`.

Layout institucional en `resources/js/layouts/seguimiento-layout.tsx`, colores en
`resources/css/app.css`. **Sin modo oscuro a propósito** — no lo reactives.

### Administración (`/admin`, solo ADMIN: `Gate::define('admin')`, middleware `can:admin`)
Pestañas en `resources/js/components/admin-nav.tsx`:

- **Años escolares** (`/admin/anios`) — crear (genera 6 seguimientos), activar, solo-lectura.
- **Periodo y seguimiento** (`/admin/seguimientos`).
- **Secciones** (`/admin/secciones`) — PEP, PAI, DP.
- **Grados** (`/admin/grados`) — catálogo `grade_levels`, fuente única de los grados.
- **Grupos** (`/admin/grupos`) — sección y director (lista solo profesores activos,
  nombres en mayúsculas).
- **Estudiantes** (`/admin/estudiantes`).
- **Asignaturas** (`/admin/asignaturas`).
- **Asignaciones** (`/admin/asignaciones`, `TeachingAssignmentController`) — profesor →
  grupo → asignaturas y dirección de grupo, por año; avisa qué profesores no tienen grupo.
- **Importar** (`/admin/importar`) — ver abajo.
- **Respaldos** (`/admin/respaldos`) — ver abajo.

### Grados: OJO con la numeración
`groups.grade` y `grade_subject.grade` guardan el **valor** del catálogo `grade_levels`,
no el número del grado. La migración siembra Preescolar = 0, 1.° = 1 … 11.° = 11, pero en
producción se corrió `GradeLevelSeeder`, que renumera: **Prekinder = 0, Kinder = 1,
Transición = 2, 1.° = 3 … 6.° = 8 … 10.° = 12, 11.° = 13**. Muestra siempre la etiqueta con
`GradeLevel::labelFor($valor)`, nunca `"{$grade}.°"`. Para pasar de "10" (del código
"10A") al valor del catálogo usa `ResolvesRecords::gradeValueForSchoolYear(10)`; el inverso
es `schoolYearForGradeValue()`. Los datos de `DemoDataSeeder` usan la numeración antigua
(10A con grado 10), así que en desarrollo las etiquetas difieren de producción.

### Importación (`/admin/importar`, lógica en `app/Support/Import/`)
Flujo: subir → **simulación** (el importador completo corre dentro de una transacción que
se revierte; la pantalla dice claramente «todavía no se ha guardado nada») → reporte fila
por fila → **Confirmar importación** → lote en `import_batches`, **revertible completo**
(`ImportService::revert`). En el historial, "Simulación sin confirmar" = no se guardó.

Tipos (`ImportService::SUPPORTED`):
- **Formato actual "Seguimiento y acuerdos"** (`CurrentFormatImporter`): una hoja por
  reunión; grupo del título, periodo/seguimiento/fecha/director de la cabecera; columnas
  por encabezado. Cada celda → aporte de su campo; Estrategias + Responsables →
  estrategia individual; Fecha → fecha de la reunión. No duplica aportes al reimportar.
  Los aportes quedan a nombre de quien importa.
- **Estudiantes desde Phidias** (`PhidiasStudentsImporter`): el `exported.xls` tal cual
  (`SheetData` detecta HTML y lee solo celdas de primer nivel; "No hay datos" = vacío).
  Usa Código (llave), Estudiante ("APELLIDOS, NOMBRES" → "Nombres Apellidos"), Sección
  ("Sexto 6A" → grupo 6A con el valor de catálogo de 6.°), Estado (activo/nuevo =
  activo) y URL de la foto. Phidias manda sobre la matrícula del año (cambia de grupo,
  revertible); retirados existentes quedan inactivos, retirados nuevos no se crean; avisa
  de quién está en la app pero no en el archivo. Fotos: job `FetchPhidiasPhotos` en cola,
  solo a quien no tiene foto, como `fotos/{id}-phidias.jpg`, solo desde hosts de
  Phidias/S3 por https. **Sin worker de cola no llegan las fotos.**
- **Estudiantes y matrículas** (`StudentsImporter`) y **Asignaciones de profesores**
  (`TeachingAssignmentsImporter`, por correo del docente), con plantillas descargables.

Emparejamiento de nombres (`NameMatcher`): sin tildes/mayúsculas, orden de palabras
indiferente, ≥ 88 % automático con aviso, empates → error, Daniel/Daniela y
Martín/Martina nunca se emparejan solos. Mismo nombre con **otro** código institucional →
error (no mezcla personas). Códigos de grupo "6A", "6 A", "6°A", "6º A" son el mismo.

### Respaldos (`/admin/respaldos`, `BackupController` + `App\Support\Backup\BackupService`)
Crear, descargar, subir, restaurar y eliminar. Formato propio .zip: `manifest.json` +
`data/{tabla}.jsonl` + `fotos/`, generado en PHP (no depende de mysqldump). Incluye
**solo las 21 tablas del seguimiento** (`BackupService::TABLES`) y las fotos; usuarios,
roles, permisos y sesiones nunca se respaldan ni se pisan. Restaurar exige escribir
RESTAURAR, crea antes un respaldo «previo a restauración», reemplaza todo en **una sola
transacción** y rechaza respaldos de versiones más nuevas (migraciones que no existen
aquí). La extracción del .zip solo acepta entradas esperadas (sin rutas "../").
Carpeta: `config('seguimiento.respaldos.directorio')` (por defecto `storage/app/backups`).
`app:respaldar-base-datos` (programado a las 02:00, contenedor `scheduler`) genera el
mismo .zip y borra solo los automáticos de más de 14 días. Los `.sql.gz` del formato
anterior se listan solo para descargar. **Los respaldos viven en el mismo servidor**:
conviene descargarlos periódicamente.

### Auditoría
`audit_logs` registra acciones de Administración (estudiantes, seguimientos,
asignaciones, director de grupo), importaciones y reversiones, y respaldos/restauraciones.

## Qué falta / problemas conocidos

- **Grupo de Transición**: `GroupSeeder` lo crea con código **"Transición A"**, pero
  Phidias exporta el curso como "Transición **TA**" (código "TA"). Al importar Phidias se
  crearía un grupo "TA" aparte. Hay que unificar el código (renombrar el grupo a "TA" o
  enseñarle al importador la equivalencia) antes de importar preescolar.
- **Cambio de contraseña obligatorio** en el primer ingreso: `users.must_change_password`
  existe pero nada lo hace cumplir (menos relevante con SSO).
- **Pruebas de la matriz de permisos** celda por celda (el encargo las pide) y subir las
  pruebas al repo si se decide quitar `tests/` del `.gitignore`.
- **Importación**: faltan las demás plantillas de `ImportTemplateType` (usuarios,
  desempeño, compromisos, apoyos externos, comité, disciplinario); estrategias grupales
  del Excel actual si vienen en otra sección de la hoja; la importación es síncrona.
- **Logo del colegio** en el membrete del PDF (hoy es texto; `public/logo.svg` es el logo de
  Laravel del starter kit, no del colegio).
- **Fase 6**: refresco casi en tiempo real (polling) en la reunión de grupo; probar de
  punta a punta `docker-compose.prod.yml` y el pipeline de `release.yml`.

## Decisiones no obvias que ya se tomaron

No las cuestiones sin leer `DECISIONES.md`. Las más importantes:

1. Starter kit de Laravel con controladores de auth propios (sin Fortify).
2. `Group::students()` es `belongsToMany` vía `enrollments` (`Enrollment extends Pivot`),
   **no** `hasManyThrough`.
3. `SEGUIMIENTO_EMC_ESCRIBE_NOTAS_CLINICAS` controla si EMC escribe notas clínicas
   (por defecto no).
4. "Observación" es columna de la Reunión de grupo, **no** sección de la Ficha.
5. La simulación de importación es la importación real dentro de una transacción
   revertida (no un validador aparte).
6. Phidias: solo se guardan los campos que la app ya tiene (código, nombre, curso,
   estado, foto); el resto de datos personales no se importa.
7. Respaldos solo de tablas propias: nunca tocar tablas compartidas con la intranet.

## Mapa rápido de archivos clave

```
app/Support/Scope.php                    — TODAS las reglas de permisos (léelo primero)
app/Support/Import/                      — importadores, NameMatcher, SheetData, ImportService
app/Support/Backup/BackupService.php     — respaldos y restauración
app/Support/StudentPhoto.php             — recorte de fotos
app/Jobs/FetchPhidiasPhotos.php          — descarga de fotos de Phidias (cola)
app/Enums/                               — un Enum por cada campo tipo-lista (incl. RoleName con alias)
app/Models/                              — 21 modelos, relaciones con genéricos
app/Http/Controllers/                    — un controlador por acción/recurso
app/Http/Controllers/Admin/              — 10 controladores de /admin
app/Http/Controllers/Auth/IntranetSsoController.php — SSO desde la intranet
app/Console/Commands/                    — app:crear-admin, app:respaldar-base-datos
database/migrations/                     — 26 migraciones
database/seeders/                        — DemoDataSeeder (ficticio), GradeLevelSeeder y GroupSeeder (reales)
config/seguimiento.php                   — configuración propia (SSO, EMC, periodos, escala IB, respaldos)
resources/views/pdf/ficha.blade.php      — plantilla del PDF; resources/fonts/pdf/ sus fuentes
resources/js/pages/                      — reunion/, estudiantes/, compromisos/, admin/ (incl. importar, asignaciones, respaldos)
routes/web.php                           — todas las rutas (Inertia, sin API)
```

## Si vas a seguir construyendo

1. Resolver el código del grupo de Transición antes de importar preescolar desde Phidias.
2. Pruebas de la matriz de permisos contra `Scope` y decidir si las pruebas se suben.
3. Plantillas de importación restantes y logo en el PDF.
4. Fase 6: polling en la reunión, probar Docker y el pipeline de release.

Antes de escribir permisos: lee `Scope.php` completo. Antes de tocar el diseño: abre el
boceto HTML. Antes de asumir que algo no está decidido: busca en `DECISIONES.md`.
