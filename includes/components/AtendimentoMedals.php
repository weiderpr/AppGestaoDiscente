<?php
/**
 * Vértice Acadêmico — Componente de Medalhas de Atendimento
 */

/**
 * Renderiza um conjunto de medalhas para os profissionais em atendimento com o aluno.
 * 
 * @param int   $alunoId      ID do aluno.
 * @param array $atendimentos Lista de atendimentos com profissionais (cada item deve ter name, profile, photo).
 * @return string HTML das medalhas.
 */
function renderAtendimentoMedals(int $alunoId, array $atendimentos): string {
    if (empty($atendimentos)) {
        return '';
    }

    $html = '<div class="atendimento-medals-wrapper" id="medals-' . $alunoId . '">';

        foreach ($atendimentos as $at) {
            $profile = $at['profile'] ?? 'Profissional';
            $name    = htmlspecialchars($at['name'] ?? 'Desconhecido');
            $photo   = $at['photo'] ?? null;
            $photoUrl = ($photo && file_exists(__DIR__ . '/../../' . $photo)) ? '/' . $photo : null;
            
            // Iniciais para caso não tenha foto
            $initials = '';
            if (!$photoUrl) {
                $parts = explode(' ', $at['name'] ?? 'P');
                $initials = strtoupper(substr($parts[0], 0, 1));
                if (count($parts) > 1) $initials .= strtoupper(substr($parts[count($parts)-1], 0, 1));
                else $initials = strtoupper(substr($parts[0], 0, 2));
            }

            // Mapeamento de Ícone e Classe por Perfil
            $icon = '👤';
            $class = 'medal-default';
            
            $pLower = mb_strtolower($profile);
            if (str_contains($pLower, 'psicó')) {
                $icon = '🧠';
                $class = 'medal-psicologo';
            } elseif (str_contains($pLower, 'pedago')) {
                $icon = '🎓';
                $class = 'medal-pedagogo';
            } elseif (str_contains($pLower, 'assistente') || str_contains($pLower, 'social')) {
                $icon = '🤝';
                $class = 'medal-assistente-social';
            } elseif (str_contains($pLower, 'coord')) {
                $icon = '⚖️';
                $class = 'medal-coordenador';
            }

            $html .= '
            <div class="atendimento-medal ' . $class . '">
                <span>' . $icon . '</span>
                <div class="medal-popover">
                    ' . ($photoUrl 
                        ? '<img src="' . $photoUrl . '" class="popover-photo" alt="' . $name . '">' 
                        : '<div class="popover-photo" style="display:flex; align-items:center; justify-content:center; background:var(--bg-surface-2nd); font-size:0.75rem; font-weight:700; color:var(--text-muted);">' . $initials . '</div>'
                    ) . '
                    <div class="popover-info">
                        <span class="popover-profile">' . htmlspecialchars($profile) . '</span>
                        <span class="popover-name">' . $name . '</span>
                    </div>
                </div>
            </div>';
        }

    $html .= '</div>';

    return $html;
}
