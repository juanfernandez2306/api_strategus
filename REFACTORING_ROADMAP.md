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

## Módulo de Usuario (User Feature)

### Endpoints
- [x] **`POST /users/register`**
  - **Acción / Controller:** `App\Users\Actions\Auth\RegisterAction` 
  (`src/Users/Actions/Auth/RegisterAction.php`)
  - **Servicio de Negocio:** `App\Users\Services\Auth\RegisterUserService` 
  (`src/Users/Services/Auth/RegisterUserService.php`)
  - **Repositorio de Persistencia:** `App\Users\Repositories\Auth\PdoUserRepository` 
  (`src/Users/Repositories/Auth/PdoUserRepository.php`)
  - **Validación de Entradas:** `App\Users\Validators\RegisterValidator` 
  (`src/Users/Validators/RegisterValidator.php`)
  - **Manejo de Errores y Transacciones:** Captura de conflictos por duplicidad en correo o documento (`BINARY(16)` UUID v7) y emisión estandarizada vía 
  `ApiResponse` (`409 Conflict` / `422 Unprocessable Entity`).

### DTOs y Objetos Inmutables
- [x] **`RegisterUserDTO`** (`src/Users/DTOs/Auth/RegisterUserDTO.php`)
  - Transfiere los datos inmutables del registro 
  (`first_name`, `last_name`, `email`, `password`, 
  `birth_date`, `dni_type`, `dni_number`, `phone_number`).

### Pruebas e Integración (QA)
- [x] **Pruebas de Integración (Integration Tests)**
  - [x] `RegisterActionIntegrationTest` 
  (`tests/Integration/Users/RegisterActionIntegrationTest.php`)
    - Pruebas cubiertas:
      1. Registro exitoso de usuario con envío de correo de verificación simulado 
      (`201 Created`).
      2. Validación de fallos ante intentos de registro duplicado por `email` o `dni_number`.
      3. Verificación del contrato JSON uniforme a través de `ApiResponse` y `HttpErrorHandler`.

* **Endpoints:**
  * [x] `POST /users/register`
        [ ] DTOs
  * [x] `POST /users/login`
  * [ ] `POST /users/email/verify`
  * [ ] `POST /users/email/resend`
  * [ ] `POST /users/password/forgot`
  * [ ] `POST /users/password/reset`

* [ ] **Parseo de JSON / Validación**
