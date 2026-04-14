<?php
/**
 * Vértice Acadêmico — Definição de Rotas
 */

use Core\Router;
use App\Controllers\UserController;
use App\Controllers\CourseController;
use App\Controllers\AlunoController;

use App\Middleware\InstitutionMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\CsrfMiddleware;

$router = new Router();
$router->globalMiddleware(new CsrfMiddleware());

// Middlewares padrão
$auth = new AuthMiddleware();
$inst = new InstitutionMiddleware();

// Dashboard e Configurações (Acesso base)
$router->get('/dashboard', function() { require __DIR__ . '/../dashboard.php'; }, 'dashboard')
       ->middleware($auth)
       ->middleware($inst);

$router->get('/settings', function() { require __DIR__ . '/../settings.php'; }, 'settings')
       ->middleware($auth)
       ->middleware($inst);

// Usuários: Somente Admin
$router->get('/admin/users', [UserController::class, 'index'], 'users.index')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());


$router->get('/admin/users/{id}', [UserController::class, 'show'], 'users.show')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

$router->post('/admin/users', [UserController::class, 'create'], 'users.create')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

$router->put('/admin/users/{id}', [UserController::class, 'update'], 'users.update')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

$router->delete('/admin/users/{id}', [UserController::class, 'delete'], 'users.delete')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

// Cursos: Acesso variado
$router->get('/courses', [CourseController::class, 'index'], 'courses.index')
       ->middleware($auth)
       ->middleware($inst);

$router->get('/courses/{id}', [CourseController::class, 'show'], 'courses.show')
       ->middleware($auth)
       ->middleware($inst);

$router->post('/courses', [CourseController::class, 'create'], 'courses.create')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

$router->put('/courses/{id}', [CourseController::class, 'update'], 'courses.update')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

$router->delete('/courses/{id}', [CourseController::class, 'delete'], 'courses.delete')
       ->middleware($auth)
       ->middleware($inst)
       ->middleware(new RoleMiddleware());

// Alunos: Histórico Multidisciplinar
$router->get('/aluno/historico/{id}', [AlunoController::class, 'historico'], 'aluno.historico')
       ->middleware($auth)
       ->middleware($inst);

return $router;
