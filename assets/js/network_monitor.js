/**
 * Vértice Acadêmico — Monitor de Conexão e Rede Autorizada
 */
(function() {
    // Evita inicialização dupla
    if (window.hasOwnProperty('__networkMonitorLoaded')) return;
    window.__networkMonitorLoaded = true;

    // Injeta os estilos do overlay
    const styles = `
        #network-error-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            padding: 20px;
            box-sizing: border-box;
        }
        #network-error-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .network-card {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 24px;
            padding: 2rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: var(--text-primary, #1e293b);
        }
        #network-error-overlay.active .network-card {
            transform: scale(1);
        }
        .network-icon-container {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: #ef4444;
            animation: pulse-red 2s infinite;
        }
        .network-icon-container svg {
            width: 40px;
            height: 40px;
        }
        .network-title {
            font-family: 'Outfit', 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }
        .network-desc {
            font-size: 0.925rem;
            color: var(--text-secondary, #475569);
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .network-instructions {
            text-align: left;
            background: var(--bg-body, #f8fafc);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
        }
        .network-instructions-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #64748b);
            margin-bottom: 0.5rem;
        }
        .network-step {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.875rem;
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .network-step:last-child {
            margin-bottom: 0;
        }
        .network-step-bullet {
            color: #ef4444;
            font-weight: bold;
        }
        .network-btn {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .network-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
            filter: brightness(1.05);
        }
        .network-btn:active {
            transform: translateY(0);
        }
        .network-btn-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: network-spin 0.8s linear infinite;
            display: none;
        }
        @keyframes network-spin {
            to { transform: rotate(360deg); }
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        [data-theme="dark"] .network-card {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }
        [data-theme="dark"] .network-instructions {
            background: #0f172a;
            border-color: #334155;
        }
    `;

    // Cria a folha de estilos
    const styleEl = document.createElement('style');
    styleEl.innerHTML = styles;
    document.head.appendChild(styleEl);

    // Cria o HTML do overlay
    const overlayHtml = `
        <div class="network-card">
            <div class="network-icon-container">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-3.536 4.978 4.978 0 011.414-3.536m0 0L8.464 8.464M5.636 18.364a9 9 0 010-12.728m0 0L8.464 8.464" />
                </svg>
            </div>
            <div class="network-title">Sem Acesso à Rede</div>
            <div class="network-desc">Não conseguimos estabelecer uma conexão com os servidores do Vértice Acadêmico.</div>
            
            <div class="network-instructions">
                <div class="network-instructions-title">O que pode ter acontecido?</div>
                <div class="network-step">
                    <span class="network-step-bullet">•</span>
                    <span>Você está completamente sem conexão com a internet.</span>
                </div>
                <div class="network-step">
                    <span class="network-step-bullet">•</span>
                    <span>Você está fora da rede ou VPN autorizada da instituição.</span>
                </div>
                <div class="network-step">
                    <span class="network-step-bullet">•</span>
                    <span>A rede da instituição está temporariamente instável.</span>
                </div>
            </div>

            <button type="button" class="network-btn" id="network-retry-btn">
                <span class="network-btn-spinner" id="network-spinner"></span>
                <span id="network-btn-text">🔄 Tentar Novamente</span>
            </button>
        </div>
    `;

    const overlayEl = document.createElement('div');
    overlayEl.id = 'network-error-overlay';
    overlayEl.innerHTML = overlayHtml;
    document.body.appendChild(overlayEl);

    const retryBtn = document.getElementById('network-retry-btn');
    const spinner = document.getElementById('network-spinner');
    const btnText = document.getElementById('network-btn-text');

    let isChecking = false;

    // Função de verificação de conexão
    async function checkConnection(isManual = false) {
        if (isChecking) return;
        isChecking = true;
        
        if (isManual) {
            spinner.style.display = 'block';
            btnText.innerText = 'Verificando...';
            retryBtn.disabled = true;
        }

        try {
            // Cria um controller de abort para timeout de 3 segundos
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);

            // Tenta obter os cabeçalhos do ping endpoint no servidor
            const response = await fetch('/api/ping.php?t=' + Date.now(), {
                method: 'HEAD',
                cache: 'no-store',
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            // Se obteve sucesso na resposta (2xx), o servidor está acessível e liberado na rede atual
            if (response.ok) {
                const wasActive = overlayEl.classList.contains('active');
                hideOverlay();
                // Apenas recarrega se estava offline anteriormente e foi um clique manual
                if (wasActive && isManual) {
                    location.reload();
                }
            } else {
                showOverlay();
            }
        } catch (error) {
            showOverlay();
        } finally {
            isChecking = false;
            if (isManual) {
                spinner.style.display = 'none';
                btnText.innerText = '🔄 Tentar Novamente';
                retryBtn.disabled = false;
            }
        }
    }

    function showOverlay() {
        overlayEl.classList.add('active');
    }

    function hideOverlay() {
        overlayEl.classList.remove('active');
    }

    // Ouvintes de eventos do navegador
    window.addEventListener('offline', () => {
        showOverlay();
    });

    window.addEventListener('online', () => {
        checkConnection(false);
    });

    retryBtn.addEventListener('click', () => {
        checkConnection(true);
    });

    // Executa a primeira checagem em background
    if (!navigator.onLine) {
        showOverlay();
    } else {
        // Primeira checagem rápida após 1.5s
        setTimeout(() => checkConnection(false), 1500);
    }

    // Configura o batimento cardíaco periódico (a cada 5 segundos)
    setInterval(() => {
        if (navigator.onLine) {
            checkConnection(false);
        } else {
            showOverlay();
        }
    }, 5000);
})();
