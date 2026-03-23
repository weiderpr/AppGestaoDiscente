-- Migração: Criar tabela conselhos_presentes
-- Data: 2026-03-23

CREATE TABLE IF NOT EXISTS conselhos_presentes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conselho_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conselho_user (conselho_id, user_id),
    CONSTRAINT fk_cp_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cp_conselho ON conselhos_presentes(conselho_id);
CREATE INDEX idx_cp_user ON conselhos_presentes(user_id);
