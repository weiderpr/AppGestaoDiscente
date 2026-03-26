<?php
/**
 * Vértice Acadêmico — Mobile Footer
 */
?>

<!-- Scripts standardizados para o mobile -->
<script src="/assets/js/components/Toast.js"></script>
<script src="/assets/js/components/Modal.js"></script>
<script src="/assets/js/components/Loading.js"></script>

<script>
    // Registro global de vibração para feedback tátil (opcional em dispositivos suportados)
    function hapticFeedback() {
        if ('vibrate' in navigator) navigator.vibrate(10);
    }
    document.querySelectorAll('.m-btn, .drawer-link, .m-card-btn').forEach(el => {
        el.addEventListener('click', hapticFeedback);
    });
</script>

</body>
</html>
