---
trigger: always_on
---

# Database Standards and Persistence Guidelines

This document defines the mandatory standards for database access and persistence in the **Vértice Acadêmico** project. All code performing database operations must strictly comply with these rules.

## 1. Technology & Methods

- **PDO (PHP Data Objects) ONLY**: Use of `mysqli_*`, `mysql_*`, or any other legacy database extensions is **strictly prohibited**.
- **Prepared Statements**: **MANDATORY** for all queries containing variable input. Direct insertion of variables into SQL strings is **FORBIDDEN** to prevent SQL Injection.
    - **Correct**: `$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$id]);`
    - **Incorrect**: `$db->query("SELECT * FROM users WHERE id = " . $id);`
- **Data Model**: The project uses an "Active Service" pattern with raw SQL. Do not introduce external ORMs without explicit architectural approval.

## 2. Connections & Persistence Layer

- **Core Access**: Always use the global `getDB()` function from `config/database.php` to obtain the PDO instance.
- **Service Layer**:
    - All business logic requiring database access must be placed in `src/App/Services/`.
    - Service classes **MUST** extend `App\Services\Service`.
    - Use the protected `$this->db` property for all operations.
- **Procedural Scripts**: In legacy or top-level functional scripts, include `config/database.php` and call `getDB()`.

## 3. Operations & Configuration

- **Fetch Mode**: Default must be **`PDO::FETCH_ASSOC`** (configured in `getDB()`). Avoid changing this globally.
- **Error Handling**: 
    - PDO is configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.
    - Expect exceptions for all failed queries. Use `try/catch` blocks around transaction-critical or potentially failing operations.
- **Transactions**: For operations involving multiple related queries, use:
    ```php
    $this->db->beginTransaction();
    try {
        // operations
        $this->db->commit();
    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
    ```
- **Type Hinting**: All methods in the service layer must use strict typing for parameters and return values (e.g., `execute(string $sql, array $params): int`, `fetchOne(...): ?array`).

## 4. Security Restrictions

- **Direct Queries**: Never execute `$_POST`, `$_GET`, or any user-controlled input directly in a query.
- **Configuration Storage**: Database credentials must **NEVER** be committed to the repository. They must reside in `config/config.local.php`.
- **Primary Keys**: Always treat IDs as integers and use `(int)` casting when retrieving from the database if necessary.
