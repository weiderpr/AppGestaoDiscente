-- Migração: Respostas de Pesquisa (Survey)
-- Data: 2026-03-25

SET FOREIGN_KEY_CHECKS = 0;

-- Tabela: respostas_avaliacao
CREATE TABLE IF NOT EXISTS respostas_avaliacao (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id    INT UNSIGNED NOT NULL,
    conselho_id     INT UNSIGNED NOT NULL,
    comentario      TEXT,
    dispositivo     VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ra_avaliacao FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ra_conselho  FOREIGN KEY (conselho_id)  REFERENCES conselhos_classe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: respostas_perguntas
CREATE TABLE IF NOT EXISTS respostas_perguntas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resposta_id     INT UNSIGNED NOT NULL,
    pergunta_id     INT UNSIGNED NOT NULL,
    nota            TINYINT(1) NOT NULL, -- 0-5
    CONSTRAINT fk_rp_resposta FOREIGN KEY (resposta_id) REFERENCES respostas_avaliacao(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
