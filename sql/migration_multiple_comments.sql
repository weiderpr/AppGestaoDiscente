-- Migração para permitir múltiplos comentários de um professor para um aluno na mesma turma
-- Remove a restrição UNIQUE KEY uk_professor_aluno_turma

ALTER TABLE comentarios_professores DROP INDEX uk_professor_aluno_turma;
