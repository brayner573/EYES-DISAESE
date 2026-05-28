<?php
$pageTitle = 'Gestión de Usuarios';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Gestión de Usuarios</h1>
</div>

<div class="card shadow-sm border-0 animate-fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Predicciones</th>
                        <th>Registro</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['users'] as $u): ?>
                    <tr>
                        <td class="ps-4 text-muted">#<?= $u['id'] ?></td>
                        <td class="fw-bold"><?= sanitize($u['name']) ?></td>
                        <td><?= sanitize($u['email']) ?></td>
                        <td>
                            <span class="badge bg-<?= $u['role'] === 'ADMIN' ? 'danger' : 'primary' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= $u['total_predictions'] ?></span></td>
                        <td class="text-muted small"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end pe-4">
                            <?php if ($u['id'] !== currentUserId()): ?>
                            <!-- Cambiar Rol -->
                            <form action="<?= BASE_URL ?>/admin/users/role" method="POST" class="d-inline-block">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="role_id" value="<?= $u['role'] === 'ADMIN' ? 2 : 1 ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cambiar a <?= $u['role'] === 'ADMIN' ? 'Usuario' : 'Admin' ?>">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                            
                            <!-- Eliminar -->
                            <form action="<?= BASE_URL ?>/admin/users/delete" method="POST" class="d-inline-block ms-1" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="badge bg-light text-muted">Tú</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
