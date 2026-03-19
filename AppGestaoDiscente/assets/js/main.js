/**
 * Vértice Acadêmico — Main JavaScript
 */

/* =============================================
   TEMA (Dark / Light)
   ============================================= */

const THEME_KEY = 'va_theme';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeIcon(theme);
    localStorage.setItem(THEME_KEY, theme);
}

function updateThemeIcon(theme) {
    document.querySelectorAll('[data-theme-icon]').forEach(el => {
        el.textContent = theme === 'dark' ? '☀️' : '🌙';
        el.setAttribute('title', theme === 'dark' ? 'Mudar para tema claro' : 'Mudar para tema escuro');
    });
}

function getInitialTheme() {
    // 1. Preferência do servidor (da conta do usuário)
    const serverTheme = document.documentElement.dataset.serverTheme;
    if (serverTheme) return serverTheme;
    // 2. Preferência local
    const stored = localStorage.getItem(THEME_KEY);
    if (stored) return stored;
    // 3. Preferência do sistema operacional
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);

    // Se usuário estiver logado, salva no servidor via AJAX
    const userId = document.documentElement.dataset.userId;
    if (userId) {
        fetch('/api/update_theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: next })
        }).catch(() => {}); // silently fail
    }
}

// Aplica tema imediatamente ao carregar (antes do CSS estar pronto)
(function() {
    const theme = document.documentElement.dataset.serverTheme
        || localStorage.getItem(THEME_KEY)
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();

/* =============================================
   DROPDOWN DO USUÁRIO
   ============================================= */

function initUserMenu() {
    const userMenu = document.getElementById('userMenu');
    if (!userMenu) return;

    const btn = userMenu.querySelector('.user-avatar-btn');
    if (!btn) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!userMenu.contains(e.target)) {
            userMenu.classList.remove('open');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') userMenu.classList.remove('open');
    });
}

/* =============================================
   TOGGLE DE SENHA (mostrar/ocultar)
   ============================================= */

function initPasswordToggle() {
    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.togglePassword;
            const input = document.getElementById(targetId);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.textContent = isPassword ? '🙈' : '👁️';
        });
    });
}

/* =============================================
   FORÇA DE SENHA
   ============================================= */

function checkPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
}

function initPasswordStrength() {
    const input = document.getElementById('password');
    const bar   = document.getElementById('strengthFill');
    const text  = document.getElementById('strengthText');
    if (!input || !bar || !text) return;

    const labels = ['', 'Fraca', 'Razoável', 'Boa', 'Forte'];
    const classes = ['', 'weak', 'fair', 'good', 'strong'];

    input.addEventListener('input', () => {
        const score = checkPasswordStrength(input.value);
        bar.className = 'strength-fill ' + (classes[score] || '');
        text.textContent = input.value ? `Senha ${labels[score] || ''}` : '';
    });
}

/* =============================================
   PREVIEW DE AVATAR
   ============================================= */

function initAvatarPreview() {
    const fileInput   = document.getElementById('photo');
    const previewImg  = document.getElementById('avatarPreview');
    const placeholder = document.getElementById('avatarPlaceholder');
    if (!fileInput || !previewImg) return;

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione um arquivo de imagem válido.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 5MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            previewImg.src = ev.target.result;
            previewImg.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    // Clicar na preview também abre o seletor de arquivo
    const ring = document.getElementById('avatarRing');
    if (ring) {
        ring.addEventListener('click', () => fileInput.click());
        ring.style.cursor = 'pointer';
    }
}

/* =============================================
   VALIDAÇÃO DE FORMULÁRIO DE REGISTRO
   ============================================= */

function initRegisterValidation() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        let valid = true;
        clearErrors(form);

        const name = form.querySelector('#name');
        if (name && name.value.trim().length < 3) {
            showError(name, 'Nome deve ter pelo menos 3 caracteres.');
            valid = false;
        }

        const email = form.querySelector('#email');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            showError(email, 'Informe um e-mail válido.');
            valid = false;
        }

        const password = form.querySelector('#password');
        if (password && password.value.length < 6) {
            showError(password, 'A senha deve ter pelo menos 6 caracteres.');
            valid = false;
        }

        const confirm = form.querySelector('#password_confirm');
        if (confirm && confirm.value !== password?.value) {
            showError(confirm, 'As senhas não coincidem.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
        } else {
            setLoading(form.querySelector('[type="submit"]'), true);
        }
    });
}

function showError(input, message) {
    input.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.innerHTML = `⚠️ ${message}`;
    input.parentElement.appendChild(feedback);
}

function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
}

/* =============================================
   UTILITÁRIOS
   ============================================= */

function setLoading(btn, loading) {
    if (!btn) return;
    btn.classList.toggle('loading', loading);
    btn.disabled = loading;
}

function dismissAlert(btn) {
    const alert = btn.closest('.alert');
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-6px)';
        alert.style.transition = 'all 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }
}

/* =============================================
   INIT
   ============================================= */

document.addEventListener('DOMContentLoaded', () => {
    // Aplica tema após DOM pronto e atualiza ícone
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    updateThemeIcon(theme);

    // Adiciona listener nos botões de tema
    document.querySelectorAll('[data-action="toggleTheme"]').forEach(btn => {
        btn.addEventListener('click', toggleTheme);
    });

    initUserMenu();
    initPasswordToggle();
    initPasswordStrength();
    initAvatarPreview();
    initRegisterValidation();
});
