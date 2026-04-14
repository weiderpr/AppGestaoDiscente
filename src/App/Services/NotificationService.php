<?php
/**
 * Vértice Acadêmico — Serviço de Notificações
 */

namespace App\Services;

class NotificationService extends Service {
    private ?int $institutionId;

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->institutionId = $_SESSION['institution_id'] ?? null;
    }

    /**
     * Conta conselhos em aberto para o usuário
     */
    public function countOpenCouncils(array $user): int {
        if (!$this->institutionId) return 0;
        
        $userId = (int)$user['id'];
        $profile = $user['profile'];
        
        if (in_array($profile, ['Administrador', 'Diretor'])) {
            $sql = "SELECT COUNT(*) as total FROM conselhos_classe cc
                    JOIN turmas t ON cc.turma_id = t.id
                    JOIN courses c ON t.course_id = c.id
                    WHERE cc.is_active = 1 AND c.institution_id = ?";
            $res = $this->fetchOne($sql, [$this->institutionId]);
            return (int)($res['total'] ?? 0);
        } elseif ($profile === 'Coordenador') {
            $sql = "SELECT COUNT(*) as total FROM conselhos_classe cc
                    JOIN turmas t ON cc.turma_id = t.id
                    JOIN course_coordinators cu ON t.course_id = cu.course_id
                    WHERE cc.is_active = 1 AND cu.user_id = ?";
            $res = $this->fetchOne($sql, [$userId]);
            return (int)($res['total'] ?? 0);
        }
        
        return 0;
    }

    /**
     * Gera uma notificação virtual sobre conselhos em aberto
     */
    private function getCouncilAlert(array $user): ?array {
        $allowed = ['Administrador', 'Diretor', 'Coordenador'];
        if (!in_array($user['profile'], $allowed)) return null;

        $count = $this->countOpenCouncils($user);
        if ($count === 0) return null;

        // ID virtual: -100 + (contagem * 10). Se a contagem mudar, o ID muda e a notificação reaparece.
        $notifId = -100 - ($count * 10);

        // Verificar se o usuário já marcou esta versão específica do alerta como lida
        $userId = (int)$user['id'];
        $isRead = $this->fetchOne("SELECT 1 FROM sys_notifications_read WHERE usuario_id = ? AND notificacao_id = ?", [$userId, $notifId]);
        if ($isRead) return null;

        return [
            'id' => $notifId,
            'institution_id' => $this->institutionId,
            'titulo' => 'Conselhos de Classe em Aberto',
            'mensagem' => "Existem {$count} conselhos de classe pendentes de finalização. Clique para gerenciar.",
            'tipo' => 'Warning',
            'link_acao' => '/courses/conselhos.php',
            'created_at' => date('Y-m-d H:i:s'),
            'required_permission' => null,
            'aluno_id' => null,
            'turma_id' => null
        ];
    }

    /**
     * Envia uma nova notificação
     */
    public function push(array $data): int {
        if (!$this->institutionId) return 0;

        $sql = "INSERT INTO sys_notifications 
                (institution_id, titulo, mensagem, tipo, aluno_id, turma_id, target_user_id, link_acao, required_permission) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->execute($sql, [
            $this->institutionId,
            $data['titulo'],
            $data['mensagem'],
            $data['tipo'] ?? 'Info',
            $data['aluno_id'] ?? null,
            $data['turma_id'] ?? null,
            $data['target_user_id'] ?? null,
            $data['link_acao'] ?? null,
            $data['required_permission'] ?? null
        ]);
    }

    /**
     * Envia uma notificação apenas se ela já não existir como não lida para o usuário
     */
    public function pushUniqueForUser(int $userId, array $data): int {
        // Verificar se já existe uma notificação não lida com o mesmo título para este usuário
        $sql = "SELECT n.id FROM sys_notifications n
                LEFT JOIN sys_notifications_read nr ON n.id = nr.notificacao_id AND nr.usuario_id = ?
                WHERE n.target_user_id = ? AND n.titulo = ? AND nr.notificacao_id IS NULL 
                LIMIT 1";
        
        $exists = $this->fetchOne($sql, [$userId, $userId, $data['titulo']]);
        if ($exists) return (int)$exists['id'];

        $data['target_user_id'] = $userId;
        return $this->push($data);
    }

    /**
     * Busca notificações não lidas para o usuário respeitando a hierarquia
     */
    public function getUnreadForUser(array $user): array {
        if (!$this->institutionId) return [];

        $userId = (int)$user['id'];
        $profile = $user['profile'];

        // Perfis com visão global
        $globalProfiles = ['Administrador', 'Pedagogo', 'Psicólogo', 'Assistente Social', 'Naapi', 'Diretor'];
        $isGlobal = in_array($profile, $globalProfiles);

        $sql = "
            SELECT n.* 
            FROM sys_notifications n
            LEFT JOIN sys_notifications_read nr ON n.id = nr.notificacao_id AND nr.usuario_id = ?
            WHERE n.institution_id = ? 
              AND nr.notificacao_id IS NULL
              AND (
                  -- 1. Notificação Direcionada Individualmente
                  n.target_user_id = ?
                  OR (
                      n.target_user_id IS NULL AND (
                          -- 2. Visão Global (Perfis administrativos/pedagógicos)
                          ? = 1
                          OR
                          -- 3. Notificações de Sistema (Sem vínculo específico)
                          (n.turma_id IS NULL AND n.aluno_id IS NULL)
                          OR
                          -- 4. Vínculo por Turma (Professor ou Coordenador)
                          (n.turma_id IN (
                              SELECT t.id FROM turmas t 
                              LEFT JOIN course_coordinators cc ON t.course_id = cc.course_id AND cc.user_id = ?
                              LEFT JOIN turma_disciplinas td ON t.id = td.turma_id
                              LEFT JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id AND tdp.professor_id = ?
                              WHERE cc.user_id IS NOT NULL OR tdp.professor_id IS NOT NULL
                          ))
                          OR
                          -- 5. Vínculo por Aluno (Professor ou Coordenador do aluno)
                          (n.aluno_id IN (
                              SELECT ta.aluno_id FROM turma_alunos ta
                              JOIN turmas t ON ta.turma_id = t.id
                              LEFT JOIN course_coordinators cc ON t.course_id = cc.course_id AND cc.user_id = ?
                              LEFT JOIN turma_disciplinas td ON t.id = td.turma_id
                              LEFT JOIN turma_disciplina_professores tdp ON td.id = tdp.turma_disciplina_id AND tdp.professor_id = ?
                              WHERE cc.user_id IS NOT NULL OR tdp.professor_id IS NOT NULL
                          ))
                      )
                  )
              )
            ORDER BY n.created_at DESC
        ";

        $notifications = $this->fetchAll($sql, [
            $userId, 
            $this->institutionId, 
            $userId, // target_user_id
            $isGlobal ? 1 : 0,
            $userId, $userId,
            $userId, $userId
        ]);

        // Injetar alerta de conselhos se aplicável
        $councilAlert = $this->getCouncilAlert($user);
        if ($councilAlert) {
            array_unshift($notifications, $councilAlert);
        }

        return $notifications;
    }

    /**
     * Marca uma notificação como lida para o usuário
     */
    public function markAsRead(int $userId, int $notificationId): bool {
        // BUG FIX: Retornar true mesmo que já exista o registro (IGNORE)
        $sql = "INSERT IGNORE INTO sys_notifications_read (usuario_id, notificacao_id) VALUES (?, ?)";
        $this->execute($sql, [$userId, $notificationId]);
        
        // Verificar se realmente está lida (pode ter sido inserida agora ou já existia)
        $check = $this->fetchOne("SELECT 1 FROM sys_notifications_read WHERE usuario_id = ? AND notificacao_id = ?", [$userId, $notificationId]);
        return $check !== null;
    }
}
