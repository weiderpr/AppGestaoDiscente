-- Migração: Adicionar coluna course_id à tabela conselhos_classe
-- Data: 2026-03-23

ALTER TABLE conselhos_classe 
ADD COLUMN course_id INT UNSIGNED NOT NULL AFTER institution_id,
ADD CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

CREATE INDEX idx_cc_course ON conselhos_classe(course_id);
