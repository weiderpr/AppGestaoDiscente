<?php
/**
 * Vértice Acadêmico — Definição de Rotas
 */

use Core\Router;
use App\Controllers\UserController;
use App\Controllers\CourseController;

$router = new Router();

$router->get('/admin/users', [UserController::class, 'index'], 'users.index');
$router->get('/admin/users/{id}', [UserController::class, 'show'], 'users.show');
$router->post('/admin/users', [UserController::class, 'create'], 'users.create');
$router->put('/admin/users/{id}', [UserController::class, 'update'], 'users.update');
$router->delete('/admin/users/{id}', [UserController::class, 'delete'], 'users.delete');

$router->get('/courses', [CourseController::class, 'index'], 'courses.index');
$router->get('/courses/{id}', [CourseController::class, 'show'], 'courses.show');
$router->post('/courses', [CourseController::class, 'create'], 'courses.create');
$router->put('/courses/{id}', [CourseController::class, 'update'], 'courses.update');
$router->delete('/courses/{id}', [CourseController::class, 'delete'], 'courses.delete');

return $router;
