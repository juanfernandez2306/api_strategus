# Hoja de Ruta y Checklist de Refactorización (Slim API)

## Arquitectura y Componentes Base

# Infraestructura Base

- [/] **Configuración de Slim**
  - [x] Instanciación de la app (`public/index.php`)
  - [x] Registro completo del contenedor DI (`app/dependencies.php`)

- [/] **Middlewares** (`src/Middleware/`)
  - [x] AuthMiddleware (`src/Middleware/AuthMiddleware.php`)
  - [x] RoleMiddleware (`src/Middleware/RoleMiddleware.php`)
  - [x] RateLimitMiddleware (`src/Middleware/RateLimitMiddleware.php`)

- [x] **Manejo de Errores** (`src/Application/Handlers/HttpErrorHandler.php`)
  - [x] Respuestas JSON estructuradas para errores (`src/Shared/Http/ApiResponse.php`)
  - [x] Manejo de respuestas https (`src/Shared/Http/HttpStatus.php`)

- [/] **Base de Datos / ORM** (`app/dependencies.php`)
  - [x] Conexión PDO
  - [x] Inyección en repositorios

## Desglose de Middlewares

* [x] **Autenticación / Autorización** 
    (`src/Middleware/AuthMiddleware.php` con validación de tokens en BD)

* [x] **CORS Middleware** (`app/settings.php`, `src/Middleware/CorsMiddleware.php`)

* [x] **Logs** (`app/settings.php` se genera una trazabilidad manual con Monolog)

* [/] Pruebas de Infraestructura y Middlewares
  - [ ] AuthMiddlewareTest (Test de integración para validación de tokens, headers y base de datos)

## Progreso de Módulos / Funcionalidades (Features)

### Módulo 1: [Nombre de la Feature 1, ej: Usuarios / Auth]

* **Estado:** 🟡 Casi Listo

* **Endpoints:**

  * [x] `POST /v1/auth/login`

  * [x] `POST /v1/auth/registro`

  * [ ] `GET /v1/usuarios/perfil`

* **Tareas Pendientes:**

  * [ ] Agregar pruebas unitarias o de integración

  * [ ] Estandarizar la respuesta JSON del controlador

### Módulo 2: [Nombre de la Feature 2, ej: Productos / Pedidos]

* **Estado:** 🟡 Casi Listo

* **Endpoints:**

  * [x] `GET /v1/productos`

  * [x] `GET /v1/productos/{id}`

  * [ ] `POST /v1/productos`

* [ ] **Parseo de JSON / Validación**

* **Tareas Pendientes:**

  * [ ] Validar el cuerpo de las peticiones (Request Body)

### Módulo 3: [Próximo Módulo]

* **Estado:** 🔴 Pendiente

* **Endpoints:**

  * [ ] Por definir

## 📋 Lista de Buenas Prácticas para Refactorizar

* [ ] **Inyección de Dependencias**: Controladores y servicios se inyectan mediante el Contenedor PSR-11 (sin `new Clase()` dentro de los controladores).

* [ ] **Responsabilidad Única (SRP)**: Los controladores solo manejan HTTP (Request/Response). La lógica de negocio vive en la capa de Servicios/Dominio.

* [ ] **Respuestas Estandarizadas**: Uso del objeto `$response` (PSR-7) para responder siempre con una estructura uniforme (ej: `{ "data": ..., "error": ... }`).

* [ ] **Variables de Entorno**: Datos sensibles cargados mediante archivo `.env` (`vlucas/phpdotenv` o similar).

## 📝 Próximos Pasos Inmediatos

1. Renombrar el Módulo 1 y Módulo 2 con las funcionalidades reales en las que estás trabajando.

2. Terminar de revisar los middlewares que ya tienes implementados.

3. Pasar el siguiente endpoint o módulo a estado `En Progreso`.