<?php
/**
 * Prediction.php — Modelo de Predicciones IA
 * CRUD para resultados de análisis de imágenes oculares
 */

class Prediction {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Crear nueva predicción */
    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO predictions
             (user_id, image_path, image_original, predicted_class, confidence, model_used, all_predictions, processing_time)
             VALUES (:uid, :path, :original, :class, :conf, :model, :all_pred, :time)"
        );
        $stmt->execute([
            ':uid'      => $data['user_id'],
            ':path'     => $data['image_path'],
            ':original' => $data['image_original'],
            ':class'    => $data['predicted_class'],
            ':conf'     => $data['confidence'],
            ':model'    => $data['model_used'],
            ':all_pred' => $data['all_predictions'] ?? null,
            ':time'     => $data['processing_time'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Obtener predicción por ID */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.name AS user_name, u.email AS user_email
             FROM predictions p JOIN users u ON p.user_id = u.id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $pred = $stmt->fetch();
        return $pred ?: null;
    }

    /** Predicciones de un usuario */
    public function findByUser(int $userId, int $limit = 50): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM predictions WHERE user_id = :uid
             ORDER BY created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Todas las predicciones (admin) */
    public function findAll(int $limit = 100): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.name AS user_name, u.email AS user_email
             FROM predictions p JOIN users u ON p.user_id = u.id
             ORDER BY p.created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Eliminar predicción */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM predictions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Contar predicciones totales */
    public function countAll(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
    }

    /** Contar predicciones de un usuario */
    public function countByUser(int $userId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM predictions WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Estadísticas por clase */
    public function statsByClass(): array {
        return $this->db->query(
            "SELECT predicted_class, COUNT(*) AS total,
                    ROUND(AVG(confidence), 2) AS avg_confidence
             FROM predictions GROUP BY predicted_class ORDER BY total DESC"
        )->fetchAll();
    }

    /** Estadísticas por modelo */
    public function statsByModel(): array {
        return $this->db->query(
            "SELECT model_used, COUNT(*) AS total,
                    ROUND(AVG(confidence), 2) AS avg_confidence
             FROM predictions GROUP BY model_used"
        )->fetchAll();
    }

    /** Predicciones recientes */
    public function recent(int $limit = 5): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.name AS user_name
             FROM predictions p JOIN users u ON p.user_id = u.id
             ORDER BY p.created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Predicciones del día */
    public function countToday(): int {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM predictions WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
    }
}
