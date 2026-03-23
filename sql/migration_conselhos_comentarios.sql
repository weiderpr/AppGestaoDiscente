-- Migração: Criar tabela conselhos_comentarios
-- Data: 2026-03-23

CREATE TABLE IF NOT EXISTS conselhos_comentarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conselho_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    comentario  TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_coment_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_coment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cc_coment_conselho ON conselhos_comentarios(conselho_id);
