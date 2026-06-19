-- Vértice Acadêmico — Migração v3: Versão de prompt no cache de análise IA
-- Adiciona a coluna prompt_version para invalidar automaticamente o cache
-- quando o prompt da IA é atualizado (ex: correção de desambiguação de nome).
--
-- Execute: mysql -u USER -p DBNAME < sql/migrations/add_ai_analysis_v3.sql

ALTER TABLE aluno_ai_analysis
    ADD COLUMN prompt_version TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Versão do prompt usado na geração. Incrementar invalida todos os caches.'
        AFTER atendimento_count;
