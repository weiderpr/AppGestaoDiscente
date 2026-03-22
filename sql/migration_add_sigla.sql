-- Adiciona a coluna 'sigla' à tabela 'disciplinas'
ALTER TABLE disciplinas ADD COLUMN sigla VARCHAR(20) AFTER descricao;
