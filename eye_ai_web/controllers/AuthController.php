<?php
/**
 * AuthController.php — Controlador de Autenticación
 * Login, Registro y Logout con seguridad profesional
 */

class AuthController {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    /** Mostrar formulario de login */
    public function loginForm() {
        if (isLoggedIn()) {
            redirect(isAdmin() ? '/admin/dashboard' : '/user/dashboard');
        }
        require VIEWS_PATH . '/auth/login.php';
    }

    /** Procesar login */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/auth/login');
        }

        // Verificar CSRF
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token de seguridad inválido.');
            redirect('/auth/login');
        }

        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones
        if (empty($email) || empty($password)) {
            setFlash('error', 'Completa todos los campos.');
            redirect('/auth/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Email inválido.');
            redirect('/auth/login');
        }

        // Buscar usuario
        $user = $this->userModel->findByEmail($email);

        if (!$user || !$this->userModel->verifyPassword($password, $user['password_hash'])) {
            $this->userModel->logActivity(null, 'LOGIN_FAILED', "Email: $email");
            setFlash('error', 'Credenciales incorrectas.');
            redirect('/auth/login');
        }

        // Crear sesión
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['_last_regeneration'] = time();

        $this->userModel->logActivity($user['id'], 'LOGIN_SUCCESS');
        setFlash('success', '¡Bienvenido, ' . sanitize($user['name']) . '!');

        redirect($user['role'] === 'ADMIN' ? '/admin/dashboard' : '/user/dashboard');
    }

    /** Mostrar formulario de registro */
    public function registerForm() {
        if (isLoggedIn()) {
            redirect('/user/dashboard');
        }
        require VIEWS_PATH . '/auth/register.php';
    }

    /** Procesar registro */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/auth/register');
        }

        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token de seguridad inválido.');
            redirect('/auth/register');
        }

        $name     = sanitize($_POST['name'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        // Validaciones
        $errors = [];
        if (strlen($name) < 2)                    $errors[] = 'El nombre debe tener al menos 2 caracteres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($password) < 6)                 $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        if ($password !== $confirm)                $errors[] = 'Las contraseñas no coinciden.';
        if ($this->userModel->emailExists($email)) $errors[] = 'Este email ya está registrado.';

        if (!empty($errors)) {
            setFlash('error', implode('<br>', $errors));
            redirect('/auth/register');
        }

        // Registrar
        if ($this->userModel->register($name, $email, $password)) {
            $this->userModel->logActivity(null, 'REGISTER', "Email: $email");
            setFlash('success', '¡Registro exitoso! Inicia sesión.');
            redirect('/auth/login');
        } else {
            setFlash('error', 'Error al registrar. Intenta de nuevo.');
            redirect('/auth/register');
        }
    }

    /** Cerrar sesión */
    public function logout() {
        if (isLoggedIn()) {
            $this->userModel->logActivity(currentUserId(), 'LOGOUT');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        setFlash('success', 'Sesión cerrada correctamente.');
        redirect('/auth/login');
    }
}
