<?php
/**
 * AdminController.php — Controlador de Administración
 * Dashboard admin, gestión de usuarios y predicciones
 */

class AdminController {
    private User $userModel;
    private Prediction $predModel;

    public function __construct() {
        $this->userModel = new User();
        $this->predModel = new Prediction();
    }

    /** Dashboard admin con estadísticas */
    public function dashboard() {
        requireAdmin();
        $data = [
            'total_users'       => $this->userModel->count(),
            'total_predictions' => $this->predModel->countAll(),
            'today_predictions' => $this->predModel->countToday(),
            'stats_class'       => $this->predModel->statsByClass(),
            'stats_model'       => $this->predModel->statsByModel(),
            'recent'            => $this->predModel->recent(8),
        ];
        require VIEWS_PATH . '/admin/dashboard.php';
    }

    /** Lista de usuarios */
    public function users() {
        requireAdmin();
        $data = ['users' => $this->userModel->findAll()];
        require VIEWS_PATH . '/admin/users.php';
    }

    /** Cambiar rol de usuario */
    public function changeRole() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/users');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/admin/users');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 2);

        if ($userId === currentUserId()) {
            setFlash('error', 'No puedes cambiar tu propio rol.'); redirect('/admin/users');
        }
        if (!in_array($roleId, [1, 2])) {
            setFlash('error', 'Rol inválido.'); redirect('/admin/users');
        }

        $this->userModel->changeRole($userId, $roleId);
        $this->userModel->logActivity(currentUserId(), 'ROLE_CHANGE', "User ID: $userId -> Role: $roleId");
        setFlash('success', 'Rol actualizado correctamente.');
        redirect('/admin/users');
    }

    /** Eliminar usuario */
    public function deleteUser() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/users');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/admin/users');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === currentUserId()) {
            setFlash('error', 'No puedes eliminarte a ti mismo.'); redirect('/admin/users');
        }

        $this->userModel->delete($userId);
        $this->userModel->logActivity(currentUserId(), 'USER_DELETE', "Deleted user ID: $userId");
        setFlash('success', 'Usuario eliminado.');
        redirect('/admin/users');
    }

    /** Historial global de predicciones */
    public function predictions() {
        requireAdmin();
        $data = ['predictions' => $this->predModel->findAll(200)];
        require VIEWS_PATH . '/admin/predictions.php';
    }

    /** Eliminar predicción */
    public function deletePrediction() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/predictions');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/admin/predictions');
        }

        $predId = (int)($_POST['prediction_id'] ?? 0);
        $pred = $this->predModel->findById($predId);

        if ($pred) {
            // Eliminar imagen
            $imgPath = ROOT_PATH . '/' . $pred['image_path'];
            if (file_exists($imgPath)) unlink($imgPath);
            $this->predModel->delete($predId);
            $this->userModel->logActivity(currentUserId(), 'PREDICTION_DELETE', "ID: $predId");
        }

        setFlash('success', 'Predicción eliminada.');
        redirect('/admin/predictions');
    }
}
