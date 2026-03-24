-- =======================================================
-- Vértice Acadêmico — Schema Completo do Banco de Dados
-- Última atualização: 2026-03-21
-- =======================================================

CREATE DATABASE IF NOT EXISTS vertice_academico
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vertice_academico;

-- =======================================================
-- Remove tabelas na ordem correta (filhas antes das mães)
-- =======================================================
SET FOREIGN_KEY_CHECKS = 0;
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
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_email   ON users (email);
CREATE INDEX idx_users_profile ON users (profile);

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
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_institutions_name ON institutions (name);

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
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_institution FOREIGN KEY (institution_id)
        REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_courses_institution ON courses (institution_id);
CREATE INDEX idx_courses_name        ON courses (name);

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
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_turmas_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_turmas_course ON turmas (course_id);

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
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_etapas_turma FOREIGN KEY (turma_id)
        REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_etapas_turma ON etapas(turma_id);

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
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_alunos_matricula ON alunos (matricula);
CREATE INDEX idx_alunos_nome      ON alunos (nome);

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

CREATE INDEX idx_cp_aluno ON comentarios_professores (aluno_id);
CREATE INDEX idx_cp_turma ON comentarios_professores (turma_id);

-- =======================================================
-- Tabela: disciplina_categorias
-- =======================================================
CREATE TABLE disciplina_categorias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    nome            VARCHAR(100) NOT NULL,
    CONSTRAINT fk_dc_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_dc_institution ON disciplina_categorias (institution_id);

-- =======================================================
-- Tabela: disciplinas
-- =======================================================
CREATE TABLE disciplinas (
    codigo          VARCHAR(15) PRIMARY KEY,
    institution_id  INT UNSIGNED NOT NULL,
    categoria_id    INT UNSIGNED NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    observacoes     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_d_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_d_categoria FOREIGN KEY (categoria_id) REFERENCES disciplina_categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_d_institution ON disciplinas (institution_id);
CREATE INDEX idx_d_categoria   ON disciplinas (categoria_id);

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
