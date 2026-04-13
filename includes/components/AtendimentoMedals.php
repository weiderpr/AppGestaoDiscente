<?php
/**
 * Vértice Acadêmico — Componente de Medalhas de Atendimento
 */

/**
 * Renderiza um conjunto de medalhas para os profissionais em atendimento com o aluno.
 * Agrupa profissionais da mesma área em uma única medalha.
 * 
 * @param int    $alunoId      ID do aluno.
 * @param array  $atendimentos Lista de atendimentos com profissionais.
 * @param string $position     Posição do popover ('top' ou 'bottom').
 * @return string HTML das medalhas.
 */
function renderAtendimentoMedals(int $alunoId, array $atendimentos, string $position = 'top'): string {
    if (empty($atendimentos)) {
        return '';
    }

    // Agrupamento por tipo de profissional
    $grouped = [];
    foreach ($atendimentos as $at) {
        $profile = $at['profile'] ?? 'Profissional';
        $pLower = mb_strtolower($profile);
        
        $type = 'Outros';
        $icon = '👤';
        $class = 'medal-default';

        if (str_contains($pLower, 'psicó')) {
            $type = 'Psicólogo';
            $icon = '🧠';
            $class = 'medal-psicologo';
        } elseif (str_contains($pLower, 'pedago')) {
            $type = 'Pedagogo';
            $icon = '🎓';
            $class = 'medal-pedagogo';
        } elseif (str_contains($pLower, 'assistente') || str_contains($pLower, 'social')) {
            $type = 'Assistente Social';
            $icon = '🤝';
            $class = 'medal-assistente-social';
        } elseif (str_contains($pLower, 'coord')) {
            $type = 'Coordenador';
            $icon = '⚖️';
            $class = 'medal-coordenador';
        } else {
            $type = $profile; // Mantém o nome original se não for um dos padrões
        }

        $grouped[$type]['atendimentos'][] = $at;
        $grouped[$type]['icon'] = $icon;
        $grouped[$type]['class'] = $class;
    }

    $posClass = ($position === 'bottom') ? ' popover-bottom' : '';
    $html = '<div class="atendimento-medals-wrapper" id="medals-' . $alunoId . '">';

    foreach ($grouped as $type => $data) {
        $html .= '<div class="atendimento-medal ' . $data['class'] . $posClass . '">';
        $html .= '<span>' . $data['icon'] . '</span>';
        
        // Popover
        $html .= '<div class="medal-popover">';
        $html .= '<div class="popover-header">' . htmlspecialchars($type) . '</div>';
        $html .= '<div class="popover-profs">';
        
        $seenProfs = [];
        foreach ($data['atendimentos'] as $at) {
            $name = htmlspecialchars($at['name'] ?? 'Desconhecido');
            
            // Evita duplicidade do mesmo profissional no mesmo tooltip
            if (in_array($name, $seenProfs)) continue;
            $seenProfs[] = $name;

            $photo = $at['photo'] ?? null;
            $photoUrl = ($photo && file_exists(__DIR__ . '/../../' . $photo)) ? '/' . $photo : null;
            
            $initials = '';
            if (!$photoUrl) {
                $parts = explode(' ', $at['name'] ?? 'P');
                $initials = strtoupper(substr($parts[0], 0, 1));
                if (count($parts) > 1) {
                    $initials .= strtoupper(substr($parts[count($parts)-1], 0, 1));
                } else {
                    $initials = strtoupper(substr($parts[0], 0, 2));
                }
            }

            $html .= '<div class="popover-prof-item">';
            if ($photoUrl) {
                $html .= '<img src="' . $photoUrl . '" class="popover-photo" alt="' . $name . '">';
            } else {
                $html .= '<div class="popover-photo" style="display:flex; align-items:center; justify-content:center; background:var(--bg-surface-2nd); font-size:0.65rem; font-weight:700; color:var(--text-muted);">' . $initials . '</div>';
            }
            $html .= '<span class="popover-name">' . $name . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // .popover-profs
        $html .= '</div>'; // .medal-popover
        $html .= '</div>'; // .atendimento-medal
    }

    $html .= '</div>';
    return $html;
}
