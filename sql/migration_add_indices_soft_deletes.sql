-- Migração: Adicionar índices e soft deletes
-- Data: 2026-03-24
-- Descrição: Melhora performance de queries e adiciona suporte a soft delete

SET FOREIGN_KEY_CHECKS = 0;

-- =======================================================
-- 1. ADICIONAR ÍNDICES FALTANTES
-- =======================================================

-- Índice para filtrar turmas ativas por curso (comum em listagens)
ALTER TABLE turmas ADD INDEX idx_turmas_is_active (is_active);

-- Índice para filtrar turmas por ano (relatórios)
ALTER TABLE turmas ADD INDEX idx_turmas_ano (ano);

-- Índice para buscar aluno por email (futuro recuperação de senha)
ALTER TABLE alunos ADD INDEX idx_alunos_email (email);

-- Índice para buscar comentários por professor
ALTER TABLE comentarios_professores ADD INDEX idx_cp_professor (professor_id);

-- Índice para ordenação temporal de comentários
ALTER TABLE comentarios_professores ADD INDEX idx_cp_created (created_at);

-- Índice para filtrar disciplinas ativas
ALTER TABLE disciplinas ADD INDEX idx_d_is_active (is_active);

-- Índice para filtrar etapas ativas
ALTER TABLE etapas ADD INDEX idx_etapas_is_active (is_active);

-- Índice para filtrar cursos ativos por instituição
ALTER TABLE courses ADD INDEX idx_courses_is_active (is_active);

-- =======================================================
-- 2. ADICIONAR SOFT DELETE (deleted_at)
-- =======================================================

-- Tabela: users
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_users_deleted ON users (deleted_at);

-- Tabela: institutions
ALTER TABLE institutions ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_inst_deleted ON institutions (deleted_at);

-- Tabela: courses
ALTER TABLE courses ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_courses_deleted ON courses (deleted_at);

-- Tabela: turmas
ALTER TABLE turmas ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_turmas_deleted ON turmas (deleted_at);

-- Tabela: etapas
ALTER TABLE etapas ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_etapas_deleted ON etapas (deleted_at);

-- Tabela: alunos
ALTER TABLE alunos ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_alunos_deleted ON alunos (deleted_at);

-- Tabela: disciplinas
ALTER TABLE disciplinas ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
CREATE INDEX idx_d_deleted ON disciplinas (deleted_at);

-- Tabela: conselhos_classe
ALTER TABLE conselhos_classe ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_cc_deleted ON conselhos_classe (deleted_at);

-- Tabela: disciplina_categorias
ALTER TABLE disciplina_categorias ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
CREATE INDEX idx_dc_deleted ON disciplina_categorias (deleted_at);

SET FOREIGN_KEY_CHECKS = 1;
