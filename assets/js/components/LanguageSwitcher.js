/**
 * Vértice Acadêmico — Language Switcher
 */

class LanguageSwitcher {
    static init() {
        document.querySelectorAll('[data-language-switch]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const locale = e.target.dataset.locale || e.target.closest('[data-language-switch]').dataset.locale;
                this.changeLanguage(locale);
            });
        });
    }

    static changeLanguage(locale) {
        fetch(`/api/set_language.php?locale=${locale}`, {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(err => {
            console.error('Error changing language:', err);
            showError('Error changing language');
        });
    }
}

// Inicializar quando DOM pronto
document.addEventListener('DOMContentLoaded', () => LanguageSwitcher.init());
