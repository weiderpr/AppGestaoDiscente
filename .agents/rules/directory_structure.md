---
trigger: always_on
---

# Project Directory Structure Standards

This document defines the mandatory directory structure for the **Vértice Acadêmico** project. All new files must be placed according to these definitions to maintain organizational integrity.

## 1. Directory Tree Overview

| Directory | Purpose | Rule |
| :--- | :--- | :--- |
| `/` (Root) | Infrastructure | Reserved for `index.php`, `.htaccess`, and global entry points. |
| `/src` | Object-Oriented Logic | All new Classes (Controllers, Services, Models) must be here. |
| `/public` | Web-Accessible Assets | (Target) All assets and global entry points. |
| `/assets` | Frontend Assets | CSS, JS, and Images used by the UI. |
| `/api` | JSON Endpoints | Centralized location for AJAX and specialized API handlers. |
| `/includes` | Shared Procedural | Reusable scripts, global middleware, and UI components (modals). |
| `/views` | UI Templates | PHP files responsible exclusively for rendering HTML. |
| `/config` | Configuration | Database settings and environment-specific configs. |
| `/scripts` | Lifecycle Scripts | Migrations, one-off maintenance, and CLI tools. |
| `/docs` | Documentation | SQL schemas, architectural diagrams, and manuals. |

## 2. Strict Placement Rules

### 2.1 Classes & Object-Oriented Code
- **Location**: Must reside in `/src/App/` or `/src/Core/`.
- **Constraint**: Never create `.php` classes directly in `/includes` or functional module folders.

### 2.2 AJAX Handlers
- **Location**: Use `/api/` for cross-cutting endpoints or `/module_name/ajax.php` for module-specific logic.
- **Goal**: Consolidate `*_ajax.php` files into centralized API directories to avoid root pollution.

### 2.3 UI Components (Modals)
- **Location**: Store reusable modal definitions in `/includes/modals/` or within `/includes/`.
- **Pattern**: Ensure these files focus on structure, relying on the central Toast/Modal systems for behavior.

### 2.4 Maintenance & Migrations
- **Location**: All files starting with `migrate_` or `tmp_` must be moved to or created in `/scripts/` (if the directory exists) or a dedicated subfolder.

## 3. Separation of Concerns

1.  **Logic vs. Presentation**: Business logic belongs in **Classes (Services)**. Presentation belongs in **Views**. Entry points should only coordinate between the two.
2.  **Public vs. Private**: Only the root (or future `/public`) and `/assets` should be directly accessible by the web server. Logic files in `/src` and `/config` must be protected.
