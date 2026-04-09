---
trigger: always_on
---

Sempre respeite as definições em .agents/rules/ antes de propor alterações.

# Project Architecture and Coding Standards

This document defines the architectural patterns, naming conventions, and coding standards for the **Vértice Acadêmico** project. All future implementations must adhere to these rules to ensure consistency and maintainability.

## 1. Directory Structure

The project follows a "Pragmatic Hybrid" architecture, combining modern MVC patterns with established procedural entry points.

- `/src`: Object-oriented core logic.
    - `/src/Core`: Base framework classes (Router, Controller, etc.).
    - `/src/App/Services`: Business logic and service layers.
- `/includes`: Reusable procedural scripts, helper functions, and global middlewares (Auth, CSRF).
- `/views`: PHP templates for UI rendering.
- `/assets`: Frontend resources (CSS, JS, Images).
- `/config`: System configuration and database initialization.
- `/api`: Specialized JSON endpoints.
- `/root`: Functional entry points (e.g., `dashboard.php`, `settings.php`).

## 2. Naming Conventions

### 2.1 Files & Folders
- **Directories**: Always `lowercase`.
- **Classes**: `PascalCase` matching the class name (e.g., `PermissionService.php`).
- **Procedural Scripts**: `snake_case.php` (e.g., `auth_helper.php`).

### 2.2 Variables & Logic
- **Classes**: `PascalCase`.
- **Methods & Functions**: `camelCase` (e.g., `getDB()`, `isLoggedIn()`).
- **Variables**: `camelCase` (e.g., `$userData`, `$currentInstitution`).
- **Constants**: `SCREAMING_SNAKE_CASE` (e.g., `DB_HOST`).
- **Database Tables/Fields**: `snake_case`.

### 2.3 Namespaces
- Follow the directory structure starting from `src` (e.g., `namespace App\Services;`).

## 3. Database Standards

- **Connection**: Always use the global `getDB()` function from `config/database.php`.
- **API**: Use **PDO** for all database interactions.
- **Security**: Never concatenate variables into SQL strings. **Always** use prepared statements with placeholders (`?` or `:params`).
- **Error Handling**: PDO is configured to throw exceptions by default (`PDO::ERRMODE_EXCEPTION`).

## 4. Coding Style (PHP)

- **Standards**: Strictly follow **PSR-12**.
- **Indentation**: 4 spaces.
- **Braces**: Opening braces for classes and methods must be on the same line as the declaration.
- **Type Hinting**: Use strict type hinting for function parameters and return values (e.g., `function register(array $data): bool`).
- **Security**:
    - Protect all POST requests with `csrf_verify()`.
    - Use `isLoggedIn()` and `hasDbPermission()` for access control.
    - Sanitize all user-input data using common helpers.

## 5. Architectural Principles

- **Separation of Concerns**: Keep business logic in `Services` and UI logic in `Views` or `includes/modals`.
- **DRY (Don't Repeat Yourself)**: Move common logic to `includes/functions.php` or specialized Services.
- **AJAX handling**: Use `success()` and `error()` methods from the base `Controller` class (or equivalent JSON/header helpers) for consistent API responses.

Sempre que sugerir mover um arquivo para a nova estrutura definida em directory_structure.md, você deve automaticamente identificar e atualizar todos os include, require e caminhos de arquivos (href/src) que apontavam para o local antigo.
