-- Migração: Criar tabela conselhos_classe
-- Data: 2026-03-23

CREATE TABLE IF NOT EXISTS conselhos_classe (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    course_id       INT UNSIGNED NOT NULL,
    turma_id        INT UNSIGNED NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    data_hora       DATETIME NOT NULL,
    local_reuniao   VARCHAR(255) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_inst FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cc_inst ON conselhos_classe(institution_id);
CREATE INDEX idx_cc_course ON conselhos_classe(course_id);
CREATE INDEX idx_cc_turma ON conselhos_classe(turma_id);
CREATE INDEX idx_cc_data ON conselhos_classe(data_hora);
