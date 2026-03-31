-- =======================================================
-- Vértice Acadêmico — Schema Completo do Banco de Dados
-- Gerado automaticamente e sincronizado com o ambiente live
-- Data: 2026-03-31 10:01:08
-- =======================================================

CREATE DATABASE IF NOT EXISTS vertice_academico
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vertice_academico;

-- =======================================================
-- Teardown (Clean Slate)
-- =======================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `respostas_perguntas`;
DROP TABLE IF EXISTS `respostas_avaliacao`;
DROP TABLE IF EXISTS `perguntas`;
DROP TABLE IF EXISTS `gestao_atendimento_comentarios`;
DROP TABLE IF EXISTS `gestao_atendimento_usuarios`;
DROP TABLE IF EXISTS `gestao_atendimentos`;
DROP TABLE IF EXISTS `atendimentos`;
DROP TABLE IF EXISTS `conselho_encaminhamento_usuarios`;
DROP TABLE IF EXISTS `conselho_encaminhamentos`;
DROP TABLE IF EXISTS `conselho_registros`;
DROP TABLE IF EXISTS `conselhos_presentes`;
DROP TABLE IF EXISTS `conselhos_comentarios`;
DROP TABLE IF EXISTS `conselhos_etapas`;
DROP TABLE IF EXISTS `conselhos_classe`;
DROP TABLE IF EXISTS `avaliacoes`;
DROP TABLE IF EXISTS `tipos_avaliacao`;
DROP TABLE IF EXISTS `etapa_notas`;
DROP TABLE IF EXISTS `notas`;
DROP TABLE IF EXISTS `turma_disciplina_professores`;
DROP TABLE IF EXISTS `turma_disciplinas`;
DROP TABLE IF EXISTS `disciplinas`;
DROP TABLE IF EXISTS `disciplina_categorias`;
DROP TABLE IF EXISTS `turma_alunos`;
DROP TABLE IF EXISTS `turma_representantes`;
DROP TABLE IF EXISTS `turma_discentes`;
DROP TABLE IF EXISTS `discentes`;
DROP TABLE IF EXISTS `alunos`;
DROP TABLE IF EXISTS `etapas`;
DROP TABLE IF EXISTS `turmas`;
DROP TABLE IF EXISTS `course_coordinators`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `profile_permissions`;
DROP TABLE IF EXISTS `restore_logs`;
DROP TABLE IF EXISTS `user_institutions`;
DROP TABLE IF EXISTS `institutions`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 0;

-- Table: alunos
CREATE TABLE `alunos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `matricula` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula` (`matricula`)
) ENGINE=InnoDB AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: gestao_atendimentos
CREATE TABLE `gestao_atendimentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `author_id` int unsigned NOT NULL,
  `aluno_id` int unsigned DEFAULT NULL,
  `turma_id` int unsigned DEFAULT NULL,
  `encaminhamento_id` int unsigned DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao_profissional` text COLLATE utf8mb4_unicode_ci,
  `descricao_publica` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aberto','Em Atendimento','Finalizado') COLLATE utf8mb4_unicode_ci DEFAULT 'Aberto',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ga_inst` (`institution_id`),
  KEY `idx_ga_author` (`author_id`),
  KEY `idx_ga_aluno` (`aluno_id`),
  KEY `idx_ga_turma` (`turma_id`),
  KEY `idx_ga_encaminhamento` (`encaminhamento_id`),
  CONSTRAINT `fk_ga_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ga_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_encaminhamento` FOREIGN KEY (`encaminhamento_id`) REFERENCES `conselho_encaminhamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ga_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: gestao_atendimento_usuarios
CREATE TABLE `gestao_atendimento_usuarios` (
  `atendimento_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  PRIMARY KEY (`atendimento_id`,`usuario_id`),
  KEY `idx_gau_user` (`usuario_id`),
  CONSTRAINT `fk_gau_atend` FOREIGN KEY (`atendimento_id`) REFERENCES `gestao_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gau_user` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: gestao_atendimento_comentarios
CREATE TABLE `gestao_atendimento_comentarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `atendimento_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gac_atendimento` (`atendimento_id`),
  KEY `idx_gac_user` (`usuario_id`),
  CONSTRAINT `fk_gac_atendimento` FOREIGN KEY (`atendimento_id`) REFERENCES `gestao_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gac_user` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: avaliacoes
CREATE TABLE `avaliacoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo_id` int unsigned NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_av_tipo` (`tipo_id`),
  KEY `idx_av_deleted` (`deleted_at`),
  CONSTRAINT `fk_av_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `tipos_avaliacao` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: comentarios_professores
CREATE TABLE `comentarios_professores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cp_professor` (`professor_id`),
  KEY `fk_cp_aluno` (`aluno_id`),
  KEY `fk_cp_turma` (`turma_id`),
  CONSTRAINT `fk_cp_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_professor` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselho_encaminhamento_usuarios
CREATE TABLE `conselho_encaminhamento_usuarios` (
  `encaminhamento_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  PRIMARY KEY (`encaminhamento_id`,`user_id`),
  KEY `fk_enc_u_user` (`user_id`),
  CONSTRAINT `fk_enc_u_enc` FOREIGN KEY (`encaminhamento_id`) REFERENCES `conselho_encaminhamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_u_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselho_encaminhamentos
CREATE TABLE `conselho_encaminhamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conselho_id` int unsigned NOT NULL,
  `aluno_id` int unsigned DEFAULT NULL,
  `author_id` int unsigned NOT NULL,
  `setor_tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_expectativa` date DEFAULT NULL,
  `status` enum('Pendente','Em Andamento','Concluído') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_enc_conselho` (`conselho_id`),
  KEY `fk_enc_aluno` (`aluno_id`),
  KEY `fk_enc_author` (`author_id`),
  CONSTRAINT `fk_enc_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enc_conselho` FOREIGN KEY (`conselho_id`) REFERENCES `conselhos_classe` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselho_registros
CREATE TABLE `conselho_registros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conselho_id` int NOT NULL,
  `aluno_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `texto` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: conselhos_classe
CREATE TABLE `conselhos_classe` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_hora` datetime NOT NULL,
  `local_reuniao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avaliacao_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cc_inst` (`institution_id`),
  KEY `idx_cc_data` (`data_hora`),
  KEY `fk_cc_turma` (`turma_id`),
  KEY `idx_cc_course` (`course_id`),
  KEY `idx_cc_avaliacao` (`avaliacao_id`),
  CONSTRAINT `fk_cc_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `avaliacoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cc_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselhos_comentarios
CREATE TABLE `conselhos_comentarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conselho_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cc_coment_user` (`user_id`),
  KEY `idx_cc_coment_conselho` (`conselho_id`),
  CONSTRAINT `fk_cc_coment_conselho` FOREIGN KEY (`conselho_id`) REFERENCES `conselhos_classe` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_coment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselhos_etapas
CREATE TABLE `conselhos_etapas` (
  `conselho_id` int unsigned NOT NULL,
  `etapa_id` int unsigned NOT NULL,
  PRIMARY KEY (`conselho_id`,`etapa_id`),
  KEY `fk_ce_etapa` (`etapa_id`),
  CONSTRAINT `fk_ce_conselho` FOREIGN KEY (`conselho_id`) REFERENCES `conselhos_classe` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: conselhos_presentes
CREATE TABLE `conselhos_presentes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conselho_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conselho_user` (`conselho_id`,`user_id`),
  KEY `idx_cp_conselho` (`conselho_id`),
  KEY `idx_cp_user` (`user_id`),
  CONSTRAINT `fk_cp_conselho` FOREIGN KEY (`conselho_id`) REFERENCES `conselhos_classe` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: course_coordinators
CREATE TABLE `course_coordinators` (
  `course_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  PRIMARY KEY (`course_id`,`user_id`),
  KEY `idx_cc_user` (`user_id`),
  CONSTRAINT `fk_cc_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: courses
CREATE TABLE `courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_courses_institution` (`institution_id`),
  KEY `idx_courses_name` (`name`),
  CONSTRAINT `fk_courses_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: discentes
CREATE TABLE `discentes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `matricula` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `sexo` enum('M','F','Outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nome_responsavel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone_responsavel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula` (`matricula`),
  KEY `idx_discentes_matricula` (`matricula`),
  KEY `idx_discentes_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: disciplina_categorias
CREATE TABLE `disciplina_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dc_institution` (`institution_id`),
  CONSTRAINT `fk_dc_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: disciplinas
CREATE TABLE `disciplinas` (
  `codigo` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution_id` int unsigned NOT NULL,
  `categoria_id` int unsigned NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`codigo`),
  KEY `idx_d_institution` (`institution_id`),
  KEY `idx_d_categoria` (`categoria_id`),
  CONSTRAINT `fk_d_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `disciplina_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_d_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: etapa_notas
CREATE TABLE `etapa_notas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etapa_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `disciplina_codigo` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` decimal(5,2) DEFAULT NULL,
  `faltas` int unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_etapa_aluno_disc` (`etapa_id`,`aluno_id`,`disciplina_codigo`),
  KEY `idx_en_etapa` (`etapa_id`),
  KEY `idx_en_aluno` (`aluno_id`),
  KEY `idx_en_disciplina` (`disciplina_codigo`),
  CONSTRAINT `fk_en_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_en_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
  CONSTRAINT `fk_en_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2777 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: etapas
CREATE TABLE `etapas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `turma_id` int unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `media_nota` decimal(5,2) NOT NULL DEFAULT '6.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etapas_turma` (`turma_id`),
  CONSTRAINT `fk_etapas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: institutions
CREATE TABLE `institutions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsible` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnpj` (`cnpj`),
  KEY `idx_institutions_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notas
CREATE TABLE `notas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etapa_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `turma_disciplina_id` int unsigned DEFAULT NULL,
  `valor` decimal(5,2) DEFAULT NULL COMMENT 'Nota do aluno na etapa',
  `faltas` tinyint unsigned DEFAULT '0' COMMENT 'Quantidade de faltas na etapa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_nota` (`etapa_id`,`aluno_id`,`turma_disciplina_id`),
  KEY `fk_notas_aluno` (`aluno_id`),
  KEY `fk_notas_td` (`turma_disciplina_id`),
  CONSTRAINT `fk_notas_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplinas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: perguntas
CREATE TABLE `perguntas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `avaliacao_id` int unsigned NOT NULL,
  `texto_pergunta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p_avaliacao` (`avaliacao_id`),
  KEY `idx_p_deleted` (`deleted_at`),
  CONSTRAINT `fk_p_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `avaliacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: profile_permissions
CREATE TABLE `profile_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile` enum('Administrador','Coordenador','Diretor','Professor','Pedagogo','Assistente Social','Naapi','Psicólogo','Outro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_profile_resource` (`profile`,`resource`)
) ENGINE=InnoDB AUTO_INCREMENT=2005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: respostas_avaliacao
CREATE TABLE `respostas_avaliacao` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `avaliacao_id` int unsigned NOT NULL,
  `conselho_id` int unsigned NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci,
  `dispositivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ra_avaliacao` (`avaliacao_id`),
  KEY `fk_ra_conselho` (`conselho_id`),
  CONSTRAINT `fk_ra_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `avaliacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_conselho` FOREIGN KEY (`conselho_id`) REFERENCES `conselhos_classe` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: respostas_perguntas
CREATE TABLE `respostas_perguntas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `resposta_id` int unsigned NOT NULL,
  `pergunta_id` int unsigned NOT NULL,
  `nota` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rp_resposta` (`resposta_id`),
  KEY `fk_rp_pergunta` (`pergunta_id`),
  CONSTRAINT `fk_rp_pergunta` FOREIGN KEY (`pergunta_id`) REFERENCES `perguntas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_resposta` FOREIGN KEY (`resposta_id`) REFERENCES `respostas_avaliacao` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: restore_logs
CREATE TABLE `restore_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `records_count` int DEFAULT NULL,
  `status` enum('success','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'error',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `restore_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tipos_avaliacao
CREATE TABLE `tipos_avaliacao` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ta_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turma_alunos
CREATE TABLE `turma_alunos` (
  `turma_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`turma_id`,`aluno_id`),
  KEY `fk_ta_aluno` (`aluno_id`),
  CONSTRAINT `fk_ta_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turma_discentes
CREATE TABLE `turma_discentes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `turma_id` int unsigned NOT NULL,
  `discente_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turma_discente` (`turma_id`,`discente_id`),
  KEY `idx_td2_discente` (`discente_id`),
  CONSTRAINT `fk_td2_discente` FOREIGN KEY (`discente_id`) REFERENCES `discentes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_td2_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turma_disciplina_professores
CREATE TABLE `turma_disciplina_professores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `turma_disciplina_id` int unsigned NOT NULL,
  `professor_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_td_professor` (`turma_disciplina_id`,`professor_id`),
  KEY `fk_tdp_professor` (`professor_id`),
  CONSTRAINT `fk_tdp_professor` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tdp_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turma_disciplinas
CREATE TABLE `turma_disciplinas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `turma_id` int unsigned NOT NULL,
  `disciplina_codigo` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_turma_disciplina` (`turma_id`,`disciplina_codigo`),
  KEY `fk_td_disciplina` (`disciplina_codigo`),
  CONSTRAINT `fk_td_disciplina` FOREIGN KEY (`disciplina_codigo`) REFERENCES `disciplinas` (`codigo`) ON DELETE CASCADE,
  CONSTRAINT `fk_td_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turma_representantes
CREATE TABLE `turma_representantes` (
  `turma_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`turma_id`,`aluno_id`),
  KEY `fk_tr_aluno` (`aluno_id`),
  CONSTRAINT `fk_tr_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tr_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: turmas
CREATE TABLE `turmas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano` int unsigned NOT NULL,
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `media_aprovacao` decimal(5,2) NOT NULL DEFAULT '6.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_turmas_course` (`course_id`),
  CONSTRAINT `fk_turmas_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_institutions
CREATE TABLE `user_institutions` (
  `user_id` int unsigned NOT NULL,
  `institution_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`institution_id`),
  KEY `idx_ui_institution` (`institution_id`),
  CONSTRAINT `fk_ui_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ui_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile` enum('Administrador','Coordenador','Diretor','Professor','Pedagogo','Assistente Social','Naapi','Outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Outro',
  `is_teacher` tinyint(1) NOT NULL DEFAULT '0',
  `theme` enum('light','dark') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_profile` (`profile`),
  KEY `idx_users_is_teacher` (`is_teacher`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- =======================================================
-- Seed Data
-- =======================================================

-- Seed: Administrador padrão
INSERT INTO users (id, name, email, password, profile, theme) 
VALUES (1, 'Administrador', 'admin@vertice.edu', 'y0.N.Tno3cdMEVjO.wItlwCkSxJui3HhrG8iTK.Ytf18me.', 'Administrador', 'light')
ON DUPLICATE KEY UPDATE email = email;

-- Seed: profile_permissions
INSERT INTO profile_permissions (profile, resource, can_access) VALUES
('Administrador', 'users.index', 1),
('Administrador', 'users.show', 1),
('Administrador', 'users.create', 1),
('Administrador', 'users.update', 1),
('Administrador', 'users.delete', 1),
('Administrador', 'courses.index', 1),
('Coordenador', 'courses.index', 1),
('Professor', 'courses.index', 1),
('Pedagogo', 'courses.index', 1),
('Administrador', 'courses.show', 1),
('Coordenador', 'courses.show', 1),
('Professor', 'courses.show', 1),
('Administrador', 'courses.create', 1),
('Coordenador', 'courses.create', 1),
('Administrador', 'courses.update', 1),
('Coordenador', 'courses.update', 1),
('Administrador', 'courses.delete', 1),
('Administrador', 'institutions.index', 1),
('Administrador', 'institutions.manage', 1),
('Administrador', 'subjects.index', 1),
('Coordenador', 'subjects.index', 1),
('Administrador', 'subjects.manage', 1),
('Coordenador', 'subjects.manage', 1),
('Administrador', 'conselhos.index', 1),
('Coordenador', 'conselhos.index', 1),
('Diretor', 'conselhos.index', 0),
('Pedagogo', 'conselhos.index', 1),
('Assistente Social', 'conselhos.index', 1),
('Psicólogo', 'conselhos.index', 1),
('Administrador', 'atendimentos.index', 1),
('Coordenador', 'atendimentos.index', 1),
('Pedagogo', 'atendimentos.index', 1),
('Assistente Social', 'atendimentos.index', 1),
('Psicólogo', 'atendimentos.index', 1),
('Coordenador', 'courses.delete', 0),
('Coordenador', 'institutions.index', 0),
('Coordenador', 'institutions.manage', 0),
('Coordenador', 'users.create', 0),
('Coordenador', 'users.delete', 0),
('Coordenador', 'users.index', 0),
('Coordenador', 'users.show', 0),
('Coordenador', 'users.update', 0),
('Diretor', 'atendimentos.index', 0),
('Diretor', 'courses.create', 0),
('Diretor', 'courses.delete', 0),
('Diretor', 'courses.index', 0),
('Diretor', 'courses.show', 0),
('Diretor', 'courses.update', 0),
('Diretor', 'institutions.index', 0),
('Diretor', 'institutions.manage', 0),
('Diretor', 'subjects.index', 0),
('Diretor', 'subjects.manage', 0),
('Diretor', 'users.create', 0),
('Diretor', 'users.delete', 0),
('Diretor', 'users.index', 0),
('Diretor', 'users.show', 0),
('Diretor', 'users.update', 0),
('Professor', 'atendimentos.index', 0),
('Professor', 'conselhos.index', 0),
('Professor', 'courses.create', 0),
('Professor', 'courses.delete', 0),
('Professor', 'courses.update', 0),
('Professor', 'institutions.index', 0),
('Professor', 'institutions.manage', 0),
('Professor', 'subjects.index', 0),
('Professor', 'subjects.manage', 0),
('Professor', 'users.create', 0),
('Professor', 'users.delete', 0),
('Professor', 'users.index', 0),
('Professor', 'users.show', 0),
('Professor', 'users.update', 0),
('Pedagogo', 'courses.create', 0),
('Pedagogo', 'courses.delete', 0),
('Pedagogo', 'courses.show', 0),
('Pedagogo', 'courses.update', 0),
('Pedagogo', 'institutions.index', 0),
('Pedagogo', 'institutions.manage', 0),
('Pedagogo', 'subjects.index', 0),
('Pedagogo', 'subjects.manage', 0),
('Pedagogo', 'users.create', 0),
('Pedagogo', 'users.delete', 0),
('Pedagogo', 'users.index', 0),
('Pedagogo', 'users.show', 0),
('Pedagogo', 'users.update', 0),
('Assistente Social', 'courses.create', 0),
('Assistente Social', 'courses.delete', 0),
('Assistente Social', 'courses.index', 0),
('Assistente Social', 'courses.show', 0),
('Assistente Social', 'courses.update', 0),
('Assistente Social', 'institutions.index', 0),
('Assistente Social', 'institutions.manage', 0),
('Assistente Social', 'subjects.index', 0),
('Assistente Social', 'subjects.manage', 0),
('Assistente Social', 'users.create', 0),
('Assistente Social', 'users.delete', 0),
('Assistente Social', 'users.index', 0),
('Assistente Social', 'users.show', 0),
('Assistente Social', 'users.update', 0),
('Naapi', 'atendimentos.index', 0),
('Naapi', 'conselhos.index', 0),
('Naapi', 'courses.create', 0),
('Naapi', 'courses.delete', 0),
('Naapi', 'courses.index', 0),
('Naapi', 'courses.show', 0),
('Naapi', 'courses.update', 0),
('Naapi', 'institutions.index', 0),
('Naapi', 'institutions.manage', 0),
('Naapi', 'subjects.index', 0),
('Naapi', 'subjects.manage', 0),
('Naapi', 'users.create', 0),
('Naapi', 'users.delete', 0),
('Naapi', 'users.index', 0),
('Naapi', 'users.show', 0),
('Naapi', 'users.update', 0),
('Psicólogo', 'courses.create', 0),
('Psicólogo', 'courses.delete', 0),
('Psicólogo', 'courses.index', 0),
('Psicólogo', 'courses.show', 0),
('Psicólogo', 'courses.update', 0),
('Psicólogo', 'institutions.index', 0),
('Psicólogo', 'institutions.manage', 0),
('Psicólogo', 'subjects.index', 0),
('Psicólogo', 'subjects.manage', 0),
('Psicólogo', 'users.create', 0),
('Psicólogo', 'users.delete', 0),
('Psicólogo', 'users.index', 0),
('Psicólogo', 'users.show', 0),
('Psicólogo', 'users.update', 0),
('Outro', 'atendimentos.index', 0),
('Outro', 'conselhos.index', 0),
('Outro', 'courses.create', 0),
('Outro', 'courses.delete', 0),
('Outro', 'courses.index', 0),
('Outro', 'courses.show', 0),
('Outro', 'courses.update', 0),
('Outro', 'institutions.index', 0),
('Outro', 'institutions.manage', 0),
('Outro', 'subjects.index', 0),
('Outro', 'subjects.manage', 0),
('Outro', 'users.create', 0),
('Outro', 'users.delete', 0),
('Outro', 'users.index', 0),
('Outro', 'users.show', 0),
('Outro', 'users.update', 0),
('Administrador', 'courses.manage', 1),
('Coordenador', 'courses.manage', 1),
('Administrador', 'students.index', 1),
('Coordenador', 'students.index', 1),
('Professor', 'students.index', 1),
('Pedagogo', 'students.index', 1),
('Assistente Social', 'students.index', 1),
('Naapi', 'students.index', 1),
('Psicólogo', 'students.index', 1),
('Administrador', 'students.manage', 1),
('Coordenador', 'students.manage', 1),
('Administrador', 'grades.manage', 1),
('Coordenador', 'grades.manage', 1),
('Professor', 'grades.manage', 1),
('Administrador', 'coordinators.manage', 1),
('Administrador', 'representantes.manage', 1),
('Coordenador', 'representantes.manage', 1),
('Administrador', 'survey.index', 1),
('Coordenador', 'survey.index', 1),
('Coordenador', 'coordinators.manage', 0),
('Diretor', 'coordinators.manage', 0),
('Diretor', 'courses.manage', 0),
('Diretor', 'grades.manage', 0),
('Diretor', 'representantes.manage', 0),
('Diretor', 'students.index', 0),
('Diretor', 'students.manage', 0),
('Diretor', 'survey.index', 0),
('Professor', 'coordinators.manage', 0),
('Professor', 'courses.manage', 0),
('Professor', 'representantes.manage', 0),
('Professor', 'settings.index', 0),
('Professor', 'students.manage', 0),
('Professor', 'survey.index', 0),
('Pedagogo', 'coordinators.manage', 0),
('Pedagogo', 'courses.manage', 0),
('Pedagogo', 'grades.manage', 0),
('Pedagogo', 'representantes.manage', 0),
('Pedagogo', 'students.manage', 0),
('Pedagogo', 'survey.index', 0),
('Assistente Social', 'coordinators.manage', 0),
('Assistente Social', 'courses.manage', 0),
('Assistente Social', 'grades.manage', 0),
('Assistente Social', 'representantes.manage', 0),
('Assistente Social', 'students.manage', 0),
('Assistente Social', 'survey.index', 0),
('Naapi', 'coordinators.manage', 0),
('Naapi', 'courses.manage', 0),
('Naapi', 'grades.manage', 0),
('Naapi', 'representantes.manage', 0),
('Naapi', 'settings.index', 0),
('Naapi', 'students.manage', 0),
('Naapi', 'survey.index', 0),
('Psicólogo', 'coordinators.manage', 0),
('Psicólogo', 'courses.manage', 0),
('Psicólogo', 'grades.manage', 0),
('Psicólogo', 'representantes.manage', 0),
('Psicólogo', 'students.manage', 0),
('Psicólogo', 'survey.index', 0),
('Outro', 'coordinators.manage', 0),
('Outro', 'courses.manage', 0),
('Outro', 'grades.manage', 0),
('Outro', 'representantes.manage', 0),
('Outro', 'settings.index', 0),
('Outro', 'students.index', 0),
('Outro', 'students.manage', 0),
('Outro', 'survey.index', 0),
('Administrador', 'settings.backup', 1),
('Administrador', 'settings.avaliacoes', 1),
('Administrador', 'settings.permissoes', 1),
('Coordenador', 'settings.avaliacoes', 1),
('Coordenador', 'settings.backup', 0),
('Coordenador', 'settings.permissoes', 0),
('Diretor', 'settings.avaliacoes', 0),
('Diretor', 'settings.backup', 0),
('Diretor', 'settings.permissoes', 0),
('Professor', 'settings.avaliacoes', 0),
('Professor', 'settings.backup', 0),
('Professor', 'settings.permissoes', 0),
('Pedagogo', 'settings.avaliacoes', 0),
('Pedagogo', 'settings.backup', 0),
('Pedagogo', 'settings.permissoes', 0),
('Assistente Social', 'settings.avaliacoes', 0),
('Assistente Social', 'settings.backup', 0),
('Assistente Social', 'settings.permissoes', 0),
('Naapi', 'settings.avaliacoes', 0),
('Naapi', 'settings.backup', 0),
('Naapi', 'settings.permissoes', 0),
('Psicólogo', 'settings.avaliacoes', 0),
('Psicólogo', 'settings.backup', 0),
('Psicólogo', 'settings.permissoes', 0),
('Outro', 'settings.avaliacoes', 0),
('Outro', 'settings.backup', 0),
('Outro', 'settings.permissoes', 0),
('Administrador', 'settings.index', 1),
('Coordenador', 'settings.index', 1),
('Diretor', 'settings.index', 1),
('Pedagogo', 'settings.index', 1),
('Assistente Social', 'settings.index', 1),
('Psicólogo', 'settings.index', 1)
ON DUPLICATE KEY UPDATE can_access = VALUES(can_access);
