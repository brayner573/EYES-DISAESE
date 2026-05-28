<?php
/**
 * User.php — Modelo de Usuarios
 * CRUD completo con prepared statements
 */

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Registrar nuevo usuario */
    public function register(string $name, string $email, string $password): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, role_id) VALUES (:name, :email, :hash, 2)"
        );
        return $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => $hash,
        ]);
    }

    /** Buscar usuario por email */
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name AS role FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.email = :email AND u.is_active = 1 LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Buscar usuario por ID */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name AS role FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Verificar contraseña */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /** Verificar si email ya existe */
    public function emailExists(string $email): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    /** Obtener todos los usuarios (admin) */
    public function findAll(): array {
        $stmt = $this->db->query(
            "SELECT u.*, r.name AS role,
                    (SELECT COUNT(*) FROM predictions p WHERE p.user_id = u.id) AS total_predictions
             FROM users u JOIN roles r ON u.role_id = r.id
             ORDER BY u.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /** Actualizar perfil */
    public function updateProfile(int $id, string $name, string $email): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET name = :name, email = :email WHERE id = :id"
        );
        return $stmt->execute([':name' => $name, ':email' => $email, ':id' => $id]);
    }

    /** Cambiar contraseña */
    public function changePassword(int $id, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        return $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    /** Cambiar rol (admin) */
    public function changeRole(int $userId, int $roleId): bool {
        $stmt = $this->db->prepare("UPDATE users SET role_id = :role WHERE id = :id");
        return $stmt->execute([':role' => $roleId, ':id' => $userId]);
    }

    /** Eliminar usuario */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Contar usuarios */
    public function count(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /** Registrar actividad */
    public function logActivity(?int $userId, string $action, ?string $details = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
             VALUES (:uid, :action, :details, :ip, :ua)"
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        ]);
    }
}
