<?php
/**
 * Vértice Acadêmico — Middleware Interface
 */

namespace Core;

interface Middleware {
    /**
     * @param array $params Parâmetros da rota
     * @param callable $next O próximo passo na cadeia (ou o handler final)
     */
    public function handle(array $params, callable $next): void;
}
