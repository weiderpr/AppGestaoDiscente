-- Migration: Adiciona campo is_teacher na tabela users
-- Permite marcar usuários de outros perfis como também sendo professores
-- Data: 2026-03-27

ALTER TABLE users ADD COLUMN is_teacher TINYINT(1) NOT NULL DEFAULT 0 AFTER profile;

CREATE INDEX idx_users_is_teacher ON users (is_teacher);
