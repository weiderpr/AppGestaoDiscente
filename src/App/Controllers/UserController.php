<?php
/**
 * Vértice Acadêmico — Controller de Usuários
 */

namespace App\Controllers;

use Core\Controller;
use App\Services\UserService;

class UserController extends Controller {
    private UserService $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function index(array $params): void {
        $institution = $this->getCurrentInstitution();
        $institutionId = $institution['id'] ?? null;

        $search = $this->get('search', '');
        $page = (int) $this->get('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $users = $this->userService->getAll($institutionId, $search, $limit, $offset);
        $total = $this->userService->count($institutionId);

        $this->render('users/index', [
            'pageTitle' => 'Gerenciar Usuários',
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
            'search' => $search
        ]);
    }

    public function show(array $params): void {
        $id = (int) ($params['id'] ?? 0);
        
        if (!$id) {
            $this->error('ID do usuário não fornecido', 400);
        }

        $user = $this->userService->findById($id);

        if (!$user) {
            $this->error('Usuário não encontrado', 404);
        }

        $this->json($user);
    }

    public function create(array $params): void {
        $data = [
            'name' => $this->post('name'),
            'email' => $this->post('email'),
            'password' => $this->post('password'),
            'phone' => $this->post('phone'),
            'profile' => $this->post('profile'),
        ];

        if (!$data['name'] || !$data['email'] || !$data['password']) {
            $this->error('Preencha todos os campos obrigatórios');
        }

        $result = $this->userService->create($data);

        if (isset($result['error'])) {
            $this->error($result['error']);
        }

        $this->success('Usuário criado com sucesso', ['id' => $result['id']]);
    }

    public function update(array $params): void {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->error('ID do usuário não fornecido', 400);
        }

        $data = [
            'name' => $this->post('name'),
            'phone' => $this->post('phone'),
            'profile' => $this->post('profile'),
            'is_active' => $this->post('is_active'),
        ];

        $result = $this->userService->update($id, $data);

        if (isset($result['error'])) {
            $this->error($result['error']);
        }

        $this->success('Usuário atualizado com sucesso');
    }

    public function delete(array $params): void {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->error('ID do usuário não fornecido', 400);
        }

        $this->userService->delete($id);
        $this->success('Usuário excluído com sucesso');
    }
}
