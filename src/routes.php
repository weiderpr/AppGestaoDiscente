<?php
/**
 * Vértice Acadêmico — Definição de Rotas
 */

use Core\Router;
use App\Controllers\UserController;
use App\Controllers\CourseController;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$router = new Router();

// Middleware de autenticação padrão para quase todas as rotas
$auth = new AuthMiddleware();

// Usuários: Somente Admin
$router->get('/admin/users', [UserController::class, 'index'], 'users.index')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->get('/admin/users/{id}', [UserController::class, 'show'], 'users.show')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->post('/admin/users', [UserController::class, 'create'], 'users.create')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->put('/admin/users/{id}', [UserController::class, 'update'], 'users.update')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->delete('/admin/users/{id}', [UserController::class, 'delete'], 'users.delete')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

// Cursos: Acesso variado
$router->get('/courses', [CourseController::class, 'index'], 'courses.index')
       ->middleware($auth); // Autenticação já garante acesso básico

$router->get('/courses/{id}', [CourseController::class, 'show'], 'courses.show')
       ->middleware($auth);

$router->post('/courses', [CourseController::class, 'create'], 'courses.create')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->put('/courses/{id}', [CourseController::class, 'update'], 'courses.update')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

$router->delete('/courses/{id}', [CourseController::class, 'delete'], 'courses.delete')
       ->middleware($auth)
       ->middleware(new RoleMiddleware());

return $router;
