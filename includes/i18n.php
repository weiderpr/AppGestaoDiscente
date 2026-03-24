<?php
/**
 * Vértice Acadêmico — Internacionalização (i18n)
 */

class I18n {
    private static string $locale = 'pt-BR';
    private static array $translations = [];
    private static array $supportedLocales = ['pt-BR', 'en-US'];

    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Priority: session > browser > default
        if (!empty($_SESSION['locale'])) {
            self::$locale = $_SESSION['locale'];
        } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = self::parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if (in_array($browserLang, self::$supportedLocales)) {
                self::$locale = $browserLang;
            }
        }

        self::loadTranslations();
    }

    public static function setLocale(string $locale): void {
        if (in_array($locale, self::$supportedLocales)) {
            self::$locale = $locale;
            $_SESSION['locale'] = $locale;
            self::loadTranslations();
        }
    }

    public static function getLocale(): string {
        return self::$locale;
    }

    public static function getSupportedLocales(): array {
        return self::$supportedLocales;
    }

    public static function t(string $key, array $params = []): string {
        $locale = self::$locale;
        $keys = explode('.', $key);
        
        $value = self::$translations[$locale][$keys[0]][$keys[1]] ?? $key;
        
        // Replace params: {{name}} => value
        foreach ($params as $param => $val) {
            $value = str_replace('{{' . $param . '}}', $val, $value);
        }
        
        return $value;
    }

    private static function parseAcceptLanguage(string $header): string {
        preg_match('/^([a-z]{2})/', strtolower($header), $matches);
        $lang = $matches[1] ?? 'pt';
        
        if ($lang === 'pt') return 'pt-BR';
        if ($lang === 'en') return 'en-US';
        
        return 'pt-BR';
    }

    private static function loadTranslations(): void {
        self::$translations = [
            'pt-BR' => [
                'common' => [
                    'save' => 'Salvar',
                    'cancel' => 'Cancelar',
                    'delete' => 'Excluir',
                    'edit' => 'Editar',
                    'create' => 'Criar',
                    'search' => 'Buscar',
                    'filter' => 'Filtrar',
                    'loading' => 'Carregando...',
                    'success' => 'Sucesso',
                    'error' => 'Erro',
                    'warning' => 'Atenção',
                    'confirm' => 'Confirmar',
                    'yes' => 'Sim',
                    'no' => 'Não',
                    'close' => 'Fechar',
                    'back' => 'Voltar',
                    'next' => 'Avançar',
                    'actions' => 'Ações',
                    'status' => 'Status',
                    'active' => 'Ativo',
                    'inactive' => 'Inativo',
                    'name' => 'Nome',
                    'email' => 'E-mail',
                    'phone' => 'Telefone',
                    'profile' => 'Perfil',
                    'created_at' => 'Criado em',
                    'updated_at' => 'Atualizado em',
                    'no_results' => 'Nenhum resultado encontrado',
                    'required' => 'Obrigatório',
                ],
                'auth' => [
                    'login' => 'Entrar',
                    'logout' => 'Sair',
                    'register' => 'Cadastrar',
                    'password' => 'Senha',
                    'confirm_password' => 'Confirmar Senha',
                    'forgot_password' => 'Esqueceu a senha?',
                    'remember_me' => 'Lembrar-me',
                    'login_success' => 'Login realizado com sucesso',
                    'login_error' => 'E-mail ou senha incorretos',
                ],
                'users' => [
                    'users' => 'Usuários',
                    'new_user' => 'Novo Usuário',
                    'edit_user' => 'Editar Usuário',
                    'delete_user' => 'Excluir Usuário',
                    'user_created' => 'Usuário criado com sucesso',
                    'user_updated' => 'Usuário atualizado com sucesso',
                    'user_deleted' => 'Usuário excluído com sucesso',
                ],
                'courses' => [
                    'courses' => 'Cursos',
                    'new_course' => 'Novo Curso',
                    'edit_course' => 'Editar Curso',
                    'turmas' => 'Turmas',
                    'disciplinas' => 'Disciplinas',
                ],
                'messages' => [
                    'confirm_delete' => 'Tem certeza que deseja excluir?',
                    'confirm_action' => 'Tem certeza que deseja continuar?',
                    'operation_success' => 'Operação realizada com sucesso',
                    'operation_error' => 'Erro ao realizar operação',
                    'token_expired' => 'Token de segurança expirado',
                ],
            ],
            'en-US' => [
                'common' => [
                    'save' => 'Save',
                    'cancel' => 'Cancel',
                    'delete' => 'Delete',
                    'edit' => 'Edit',
                    'create' => 'Create',
                    'search' => 'Search',
                    'filter' => 'Filter',
                    'loading' => 'Loading...',
                    'success' => 'Success',
                    'error' => 'Error',
                    'warning' => 'Warning',
                    'confirm' => 'Confirm',
                    'yes' => 'Yes',
                    'no' => 'No',
                    'close' => 'Close',
                    'back' => 'Back',
                    'next' => 'Next',
                    'actions' => 'Actions',
                    'status' => 'Status',
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'name' => 'Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                    'profile' => 'Profile',
                    'created_at' => 'Created at',
                    'updated_at' => 'Updated at',
                    'no_results' => 'No results found',
                    'required' => 'Required',
                ],
                'auth' => [
                    'login' => 'Login',
                    'logout' => 'Logout',
                    'register' => 'Register',
                    'password' => 'Password',
                    'confirm_password' => 'Confirm Password',
                    'forgot_password' => 'Forgot password?',
                    'remember_me' => 'Remember me',
                    'login_success' => 'Login successful',
                    'login_error' => 'Invalid email or password',
                ],
                'users' => [
                    'users' => 'Users',
                    'new_user' => 'New User',
                    'edit_user' => 'Edit User',
                    'delete_user' => 'Delete User',
                    'user_created' => 'User created successfully',
                    'user_updated' => 'User updated successfully',
                    'user_deleted' => 'User deleted successfully',
                ],
                'courses' => [
                    'courses' => 'Courses',
                    'new_course' => 'New Course',
                    'edit_course' => 'Edit Course',
                    'turmas' => 'Classes',
                    'disciplinas' => 'Subjects',
                ],
                'messages' => [
                    'confirm_delete' => 'Are you sure you want to delete?',
                    'confirm_action' => 'Are you sure you want to continue?',
                    'operation_success' => 'Operation completed successfully',
                    'operation_error' => 'Error performing operation',
                    'token_expired' => 'Security token expired',
                ],
            ],
        ];
    }
}

// Funções helper
function __(string $key, array $params = []): string {
    return I18n::t($key, $params);
}

function locale(): string {
    return I18n::getLocale();
}

function set_locale(string $locale): void {
    I18n::setLocale($locale);
}
