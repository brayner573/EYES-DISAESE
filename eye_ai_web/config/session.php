<?php
/**
 * session.php — Manejo Seguro de Sesiones
 * Funciones helper para autenticación y control de acceso
 */

// Asegurar existencia de getallheaders() para entornos PHP-CGI/FPM
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Iniciar sesión con configuración segura
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Permitir autenticación de API vía cabecera Authorization: Bearer <session_id>
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $isApi = false;

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $sessionId = $matches[1];
            session_id($sessionId);
            $isApi = true;
        }

        if (!$isApi) {
            ini_set('session.use_only_cookies', 1);
        }
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', 3600); // 1 hora

        session_name('EYE_AI_SESSION');
        session_start();

        // Regenerar ID cada 30 min para evitar session fixation (solo para web normal)
        if (!$isApi) {
            if (!isset($_SESSION['_last_regeneration'])) {
                $_SESSION['_last_regeneration'] = time();
            } elseif (time() - $_SESSION['_last_regeneration'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['_last_regeneration'] = time();
            }
        }
    }
}

// ─── Funciones de Autenticación ──────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUserName(): string {
    return $_SESSION['user_name'] ?? 'Invitado';
}

function currentUserEmail(): string {
    return $_SESSION['user_email'] ?? '';
}

function currentUserRole(): string {
    return $_SESSION['role'] ?? 'USUARIO';
}

// ─── Control de Acceso ───────────────────────────────────────

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Debes iniciar sesión para acceder.');
        redirect('/auth/login');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'No tienes permisos de administrador.');
        redirect('/user/dashboard');
    }
}

// ─── Mensajes Flash ──────────────────────────────────────────

function setFlash(string $type, string $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Utilidades ──────────────────────────────────────────────

function redirect(string $path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
