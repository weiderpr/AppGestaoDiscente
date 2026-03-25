</main>
<!-- /Conteúdo da página -->

<!-- ======== FOOTER ======== -->
<footer class="app-footer" role="contentinfo">
    <p>© <?= date('Y') ?> <strong>Vértice Acadêmico</strong> — Sistema de Gestão de Indicadores Discentes</p>
</footer>
<!-- ======== /FOOTER ======== -->

</div><!-- /app-wrapper -->

<!-- Scripts (extraJS definido pela página) -->
<?php if (isset($extraJS)): foreach ($extraJS as $js): ?>
<script src="<?= $js ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
