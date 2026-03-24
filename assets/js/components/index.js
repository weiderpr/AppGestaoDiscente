/**
 * Vértice Acadêmico — UI Components Bundle
 * Carrega todos os componentes de UI
 */

// Carregar CSS dinamicamente
(function() {
    const components = ['toast', 'loading', 'modal'];
    const basePath = '/assets/css/components/';
    
    components.forEach(component => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = basePath + component + '.css';
        document.head.appendChild(link);
    });
})();

// Carregar componentes JS
// Toast já está disponível globalmente
// Loading já está disponível globalmente  
// Modal já está disponível globalmente

// Export para módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Toast, Spinner, Skeleton, Modal };
}
