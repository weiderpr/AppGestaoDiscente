-- Migração: Módulo de Avaliações
-- Data: 2026-03-25
-- Descrição: Criação das tabelas para o gerenciamento administrativo de avaliações

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabela: tipos_avaliacao
CREATE TABLE IF NOT EXISTS tipos_avaliacao (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    descricao   TEXT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ta_deleted ON tipos_avaliacao (deleted_at);

-- 2. Tabela: avaliacoes
CREATE TABLE IF NOT EXISTS avaliacoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo_id     INT NOT NULL,
    nome        VARCHAR(255) NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_av_tipo FOREIGN KEY (tipo_id) REFERENCES tipos_avaliacao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_av_tipo    ON avaliacoes (tipo_id);
CREATE INDEX idx_av_deleted ON avaliacoes (deleted_at);

-- 3. Tabela: perguntas
CREATE TABLE IF NOT EXISTS perguntas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id    INT NOT NULL,
    texto_pergunta  TEXT NOT NULL,
    ordem           INT DEFAULT 1,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_p_avaliacao FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para acelerar a busca de perguntas por avaliação (conforme solicitado pelo usuário)
CREATE INDEX idx_p_avaliacao ON perguntas (avaliacao_id);
CREATE INDEX idx_p_deleted   ON perguntas (deleted_at);

SET FOREIGN_KEY_CHECKS = 1;
