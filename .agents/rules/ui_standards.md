---
trigger: always_on
---

# UI Standards and Component Guidelines

This document defines the user interface standards, component structures, and styling restrictions for the **Vértice Acadêmico** project. All new UI developments must strictly follow these patterns.

## 1. Modals

The project uses a custom, lightweight modal system. Avoid adding external libraries like Bootstrap JS for modals.

- **Implementation**: Always use the system defined in `/includes/modal.php`.
- **HTML Structure**:
    ```html
    <div id="modalId" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Título do Modal</h3>
                <button class="modal-close" onclick="closeModal('modalId')">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Conteúdo aqui -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalId')">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    ```
- **JavaScript Triggers**:
    - `openModal('modalId')`: To display the modal.
    - `closeModal('modalId')`: To hide the modal.
    - `globalModal('modalId', 'open'|'close')`: Alternative helper.

## 2. Forms

Forms must follow a consistent structure to ensure proper spacing and validation feedback.

- **Structure**:
    ```html
    <div class="form-group">
        <label class="form-label">Nome do Campo <span class="required">*</span></label>
        <div class="input-group">
            <span class="input-icon">👤</span> <!-- Ícone opcional -->
            <input type="text" class="form-control" id="fieldId" placeholder="Exemplo">
        </div>
        <!-- Feedback de erro (invisível por padrão) -->
        <div class="invalid-feedback">Este campo é obrigatório.</div>
    </div>
    ```
- **Inputs**: Always use the `.form-control` class. For invalid states, add `.is-invalid` to the input.
- **Buttons**: Use `.btn` with modifiers:
    - `.btn-primary`: For main actions (Background gradient).
    - `.btn-secondary`: For neutral/cancel actions.
    - `.btn-sm`, `.btn-lg`: For different sizes.

## 3. Notifications (Toasts & Alerts)

- **Native `alert()` is FORBIDDEN**: Never use the default browser alert.
- **Toasts**: Use the custom system in `assets/js/components/Toast.js`.
- **Functions**:
    - `showToast(message, type, duration)`: Base function.
    - `showSuccess(message)`: Success notification (Green).
    - `showError(message)`: Error notification (Red).
    - `showWarning(message)`: Warning notification (Yellow).
    - `showInfo(message)`: Informational notification (Blue).
- **Persistent Alerts**: For inline page warnings, use the `.alert` class with modifiers `.alert-danger`, `.alert-success`, etc.

## 4. Styling Restrictions

- **No Inline Styles**: The use of `style="..."` in HTML elements is strictly prohibited, except for dynamic positions calculated via JS.
- **CSS Variables Only**: All colors, margins, and borders must use the CSS Custom Properties defined in `:root` inside `assets/css/style.css`.
    - Example: `color: var(--color-primary);` instead of `color: #4f46e5;`.
- **New Colors**: Do not create new color definitions or ad-hoc hex codes. If a new color is necessary, it must be proposed and added to the global `:root` variables.
- **Spacing**: Use standard utility classes like `.mt-sm`, `.mt-md`, `.gap-sm` for margins and gaps when possible.
