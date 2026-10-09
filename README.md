# Seguimiento de Estudiantes · The Victoria School (TVS)

Sistema institucional web para el **Colegio The Victoria School (TVS)** diseñado para gestionar, acompañar y consolidar el seguimiento integral y acuerdos por estudiante a lo largo del año escolar (Continuo IB: PEP, PAI y DP). Reemplaza el formato tradicional en hoja de cálculo por una plataforma web colaborativa, reactiva y centralizada.

---

## 🚀 Características Principales

- **Reunión de Grupo en Tiempo Real:** Tablero interactivo donde profesores de asignatura, directores de grupo, coordinación y equipo de apoyo consolidan aportes formativos, fortalezas y aspectos de mejora simultáneamente.
- **Asignaturas para tener en cuenta:** Registro de observaciones pedagógicas cualitativas por materia, chips identificadores y alertas contextuales para calificaciones IB en nivel de atención ($\le 3$).
- **Ficha Integral del Estudiante:** Vista 360° con historial de acompañamiento, desempeño por periodos, compromisos (colegio y familia), decisiones de comités y casos de convivencia.
- **Exportación a PDF Institucional:** Generación de informes oficiales vectoriales de alta fidelidad diagramados conforme a la identidad gráfica institucional de TVS (azul `#364E76`, rojo `#ED3236`).
- **Control de Acceso Basado en Roles y Alcance:** Permisos estrictos con `spatie/laravel-permission` y políticas según asignación académica (Profesor, Director de Grupo, Psicología, EMC/Coordinación, Administrador).
- **Módulo de Administración:** Gestión de estudiantes, asignaciones, grupos, asignaturas y periodos de seguimiento.

---

## 🛠️ Stack Tecnológico

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Inertia.js (React 18 + TypeScript + Vite)
- **Estilos:** Tailwind CSS con componentes shadcn/ui y tokens institucionales TVS
- **Base de Datos:** MySQL 8+ / MariaDB (compatible con SQLite para desarrollo rápido)
- **Motor de PDF:** `barryvdh/laravel-dompdf`
- **Herramientas de Calidad:** Laravel Pint (formato PHP), PHPStan (análisis estático Nivel 6), ESLint y Prettier

---

## 💻 Requisitos Previos

Antes de instalar el proyecto, asegúrate de contar con:

- **PHP 8.2 o superior** (con extensiones `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `gd`, `zip`, `fileinfo`)
- **Composer** (v2+)
- **Node.js** (v20+ o v22 LTS) y **npm**
- **Servidor de Base de Datos:** MySQL 8.0+, MariaDB 10.4+ o SQLite
- *(Opcional)* **Docker** y **Docker Compose**

---

## 📦 Guía de Instalación Paso a Paso (Entorno Local)

### 1. Clonar el Repositorio

```bash
git clone https://github.com/caprietoj/Seguimiento_Estudiantes_TVS.git
cd Seguimiento_Estudiantes_TVS
```

### 2. Instalar Dependencias de PHP

```bash
composer install
```

### 3. Instalar Dependencias de JavaScript

```bash
npm install
```

### 4. Configurar el Archivo de Entorno

Copia el archivo de ejemplo y genera la clave criptográfica de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` para ajustar las credenciales de tu base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seguimiento_estudiantes
DB_USERNAME=root
DB_PASSWORD=tu_password
```

*(Si utilizas XAMPP, asegúrate de crear la base de datos `seguimiento_estudiantes` en phpMyAdmin o MySQL antes de ejecutar las migraciones).*

### 5. Ejecutar Migraciones y Cargar Datos Iniciales (Seeders)

Aplica la estructura de base de datos y carga los datos de demostración con usuarios, grupos (10A, 9B), asignaturas y seguimientos iniciales:

```bash
php artisan migrate --seed
```

### 6. Compilar los Assets del Frontend

Para entorno de desarrollo (con recarga rápida en vivo):
```bash
npm run dev
```

O para compilar el paquete de producción optimizado:
```bash
npm run build
```

### 7. Iniciar el Servidor de Desarrollo

En una terminal independiente, levanta el servidor local de Laravel:

```bash
php artisan serve
```

Ingresa en tu navegador a: **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## 🐳 Instalación con Docker (Opcional)

Si prefieres ejecutar el entorno completo mediante contenedores:

```bash
cp .env.example .env
docker compose up -d
```

Servicios levantados:
- **Aplicación web:** [http://localhost:8080](http://localhost:8080)
- **Vite (HMR):** [http://localhost:5173](http://localhost:5173)
- **MySQL 8.4:** puerto 3306
- **Mailpit:** [http://localhost:8025](http://localhost:8025)

---

## 🔐 Usuarios y Credenciales de Prueba

Todos los usuarios del seeder inicial tienen por defecto la contraseña: **`password`**

| Usuario / Correo | Rol / Alcance | Descripción |
|---|---|---|
| `admin@tvs.edu.co` | **ADMIN** | Control total, administración del sistema y configuración. |
| `andres.castano@tvs.edu.co` | **Profesor** | Director de grupo de 10A y docente de Historia. |
| `diana.rios@tvs.edu.co` | **Profesor** | Docente de Matemáticas en 10A y 9B. |
| `laura.restrepo@tvs.edu.co` | **Profesor** | Docente de Inglés y Francés en 10A y 9B. |
| `catalina.mejia@tvs.edu.co` | **Psicología** | Departamento de apoyo formativo y notas clínicas. |
| `felipe.uribe@tvs.edu.co` | **EMC (Coordinación)** | Coordinador general institucional (todas las secciones). |
| `natalia.serna@tvs.edu.co` | **EMC (Coordinación)** | Coordinación de la sección DP (grados 10 y 11). |

*(Nota: En entornos de producción, cambie inmediatamente las contraseñas predeterminadas).*

---

## 🧪 Comandos Útiles de Mantenimiento y Calidad

```bash
# Formateo y corrección de estándares de código PHP
./vendor/bin/pint

# Análisis estático de tipos con PHPStan (Nivel 6)
./vendor/bin/phpstan analyse

# Linter y verificación de sintaxis en TypeScript/React
npm run lint

# Formateo de código frontend con Prettier
npm run format
```

---

## 📄 Licencia

Este proyecto es de uso exclusivo y confidencial para **The Victoria School**. Todos los derechos reservados.
