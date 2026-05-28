<?php
/**
 * web.php — Sistema de Rutas Simple
 * Mapea URLs a controladores y métodos
 */

// Definición de rutas: 'ruta' => [Controlador, Método, Roles permitidos]
$routes = [
    // ─── Públicas ────────────────────────────────────
    '/'              => ['AuthController', 'loginForm',    ['*']],
    '/auth/login'    => ['AuthController', 'loginForm',    ['*']],
    '/auth/login/submit'   => ['AuthController', 'login',  ['*']],
    '/auth/register' => ['AuthController', 'registerForm', ['*']],
    '/auth/register/submit' => ['AuthController', 'register', ['*']],
    '/auth/logout'   => ['AuthController', 'logout',       ['*']],

    // ─── Usuario ─────────────────────────────────────
    '/user/dashboard'   => ['UserController', 'dashboard',      ['ADMIN', 'USUARIO']],
    '/user/profile'     => ['UserController', 'profile',        ['ADMIN', 'USUARIO']],
    '/user/profile/update'    => ['UserController', 'updateProfile',   ['ADMIN', 'USUARIO']],
    '/user/password/change'   => ['UserController', 'changePassword',  ['ADMIN', 'USUARIO']],
    '/user/history'     => ['UserController', 'history',        ['ADMIN', 'USUARIO']],

    // ─── Predicción IA ───────────────────────────────
    '/prediction/form'    => ['PredictionController', 'form',    ['ADMIN', 'USUARIO']],
    '/prediction/predict' => ['PredictionController', 'predict', ['ADMIN', 'USUARIO']],

    // ─── Admin ───────────────────────────────────────
    '/admin/dashboard'   => ['AdminController', 'dashboard',       ['ADMIN']],
    '/admin/users'       => ['AdminController', 'users',           ['ADMIN']],
    '/admin/users/role'  => ['AdminController', 'changeRole',      ['ADMIN']],
    '/admin/users/delete'=> ['AdminController', 'deleteUser',      ['ADMIN']],
    '/admin/predictions' => ['AdminController', 'predictions',     ['ADMIN']],
    '/admin/predictions/delete' => ['AdminController', 'deletePrediction', ['ADMIN']],
];

/**
 * Resolver la ruta actual
 */
function resolveRoute(array $routes) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // Eliminar BASE_URL del inicio
    $base = BASE_URL;
    if (strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }

    // Limpiar query string y trailing slash
    $uri = strtok($uri, '?');
    $uri = rtrim($uri, '/') ?: '/';

    // Buscar ruta
    if (!isset($routes[$uri])) {
        http_response_code(404);
        require VIEWS_PATH . '/errors/404.php';
        exit;
    }

    [$controllerName, $method, $allowedRoles] = $routes[$uri];

    // Verificar permisos
    if (!in_array('*', $allowedRoles)) {
        if (!isLoggedIn()) {
            setFlash('error', 'Debes iniciar sesión.');
            redirect('/auth/login');
        }
        if (!in_array(currentUserRole(), $allowedRoles)) {
            setFlash('error', 'No tienes permisos para acceder.');
            redirect('/user/dashboard');
        }
    }

    // Instanciar controlador y ejecutar método
    $controller = new $controllerName();
    $controller->$method();
}
