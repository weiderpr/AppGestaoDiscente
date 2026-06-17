-- Vértice Acadêmico — Migração v2: Análise IA com novas fontes de dados
-- Adiciona contadores para conselho_registros, encaminhamentos e atendimentos
-- ao cache de análise pedagógica.
--
-- Execute: mysql -u USER -p DBNAME < sql/migrations/add_ai_analysis_v2.sql

ALTER TABLE aluno_ai_analysis
    ADD COLUMN registro_count       INT NOT NULL DEFAULT 0
        COMMENT 'Anotações do conselho de classe (post-its)'       AFTER grade_etapas,
    ADD COLUMN encaminhamento_count INT NOT NULL DEFAULT 0
        COMMENT 'Encaminhamentos do conselho de classe'            AFTER registro_count,
    ADD COLUMN atendimento_count    INT NOT NULL DEFAULT 0
        COMMENT 'Atendimentos profissionais (descrição pública)'   AFTER encaminhamento_count;
