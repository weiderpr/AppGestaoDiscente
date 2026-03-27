<?php
/**
 * Vértice Acadêmico — Controller de Cursos
 */

namespace App\Controllers;

use Core\Controller;
use App\Services\CourseService;

class CourseController extends Controller {
    private CourseService $courseService;

    public function __construct() {
        $this->courseService = new CourseService();
    }

    public function index(array $params): void {
        $institution = $this->getCurrentInstitution();
        $institutionId = $institution['id'] ?? null;

        if (!$institutionId) {
            $this->redirect('/select_institution.php?redirect=/courses');
        }

        $search = $this->get('search', '');
        $courses = $this->courseService->getAll($institutionId, $search);

        $this->render('courses/index', [
            'pageTitle' => 'Cursos',
            'courses' => $courses,
            'search' => $search
        ]);
    }

    public function show(array $params): void {
        $id = (int) ($params['id'] ?? 0);
        
        if (!$id) {
            $this->error('ID do curso não fornecido', 400);
        }

        $course = $this->courseService->findById($id);

        if (!$course) {
            $this->error('Curso não encontrado', 404);
        }

        $turmas = $this->courseService->getTurmas($id);
        $coordinators = $this->courseService->getCoordinators($id);

        $this->json([
            'course' => $course,
            'turmas' => $turmas,
            'coordinators' => $coordinators
        ]);
    }

    public function create(array $params): void {
        $institution = $this->getCurrentInstitution();
        $institutionId = $institution['id'] ?? null;

        if (!$institutionId) {
            $this->error('Nenhuma instituição selecionada');
        }

        $data = [
            'name' => $this->post('name'),
            'location' => $this->post('location'),
        ];

        if (!$data['name']) {
            $this->error('Preencha o nome do curso');
        }

        $result = $this->courseService->create($institutionId, $data);

        if (isset($result['error'])) {
            $this->error($result['error']);
        }

        $this->success('Curso criado com sucesso', ['id' => $result['id']]);
    }

    public function update(array $params): void {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->error('ID do curso não fornecido', 400);
        }

        $data = [
            'name' => $this->post('name'),
            'location' => $this->post('location'),
            'is_active' => $this->post('is_active'),
        ];

        $result = $this->courseService->update($id, $data);

        if (isset($result['error'])) {
            $this->error($result['error']);
        }

        $this->success('Curso atualizado com sucesso');
    }

    public function delete(array $params): void {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->error('ID do curso não fornecido', 400);
        }

        $this->courseService->delete($id);
        $this->success('Curso excluído com sucesso');
    }
}
