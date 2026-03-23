-- Migração: Criar tabela conselhos_etapas (relação N:N)
-- Data: 2026-03-23

CREATE TABLE IF NOT EXISTS conselhos_etapas (
    conselho_id INT UNSIGNED NOT NULL,
    etapa_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (conselho_id, etapa_id),
    CONSTRAINT fk_ce_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_ce_etapa FOREIGN KEY (etapa_id) REFERENCES etapas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
