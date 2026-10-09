# Contexto del proyecto — para quien retome este trabajo

Este archivo es un resumen para otro agente (humano o IA) que no haya visto la
conversación original. Léelo primero; para el detalle completo del encargo original
lee también `README.md` (cómo correr y desplegar) y `DECISIONES.md` (qué se decidió
cuando el encargo no era explícito, y por qué).

## Qué es esta aplicación

**The Victoria School** (colegio IB en Colombia) lleva el seguimiento académico y de
convivencia de cada estudiante en un formato de Excel llamado "Seguimiento y
acuerdos": una hoja por reunión, donde el director de grupo consolida la información
y cada profesor/coordinador/psicóloga aporta lo suyo.

Esta aplicación **reemplaza ese Excel**. La idea central: cada estudiante tiene una
**ficha** que se alimenta durante todo el año escolar (no solo en la reunión), y la
"reunión de seguimiento" es simplemente el momento en que varias personas aportan al
mismo tiempo sobre los estudiantes de un grupo.

Dos archivos de referencia viven en la raíz del repo y **no se deben borrar**:

- `Seguimiento de Estudiantes.html` — boceto interactivo (HTML + localStorage) ya
  aprobado por el colegio. Es la referencia visual y de comportamiento más confiable:
  cuando haya duda sobre cómo debe verse o comportarse algo, ábrelo en un navegador
  (selector "Viendo como" para probar los distintos roles) antes de inventar un diseño.
- `Seguimiento 10A-2.xlsx` — el Excel real que usa el colegio hoy. Es el formato que
  el asistente de migración (Fase 5, **sin construir todavía**) debe poder leer.

El encargo original completo (el prompt que generó este proyecto, en español, ~14
secciones) no está en el repo como archivo; si lo necesitas textualmente, pídeselo a
quien te haya puesto a trabajar en esto. Lo esencial de ese encargo está resumido
abajo y en `DECISIONES.md`.

## Quiénes usan la aplicación (roles)

Cuatro roles (`spatie/laravel-permission`, ver `app/Enums/RoleName.php`):

| Rol (slug) | Quiénes son |
|---|---|
| `admin` | Administra la aplicación. Ve y edita todo. |
| `profesor` | Docentes. Aportan en sus grupos/asignatura. |
| `psicologia` | Dpto. de apoyo. Ve todo, escribe las notas clínicas. |
| `emc` | Coordinadores. Ven y editan casi todo (salvo notas clínicas, configurable). |

**La "dirección de grupo" NO es un rol**: es una asignación (`groups.director_id`
apunta a un `users.id`). Un profesor director de 10A tiene permisos extra, pero solo
en 10A. Un usuario puede tener varios roles a la vez (sus permisos se suman).

Toda la lógica de "quién puede ver/escribir qué" está centralizada en **una sola
clase**: `app/Support/Scope.php`. Tiene métodos estáticos como `Scope::inScope($user,
$group)`, `Scope::clinicalSee(...)`, `Scope::writesField(...)`, etc. **Cualquier
controlador o Policy nueva debe llamar a `Scope::`, nunca reinventar la regla.** Esta
clase es la traducción fiel del objeto `P` del boceto HTML (sección "Permisos" del
archivo `.html`), adaptado a los 4 roles reales. Si cambias una regla de permisos,
cámbiala ahí y en ningún otro lado.

## Stack

- Laravel 12 (PHP 8.3+), MySQL 8 (`utf8mb4_unicode_ci`).
- Inertia.js + React 19 + TypeScript + Vite, Tailwind CSS v4, componentes shadcn/Radix
  ya instalados en `resources/js/components/ui/`.
- `spatie/laravel-permission` (roles) + Policies de Laravel + `App\Support\Scope`
  (reglas de alcance, ver arriba).
- `maatwebsite/excel` (instalado, **sin usar todavía** — es para la Fase 5).
- `barryvdh/laravel-dompdf` (ya en uso: exportar la ficha a PDF).
- Pest (pruebas), Pint (estilo), Larastan/PHPStan nivel 6 (análisis estático),
  ESLint + Prettier (frontend).
- Docker: `Dockerfile` (multi-etapa, `serversideup/php:8.3-fpm-nginx`),
  `docker-compose.yml` (dev) y `docker-compose.prod.yml` (prod) ya escritos, pero
  **nunca probados con Docker real** — esta máquina de desarrollo no tenía Docker
  instalado. Si vas a tocar algo de Docker, pruébalo de verdad antes de darlo por
  bueno.

## Cómo correr esto ahora mismo (desarrollo local sin Docker)

Este entorno usa XAMPP (MySQL en `127.0.0.1:3306`, usuario `root` sin contraseña,
base de datos `seguimiento_estudiantes`, ya creada).

```bash
composer install
npm install
php artisan migrate --seed   # o migrate:fresh --seed para reiniciar todo
composer run dev             # levanta: php artisan serve (puerto 8000) + vite (5173) + cola + logs
```

Abre `http://127.0.0.1:8000`. Usuarios de prueba (contraseña `password` para todos,
ver `database/seeders/DemoDataSeeder.php`):

| Correo | Rol |
|---|---|
| `admin@tvs.edu.co` | ADMIN |
| `andres.castano@tvs.edu.co` | Profesor, director de 10A |
| `diana.rios@tvs.edu.co` | Profesora (Matemáticas, 10A y 9B) |
| `laura.restrepo@tvs.edu.co` | Profesora (Inglés y Francés, 10A y 9B) |
| `catalina.mejia@tvs.edu.co` | Psicología |
| `felipe.uribe@tvs.edu.co` | EMC, sin sección (ve todo el colegio) |
| `natalia.serna@tvs.edu.co` | EMC, limitada a la sección DP |

Antes de dar por terminado cualquier cambio, corre:

```bash
./vendor/bin/pint --test        # estilo PHP
./vendor/bin/phpstan analyse    # análisis estático — debe dar 0 errores, sin baseline
./vendor/bin/pest               # pruebas
npm run lint                    # ESLint
npm run format:check            # Prettier
npm run build                   # confirma que el frontend compila
```

**Larastan corre sin `phpstan-baseline.neon`** (se eliminó a propósito). Si una
relación de Eloquent nueva causa errores en cascada ("Access to an undefined
property..."), casi siempre es porque le falta el docblock genérico
(`@return HasMany<Modelo, $this>`) o porque el modelo usa `protected function
casts(): array` en vez de `protected $casts = [...]` (esta versión de Larastan NO
infiere bien los casts declarados como método — usa siempre la forma de propiedad).
Ver el primer punto de la sección "Fase 1" en `DECISIONES.md` para el detalle de este
hallazgo.

**No hay commits de git todavía** (el repo no está inicializado). Pregúntale al
usuario si quiere que inicialices git antes de hacerlo.

## Qué está construido y qué falta

El encargo original se dividía en 6 fases. Estado real a la fecha:

### ✅ Fase 1 — Base
Migraciones, modelos (con genéricos de Eloquent completos), Enums de PHP, factories,
seeders con datos ficticios, Docker (sin probar), CI (`.github/workflows/ci.yml` y
`release.yml`).

### ⚠️ Fase 2 — Usuarios y permisos (parcial)
Lo que SÍ existe: roles con spatie, `App\Support\Scope` con todas las reglas de la
matriz de permisos, Policies (`GroupPolicy`, `StudentPolicy`) que delegan en `Scope`.

Lo que **falta** de esta fase:
- El registro público (`/register`) **sigue activo** — el encargo pide que NO lo
  esté ("sin registro público"). Hay que quitar la ruta y el controlador, o al menos
  bloquearlo.
- No hay cambio de contraseña obligatorio en el primer ingreso
  (`users.must_change_password` ya existe en la base de datos, pero nada en el
  frontend/middleware lo hace cumplir todavía).
- No hay pruebas Pest de la matriz de permisos (el encargo las pide explícitamente,
  "para cada celda de esta matriz"). Las pruebas que existen hoy son las que trae el
  starter kit (login, registro, etc.), no las de `Scope`.
- Rate limiting de login: ya cumple el encargo tal cual (`LoginRequest::
  ensureIsNotRateLimited()` usa `RateLimiter::tooManyAttempts($key, 5)` — 5 intentos).
  No hace falta tocarlo.

### ✅ Fase 3 — Vistas principales
Las tres vistas base del boceto, con sus controladores, construidas y **probadas en
el navegador** (sin errores de consola, con datos reales):

- **Reunión de grupo** (`/reunion`, `/reunion/{group}/{followUpId}`) —
  `app/Http/Controllers/ReunionController.php` +
  `resources/js/pages/reunion/show.tsx`.
- **Ficha del estudiante** (`/estudiantes`, `/estudiantes/{student}`) —
  `app/Http/Controllers/StudentController.php` +
  `resources/js/pages/estudiantes/show.tsx`. Incluye exportar a PDF
  (`/estudiantes/{student}/pdf`, usa `resources/views/pdf/ficha.blade.php`, tamaño carta con numeración
  de páginas automática por canvas de DomPDF, membrete institucional TVS / Continuo IB, código institucional,
  tarjetas KPI, desglose de compromisos colegio/familia, timeline de evaluaciones de estrategias, escala IB 1-7,
  apoyos confidenciales y bloque de firmas) y foto de perfil (`app/Http/Controllers/PhotoController.php`,
  recorte cuadrado con GD, servida por ruta autenticada — nunca pública).
- **Compromisos** (`/compromisos`) — `app/Http/Controllers/CompromisosController.php`
  + `resources/js/pages/compromisos/index.tsx`.

Controladores de apoyo para las acciones dentro de esas vistas (crear/borrar aportes,
estrategias, compromisos, apoyos externos, notas de desempeño, decisiones de comité,
casos disciplinarios): `ContributionController`, `StrategyController`,
`StrategyReviewController`, `CommitmentController`, `ExternalSupportController`,
`GradeController`, `CommitteeDecisionController`, `DisciplinaryCaseController`,
`GroupMeetingController`.

El layout institucional (barra azul/roja, pestañas, sin modo oscuro) está en
`resources/js/layouts/seguimiento-layout.tsx`. Los colores/tipografías
institucionales están en `resources/css/app.css` (tokens `--primary`, `--brand-red`,
`--good`/`--warn`/`--crit`, fuentes Bricolage Grotesque / Public Sans / IBM Plex
Mono). **El modo oscuro está deshabilitado a propósito**
(`resources/js/hooks/use-appearance.tsx` nunca aplica la clase `dark`) porque el
encargo pide "fondo blanco, sin modo oscuro" — no lo reactives.

### ⚠️ Fase 4 — Administración (parcial)
Existe (todo bajo `/admin`, protegido por `Gate::define('admin', ...)` en
`AppServiceProvider`, middleware `can:admin`):

- **Años escolares** (`/admin/anios`) — crear uno genera automáticamente sus 6
  seguimientos (3 periodos × 2 seguimientos, configurable en
  `config('seguimiento.periodos')`); activar/marcar solo-lectura.
- **Secciones** (`/admin/secciones`) — crear/editar/eliminar (bloquea el borrado si
  hay grupos usando la sección).
- **Grados** (`/admin/grados`) — catálogo `grade_levels` (Preescolar = 0 a 11.°,
  nace sembrado en la migración): crear, renombrar, activar/desactivar y eliminar
  (bloqueado si lo usan grupos o `grade_subject`). Es la fuente única de los grados:
  grupos y asignaturas leen de ahí (no de `config`), y las etiquetas de la ficha/PDF
  salen del catálogo.
- **Grupos** (`/admin/grupos`) — crear, editar sección/director, por año escolar.
- **Asignaturas** (`/admin/asignaturas`) — crear/editar, con los grados en los que
  aplica (`grade_subject`).
- **Estudiantes** (`/admin/estudiantes`) — crear estudiantes con o sin matrícula inicial,
  filtrar por año escolar/grupo/estado/búsqueda, cambiar o quitar grupo directamente por año,
  editar datos básicos, activar/desactivar, y registrar en `AuditLog`.
- **Periodo y seguimiento** (`/admin/seguimientos`) — listar seguimientos por año escolar
  con conteos de reuniones de grupo, aportes y estrategias vinculadas; crear seguimientos
  manuales o autogenerar los 6 seguimientos por defecto (3 periodos × 2 seguimientos); editar
  número/periodo; y eliminar con salvaguarda (bloquea el borrado si ya tiene datos asociados).
  Registra todas las acciones administrativas en `AuditLog`. Además, la ficha del estudiante
  ahora expone el selector de periodo/seguimiento para vincular aportes al seguimiento activo.

Lo que **falta** de esta fase (pedido explícitamente en el encargo, sección 6.5):

- **Usuarios**: crear, editar, desactivar, asignar roles y sección EMC, restablecer
  contraseña con contraseña temporal mostrada una sola vez. Ahora mismo el único
  mecanismo es `php artisan app:crear-admin correo` por consola
  (`app/Console/Commands/CrearAdmin.php`) — sirve para el primer ADMIN, no reemplaza
  la pantalla de administración de usuarios.
- **Asignaciones de profesores** (profesor → grupo → asignatura): sin pantalla; hoy
  solo existen las que crea `DemoDataSeeder`.
- **Auditoría**: la tabla `audit_logs` y el modelo `AuditLog` ya están conectados y en uso
  en los módulos administrativos de estudiantes y seguimientos (`admin.estudiantes`,
  `admin.seguimientos`). Falta extender el registro a las demás acciones del ADMIN y
  a las consultas de información confidencial.
- Carga masiva de usuarios/asignaturas por Excel (el encargo la menciona como opción
  para asignaturas y usuarios).

### ❌ Fase 5 — Migración de datos (sin empezar)
El asistente completo descrito en el encargo (leer el Excel actual columna por
nombre de encabezado, no por posición; emparejar estudiantes por nombre con
porcentaje de similitud; plantillas descargables; simulación sin guardar nada; Job en
cola con barra de progreso; reversión de un lote completo). La tabla `import_batches`
y las columnas de origen (`import_batch_id`, `source_file`, `source_row`) ya existen
en casi todas las tablas de contenido, así que el modelo de datos está listo para
recibirlo, pero no hay ni lector de Excel ni controlador ni vista.

### ❌ Fase 6 — Cierre (sin empezar)
Polling casi en tiempo real en la reunión de grupo (hoy no hay refresco automático:
hay que recargar o volver a navegar para ver los aportes de otra persona).
`docker-compose.prod.yml` existe pero no se ha probado. El pipeline de GitHub Actions
para publicar en Docker Hub (`.github/workflows/release.yml`) tampoco se ha probado
de punta a punta. Copias de seguridad: el comando
`php artisan app:respaldar-base-datos` existe y se probó localmente (mysqldump +
gzip), y está programado en `routes/console.php`, pero correr el contenedor
`scheduler` real nunca se verificó porque no hay Docker en esta máquina.

## Decisiones no obvias que ya se tomaron

No las repitas ni las cuestiones sin leer `DECISIONES.md` primero. Las más
importantes para seguir programando:

1. El starter kit de Laravel (`laravel/react-starter-kit`) no usa Fortify — trae sus
   propios controladores de auth estilo Breeze. Se dejaron así (no se instaló
   Fortify encima).
2. `Group::students()` es un `belongsToMany` a través de `enrollments` (con
   `Enrollment extends Pivot`, no `extends Model`) — **no** un `hasManyThrough`. Si
   ves código viejo o ejemplos con `hasManyThrough` para esto, está mal: se intentó
   así primero y falló porque `enrollments` no tiene una FK de vuelta hacia
   `students` (es una tabla de matrícula con su propia PK, no una tabla "intermedia"
   en el sentido clásico de Laravel).
3. `config('seguimiento.emc_escribe_notas_clinicas')` (en `.env`:
   `SEGUIMIENTO_EMC_ESCRIBE_NOTAS_CLINICAS`) controla si EMC puede escribir notas
   clínicas además de leerlas. Por defecto `false` (solo Psicología escribe).
4. "Observación" es una columna de la cuadrícula de Reunión de grupo, pero **no**
   es una sección de la Ficha del estudiante — así lo define el boceto aprobado y el
   encargo (sección 6.2 lista 11 secciones, sin "Observación"). No la agregues ahí de
   nuevo; ya se intentó y se revirtió.

## Mapa rápido de archivos clave

```
app/Support/Scope.php                  — TODAS las reglas de permisos (léelo primero)
app/Enums/                             — un Enum de PHP por cada campo tipo-lista
app/Models/                            — 19 modelos, todas las relaciones con genéricos
app/Http/Controllers/                  — un controlador por acción/recurso (ver arriba)
app/Http/Controllers/Admin/            — los 4 controladores de /admin que sí existen
database/migrations/                   — 24 migraciones, en orden de dependencias
database/seeders/DemoDataSeeder.php    — los datos ficticios de prueba
config/seguimiento.php                 — toda la configuración propia de la app
resources/js/layouts/seguimiento-layout.tsx — barra superior institucional
resources/js/pages/                    — una carpeta por recurso (reunion/, estudiantes/, compromisos/, admin/)
resources/css/app.css                  — paleta y tipografías institucionales
routes/web.php                         — todas las rutas (sin API, todo vía Inertia)
DECISIONES.md                          — por qué se hizo cada cosa no-obvia
README.md                              — cómo correr/desplegar, con más detalle que este archivo
```

## Si vas a seguir construyendo

Orden sugerido (completa lo que falta de cada fase antes de pasar a la siguiente,
como pide el encargo original):

1. Cerrar la Fase 2: quitar/bloquear el registro público, forzar cambio de
   contraseña en el primer ingreso, escribir las pruebas Pest de la matriz de
   permisos contra `App\Support\Scope`.
2. Cerrar la Fase 4: pantallas de Usuarios, Estudiantes/Matrículas, Asignaciones de
   profesores, y conectar `AuditLog` a las acciones sensibles.
3. Fase 5 completa (el asistente de migración de Excel).
4. Fase 6 (polling, probar Docker de verdad, probar el pipeline de release).

Antes de escribir una sola línea de permisos nueva: lee `app/Support/Scope.php`
completo. Antes de tocar el diseño: abre `Seguimiento de Estudiantes.html` en un
navegador. Antes de asumir que algo no está decidido: busca en `DECISIONES.md`.
