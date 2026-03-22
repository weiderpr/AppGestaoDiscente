-- Remove existing foreign keys pointing to disciplinas
ALTER TABLE turma_disciplinas DROP FOREIGN KEY fk_td_disciplina;
ALTER TABLE etapa_notas DROP FOREIGN KEY fk_en_disciplina;

-- Fix the previously added codigo column (from previous attempt)
ALTER TABLE disciplinas DROP COLUMN codigo;

-- Remove AUTO_INCREMENT
ALTER TABLE disciplinas MODIFY id INT UNSIGNED NOT NULL;

-- Drop primary key
ALTER TABLE disciplinas DROP PRIMARY KEY;

-- Rename 'id' to 'codigo' and change type to VARCHAR(15)
ALTER TABLE disciplinas CHANGE id codigo VARCHAR(15) NOT NULL;
ALTER TABLE disciplinas ADD PRIMARY KEY (codigo);

-- Change referencing columns to VARCHAR(15)
ALTER TABLE turma_disciplinas CHANGE disciplina_id disciplina_codigo VARCHAR(15) NOT NULL;
ALTER TABLE etapa_notas CHANGE disciplina_id disciplina_codigo VARCHAR(15) NOT NULL;

-- Re-add foreign keys
ALTER TABLE turma_disciplinas ADD CONSTRAINT fk_td_disciplina FOREIGN KEY (disciplina_codigo) REFERENCES disciplinas(codigo) ON DELETE CASCADE;
ALTER TABLE etapa_notas ADD CONSTRAINT fk_en_disciplina FOREIGN KEY (disciplina_codigo) REFERENCES disciplinas(codigo) ON DELETE CASCADE;
