<?php
/**
 * Vértice Acadêmico — API Segunda Chamada
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../src/App/Services/Service.php';
require_once __DIR__ . '/../src/App/Services/SegundaChamadaService.php';
require_once __DIR__ . '/../src/App/Services/MailService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão expirada. Por favor, faça login novamente.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$service = new \App\Services\SegundaChamadaService();
$inst = getCurrentInstitution();
$instId = $inst['id'] ?? 0;
$user = getCurrentUser();

$isCoordinator = ($user['profile'] === 'Coordenador' && !hasDbPermission('segundachamada.view_all', false));
$coordinatorUserId = $isCoordinator ? (int)$user['id'] : null;

if (!$instId) {
    echo json_encode(['success' => false, 'error' => 'Instituição não selecionada.']);
    exit;
}

try {
    switch ($action) {
        case 'get_student_disciplines':
            hasDbPermission('segundachamada.index');
            $alunoId = (int)($_GET['aluno_id'] ?? 0);
            $turmaId = (int)($_GET['turma_id'] ?? 0);
            
            if (!$alunoId) {
                throw new Exception('ID do aluno obrigatório.');
            }
            
            if (!$turmaId) {
                $turmaId = $service->getStudentTurma($alunoId);
            }
            
            if (!$turmaId) {
                echo json_encode(['success' => true, 'disciplinas' => []]);
                exit;
            }
            
            $disciplinas = $service->getStudentDisciplines($alunoId, $turmaId);
            echo json_encode(['success' => true, 'disciplinas' => $disciplinas]);
            break;

        case 'get_disciplina_name':
            hasDbPermission('segundachamada.index');
            $codigo = trim($_GET['codigo'] ?? '');
            $name = $service->getDisciplinaName($codigo);
            echo json_encode(['success' => true, 'name' => $name]);
            break;

        case 'search_alunos':
            hasDbPermission('segundachamada.index');
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 3) {
                echo json_encode(['success' => true, 'alunos' => []]);
                exit;
            }
            $alunos = $service->searchAlunos($instId, $q, $coordinatorUserId);
            echo json_encode(['success' => true, 'alunos' => $alunos]);
            break;

        case 'get':
            hasDbPermission('segundachamada.index');
            $id = (int)($_GET['id'] ?? 0);
            $registro = $service->getById($id);
            if (!$registro) {
                throw new Exception('Registro de segunda chamada não encontrado.');
            }
            if ($coordinatorUserId !== null && !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$registro['aluno_id'])) {
                throw new Exception('Você não tem permissão para acessar esta solicitação.');
            }
            echo json_encode(['success' => true, 'data' => $registro]);
            break;

        case 'save':
            hasDbPermission('segundachamada.manage');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método de requisição inválido.');
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança expirado ou inválido (CSRF).');
            }

            $id = (int)($_POST['id'] ?? 0);
            
            $data = [
                'aluno_id' => (int)($_POST['aluno_id'] ?? 0),
                'telefone_aluno' => trim($_POST['telefone_aluno'] ?? ''),
                'email_aluno' => trim($_POST['email_aluno'] ?? ''),
                'nome_responsavel' => trim($_POST['nome_responsavel'] ?? ''),
                'telefone_responsavel' => trim($_POST['telefone_responsavel'] ?? ''),
                'disciplina_codigo' => trim($_POST['disciplina_codigo'] ?? ''),
                'atividade_nome' => trim($_POST['atividade_nome'] ?? ''),
                'justificativa' => trim($_POST['justificativa'] ?? ''),
                'data_atividade_perdida' => trim($_POST['data_atividade_perdida'] ?? ''),
                'status' => trim($_POST['status'] ?? 'Pendente'),
                'observacoes_status' => trim($_POST['observacoes_status'] ?? ''),
                'institution_id' => $instId,
                'usuario_id' => $user['id']
            ];

            // Validações de Permissão de Coordenador
            if ($coordinatorUserId !== null) {
                if (!$service->isCoordinatorOfStudent($coordinatorUserId, $data['aluno_id'])) {
                    throw new Exception('Você não tem permissão para cadastrar ou editar solicitações para este aluno (fora do escopo do seu curso).');
                }
                if ($id > 0) {
                    $oldRequest = $service->getById($id);
                    if ($oldRequest && !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$oldRequest['aluno_id'])) {
                        throw new Exception('Você não tem permissão para alterar esta solicitação.');
                    }
                }
            }

            // Validações básicas
            if (empty($data['aluno_id'])) throw new Exception('O aluno é obrigatório.');
            if (empty($data['telefone_aluno'])) throw new Exception('O telefone do aluno é obrigatório.');
            if (empty($data['email_aluno'])) throw new Exception('O e-mail do aluno é obrigatório.');
            if (!filter_var($data['email_aluno'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('O e-mail do aluno informado é inválido.');
            }
            if (empty($data['disciplina_codigo'])) throw new Exception('A disciplina é obrigatória.');
            if (empty($data['atividade_nome'])) throw new Exception('O nome/descrição da atividade é obrigatório.');
            if (empty($data['justificativa'])) throw new Exception('A justificativa é obrigatória.');
            if (empty($data['data_atividade_perdida'])) throw new Exception('A data da atividade perdida é obrigatória.');

            // Forçar status Pendente na criação (travado para o usuário)
            if ($id === 0) {
                $data['status'] = 'Pendente';
            }

            // Processamento do Anexo
            $hasFile = isset($_FILES['anexo']) && $_FILES['anexo']['error'] !== UPLOAD_ERR_NO_FILE;
            if ($hasFile) {
                $file = $_FILES['anexo'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Erro no upload do arquivo (Código: {$file['error']}).");
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedImageExts = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedExts = array_merge(['pdf'], $allowedImageExts);

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception("Tipo de arquivo não permitido. Apenas PDF ou Imagem (JPG, JPEG, PNG, WEBP) são aceitos.");
                }

                $uploadDir = __DIR__ . '/../assets/uploads/segundachamada/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = uniqid('sc_', true) . '.' . $ext;
                $filePath = 'assets/uploads/segundachamada/' . $fileName;
                $destPath = $uploadDir . $fileName;

                if ($ext === 'pdf') {
                    // PDF max 10MB
                    $maxPdfSize = 10 * 1024 * 1024; // 10MB
                    if ($file['size'] > $maxPdfSize) {
                        throw new Exception("O tamanho máximo permitido para arquivos PDF é de 10MB.");
                    }
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        throw new Exception("Falha ao salvar o arquivo PDF no servidor.");
                    }
                    
                    $data['anexo_caminho'] = $filePath;
                    $data['anexo_nome'] = $file['name'];
                    $data['anexo_tipo'] = $file['type'];
                    $data['anexo_tamanho'] = $file['size'];
                } else {
                    // Imagem - Compactar para 2MB
                    $maxImageSize = 2 * 1024 * 1024; // 2MB
                    
                    // Se a imagem for maior que 2MB ou sempre que for imagem, vamos tentar comprimir.
                    $info = @getimagesize($file['tmp_name']);
                    if ($info === false) {
                        // Não é uma imagem válida, tentar salvar como arquivo simples
                        if ($file['size'] > $maxImageSize) {
                            throw new Exception("O arquivo de imagem excede o tamanho limite de 2MB.");
                        }
                        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                            throw new Exception("Falha ao salvar a imagem no servidor.");
                        }
                        $data['anexo_caminho'] = $filePath;
                        $data['anexo_nome'] = $file['name'];
                        $data['anexo_tipo'] = $file['type'];
                        $data['anexo_tamanho'] = $file['size'];
                    } else {
                        // Tentar compressão GD
                        $mime = $info['mime'];
                        $srcImg = null;
                        
                        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                            $srcImg = @imagecreatefromjpeg($file['tmp_name']);
                        } elseif ($mime === 'image/png') {
                            $srcImg = @imagecreatefrompng($file['tmp_name']);
                        } elseif ($mime === 'image/webp') {
                            $srcImg = @imagecreatefromwebp($file['tmp_name']);
                        }

                        if ($srcImg) {
                            // Definir qualidade de compressão inicial (70%)
                            $quality = 70;
                            
                            // Se a imagem for enorme em pixels, redimensionamos para economizar memória e espaço
                            $width = imagesx($srcImg);
                            $height = imagesy($srcImg);
                            if ($width > 2000 || $height > 2000) {
                                $ratio = min(2000 / $width, 2000 / $height);
                                $newWidth = (int)($width * $ratio);
                                $newHeight = (int)($height * $ratio);
                                $scaledImg = imagescale($srcImg, $newWidth, $newHeight);
                                if ($scaledImg) {
                                    imagedestroy($srcImg);
                                    $srcImg = $scaledImg;
                                }
                            }

                            // Salvar no formato comprimido
                            if ($mime === 'image/png') {
                                // Para PNG, podemos salvar diretamente com nível de compressão 7
                                imagepng($srcImg, $destPath, 7);
                            } elseif ($mime === 'image/webp') {
                                imagewebp($srcImg, $destPath, $quality);
                            } else {
                                imagejpeg($srcImg, $destPath, $quality);
                            }
                            imagedestroy($srcImg);

                            // Verificar tamanho final
                            if (file_exists($destPath)) {
                                $newSize = filesize($destPath);
                                // Se mesmo após a compressão for maior que 2MB (improvável), lançar erro
                                if ($newSize > $maxImageSize) {
                                    @unlink($destPath);
                                    throw new Exception("A imagem comprimida ainda excede o limite de 2MB. Envie uma imagem menor.");
                                }
                                $data['anexo_caminho'] = $filePath;
                                $data['anexo_nome'] = $file['name'];
                                $data['anexo_tipo'] = $mime;
                                $data['anexo_tamanho'] = $newSize;
                            } else {
                                throw new Exception("Erro ao processar a compressão da imagem.");
                            }
                        } else {
                            // Fallback se GD falhar ou não suportar o tipo
                            if ($file['size'] > $maxImageSize) {
                                throw new Exception("A imagem excede o tamanho limite de 2MB e não pôde ser comprimida automaticamente.");
                            }
                            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                                throw new Exception("Falha ao salvar a imagem no servidor.");
                            }
                            $data['anexo_caminho'] = $filePath;
                            $data['anexo_nome'] = $file['name'];
                            $data['anexo_tipo'] = $file['type'];
                            $data['anexo_tamanho'] = $file['size'];
                        }
                    }
                }

                // Se for uma edição e já tinha arquivo antigo, remover o antigo
                if ($id > 0) {
                    $oldRecord = $service->getById($id);
                    if ($oldRecord && !empty($oldRecord['anexo_caminho'])) {
                        $oldFile = __DIR__ . '/../' . $oldRecord['anexo_caminho'];
                        if (file_exists($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                }
            }

            if ($id > 0) {
                $service->update($id, $data);
                $message = 'Solicitação de segunda chamada atualizada com sucesso.';
            } else {
                $newId = $service->add($data);
                $message = 'Solicitação de segunda chamada cadastrada com sucesso.';

                // Envia e-mail para o professor da disciplina na turma
                try {
                    $alunoMeta = $service->getStudentMeta($data['aluno_id']);

                    if ($alunoMeta) {
                        $alunoNome = $alunoMeta['nome'];
                        $alunoCurso = $alunoMeta['curso'];
                        $alunoSerie = $alunoMeta['serie'];
                        $turmaId = $alunoMeta['turma_id'];

                        // Resolve o coordenador do curso da turma
                        $coordenador = $service->getCoordenadorByTurma($turmaId);
                        $fromName = "Vértice Acadêmico";
                        $fromEmail = "noreply@verticeacademico.com.br";
                        if ($coordenador) {
                            $fromName = $coordenador['name'];
                            $fromEmail = $coordenador['email'];
                        }

                        // Busca o(s) professor(es) vinculados a esta disciplina nesta turma
                        $professores = $service->getProfessoresByTurmaDisciplina($turmaId, $data['disciplina_codigo']);

                        if (!empty($professores)) {
                            $hoje = date('d/m/Y');
                            $dataAtividade = date('d/m/Y', strtotime($data['data_atividade_perdida']));
                            $subject = "=?UTF-8?B?" . base64_encode("Solicitação de Segunda Chamada - {$alunoNome}") . "?=";

                            foreach ($professores as $prof) {
                                $profNome = $prof['name'];
                                $profEmail = $prof['email'];

                                $emailBody = "
                                <html>
                                <head>
                                  <meta charset='UTF-8'>
                                  <title>Solicitação de Segunda Chamada</title>
                                </head>
                                <body style='font-family: Arial, sans-serif; color: #333333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                                  <div style='background: linear-gradient(135deg, #4f46e5, #3b82f6); color: #ffffff; padding: 20px; border-radius: 6px 6px 0 0; text-align: center;'>
                                    <h2 style='margin: 0; font-size: 20px;'>Vértice Acadêmico</h2>
                                    <p style='margin: 5px 0 0 0; font-size: 14px;'>Acompanhamento de Segunda Chamada</p>
                                  </div>
                                  
                                  <div style='padding: 20px;'>
                                    <p>Olá, <strong>{$profNome}</strong>,</p>
                                    
                                    <p>Gostaríamos de informar que uma nova solicitação de segunda chamada foi registrada no sistema:</p>
                                    
                                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                                      <tr style='background-color: #f8fafc;'>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Aluno:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoNome}</td>
                                      </tr>
                                      <tr>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Curso:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoCurso}</td>
                                      </tr>
                                      <tr style='background-color: #f8fafc;'>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Série:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoSerie}</td>
                                      </tr>
                                      <tr>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Data da Solicitação:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$hoje}</td>
                                      </tr>
                                      <tr style='background-color: #f8fafc;'>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Atividade Perdida:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'><strong>" . htmlspecialchars($data['atividade_nome']) . "</strong></td>
                                      </tr>
                                      <tr>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Data da Atividade:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$dataAtividade}</td>
                                      </tr>
                                      <tr style='background-color: #f8fafc; vertical-align: top;'>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Justificativa:</td>
                                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-style: italic;'>\"" . htmlspecialchars($data['justificativa']) . "\"</td>
                                      </tr>
                                    </table>
                                    
                                    <p style='background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; font-size: 14px;'>
                                      ⚠️ <strong>Status:</strong> A solicitação está em análise pelo colegiado do curso, você receberá o encaminhamento da solicitação em breve.
                                    </p>
                                  </div>
                                  
                                  <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                                    Mensagem automática enviada pelo sistema de Gestão Discente Vértice Acadêmico.
                                  </div>
                                </body>
                                </html>
                                ";

                                $headersArray = [
                                    'MIME-Version' => '1.0',
                                    'Content-type' => 'text/html; charset=UTF-8',
                                    'From' => "=?UTF-8?B?" . base64_encode($fromName) . "?= <" . (defined('SMTP_USER') ? SMTP_USER : $fromEmail) . ">",
                                    'Reply-To' => $fromEmail,
                                    'X-Mailer' => 'PHP/' . phpversion()
                                ];

                                $sent = false;
                                try {
                                    $sent = \App\Services\MailService::send($profEmail, $subject, $emailBody, $headersArray);
                                } catch (Exception $mailEx) {
                                    $sent = false;
                                }

                                if (!$sent) {
                                    $emailDir = __DIR__ . '/../assets/uploads/emails';
                                    if (!is_dir($emailDir)) {
                                        @mkdir($emailDir, 0777, true);
                                    }
                                    $safeNome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $alunoNome);
                                    $filename = "solicitacao_{$safeNome}_" . time() . "_" . uniqid() . ".html";
                                    @file_put_contents("{$emailDir}/{$filename}", $emailBody);
                                }
                            }
                        }
                    }
                } catch (Exception $mailEx) {
                    // Silently ignore mail errors to ensure registration completes successfully
                }
            }

            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'resend_email':
            hasDbPermission('segundachamada.index');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método de requisição inválido.');
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança expirado ou inválido (CSRF).');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');

            $data = $service->getById($id);
            if (!$data) {
                throw new Exception('Solicitação de segunda chamada não encontrada.');
            }

            if ($coordinatorUserId !== null && !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$data['aluno_id'])) {
                throw new Exception('Você não tem permissão para reenviar e-mails para esta solicitação.');
            }

            // Resolve o coordenador do curso da turma
            $alunoMeta = $service->getStudentMeta($data['aluno_id']);
            $fromName = "Vértice Acadêmico";
            $fromEmail = "noreply@verticeacademico.com.br";
            $turmaId = 0;

            if ($alunoMeta) {
                $turmaId = $alunoMeta['turma_id'];
                $coordenador = $service->getCoordenadorByTurma($turmaId);
                if ($coordenador) {
                    $fromName = $coordenador['name'];
                    $fromEmail = $coordenador['email'];
                }
            }

            // Busca os professores vinculados
            $professores = [];
            if ($turmaId > 0) {
                $professores = $service->getProfessoresByTurmaDisciplina($turmaId, $data['disciplina_codigo']);
            }

            if (empty($professores)) {
                throw new Exception('Nenhum professor vinculado a esta disciplina e turma com e-mail cadastrado.');
            }

            $alunoNome = $data['aluno_nome'];
            $alunoCurso = $alunoMeta['curso'] ?? 'Curso não identificado';
            $alunoSerie = $alunoMeta['serie'] ?? 'Série não identificada';
            $hoje = date('d/m/Y');
            $dataAtividade = date('d/m/Y', strtotime($data['data_atividade_perdida']));
            $subject = "=?UTF-8?B?" . base64_encode("Solicitação de Segunda Chamada - {$alunoNome}") . "?=";

            $sentCount = 0;
            $failedCount = 0;
            $savedOfflineFiles = [];

            foreach ($professores as $prof) {
                $profNome = $prof['name'];
                $profEmail = $prof['email'];

                $emailBody = "
                <html>
                <head>
                  <meta charset='UTF-8'>
                  <title>Solicitação de Segunda Chamada</title>
                </head>
                <body style='font-family: Arial, sans-serif; color: #333333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                  <div style='background: linear-gradient(135deg, #4f46e5, #3b82f6); color: #ffffff; padding: 20px; border-radius: 6px 6px 0 0; text-align: center;'>
                    <h2 style='margin: 0; font-size: 20px;'>Vértice Acadêmico</h2>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>Acompanhamento de Segunda Chamada</p>
                  </div>
                  
                  <div style='padding: 20px;'>
                    <p>Olá, <strong>{$profNome}</strong>,</p>
                    
                    <p>Gostaríamos de informar que uma solicitação de segunda chamada foi registrada no sistema (Notificação reenviada):</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                      <tr style='background-color: #f8fafc;'>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Aluno:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoNome}</td>
                      </tr>
                      <tr>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Curso:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoCurso}</td>
                      </tr>
                      <tr style='background-color: #f8fafc;'>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Série:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$alunoSerie}</td>
                      </tr>
                      <tr>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Data da Solicitação:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$hoje}</td>
                      </tr>
                      <tr style='background-color: #f8fafc;'>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Atividade Perdida:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'><strong>" . htmlspecialchars($data['atividade_nome']) . "</strong></td>
                      </tr>
                      <tr>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Data da Atividade:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0;'>{$dataAtividade}</td>
                      </tr>
                      <tr style='background-color: #f8fafc; vertical-align: top;'>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;'>Justificativa:</td>
                        <td style='padding: 10px; border: 1px solid #e2e8f0; font-style: italic;'>\"" . htmlspecialchars($data['justificativa']) . "\"</td>
                      </tr>
                    </table>
                    
                    <p style='background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; font-size: 14px;'>
                      ⚠️ <strong>Status:</strong> A solicitação está em análise pelo colegiado do curso, você receberá o encaminhamento da solicitação em breve.
                    </p>
                  </div>
                  
                  <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                    Mensagem automática enviada pelo sistema de Gestão Discente Vértice Acadêmico.
                  </div>
                </body>
                </html>
                ";

                $headersArray = [
                    'MIME-Version' => '1.0',
                    'Content-type' => 'text/html; charset=UTF-8',
                    'From' => "=?UTF-8?B?" . base64_encode($fromName) . "?= <" . (defined('SMTP_USER') ? SMTP_USER : $fromEmail) . ">",
                    'Reply-To' => $fromEmail,
                    'X-Mailer' => 'PHP/' . phpversion()
                ];

                $sent = false;
                try {
                    $sent = \App\Services\MailService::send($profEmail, $subject, $emailBody, $headersArray);
                } catch (Exception $mailEx) {
                    $sent = false;
                    $smtpErrors[] = $mailEx->getMessage();
                }

                if ($sent) {
                    $sentCount++;
                } else {
                    $failedCount++;
                    // Salva offline
                    $emailDir = __DIR__ . '/../assets/uploads/emails';
                    if (!is_dir($emailDir)) {
                        @mkdir($emailDir, 0777, true);
                    }
                    $safeNome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $alunoNome);
                    $filename = "reenvio_{$safeNome}_" . time() . "_" . uniqid() . ".html";
                    if (@file_put_contents("{$emailDir}/{$filename}", $emailBody)) {
                        $savedOfflineFiles[] = "assets/uploads/emails/{$filename}";
                    }
                }
            }

            if ($failedCount > 0) {
                $errorLogs = !empty($smtpErrors) ? " Diagnóstico: " . implode(" | ", array_unique($smtpErrors)) : "";
                if ($sentCount > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => "E-mail de notificação enviado para {$sentCount} professor(es). As outras tentativas falharam e foram salvas offline para pré-visualização em: " . implode(', ', $savedOfflineFiles) . "." . $errorLogs
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => "A tentativa de envio falhou. A notificação foi salva em arquivo offline para pré-visualização: " . implode(', ', $savedOfflineFiles) . "." . $errorLogs
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "E-mail de notificação reenviado com sucesso para {$sentCount} professor(es)!"
                ]);
            }
            break;

        case 'delete':
            hasDbPermission('segundachamada.manage');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método de requisição inválido.');
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança expirado ou inválido (CSRF).');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');

            if ($coordinatorUserId !== null) {
                $registro = $service->getById($id);
                if (!$registro || !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$registro['aluno_id'])) {
                    throw new Exception('Você não tem permissão para excluir esta solicitação.');
                }
            }

            if ($service->delete($id)) {
                echo json_encode(['success' => true, 'message' => 'Solicitação excluída com sucesso.']);
            } else {
                throw new Exception('Não foi possível excluir a solicitação.');
            }
            break;

        case 'progress':
            hasDbPermission('segundachamada.andamento');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método de requisição inválido.');
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança expirado ou inválido (CSRF).');
            }

            $id = (int)($_POST['id'] ?? 0);
            $encaminhamento = trim($_POST['encaminhamento'] ?? '');
            $justificativa = trim($_POST['justificativa'] ?? '');
            $notifyAluno = isset($_POST['notify_aluno']) && $_POST['notify_aluno'] == '1';
            $notifyProfessor = isset($_POST['notify_professor']) && $_POST['notify_professor'] == '1';
            $notifyCustom = isset($_POST['notify_custom']) && $_POST['notify_custom'] == '1';
            $customEmail = trim($_POST['custom_email'] ?? '');

            if (!$id) throw new Exception('ID inválido.');
            if (empty($encaminhamento)) throw new Exception('O encaminhamento é obrigatório.');
            if ($encaminhamento === 'Indeferido' && empty($justificativa)) {
                throw new Exception('A justificativa é obrigatória em caso de indeferimento.');
            }
            if ($notifyCustom && !empty($customEmail)) {
                if (!filter_var($customEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('O e-mail informado para outro endereço é inválido.');
                }
            }

            $registro = $service->getById($id);
            if (!$registro) {
                throw new Exception('Solicitação de segunda chamada não encontrada.');
            }

            // Segurança do Coordenador
            if ($coordinatorUserId !== null && !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$registro['aluno_id'])) {
                throw new Exception('Você não tem permissão para dar andamento nesta solicitação.');
            }

            // Atualiza status e encaminhamento no banco
            if (!$service->updateStatusAndReferral($id, $encaminhamento, $justificativa)) {
                throw new Exception('Erro ao atualizar o encaminhamento no banco de dados.');
            }

            // Salva/atualiza o email customizado no perfil do usuário logado
            $loggedUserId = $_SESSION['user_id'] ?? null;
            if ($loggedUserId) {
                // Atualiza com o valor enviado se marcado, ou limpa caso desmarcado
                $valToSave = ($notifyCustom && !empty($customEmail)) ? $customEmail : null;
                $db = getDB();
                $stmtUpdateUser = $db->prepare("UPDATE users SET segundachamada_custom_email = ? WHERE id = ?");
                $stmtUpdateUser->execute([$valToSave, $loggedUserId]);
            }

            // E-mail dispatch
            $sentCount = 0;
            $failedCount = 0;
            $savedOfflineFiles = [];
            $smtpErrors = [];

            if ($notifyAluno || $notifyProfessor || ($notifyCustom && !empty($customEmail))) {
                // Resolvendo dados para o e-mail
                $alunoNome = $registro['aluno_nome'] ?? '';
                $alunoMatricula = $registro['aluno_matricula'] ?? '';
                $disciplinaNome = $registro['disciplina_nome'] ?? '';
                $atividadeNome = $registro['atividade_nome'] ?? 'Segunda Chamada';
                $dataAtividade = date('d/m/Y', strtotime($registro['data_atividade_perdida']));

                // Busca metadados de curso/serie do aluno
                $alunoMeta = $service->getStudentMeta((int)$registro['aluno_id']);
                $alunoSerie = $alunoMeta['serie'] ?? '—';
                $alunoCurso = $alunoMeta['curso'] ?? '—';

                // Status Formatado
                $statusLabel = 'Pendente';
                if ($encaminhamento === 'Deferido Ad Referendum' || $encaminhamento === 'Deferido pelo Colegiado') {
                    $statusLabel = 'DEFERIDO';
                } elseif ($encaminhamento === 'Indeferido') {
                    $statusLabel = 'INDEFERIDO';
                }

                // Remetente Dinâmico (Coordenador)
                $fromName = "Vértice Acadêmico";
                $fromEmail = "noreply@verticeacademico.com.br";
                if ($alunoMeta && !empty($alunoMeta['turma_id'])) {
                    $coord = $service->getCoordenadorByTurma((int)$alunoMeta['turma_id']);
                    if ($coord) {
                        $fromName = $coord['name'];
                        $fromEmail = $coord['email'];
                    }
                }

                $headersArray = [
                    'MIME-Version' => '1.0',
                    'Content-type' => 'text/html; charset=UTF-8',
                    'From' => "=?UTF-8?B?" . base64_encode($fromName) . "?= <" . (defined('SMTP_USER') ? SMTP_USER : $fromEmail) . ">",
                    'Reply-To' => $fromEmail,
                    'X-Mailer' => 'PHP/' . phpversion()
                ];

                // Destinatários de Envio
                $targets = [];
                if ($notifyAluno && !empty($registro['email_aluno'])) {
                    $targets['aluno'] = [
                        'email' => $registro['email_aluno'],
                        'label' => $alunoNome,
                        'role' => 'Aluno'
                    ];
                }
                if ($notifyProfessor && $alunoMeta && !empty($alunoMeta['turma_id'])) {
                    $profs = $service->getProfessoresByTurmaDisciplina((int)$alunoMeta['turma_id'], $registro['disciplina_codigo']);
                    foreach ($profs as $p) {
                        $targets['prof_' . $p['email']] = [
                            'email' => $p['email'],
                            'label' => $p['name'],
                            'role' => 'Prof'
                        ];
                    }
                }
                if ($notifyCustom && !empty($customEmail)) {
                    $targets['custom'] = [
                        'email' => $customEmail,
                        'label' => 'Destinatário Adicional',
                        'role' => 'Outro'
                    ];
                }

                foreach ($targets as $key => $target) {
                    // Corpo do HTML do e-mail (Padronizado com o layout de cadastro)
                    $emailBody = '
                    <html>
                    <head>
                      <meta charset="UTF-8">
                      <title>Atualização de Segunda Chamada</title>
                    </head>
                    <body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
                      <div style="background: linear-gradient(135deg, #4f46e5, #3b82f6); color: #ffffff; padding: 20px; border-radius: 6px 6px 0 0; text-align: center;">
                        <h2 style="margin: 0; font-size: 20px;">Vértice Acadêmico</h2>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">Acompanhamento de Segunda Chamada</p>
                      </div>
                      
                      <div style="padding: 20px;">
                        <p>Olá, <strong>' . htmlspecialchars($target['label']) . '</strong>,</p>
                        
                        <p>Gostaríamos de informar que houve uma atualização no andamento da solicitação de segunda chamada:</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                          <tr style="background-color: #f8fafc;">
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold; width: 150px;">Aluno:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">' . htmlspecialchars($alunoNome) . ' (' . htmlspecialchars($alunoMatricula) . ')</td>
                          </tr>
                          <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Curso:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">' . htmlspecialchars($alunoCurso) . '</td>
                          </tr>
                          <tr style="background-color: #f8fafc;">
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Série:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">' . htmlspecialchars($alunoSerie) . '</td>
                          </tr>
                          <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Disciplina:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">' . htmlspecialchars($disciplinaNome) . '</td>
                          </tr>
                          <tr style="background-color: #f8fafc;">
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Atividade Perdida:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><strong>' . htmlspecialchars($atividadeNome) . '</strong></td>
                          </tr>
                          <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Data da Atividade:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">' . htmlspecialchars($dataAtividade) . '</td>
                          </tr>
                          <tr style="background-color: #f8fafc; vertical-align: top;">
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Justificativa Original:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-style: italic;">"' . htmlspecialchars($registro['justificativa']) . '"</td>
                          </tr>
                          <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Encaminhamento:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">' . htmlspecialchars($encaminhamento) . '</td>
                          </tr>' . (!empty($justificativa) ? '
                          <tr style="background-color: #f8fafc; vertical-align: top;">
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;">Parecer/Justificativa:</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-style: italic;">"' . htmlspecialchars($justificativa) . '"</td>
                          </tr>' : '') . '
                        </table>
                        
                        ' . ($statusLabel === 'DEFERIDO' ? '
                        <p style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 4px; font-size: 14px;">
                          ✅ <strong>Status:</strong> A solicitação foi <strong>DEFERIDA</strong> (' . htmlspecialchars($encaminhamento) . ').
                        </p>' : '
                        <p style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 4px; font-size: 14px;">
                          ❌ <strong>Status:</strong> A solicitação foi <strong>INDEFERIDA</strong>.
                        </p>') . '
                      </div>
                      
                      <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center; font-size: 12px; color: #64748b;">
                        Mensagem automática enviada pelo sistema de Gestão Discente Vértice Acadêmico.
                      </div>
                    </body>
                    </html>';

                    $sent = false;
                    try {
                        $sent = \App\Services\MailService::send($target['email'], "=?UTF-8?B?" . base64_encode("Atualização de Segunda Chamada — {$statusLabel}") . "?=", $emailBody, $headersArray);
                    } catch (Exception $mailEx) {
                        $sent = false;
                        $smtpErrors[] = $mailEx->getMessage();
                    }

                    if ($sent) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                        // Salva offline em caso de falha de conexão local
                        $emailDir = __DIR__ . '/../assets/uploads/emails';
                        if (!is_dir($emailDir)) {
                            @mkdir($emailDir, 0777, true);
                        }
                        $cleanLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $target['role'] . '_' . $target['label']);
                        $filename = "andamento_{$cleanLabel}_" . time() . "_" . uniqid() . ".html";
                        if (@file_put_contents("{$emailDir}/{$filename}", $emailBody)) {
                            $savedOfflineFiles[] = "assets/uploads/emails/{$filename}";
                        }
                    }
                }
            }

            // Mensagem de Resposta AJAX
            $msg = "Encaminhamento registrado com sucesso.";
            if ($sentCount > 0 || $failedCount > 0) {
                $msg .= " Notificações processadas: {$sentCount} enviada(s).";
                if ($failedCount > 0) {
                    $msg .= " {$failedCount} falha(s) salvas offline: " . implode(', ', $savedOfflineFiles);
                    if (!empty($smtpErrors)) {
                        $msg .= " Diagnóstico: " . implode(' | ', array_unique($smtpErrors));
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        case 'reopen':
            hasDbPermission('segundachamada.andamento');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método de requisição inválido.');
            }
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança expirado ou inválido (CSRF).');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');

            $registro = $service->getById($id);
            if (!$registro) {
                throw new Exception('Solicitação de segunda chamada não encontrada.');
            }

            // Segurança do Coordenador
            if ($coordinatorUserId !== null && !$service->isCoordinatorOfStudent($coordinatorUserId, (int)$registro['aluno_id'])) {
                throw new Exception('Você não tem permissão para alterar esta solicitação.');
            }

            if ($service->reopen($id)) {
                echo json_encode(['success' => true, 'message' => 'Solicitação reaberta com sucesso.']);
            } else {
                throw new Exception('Não foi possível reabrir a solicitação.');
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Ação '{$action}' não implementada ou desconhecida."]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
