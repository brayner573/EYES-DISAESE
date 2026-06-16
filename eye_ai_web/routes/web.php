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
    '/prediction/view/{id}' => ['PredictionController', 'view',  ['ADMIN', 'USUARIO']],

    // ─── API Móvil ───────────────────────────────────
    '/api/auth/login'              => ['ApiController', 'login',          ['*']],
    '/api/auth/register'           => ['ApiController', 'register',       ['*']],
    '/api/prediction/run'          => ['ApiController', 'predict',        ['ADMIN', 'USUARIO']],
    '/api/prediction/history'      => ['ApiController', 'history',        ['ADMIN', 'USUARIO']],
    '/api/prediction/view/{id}'    => ['ApiController', 'viewPrediction', ['ADMIN', 'USUARIO']],

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

    // Buscar ruta con soporte para parámetros tipo {id}
    $matchedRoute = null;
    $params = [];

    foreach ($routes as $routePattern => $handler) {
        // Convertir /prediction/view/{id} a un patrón regex
        $regex = '#^' . preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_]+)', $routePattern) . '$#';
        if (preg_match($regex, $uri, $matches)) {
            $matchedRoute = $handler;
            array_shift($matches);
            $params = $matches;
            break;
        }
    }

    if (!$matchedRoute) {
        http_response_code(404);
        if (strpos($uri, '/api/') === 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Endpoint no encontrado.']);
            exit;
        }
        require VIEWS_PATH . '/errors/404.php';
        exit;
    }

    [$controllerName, $method, $allowedRoles] = $matchedRoute;

    // Verificar permisos
    if (!in_array('*', $allowedRoles)) {
        if (!isLoggedIn()) {
            if (strpos($uri, '/api/') === 0) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'No autorizado. Por favor inicie sesión.']);
                exit;
            }
            setFlash('error', 'Debes iniciar sesión.');
            redirect('/auth/login');
        }
        if (!in_array(currentUserRole(), $allowedRoles)) {
            if (strpos($uri, '/api/') === 0) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para acceder a este recurso.']);
                exit;
            }
            setFlash('error', 'No tienes permisos para acceder.');
            redirect('/user/dashboard');
        }
    }

    // Instanciar controlador y ejecutar método con parámetros
    $controller = new $controllerName();
    $controller->$method(...$params);
}
