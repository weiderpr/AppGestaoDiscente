<?php
/**
 * Vértice Acadêmico — Controller de Alunos
 */

namespace App\Controllers;

use Core\Controller;
use App\Services\AlunoService;

class AlunoController extends Controller {
    private AlunoService $alunoService;

    public function __construct() {
        $this->alunoService = new AlunoService();
    }

    /**
     * Exibe o histórico multidisciplinar do aluno
     */
    public function historico(array $params): void {
        $this->requireLogin();
        $this->checkPermission('students.history');
        $alunoId = (int) ($params['id'] ?? 0);
        if (!$alunoId) {
            $this->redirect('/dashboard.php');
        }

        $aluno = $this->alunoService->findById($alunoId);
        if (!$aluno) {
            $this->error('Aluno não encontrado', 404);
        }

        // Buscar turmas para exibir no header
        $turmas = $this->alunoService->getTurmas($alunoId);
        $turmaAtual = !empty($turmas) ? $turmas[0] : null;

        // Buscar histórico agregado
        $history = $this->alunoService->getMultidisciplinaryHistory($alunoId);

        $this->render('alunos/historico', [
            'pageTitle' => 'Histórico Multidisciplinar',
            'aluno' => $aluno,
            'turma' => $turmaAtual,
            'history' => $history,
            'isAjax' => $this->isAjax()
        ]);
    }
}
