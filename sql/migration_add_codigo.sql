-- Adiciona o campo 'codigo' na tabela 'disciplinas'
ALTER TABLE disciplinas
ADD COLUMN codigo VARCHAR(15) DEFAULT NULL AFTER categoria_id;

-- Adiciona um índice para o novo campo para otimizar buscas
CREATE INDEX idx_d_codigo ON disciplinas (codigo);
