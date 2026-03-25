<?php
/**
 * Vértice Acadêmico — Partial: Dashboard de Avaliações
 */
?>
<div class="card settings-card">
    <div class="settings-card-header">
        <div class="settings-card-icon">📊</div>
        <div>
            <div class="settings-card-title">Módulo de Avaliações</div>
            <div class="settings-card-desc">Gerencie os tipos de avaliação, questionários e perguntas do sistema.</div>
        </div>
    </div>
    <div class="card-body">
        <p style="color:var(--text-secondary);margin:0 0 2rem;font-size: .9375rem;line-height:1.6;">
            O módulo de avaliações permite criar questionários personalizados para coletar feedbacks, realizar avaliações de desempenho 
            ou pesquisas acadêmicas. Comece definindo os <strong>Tipos</strong> e depois crie suas <strong>Avaliações</strong>.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">
            <!-- Card: Tipos -->
            <div style="background:var(--bg-surface-2nd);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:1.5rem;display:flex;flex-direction:column;gap:1rem;transition:all .2s;position:relative;">
                <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--color-primary-light);color:var(--color-primary);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📂</div>
                <div>
                    <div style="font-weight:700;color:var(--text-primary);margin-bottom:.25rem;">Tipos de Avaliação</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);line-height:1.5;">Defina categorias como "Formativa", "Satisfação" ou "Autoavaliação".</div>
                </div>
                <a href="?section=avaliacoes&sub=tipos" class="btn btn-ghost btn-sm" style="margin-top:auto;width:fit-content;">Gerenciar Tipos</a>
                <a href="?section=avaliacoes&sub=tipos" style="position:absolute;inset:0;z-index:1;text-indent:-9999px;">Acessar</a>
            </div>

            <!-- Card: Lista -->
            <div style="background:var(--bg-surface-2nd);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:1.5rem;display:flex;flex-direction:column;gap:1rem;transition:all .2s;position:relative;">
                <div style="width:48px;height:48px;border-radius:var(--radius-md);background:rgba(16,185,129,.1);color:var(--color-success);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📋</div>
                <div>
                    <div style="font-weight:700;color:var(--text-primary);margin-bottom:.25rem;">Gerenciar Avaliações</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);line-height:1.5;">Visualize, edite ou remova as avaliações e questionários já criados.</div>
                </div>
                <a href="?section=avaliacoes&sub=lista" class="btn btn-ghost btn-sm" style="margin-top:auto;width:fit-content;">Ver Avaliações</a>
                <a href="?section=avaliacoes&sub=lista" style="position:absolute;inset:0;z-index:1;text-indent:-9999px;">Acessar</a>
            </div>
        </div>
    </div>
</div>
