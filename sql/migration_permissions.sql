-- Migração: Tabela de Permissões por Perfil
-- Data: 2026-03-27

CREATE TABLE IF NOT EXISTS profile_permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
                ) NOT NULL,
    resource    VARCHAR(100) NOT NULL,
    can_access  TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_profile_resource (profile, resource)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial baseado nas configurações atuais do routes.php
INSERT INTO profile_permissions (profile, resource, can_access) VALUES
-- Usuários (Apenas Admin)
('Administrador', 'users.index', 1),
('Administrador', 'users.show', 1),
('Administrador', 'users.create', 1),
('Administrador', 'users.update', 1),
('Administrador', 'users.delete', 1),

-- Cursos (Admin e Coordenador para escrita, todos para leitura)
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

-- Alunos (Todos podem ver, Admin/Coord gerenciam)
('Administrador', 'students.index', 1),
('Coordenador', 'students.index', 1),
('Professor', 'students.index', 1),
('Pedagogo', 'students.index', 1),
('Assistente Social', 'students.index', 1),
('Naapi', 'students.index', 1),
('Psicólogo', 'students.index', 1),
('Administrador', 'students.manage', 1),
('Coordenador', 'students.manage', 1),

-- Notas e Avaliações (Admin/Coord/Professor)
('Administrador', 'grades.manage', 1),
('Coordenador', 'grades.manage', 1),
('Professor', 'grades.manage', 1),

-- Representantes e Coordenadores
('Administrador', 'coordinators.manage', 1),
('Administrador', 'representantes.manage', 1),
('Coordenador', 'representantes.manage', 1),

-- Configurações e Pesquisas
('Administrador', 'settings.index', 1),
('Coordenador', 'settings.index', 1),
('Administrador', 'survey.index', 1),
('Coordenador', 'survey.index', 1),

-- Instituições (Apenas Admin)
('Administrador', 'institutions.index', 1),
('Administrador', 'institutions.manage', 1),

-- Disciplinas (Admin e Coordenador)
('Administrador', 'subjects.index', 1),
('Coordenador', 'subjects.index', 1),
('Administrador', 'subjects.manage', 1),
('Coordenador', 'subjects.manage', 1),

-- Conselhos (Vários perfis)
('Administrador', 'conselhos.index', 1),
('Coordenador', 'conselhos.index', 1),
('Diretor', 'conselhos.index', 1),
('Pedagogo', 'conselhos.index', 1),
('Assistente Social', 'conselhos.index', 1),
('Psicólogo', 'conselhos.index', 1),

-- Atendimentos (Vários perfis)
('Administrador', 'atendimentos.index', 1),
('Coordenador', 'atendimentos.index', 1),
('Pedagogo', 'atendimentos.index', 1),
('Assistente Social', 'atendimentos.index', 1),
('Psicólogo', 'atendimentos.index', 1)
ON DUPLICATE KEY UPDATE can_access = VALUES(can_access);
