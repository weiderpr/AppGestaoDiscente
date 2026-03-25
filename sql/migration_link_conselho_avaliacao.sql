-- Migração Final e Vínculo: Conselhos de Classe e Avaliações
-- Data: 2026-03-25

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Remover FKs conhecidas
ALTER TABLE avaliacoes DROP FOREIGN KEY fk_av_tipo;
ALTER TABLE perguntas DROP FOREIGN KEY fk_p_avaliacao;

-- 2. Padronizar todas as colunas para INT UNSIGNED
ALTER TABLE tipos_avaliacao MODIFY id INT UNSIGNED AUTO_INCREMENT;

ALTER TABLE avaliacoes MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE avaliacoes MODIFY tipo_id INT UNSIGNED NOT NULL;

ALTER TABLE perguntas MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE perguntas MODIFY avaliacao_id INT UNSIGNED NOT NULL;

ALTER TABLE conselhos_classe MODIFY avaliacao_id INT UNSIGNED NULL;

-- 3. Restaurar as FKs com os tipos corretos
ALTER TABLE avaliacoes ADD CONSTRAINT fk_av_tipo FOREIGN KEY (tipo_id) REFERENCES tipos_avaliacao(id) ON DELETE CASCADE;
ALTER TABLE perguntas ADD CONSTRAINT fk_p_avaliacao FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE;

-- Tenta remover a FK do conselho se existir antes de adicionar para garantir idempotência
-- No MySQL sem IF EXISTS, se não houver a FK, o comando abaixo falhará. 
-- Mas como estamos em um script, se falhar aqui, o resto para.
-- Então vamos apenas tentar adicionar. Se já houver, o erro 1061/1050 ocorrerá.
-- Na verdade, a coluna avaliacao_id é nova, então a FK não deve existir a menos que o script rodou parcialmente.

ALTER TABLE conselhos_classe ADD CONSTRAINT fk_cc_avaliacao FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
