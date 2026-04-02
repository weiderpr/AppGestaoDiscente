/**
 * ImagePreview Utility 
 * Exibe um balão com a imagem ampliada ao passar o mouse por um tempo determinado.
 * Uso: Adicionar o atributo data-preview-image="/caminho/da/imagem.jpg" em qualquer elemento.
 */
const ImagePreview = {
    balloon: null,
    timer: null,
    delay: 500, // Tempo em ms para abrir (0.5s)

    init() {
        if (this.balloon) return; // Garante inicialização única

        // Cria o elemento do balão se não existir
        this.balloon = document.createElement('div');
        this.balloon.id = 'image-preview-balloon';
        this.balloon.className = 'image-preview-balloon';
        this.balloon.style.display = 'none';
        this.balloon.style.position = 'fixed';
        this.balloon.style.pointerEvents = 'none';
        document.body.appendChild(this.balloon);

        // Delegação de eventos global
        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('[data-preview-image]');
            if (target) {
                this.handleMouseEnter(target);
            }
        });

        document.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-preview-image]');
            if (target) {
                this.handleMouseLeave();
            }
        });

        // Fecha ao rolar a página ou redimensionar
        window.addEventListener('scroll', () => this.hide(), { passive: true });
        window.addEventListener('resize', () => this.hide(), { passive: true });
    },

    handleMouseEnter(target) {
        this.clearTimer();
        const imageUrl = target.getAttribute('data-preview-image');
        if (!imageUrl || imageUrl.trim() === '') return;

        this.timer = setTimeout(() => {
            this.show(target, imageUrl);
        }, this.delay);
    },

    handleMouseLeave() {
        this.clearTimer();
    },

    clearTimer() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this.hide();
    },

    show(target, imageUrl) {
        if (!this.balloon) return;

        // Atualiza imagem
        this.balloon.innerHTML = `<img src="${imageUrl}" alt="Preview" onerror="this.parentElement.style.display='none'">`;
        this.balloon.style.display = 'block';

        // Calcula posicionamento
        const rect = target.getBoundingClientRect();
        const balloonRect = this.balloon.getBoundingClientRect();

        // Tenta posicionar acima do elemento
        let top = rect.top - balloonRect.height - 12;
        let left = rect.left + (rect.width / 2) - (balloonRect.width / 2);

        // Se sair pelo topo, inverte para baixo
        if (top < 10) {
            top = rect.bottom + 12;
        }

        // Ajusta limites horizontais
        if (left < 15) left = 15;
        const maxLeft = window.innerWidth - balloonRect.width - 15;
        if (left > maxLeft) left = maxLeft;

        this.balloon.style.top = `${top}px`;
        this.balloon.style.left = `${left}px`;
    },

    hide() {
        if (this.balloon) {
            this.balloon.style.display = 'none';
        }
    }
};

// Inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ImagePreview.init());
} else {
    ImagePreview.init();
}
