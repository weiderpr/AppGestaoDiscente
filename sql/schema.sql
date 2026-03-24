-- =======================================================
-- Vértice Acadêmico — Schema Completo do Banco de Dados
-- Última atualização: 2026-03-24
-- =======================================================

CREATE DATABASE IF NOT EXISTS vertice_academico
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vertice_academico;

-- =======================================================
-- Remove tabelas na ordem correta (filhas antes das mães)
-- =======================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS conselhos_presentes;
DROP TABLE IF EXISTS conselhos_comentarios;
DROP TABLE IF EXISTS conselhos_etapas;
DROP TABLE IF EXISTS conselhos_classe;
DROP TABLE IF EXISTS etapa_notas;
DROP TABLE IF EXISTS restore_logs;
DROP TABLE IF EXISTS turma_disciplina_professores;
DROP TABLE IF EXISTS turma_disciplinas;
DROP TABLE IF EXISTS disciplinas;
DROP TABLE IF EXISTS disciplina_categorias;
DROP TABLE IF EXISTS turma_alunos;
DROP TABLE IF EXISTS turma_representantes;
DROP TABLE IF EXISTS alunos;
DROP TABLE IF EXISTS course_coordinators;
DROP TABLE IF EXISTS etapas;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS user_institutions;
DROP TABLE IF EXISTS institutions;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- =======================================================
-- Tabela: users
-- =======================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20)  DEFAULT NULL,
    photo       VARCHAR(255) DEFAULT NULL,
    profile     ENUM(
                    'Administrador',
                    'Coordenador',
                    'Diretor',
                    'Professor',
                    'Pedagogo',
                    'Assistente Social',
                    'Naapi',
                    'Psicólogo',
                    'Outro'
                ) NOT NULL DEFAULT 'Outro',
    theme       ENUM('light', 'dark') NOT NULL DEFAULT 'light',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at  TIMESTAMP NULL DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_email    ON users (email);
CREATE INDEX idx_users_profile  ON users (profile);
CREATE INDEX idx_users_deleted   ON users (deleted_at);

-- -------------------------------------------------------
-- Usuário Administrador padrão do sistema
-- Login: admin@vertice.edu  |  Senha: Admin@2024
-- -------------------------------------------------------
INSERT INTO users (name, email, password, phone, profile, theme)
VALUES (
    'Administrador',
    'admin@vertice.edu',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@2024 (bcrypt)
    NULL,
    'Administrador',
    'light'
);

-- =======================================================
-- Tabela: institutions
-- =======================================================
CREATE TABLE institutions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    cnpj         VARCHAR(18)  NOT NULL UNIQUE,
    photo        VARCHAR(255) DEFAULT NULL,
    responsible  VARCHAR(255) DEFAULT NULL,
    address      VARCHAR(500) DEFAULT NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    deleted_at   TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_institutions_name ON institutions (name);
CREATE INDEX idx_inst_deleted      ON institutions (deleted_at);

-- =======================================================
-- Tabela: user_institutions  (N:N — usuários ↔ instituições)
-- =======================================================
CREATE TABLE user_institutions (
    user_id        INT UNSIGNED NOT NULL,
    institution_id INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, institution_id),
    CONSTRAINT fk_ui_user        FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ui_institution FOREIGN KEY (institution_id)
        REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ui_institution ON user_institutions (institution_id);

-- =======================================================
-- Tabela: courses  (curso pertence a uma instituição)
-- =======================================================
CREATE TABLE courses (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id INT UNSIGNED NOT NULL,
    name           VARCHAR(255) NOT NULL,
    location       VARCHAR(255) DEFAULT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    deleted_at     TIMESTAMP NULL DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_institution FOREIGN KEY (institution_id)
        REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_courses_institution ON courses (institution_id);
CREATE INDEX idx_courses_name        ON courses (name);
CREATE INDEX idx_courses_is_active   ON courses (is_active);
CREATE INDEX idx_courses_deleted    ON courses (deleted_at);

-- =======================================================
-- Tabela: turmas  (turma pertence a um curso)
-- =======================================================
CREATE TABLE turmas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    description     VARCHAR(255) NOT NULL,
    ano             INT UNSIGNED NOT NULL,
    nota_maxima     DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    media_aprovacao DECIMAL(5,2) NOT NULL DEFAULT 6.00,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_turmas_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_turmas_course    ON turmas (course_id);
CREATE INDEX idx_turmas_is_active ON turmas (is_active);
CREATE INDEX idx_turmas_ano       ON turmas (ano);
CREATE INDEX idx_turmas_deleted   ON turmas (deleted_at);

-- =======================================================
-- Tabela: etapas  (etapa pertence a uma turma)
-- =======================================================
CREATE TABLE etapas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id        INT UNSIGNED NOT NULL,
    description     VARCHAR(255) NOT NULL,
    nota_maxima     DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    media_nota      DECIMAL(5,2) NOT NULL DEFAULT 6.00,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_etapas_turma FOREIGN KEY (turma_id)
        REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_etapas_turma     ON etapas(turma_id);
CREATE INDEX idx_etapas_is_active ON etapas(is_active);
CREATE INDEX idx_etapas_deleted   ON etapas(deleted_at);

-- =======================================================
-- Tabela: course_coordinators (muitos-para-muitos)
-- =======================================================
CREATE TABLE course_coordinators (
    course_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, user_id),
    CONSTRAINT fk_cc_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cc_user ON course_coordinators(user_id);

-- =======================================================
-- Tabela: alunos
-- =======================================================
CREATE TABLE alunos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula   VARCHAR(11)  NOT NULL UNIQUE,
    nome        VARCHAR(255) NOT NULL,
    telefone    VARCHAR(20)  DEFAULT NULL,
    email       VARCHAR(255) DEFAULT NULL,
    photo       VARCHAR(255) DEFAULT NULL,
    deleted_at  TIMESTAMP NULL DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_alunos_matricula ON alunos (matricula);
CREATE INDEX idx_alunos_nome      ON alunos (nome);
CREATE INDEX idx_alunos_email     ON alunos (email);
CREATE INDEX idx_alunos_deleted   ON alunos (deleted_at);

-- =======================================================
-- Tabela: turma_representantes (N:N — turmas ↔ alunos)
-- =======================================================
CREATE TABLE turma_representantes (
    turma_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (turma_id, aluno_id),
    CONSTRAINT fk_tr_turma FOREIGN KEY (turma_id)
        REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_aluno FOREIGN KEY (aluno_id)
        REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tr_aluno ON turma_representantes (aluno_id);

-- =======================================================
-- Tabela: turma_alunos (N:N — turmas ↔ alunos)
-- =======================================================
CREATE TABLE turma_alunos (
    turma_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (turma_id, aluno_id),
    CONSTRAINT fk_ta_turma FOREIGN KEY (turma_id)
        REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_aluno FOREIGN KEY (aluno_id)
        REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ta_aluno ON turma_alunos (aluno_id);

-- =======================================================
-- Tabela: comentarios_professores (comentários dos professores sobre alunos)
-- =======================================================
CREATE TABLE comentarios_professores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    professor_id INT UNSIGNED NOT NULL,
    aluno_id    INT UNSIGNED NOT NULL,
    turma_id    INT UNSIGNED NOT NULL,
    conteudo    TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_professor FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cp_aluno    ON comentarios_professores (aluno_id);
CREATE INDEX idx_cp_turma    ON comentarios_professores (turma_id);
CREATE INDEX idx_cp_professor ON comentarios_professores (professor_id);
CREATE INDEX idx_cp_created   ON comentarios_professores (created_at);

-- =======================================================
-- Tabela: disciplina_categorias
-- =======================================================
CREATE TABLE disciplina_categorias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    nome            VARCHAR(100) NOT NULL,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_dc_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_dc_institution ON disciplina_categorias (institution_id);
CREATE INDEX idx_dc_deleted     ON disciplina_categorias (deleted_at);

-- =======================================================
-- Tabela: disciplinas
-- =======================================================
CREATE TABLE disciplinas (
    codigo          VARCHAR(15) PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    categoria_id    INT UNSIGNED NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    observacoes     TEXT,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_d_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_d_categoria FOREIGN KEY (categoria_id) REFERENCES disciplina_categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_d_institution ON disciplinas (institution_id);
CREATE INDEX idx_d_categoria   ON disciplinas (categoria_id);
CREATE INDEX idx_d_is_active   ON disciplinas (is_active);
CREATE INDEX idx_d_deleted     ON disciplinas (deleted_at);

-- =======================================================
-- Tabela: turma_disciplinas (N:N — turmas ↔ disciplinas)
-- =======================================================
CREATE TABLE turma_disciplinas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id    INT UNSIGNED NOT NULL,
    disciplina_codigo VARCHAR(15) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_turma_disciplina (turma_id, disciplina_codigo),
    CONSTRAINT fk_td_turma FOREIGN KEY (turma_id)
        REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_td_disciplina FOREIGN KEY (disciplina_codigo)
        REFERENCES disciplinas(codigo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_td_turma     ON turma_disciplinas (turma_id);
CREATE INDEX idx_td_disciplina ON turma_disciplinas (disciplina_codigo);

-- =======================================================
-- Tabela: turma_disciplina_professores (N:N — relação turma-disciplina ↔ professores)
-- =======================================================
CREATE TABLE turma_disciplina_professores (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_disciplina_id INT UNSIGNED NOT NULL,
    professor_id        INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_td_professor (turma_disciplina_id, professor_id),
    CONSTRAINT fk_tdp_td FOREIGN KEY (turma_disciplina_id)
        REFERENCES turma_disciplinas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tdp_professor FOREIGN KEY (professor_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tdp_td       ON turma_disciplina_professores (turma_disciplina_id);
CREATE INDEX idx_tdp_professor ON turma_disciplina_professores (professor_id);

-- =======================================================
-- Tabela: restore_logs (log de restaurações de backup)
-- =======================================================
CREATE TABLE restore_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    restore_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason      TEXT NOT NULL,
    file_name   VARCHAR(255) DEFAULT NULL,
    file_size   INT UNSIGNED DEFAULT NULL,
    records_count INT UNSIGNED DEFAULT 0,
    status      ENUM('success', 'error') NOT NULL DEFAULT 'success',
    error_message TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_rl_user  ON restore_logs (user_id);
CREATE INDEX idx_rl_date ON restore_logs (restore_date);

-- =======================================================
-- Tabela: etapa_notas (registro de nota e frequência do aluno por etapa)
-- =======================================================
CREATE TABLE etapa_notas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etapa_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    disciplina_codigo VARCHAR(15) NOT NULL,
    nota DECIMAL(5,2) DEFAULT NULL,
    faltas INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_etapa_aluno_disc (etapa_id, aluno_id, disciplina_codigo),
    CONSTRAINT fk_en_etapa FOREIGN KEY (etapa_id) REFERENCES etapas(id) ON DELETE CASCADE,
    CONSTRAINT fk_en_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    CONSTRAINT fk_en_disciplina FOREIGN KEY (disciplina_codigo) REFERENCES disciplinas(codigo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_en_etapa ON etapa_notas(etapa_id);
CREATE INDEX idx_en_aluno ON etapa_notas(aluno_id);
CREATE INDEX idx_en_disciplina ON etapa_notas(disciplina_codigo);

-- =======================================================
-- Tabela: conselhos_classe
-- =======================================================
CREATE TABLE conselhos_classe (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    course_id       INT UNSIGNED NOT NULL,
    turma_id        INT UNSIGNED NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    data_hora       DATETIME NOT NULL,
    local_reuniao   VARCHAR(255) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_inst FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cc_inst    ON conselhos_classe(institution_id);
CREATE INDEX idx_cc_course  ON conselhos_classe(course_id);
CREATE INDEX idx_cc_turma   ON conselhos_classe(turma_id);
CREATE INDEX idx_cc_data    ON conselhos_classe(data_hora);
CREATE INDEX idx_cc_deleted ON conselhos_classe(deleted_at);

-- =======================================================
-- Tabela: conselhos_etapas (relação N:N)
-- =======================================================
CREATE TABLE conselhos_etapas (
    conselho_id INT UNSIGNED NOT NULL,
    etapa_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (conselho_id, etapa_id),
    CONSTRAINT fk_ce_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_ce_etapa FOREIGN KEY (etapa_id) REFERENCES etapas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- Tabela: conselhos_comentarios
-- =======================================================
CREATE TABLE conselhos_comentarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conselho_id     INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NOT NULL,
    aluno_id        INT UNSIGNED DEFAULT NULL,
    conteudo        TEXT NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ccons_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_ccons_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ccons_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ccons_conselho ON conselhos_comentarios(conselho_id);
CREATE INDEX idx_ccons_usuario  ON conselhos_comentarios(usuario_id);
CREATE INDEX idx_ccons_aluno    ON conselhos_comentarios(aluno_id);

-- =======================================================
-- Tabela: conselhos_presentes
-- =======================================================
CREATE TABLE conselhos_presentes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conselho_id INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    presente    TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_conselho FOREIGN KEY (conselho_id) REFERENCES conselhos_classe(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cp_conselho ON conselhos_presentes(conselho_id);
CREATE INDEX idx_cp_usuario  ON conselhos_presentes(usuario_id);
