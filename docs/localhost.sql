-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 23/03/2026 às 13:21
-- Versão do servidor: 8.0.45-0ubuntu0.24.04.1
-- Versão do PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int UNSIGNED NOT NULL,
  `dbase` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `user` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `query` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `col_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `col_type` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `col_length` text COLLATE utf8mb3_bin,
  `col_collation` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) COLLATE utf8mb3_bin DEFAULT '',
  `col_default` text COLLATE utf8mb3_bin
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int UNSIGNED NOT NULL,
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `column_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `transformation_options` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `input_transformation` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) COLLATE utf8mb3_bin NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `settings_data` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `export_type` varchar(10) COLLATE utf8mb3_bin NOT NULL,
  `template_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `template_data` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `tables` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint UNSIGNED NOT NULL,
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `db` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `table` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sqlquery` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `item_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `item_type` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `page_nr` int UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `tables` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Recently accessed tables';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `master_table` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `master_field` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `foreign_db` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `foreign_table` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `foreign_field` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `search_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `search_data` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `pdf_page_number` int NOT NULL DEFAULT '0',
  `x` float UNSIGNED NOT NULL DEFAULT '0',
  `y` float UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  `display_field` varchar(64) COLLATE utf8mb3_bin NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `prefs` text COLLATE utf8mb3_bin NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `version` int UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text COLLATE utf8mb3_bin NOT NULL,
  `schema_sql` text COLLATE utf8mb3_bin,
  `data_sql` longtext COLLATE utf8mb3_bin,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') COLLATE utf8mb3_bin DEFAULT NULL,
  `tracking_active` int UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `config_data` text COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- Despejando dados para a tabela `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('dev', '2026-03-23 13:10:03', '{\"lang\":\"pt_BR\",\"Console\\/Mode\":\"collapse\"}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `tab` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `allowed` enum('Y','N') COLLATE utf8mb3_bin NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) COLLATE utf8mb3_bin NOT NULL,
  `usergroup` varchar(64) COLLATE utf8mb3_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Users and their assignments to user groups';

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Índices de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Índices de tabela `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Índices de tabela `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Índices de tabela `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Índices de tabela `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Índices de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Índices de tabela `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Índices de tabela `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Índices de tabela `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Índices de tabela `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Índices de tabela `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Banco de dados: `vertice_academico`
--
CREATE DATABASE IF NOT EXISTS `vertice_academico` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vertice_academico`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int UNSIGNED NOT NULL,
  `matricula` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `alunos`
--

INSERT INTO `alunos` (`id`, `matricula`, `nome`, `telefone`, `email`, `photo`, `created_at`, `updated_at`) VALUES
(1, '20260022121', 'Fulano de tal', '35988080089', 'fulano@fulano.com', 'assets/uploads/alunos/student_69bc3113a4db28.53276119.png', '2026-03-19 20:23:31', '2026-03-19 20:23:31'),
(2, '94798347598', 'Novo aluno', '', '', 'assets/uploads/alunos/student_69bc340a7e09a7.95183811.png', '2026-03-19 20:36:10', '2026-03-19 20:36:10'),
(3, '20263005073', 'CARLOS VINÍCIUS BERNARDES LIMA', '', '', 'assets/uploads/alunos/student_69bc35e64747a4.99255928.png', '2026-03-19 20:40:43', '2026-03-19 20:44:06'),
(4, '20263005082', 'DAVI JOSÉ WERNECK', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(5, '20253003638', 'EMANUELE VITÓRIA RIBEIRO DE ARAUJO SIMÃO', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(6, '20263007720', 'FÁBIO EDUARDO RODRIGUES BATISTA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(7, '20263004998', 'FELIPE EDUARDO OLIVEIRA GONCALVES', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(8, '20253011774', 'FELIPE GABRIEL MARCIANO SOUZA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(9, '20263007757', 'FLÁVIO HENRIQUE BRAVO CERQUEIRA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(10, '20263005126', 'GABRIEL ANDRADE CUNHA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(11, '20263005037', 'GABRIEL BORGES SILVA FLORES', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(12, '20263005064', 'GUILHERME CESAR FERREIRA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(13, '20263005019', 'ISADORA DE OLIVEIRA GALDINO', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(14, '20263003210', 'ITALO NUNES RIBEIRO', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(15, '20263004951', 'LARISSA INACIO NEVES', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(16, '20263003140', 'MARIA JULIA VALUTA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(17, '20253016162', 'MIGUEL FERREIRA LOURENCONI', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(18, '20253008465', 'PAULO CESAR PRADO RIBEIRO BRAGA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(19, '20253005230', 'PEDRO ANDRADE', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(20, '20243010263', 'PEDRO HENRIQUE DE FREITAS FROGERI', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(21, '20263002877', 'PEDRO HENRIQUE LILÓ', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(22, '20263001898', 'RAFAEL FRANCISCO VALINHAS CAMPOS', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(23, '20263004989', 'THAYLLOR OLIVEIRA FERNANDES', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43'),
(24, '20253008634', 'THIAGO SANTOS E SILVA', NULL, NULL, NULL, '2026-03-19 20:40:43', '2026-03-19 20:40:43');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comentarios_professores`
--

CREATE TABLE `comentarios_professores` (
  `id` int UNSIGNED NOT NULL,
  `professor_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `comentarios_professores`
--

INSERT INTO `comentarios_professores` (`id`, `professor_id`, `aluno_id`, `turma_id`, `conteudo`, `created_at`, `updated_at`) VALUES
(1, 4, 3, 1, '<b>Bom</b> aluno, porém com alguns problemas <i>disciplinares</i>', '2026-03-22 14:08:37', '2026-03-22 14:08:37'),
(2, 4, 3, 1, 'jgjgh', '2026-03-22 14:13:16', '2026-03-22 14:13:16'),
(3, 4, 3, 1, 'Bom aluno, porém com alguns problemas disciplinares', '2026-03-22 14:18:51', '2026-03-22 14:18:51'),
(4, 4, 3, 1, 'Novo comentários', '2026-03-22 14:25:13', '2026-03-22 14:25:13'),
(6, 5, 3, 1, 'Aluno bom <b>indisciplina</b> na minha disciplina', '2026-03-22 14:35:45', '2026-03-22 14:35:45'),
(7, 2, 3, 1, 'Aluno muito bom', '2026-03-22 14:45:02', '2026-03-22 14:45:02'),
(8, 5, 3, 1, 'O aluno sempre se levanta do seu lugar e causa um certo tumulto na sala de aula, apesar se sempre conseguir bons resultados, o aluno tumultua o restante da sala', '2026-03-22 15:18:57', '2026-03-22 15:18:57'),
(9, 2, 4, 1, 'Bom, possui um bom desempenho nas disciplinas exatas, é comportado e assumi uma certa liderança na sala', '2026-03-22 15:35:43', '2026-03-22 15:35:43'),
(10, 2, 4, 1, 'Apresentou um comportamento ríspido na ultima aula, entrou em confronto com um colega de turma', '2026-03-22 15:36:43', '2026-03-22 15:36:43'),
(11, 2, 4, 1, 'Baixo desempenho em disciplinas de linguagens, escreve muito mal', '2026-03-22 15:37:33', '2026-03-22 15:37:33'),
(12, 2, 4, 1, 'notas muito baixas, risco de reprovação', '2026-03-22 15:39:03', '2026-03-22 15:39:03'),
(13, 2, 3, 1, 'Aluno na média.', '2026-03-22 15:52:28', '2026-03-22 15:52:28'),
(14, 2, 3, 1, 'Bom aluno, bom desempenho acadêmico', '2026-03-22 15:52:48', '2026-03-22 15:52:48'),
(15, 2, 3, 1, 'Aluno com comportamento ruim, porém notas boas', '2026-03-22 15:53:07', '2026-03-22 15:53:07'),
(16, 2, 3, 1, 'alunos com notas ruim, porém com comportamento bom', '2026-03-22 15:53:24', '2026-03-22 15:53:24'),
(17, 2, 3, 1, 'Aluno ruim, com notas muito baixas', '2026-03-22 16:00:49', '2026-03-22 16:00:49'),
(18, 2, 3, 1, 'Aluno com comportamento muito ruim', '2026-03-22 16:00:56', '2026-03-22 16:00:56'),
(19, 2, 3, 1, 'não executa as atividades', '2026-03-22 16:01:09', '2026-03-22 16:01:09'),
(20, 2, 3, 1, 'Aluno na média', '2026-03-22 16:08:07', '2026-03-22 16:08:07'),
(21, 2, 3, 1, 'Aluno com desempenho médio', '2026-03-22 16:08:14', '2026-03-22 16:08:14'),
(22, 2, 3, 1, 'aluno com média boa nas disciplinas de linguagens', '2026-03-22 16:08:49', '2026-03-22 16:08:49'),
(23, 2, 3, 1, 'aluno apresentando melhora', '2026-03-22 16:09:02', '2026-03-22 16:09:02'),
(24, 2, 3, 1, 'aluno com rendimento muito bom', '2026-03-22 16:09:17', '2026-03-22 16:09:17'),
(25, 2, 3, 1, 'aluno com bom rendimento', '2026-03-22 16:12:06', '2026-03-22 16:12:06'),
(26, 2, 3, 1, 'aluno com notas boas', '2026-03-22 16:12:11', '2026-03-22 16:12:11'),
(27, 2, 3, 1, 'bom comportamento', '2026-03-22 16:12:21', '2026-03-22 16:12:21'),
(28, 2, 3, 1, 'notas altas', '2026-03-22 16:12:27', '2026-03-22 16:12:27'),
(29, 2, 3, 1, 'bom desempenho acadêmico', '2026-03-22 16:12:48', '2026-03-22 16:12:48'),
(30, 2, 3, 1, 'Aluno com notas ruins<div><br></div>', '2026-03-23 12:20:56', '2026-03-23 12:20:56'),
(31, 2, 3, 1, 'aluno péssimo em comportamento', '2026-03-23 12:21:04', '2026-03-23 12:21:04'),
(32, 2, 3, 1, 'aluno ruim', '2026-03-23 12:23:01', '2026-03-23 12:23:01'),
(33, 2, 3, 1, 'aluno com notas baixas', '2026-03-23 12:23:08', '2026-03-23 12:23:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `courses`
--

CREATE TABLE `courses` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `courses`
--

INSERT INTO `courses` (`id`, `institution_id`, `name`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Técnico em Informática Integrado', 'Coordenação dos cursos técnicos', 1, '2026-03-19 19:43:17', '2026-03-19 19:43:17'),
(2, 1, 'Técnico em Edificações Integrado', 'Sala da coordenação', 1, '2026-03-21 20:22:25', '2026-03-21 20:22:25'),
(3, 1, 'Técnico em Mecatrônica Integrado', 'Sala da Coordenação', 1, '2026-03-21 20:22:51', '2026-03-21 20:22:51'),
(4, 1, 'Técnico em Mecatrônica Subsequente', 'Sala da Coordenação', 1, '2026-03-21 20:23:10', '2026-03-21 20:23:10'),
(5, 1, 'Bacharelado em Sistemas de Informação', NULL, 1, '2026-03-21 20:23:26', '2026-03-21 20:23:26'),
(6, 1, 'Bacharelado em Engenharia Civil', NULL, 1, '2026-03-21 20:23:38', '2026-03-21 20:23:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `course_coordinators`
--

CREATE TABLE `course_coordinators` (
  `course_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `course_coordinators`
--

INSERT INTO `course_coordinators` (`course_id`, `user_id`) VALUES
(1, 3),
(2, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `discentes`
--

CREATE TABLE `discentes` (
  `id` int UNSIGNED NOT NULL,
  `matricula` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `sexo` enum('M','F','Outro') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` text COLLATE utf8mb4_unicode_ci,
  `nome_responsavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone_responsavel` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `disciplinas`
--

CREATE TABLE `disciplinas` (
  `codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `categoria_id` int UNSIGNED NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `disciplinas`
--

INSERT INTO `disciplinas` (`codigo`, `institution_id`, `categoria_id`, `descricao`, `observacoes`, `created_at`) VALUES
('MA.001', 1, 1, 'Matemática', '', '2026-03-21 19:39:11'),
('MA.002', 1, 1, 'Cálculo', '', '2026-03-21 19:39:31'),
('PC1.001', 1, 3, 'Programação de Computadores 1', '', '2026-03-21 19:38:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `disciplina_categorias`
--

CREATE TABLE `disciplina_categorias` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `disciplina_categorias`
--

INSERT INTO `disciplina_categorias` (`id`, `institution_id`, `nome`) VALUES
(1, 1, 'Exatas'),
(2, 1, 'Linguagens'),
(3, 1, 'Técnicas');

-- --------------------------------------------------------

--
-- Estrutura para tabela `etapas`
--

CREATE TABLE `etapas` (
  `id` int UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `media_nota` decimal(5,2) NOT NULL DEFAULT '6.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `etapas`
--

INSERT INTO `etapas` (`id`, `turma_id`, `description`, `nota_maxima`, `media_nota`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '1 Bim', 20.00, 12.00, 1, '2026-03-20 19:45:01', '2026-03-21 18:10:11'),
(2, 1, '2 Bim', 30.00, 18.00, 1, '2026-03-21 17:43:33', '2026-03-21 17:43:33'),
(3, 1, '3 Bim', 20.00, 12.00, 1, '2026-03-21 17:43:44', '2026-03-21 17:43:44'),
(4, 1, '4 Bim', 30.00, 18.00, 1, '2026-03-21 17:43:54', '2026-03-21 17:43:54');

-- --------------------------------------------------------

--
-- Estrutura para tabela `etapa_notas`
--

CREATE TABLE `etapa_notas` (
  `id` int UNSIGNED NOT NULL,
  `etapa_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `disciplina_codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` decimal(5,2) DEFAULT NULL,
  `faltas` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `etapa_notas`
--

INSERT INTO `etapa_notas` (`id`, `etapa_id`, `aluno_id`, `disciplina_codigo`, `nota`, `faltas`, `created_at`, `updated_at`) VALUES
(96, 1, 3, 'PC1.001', 10.00, 3, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(97, 1, 4, 'PC1.001', 5.00, 4, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(98, 1, 5, 'PC1.001', 6.00, 5, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(99, 1, 6, 'PC1.001', 7.00, 4, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(100, 1, 7, 'PC1.001', 5.00, 3, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(101, 1, 8, 'PC1.001', 11.00, 6, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(102, 1, 9, 'PC1.001', 12.00, 7, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(103, 1, 10, 'PC1.001', 14.00, 8, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(104, 1, 11, 'PC1.001', 15.00, 7, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(105, 1, 12, 'PC1.001', 8.00, 6, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(106, 1, 13, 'PC1.001', 7.00, 5, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(107, 1, 14, 'PC1.001', 10.00, 4, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(108, 1, 15, 'PC1.001', 20.00, 3, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(109, 1, 16, 'PC1.001', 12.00, 2, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(110, 1, 17, 'PC1.001', 13.00, 3, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(111, 1, 18, 'PC1.001', 14.00, 4, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(112, 1, 19, 'PC1.001', 11.00, 5, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(113, 1, 20, 'PC1.001', 12.00, 6, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(114, 1, 21, 'PC1.001', 2.00, 8, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(115, 1, 22, 'PC1.001', 20.00, 9, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(116, 1, 23, 'PC1.001', 13.00, 0, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(117, 1, 24, 'PC1.001', 17.00, 4, '2026-03-21 20:09:55', '2026-03-21 20:12:06'),
(140, 1, 3, 'MA.001', 15.00, 3, '2026-03-21 20:12:06', '2026-03-21 20:25:31'),
(141, 1, 4, 'MA.001', 5.00, 4, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(142, 1, 5, 'MA.001', 6.00, 5, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(143, 1, 6, 'MA.001', 7.00, 4, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(144, 1, 7, 'MA.001', 5.00, 3, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(145, 1, 8, 'MA.001', 11.00, 6, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(146, 1, 9, 'MA.001', 12.00, 7, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(147, 1, 10, 'MA.001', 14.00, 8, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(148, 1, 11, 'MA.001', 15.00, 7, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(149, 1, 12, 'MA.001', 8.00, 6, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(150, 1, 13, 'MA.001', 7.00, 5, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(151, 1, 14, 'MA.001', 10.00, 4, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(152, 1, 15, 'MA.001', 20.00, 3, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(153, 1, 16, 'MA.001', 12.00, 2, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(154, 1, 17, 'MA.001', 13.00, 3, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(155, 1, 18, 'MA.001', 14.00, 4, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(156, 1, 19, 'MA.001', 11.00, 5, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(157, 1, 20, 'MA.001', 12.00, 6, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(158, 1, 21, 'MA.001', 2.00, 8, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(159, 1, 22, 'MA.001', 20.00, 9, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(160, 1, 23, 'MA.001', 13.00, 0, '2026-03-21 20:12:06', '2026-03-21 20:12:06'),
(161, 1, 24, 'MA.001', 17.00, 4, '2026-03-21 20:12:06', '2026-03-21 20:12:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `institutions`
--

CREATE TABLE `institutions` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsible` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `institutions`
--

INSERT INTO `institutions` (`id`, `name`, `cnpj`, `photo`, `responsible`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Centro Federal de Educação Tecnológica de Minas Gerais - CEFET-MG', '00.000.000/0000-00', 'assets/uploads/institutions/inst_69bc22e1357a27.48238035.png', 'André Rodrigues Monticeli', 'Av. dos Imigrantes, 1000 - Vargem - Varginha/MG', 1, '2026-03-19 19:22:57', '2026-03-19 19:22:57');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notas`
--

CREATE TABLE `notas` (
  `id` int UNSIGNED NOT NULL,
  `etapa_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `turma_disciplina_id` int UNSIGNED DEFAULT NULL,
  `valor` decimal(5,2) DEFAULT NULL COMMENT 'Nota do aluno na etapa',
  `faltas` tinyint UNSIGNED DEFAULT '0' COMMENT 'Quantidade de faltas na etapa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `notas`
--

INSERT INTO `notas` (`id`, `etapa_id`, `aluno_id`, `turma_disciplina_id`, `valor`, `faltas`, `created_at`, `updated_at`) VALUES
(1, 1, 4, NULL, 12.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(2, 1, 5, NULL, 5.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(3, 1, 6, NULL, 20.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(4, 1, 7, NULL, 13.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(5, 1, 8, NULL, 11.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(6, 1, 9, NULL, 10.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(7, 1, 10, NULL, 20.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(8, 1, 11, NULL, 13.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(9, 1, 12, NULL, 10.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(10, 1, 13, NULL, 14.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(11, 1, 14, NULL, 13.00, 7, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(12, 1, 15, NULL, 12.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(13, 1, 16, NULL, 10.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(14, 1, 17, NULL, 5.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(15, 1, 18, NULL, 12.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(16, 1, 19, NULL, 12.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(17, 1, 20, NULL, 13.00, 1, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(18, 1, 21, NULL, 5.00, 1, '2026-03-20 22:51:44', '2026-03-20 22:51:44'),
(19, 1, 22, NULL, 6.00, 0, '2026-03-20 22:51:44', '2026-03-20 22:51:44');

-- --------------------------------------------------------

--
-- Estrutura para tabela `restore_logs`
--

CREATE TABLE `restore_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `records_count` int DEFAULT NULL,
  `status` enum('success','error') COLLATE utf8mb4_unicode_ci DEFAULT 'error',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `restore_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `restore_logs`
--

INSERT INTO `restore_logs` (`id`, `user_id`, `reason`, `file_name`, `file_size`, `records_count`, `status`, `error_message`, `restore_date`) VALUES
(1, 1, 'Restauração devido a mudança de servidor', 'backup_vertice_academico_2026-03-20_14-54-51.sql', 29300, NULL, 'error', 'SQLSTATE[HY000]: General error: 3780 Referencing column \'disciplina_id\' and referenced column \'id\' in foreign key constraint \'fk_td_disciplina\' are incompatible.', '2026-03-20 20:43:38'),
(2, 1, 'teste', 'backup_vertice_academico_2026-03-20_14-54-51.sql', 29300, 88, 'success', NULL, '2026-03-20 21:42:53'),
(3, 1, 'Teste', 'backup_vertice_academico_2026-03-20_14-54-51.sql', 29300, 88, 'success', NULL, '2026-03-20 21:57:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int UNSIGNED NOT NULL,
  `course_id` int UNSIGNED NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano` int UNSIGNED NOT NULL,
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `media_aprovacao` decimal(5,2) NOT NULL DEFAULT '6.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turmas`
--

INSERT INTO `turmas` (`id`, `course_id`, `description`, `ano`, `nota_maxima`, `media_aprovacao`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Primeiro ano', 2026, 100.00, 60.00, 1, '2026-03-19 19:44:04', '2026-03-19 19:50:30'),
(2, 1, 'Segundo ano', 2026, 100.00, 60.00, 1, '2026-03-19 20:21:24', '2026-03-19 20:21:24'),
(3, 1, 'Terceiro Ano', 2026, 100.00, 60.00, 1, '2026-03-21 20:17:25', '2026-03-21 20:17:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_alunos`
--

CREATE TABLE `turma_alunos` (
  `turma_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turma_alunos`
--

INSERT INTO `turma_alunos` (`turma_id`, `aluno_id`, `created_at`) VALUES
(1, 3, '2026-03-21 19:42:54'),
(1, 4, '2026-03-19 20:57:47'),
(1, 5, '2026-03-19 20:57:47'),
(1, 6, '2026-03-19 20:57:47'),
(1, 7, '2026-03-19 20:57:47'),
(1, 8, '2026-03-19 20:57:47'),
(1, 9, '2026-03-19 20:57:47'),
(1, 10, '2026-03-19 20:57:47'),
(1, 11, '2026-03-19 20:57:47'),
(1, 12, '2026-03-19 20:57:47'),
(1, 13, '2026-03-19 20:57:47'),
(1, 14, '2026-03-19 20:57:47'),
(1, 15, '2026-03-19 20:57:47'),
(1, 16, '2026-03-19 20:57:47'),
(1, 17, '2026-03-19 20:57:47'),
(1, 18, '2026-03-19 20:57:47'),
(1, 19, '2026-03-19 20:57:47'),
(1, 20, '2026-03-19 20:57:47'),
(1, 21, '2026-03-19 20:40:43'),
(1, 22, '2026-03-19 20:40:43'),
(1, 23, '2026-03-21 19:42:54'),
(1, 24, '2026-03-21 19:42:54'),
(2, 1, '2026-03-19 20:33:20'),
(2, 2, '2026-03-19 20:36:10'),
(2, 4, '2026-03-19 20:48:56'),
(2, 5, '2026-03-19 20:48:56'),
(2, 6, '2026-03-19 20:48:56'),
(2, 7, '2026-03-19 20:48:56'),
(2, 8, '2026-03-19 20:48:56'),
(2, 9, '2026-03-19 20:48:56'),
(2, 10, '2026-03-19 20:48:56'),
(2, 11, '2026-03-19 20:48:56'),
(2, 12, '2026-03-19 20:48:56'),
(2, 13, '2026-03-19 20:48:56'),
(2, 14, '2026-03-19 20:48:56'),
(2, 15, '2026-03-19 20:48:56'),
(2, 16, '2026-03-19 20:48:56'),
(2, 17, '2026-03-19 20:48:56'),
(2, 18, '2026-03-19 20:48:56'),
(2, 19, '2026-03-19 20:48:56'),
(2, 20, '2026-03-19 20:48:56'),
(2, 21, '2026-03-19 20:48:56'),
(2, 22, '2026-03-19 20:48:56'),
(2, 24, '2026-03-19 20:48:56');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_discentes`
--

CREATE TABLE `turma_discentes` (
  `id` int UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL,
  `discente_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_disciplinas`
--

CREATE TABLE `turma_disciplinas` (
  `id` int UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL,
  `disciplina_codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turma_disciplinas`
--

INSERT INTO `turma_disciplinas` (`id`, `turma_id`, `disciplina_codigo`, `created_at`) VALUES
(4, 1, 'MA.001', '2026-03-21 19:40:13'),
(5, 1, 'PC1.001', '2026-03-21 19:40:22'),
(6, 1, 'MA.002', '2026-03-22 14:32:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_disciplina_professores`
--

CREATE TABLE `turma_disciplina_professores` (
  `id` int UNSIGNED NOT NULL,
  `turma_disciplina_id` int UNSIGNED NOT NULL,
  `professor_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turma_disciplina_professores`
--

INSERT INTO `turma_disciplina_professores` (`id`, `turma_disciplina_id`, `professor_id`, `created_at`) VALUES
(8, 4, 4, '2026-03-21 19:40:35'),
(9, 6, 5, '2026-03-22 14:32:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_representantes`
--

CREATE TABLE `turma_representantes` (
  `turma_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turma_representantes`
--

INSERT INTO `turma_representantes` (`turma_id`, `aluno_id`, `created_at`) VALUES
(1, 3, '2026-03-21 20:14:10'),
(1, 4, '2026-03-19 20:50:17');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile` enum('Administrador','Coordenador','Diretor','Professor','Pedagogo','Assistente Social','Naapi','Outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Outro',
  `theme` enum('light','dark') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `photo`, `profile`, `theme`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@vertice.edu', '$2y$10$S80vkt7J.N.Tno3cdMEVjO.wItlwCkSxJui3HhrG8iTK.Ytf18me.', NULL, NULL, 'Administrador', 'light', 1, '2026-03-19 18:45:33', '2026-03-19 19:18:06'),
(2, 'Weider Pereira Rodrigues', 'weiderpr@gmail.com', '$2y$10$B91dP8D0y/bkHK5ag9bk0etHKdpMi6muWvKXtGye8Sk0CFadNi1B.', '35988080089', 'assets/uploads/avatars/avatar_69bc231160ffc9.38286637.png', 'Administrador', 'light', 1, '2026-03-19 19:19:36', '2026-03-23 12:15:57'),
(3, 'Douglas Machado Tavares', 'douglas@cefetmg.br', '$2y$10$Yh2.ZXjEydOUSEOHNn3hoebwpBkbRqfe.K7Glc/Pl7dnzrtw4ENC6', '0', 'assets/uploads/avatars/avatar_69bc2d889461d0.62236522.png', 'Coordenador', 'light', 1, '2026-03-19 19:45:50', '2026-03-19 20:08:24'),
(4, 'teste@teste.com', 'teste@teste.com', '$2y$10$jjIsD/aoORvIoCD23QRN7e007yE5n9FJAMX6.QVgvVrOyMEgaXrvG', '3533554455', NULL, 'Professor', 'light', 1, '2026-03-20 20:31:51', '2026-03-20 22:05:49'),
(5, 'Rosicler', 'rosicler@cefetmg.br', '$2y$10$1V6DT/EvZ2amsNFnRdl5oOqWUGs3SX8tyJu7zoagSC8lW1rAeGKtq', '', NULL, 'Professor', 'light', 1, '2026-03-22 14:31:48', '2026-03-22 14:31:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_institutions`
--

CREATE TABLE `user_institutions` (
  `user_id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `user_institutions`
--

INSERT INTO `user_institutions` (`user_id`, `institution_id`, `created_at`) VALUES
(1, 1, '2026-03-19 19:26:27'),
(2, 1, '2026-03-19 19:27:10'),
(3, 1, '2026-03-19 20:08:24'),
(4, 1, '2026-03-20 20:31:51'),
(5, 1, '2026-03-22 14:31:48');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`);

--
-- Índices de tabela `comentarios_professores`
--
ALTER TABLE `comentarios_professores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cp_professor` (`professor_id`),
  ADD KEY `fk_cp_aluno` (`aluno_id`),
  ADD KEY `fk_cp_turma` (`turma_id`);

--
-- Índices de tabela `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_courses_institution` (`institution_id`),
  ADD KEY `idx_courses_name` (`name`);

--
-- Índices de tabela `course_coordinators`
--
ALTER TABLE `course_coordinators`
  ADD PRIMARY KEY (`course_id`,`user_id`),
  ADD KEY `idx_cc_user` (`user_id`);

--
-- Índices de tabela `discentes`
--
ALTER TABLE `discentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `idx_discentes_matricula` (`matricula`),
  ADD KEY `idx_discentes_nome` (`nome`);

--
-- Índices de tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `idx_d_institution` (`institution_id`),
  ADD KEY `idx_d_categoria` (`categoria_id`);

--
-- Índices de tabela `disciplina_categorias`
--
ALTER TABLE `disciplina_categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dc_institution` (`institution_id`);

--
-- Índices de tabela `etapas`
--
ALTER TABLE `etapas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_etapas_turma` (`turma_id`);

--
-- Índices de tabela `etapa_notas`
--
ALTER TABLE `etapa_notas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_etapa_aluno_disc` (`etapa_id`,`aluno_id`,`disciplina_codigo`),
  ADD KEY `idx_en_etapa` (`etapa_id`),
  ADD KEY `idx_en_aluno` (`aluno_id`),
  ADD KEY `idx_en_disciplina` (`disciplina_codigo`);

--
-- Índices de tabela `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `idx_institutions_name` (`name`);

--
-- Índices de tabela `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nota` (`etapa_id`,`aluno_id`,`turma_disciplina_id`),
  ADD KEY `fk_notas_aluno` (`aluno_id`),
  ADD KEY `fk_notas_td` (`turma_disciplina_id`);

--
-- Índices de tabela `restore_logs`
--
ALTER TABLE `restore_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_turmas_course` (`course_id`);

--
-- Índices de tabela `turma_alunos`
--
ALTER TABLE `turma_alunos`
  ADD PRIMARY KEY (`turma_id`,`aluno_id`),
  ADD KEY `fk_ta_aluno` (`aluno_id`);

--
-- Índices de tabela `turma_discentes`
--
ALTER TABLE `turma_discentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_turma_discente` (`turma_id`,`discente_id`),
  ADD KEY `idx_td2_discente` (`discente_id`);

--
-- Índices de tabela `turma_disciplinas`
--
ALTER TABLE `turma_disciplinas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_turma_disciplina` (`turma_id`,`disciplina_codigo`),
  ADD KEY `fk_td_disciplina` (`disciplina_codigo`);

--
-- Índices de tabela `turma_disciplina_professores`
--
ALTER TABLE `turma_disciplina_professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_td_professor` (`turma_disciplina_id`,`professor_id`),
  ADD KEY `fk_tdp_professor` (`professor_id`);

--
-- Índices de tabela `turma_representantes`
--
ALTER TABLE `turma_representantes`
  ADD PRIMARY KEY (`turma_id`,`aluno_id`),
  ADD KEY `fk_tr_aluno` (`aluno_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_profile` (`profile`);

--
-- Índices de tabela `user_institutions`
--
ALTER TABLE `user_institutions`
  ADD PRIMARY KEY (`user_id`,`institution_id`),
  ADD KEY `idx_ui_institution` (`institution_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de tabela `comentarios_professores`
--
ALTER TABLE `comentarios_professores`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `discentes`
--
ALTER TABLE `discentes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `disciplina_categorias`
--
ALTER TABLE `disciplina_categorias`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `etapas`
--
ALTER TABLE `etapas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `etapa_notas`
--
ALTER TABLE `etapa_notas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT de tabela `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `restore_logs`
--
ALTER TABLE `restore_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `turma_discentes`
--
ALTER TABLE `turma_discentes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turma_disciplinas`
--
ALTER TABLE `turma_disciplinas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `turma_disciplina_professores`
--
ALTER TABLE `turma_disciplina_professores`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `comentarios_professores`
--
ALTER TABLE `comentarios_professores`
  ADD CONSTRAINT `fk_cp_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_professor` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `course_coordinators`
--
ALTER TABLE `course_coordinators`
  ADD CONSTRAINT `fk_cc_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `disciplinas`
--
ALTER TABLE `disciplinas`
  ADD CONSTRAINT `fk_d_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `disciplina_categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_d_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `disciplina_categorias`
--
ALTER TABLE `disciplina_categorias`
  ADD CONSTRAINT `fk_dc_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `etapas`
--
ALTER TABLE `etapas`
  ADD CONSTRAINT `fk_etapas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `etapa_notas`
--
ALTER TABLE `etapa_notas`
  ADD CONSTRAINT `fk_en_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_en_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_en_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `fk_notas_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notas_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notas_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplinas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `fk_turmas_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_alunos`
--
ALTER TABLE `turma_alunos`
  ADD CONSTRAINT `fk_ta_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ta_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_discentes`
--
ALTER TABLE `turma_discentes`
  ADD CONSTRAINT `fk_td2_discente` FOREIGN KEY (`discente_id`) REFERENCES `discentes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_td2_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_disciplinas`
--
ALTER TABLE `turma_disciplinas`
  ADD CONSTRAINT `fk_td_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_td_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_disciplina_professores`
--
ALTER TABLE `turma_disciplina_professores`
  ADD CONSTRAINT `fk_tdp_professor` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tdp_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_representantes`
--
ALTER TABLE `turma_representantes`
  ADD CONSTRAINT `fk_tr_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tr_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `user_institutions`
--
ALTER TABLE `user_institutions`
  ADD CONSTRAINT `fk_ui_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ui_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
