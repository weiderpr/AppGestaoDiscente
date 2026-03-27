# Proposta: Sistema de Permissões RBAC via Middleware — Vértice Acadêmico

Este documento detalha como centralizar o controle de acesso no sistema, eliminando as verificações manuais nos Controllers e trazendo mais segurança e manutenibilidade.

## 1. O Conceito de Middleware

O **Middleware** atua como uma "camada de filtragem" entre o Router (quem recebe a URL) e o Controller (quem executa a lógica). Em vez de o Controller perguntar "quem é esse usuário?", a rota já define: "para chegar aqui, precisa passar por estes filtros".

### Interface Base (`src/Core/Middleware.php`)
Uma interface simples que define o contrato para todos os filtros do sistema.

```php
namespace Core;

interface Middleware {
    /**
     * @param array $params Parâmetros da rota
     * @param callable $next O próximo passo na cadeia (ou o handler final)
     */
    public function handle(array $params, callable $next): void;
}
```

---

## 2. Exemplos de Filtros (Fácil Reutilização)

### `AuthMiddleware` (Garante login)
Verifica se o usuário está logado antes de qualquer outra ação.

```php
namespace App\Middleware;

use Core\Middleware;

class AuthMiddleware implements Middleware {
    public function handle(array $params, callable $next): void {
        require_once __DIR__ . '/../../includes/auth.php';
        
        if (!isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
        
        $next($params); // Prossegue para o próximo filtro ou controller
    }
}
```

### `RoleMiddleware` (Garante perfil/permissão — RBAC)
Verifica o perfil do usuário contra uma lista de perfis permitidos.

```php
namespace App\Middleware;

class RoleMiddleware implements Middleware {
    private array $allowedProfiles;

    public function __construct(array $allowedProfiles) {
        $this->allowedProfiles = $allowedProfiles;
    }

    public function handle(array $params, callable $next): void {
        require_once __DIR__ . '/../../includes/auth.php';
        $user = getCurrentUser(); 
        
        if (!$user || !in_array($user['profile'], $this->allowedProfiles)) {
            // Se for requisição AJAX, retorna JSON 403
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Acesso negado: Perfil insuficiente.']);
                exit;
            }
            
            // Caso contrário, redireciona ou mostra erro HTML
            header('Location: /dashboard.php?error=access_denied');
            exit;
        }
        
        $next($params);
    }
}
```

---

## 3. Integrando com o Arquivo de Rotas (`src/routes.php`)

Com essa mudança, o arquivo `routes.php` se torna a **única fonte da verdade** para segurança:

```php
// Somente administradores podem gerenciar usuários
$router->post('/admin/users', [UserController::class, 'create'])
       ->middleware(new AuthMiddleware())
       ->middleware(new RoleMiddleware(['Administrador']));

// Professores, Coordenadores e Pedagogos podem ver cursos
$router->get('/courses', [CourseController::class, 'index'])
       ->middleware(new AuthMiddleware())
       ->middleware(new RoleMiddleware(['Administrador', 'Coordenador', 'Professor', 'Pedagogo']));
```

---

## 4. Como o Router Executa Isso (`src/Core/Router.php`)

O método `dispatch` do Router passaria a ser responsável por "empilhar" esses middlewares e executá-los em ordem. 

Se o `AuthMiddleware` falhar, ele interrompe tudo. Se passar, o `RoleMiddleware` é executado. Se passar, o `Controller` finalmente entra em cena.

---

## 5. Benefícios Imediatos

1.  **Segurança Visível:** Você não precisa abrir o `CourseController.php` para saber quem pode deletar um curso; basta olhar o `routes.php`.
2.  **Facilidade de Auditoria:** Se surgir um novo perfil (ex: "Supervisor"), basta adicioná-lo ao array no arquivo de rotas.
3.  **Código Limpo:** Os Controllers ficam focados apenas em lógica de negócio (salvar, listar, deletar), sem código de segurança repetitivo.
4.  **Extensibilidade:** No futuro, você pode adicionar um `CsrfMiddleware` ou `RateLimitMiddleware` da mesma forma.
