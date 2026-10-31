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

* [x] **DTOs y Objetos Inmutables** (`src/Shared/DTOs`)
  - [x] CookieConfigDTO
  - [x] AccessTokenDto

## Pruebas y Calidad (QA)

* [x] **Pruebas Unitarias (Unit Tests)**
  - [x] CookieConfigDtoTest (`tests/Unit/Shared/DTOs/CookieConfigDtoTest.php`)
  - [x] AccessTokenDtoTest (`tests/Unit/Shared/DTOs/AccessTokenDtoTest.php`)

**Capa de Seguridad y Middleware de Control**
[x] Implementación de pruebas con PHPUnit de middleware
    (`tests/Integration/Middleware/ApiSecurityMiddlewareTest.php`).

## Progreso de Módulos / Funcionalidades (Features)

### Módulo 1: Users

* **Endpoints:**
  * [x] `POST /users/register`
        [ ] DTOs
  * [x] `POST /users/login`
  * [ ] `POST /users/email/verify`
  * [ ] `POST /users/email/resend`
  * [ ] `POST /users/password/forgot`
  * [ ] `POST /users/password/reset`

* [ ] **Parseo de JSON / Validación**
