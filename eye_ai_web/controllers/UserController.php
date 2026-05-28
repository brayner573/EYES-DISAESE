<?php
/**
 * UserController.php — Controlador de Usuario
 * Dashboard, perfil y funciones de usuario normal
 */

class UserController {
    private User $userModel;
    private Prediction $predModel;

    public function __construct() {
        $this->userModel = new User();
        $this->predModel = new Prediction();
    }

    /** Dashboard del usuario */
    public function dashboard() {
        requireLogin();
        $userId = currentUserId();
        $data = [
            'total_predictions' => $this->predModel->countByUser($userId),
            'recent'            => $this->predModel->findByUser($userId, 5),
            'user'              => $this->userModel->findById($userId),
        ];
        require VIEWS_PATH . '/user/dashboard.php';
    }

    /** Ver perfil */
    public function profile() {
        requireLogin();
        $data = ['user' => $this->userModel->findById(currentUserId())];
        require VIEWS_PATH . '/user/profile.php';
    }

    /** Actualizar perfil */
    public function updateProfile() {
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/user/profile');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/user/profile');
        }

        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $userId = currentUserId();

        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Datos inválidos.'); redirect('/user/profile');
        }

        // Verificar email único (excluyendo el propio)
        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $userId) {
            setFlash('error', 'Este email ya está en uso.'); redirect('/user/profile');
        }

        $this->userModel->updateProfile($userId, $name, $email);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $this->userModel->logActivity($userId, 'PROFILE_UPDATE');
        setFlash('success', 'Perfil actualizado correctamente.');
        redirect('/user/profile');
    }

    /** Cambiar contraseña */
    public function changePassword() {
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/user/profile');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/user/profile');
        }

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $userId  = currentUserId();

        $user = $this->userModel->findById($userId);

        if (!password_verify($current, $user['password_hash'])) {
            setFlash('error', 'La contraseña actual es incorrecta.'); redirect('/user/profile');
        }
        if (strlen($new) < 6) {
            setFlash('error', 'La nueva contraseña debe tener al menos 6 caracteres.'); redirect('/user/profile');
        }
        if ($new !== $confirm) {
            setFlash('error', 'Las contraseñas no coinciden.'); redirect('/user/profile');
        }

        $this->userModel->changePassword($userId, $new);
        $this->userModel->logActivity($userId, 'PASSWORD_CHANGE');
        setFlash('success', 'Contraseña cambiada correctamente.');
        redirect('/user/profile');
    }

    /** Historial de predicciones */
    public function history() {
        requireLogin();
        $data = [
            'predictions' => $this->predModel->findByUser(currentUserId(), 100),
        ];
        require VIEWS_PATH . '/user/history.php';
    }
}
