-- =======================================================
-- Vértice Acadêmico — Schema Completo do Banco de Dados
-- Última atualização: 2026-03-18
-- =======================================================

CREATE DATABASE IF NOT EXISTS vertice_academico
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vertice_academico;

-- =======================================================
-- Remove tabelas na ordem correta (filhas antes das mães)
-- =======================================================
DROP TABLE IF EXISTS user_institutions;
DROP TABLE IF EXISTS institutions;
DROP TABLE IF EXISTS users;

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
-- IMPORTANTE: altere a senha após o primeiro acesso!
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
