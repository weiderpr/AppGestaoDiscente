-- ============================================================
-- Vértice Acadêmico — Migração: Análise de Alunos via IA
-- Execute este script uma única vez no banco vertice_academico
-- ============================================================

CREATE TABLE IF NOT EXISTS aluno_ai_analysis (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    aluno_id       INT UNSIGNED    NOT NULL,
    turma_id       INT UNSIGNED    NOT NULL,
    comment_count  INT UNSIGNED    NOT NULL DEFAULT 0  COMMENT 'Snapshot da qtd de comentários usada na última análise',
    grade_etapas   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Snapshot da qtd de etapas com notas usada na última análise',
    analysis       JSON            NOT NULL             COMMENT 'Resultado JSON retornado pela IA',
    provider       VARCHAR(50)     NULL                 COMMENT 'Qual provedor de IA gerou esta análise',
    generated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_aluno_turma (aluno_id, turma_id),
    CONSTRAINT fk_ai_analysis_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ai_analysis_turma FOREIGN KEY (turma_id) REFERENCES turmas(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cache de análises pedagógicas geradas por IA para cada aluno/turma';

-- Permissão: Administrador pode acessar a seção de Componentes em Configurações
-- (execute para cada instituição existente, substituindo <institution_id> pelo ID correto)
-- INSERT INTO profile_permissions (profile, resource, can_access, instituicao_id)
-- VALUES ('Administrador', 'settings.componentes', 1, <institution_id>)
-- ON DUPLICATE KEY UPDATE can_access = 1;
