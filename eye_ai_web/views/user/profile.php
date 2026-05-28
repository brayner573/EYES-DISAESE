<?php
$pageTitle = 'Mi Perfil';
require VIEWS_PATH . '/layouts/header.php';
$user = $data['user'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Mi Perfil</h1>
</div>

<div class="row g-4 animate-fade-in">
    <!-- Editar Perfil -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Datos Personales</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/user/profile/update" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nombre Completo</label>
                        <input type="text" name="name" class="form-control bg-light" value="<?= sanitize($user['name']) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control bg-light" value="<?= sanitize($user['email']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-save me-1"></i> Actualizar Perfil</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cambiar Contraseña -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>Cambiar Contraseña</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/user/password/change" method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Contraseña Actual</label>
                        <input type="password" name="current_password" class="form-control bg-light" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nueva Contraseña</label>
                        <input type="password" name="new_password" class="form-control bg-light" minlength="6" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium">Confirmar Nueva Contraseña</label>
                        <input type="password" name="confirm_password" class="form-control bg-light" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-dark shadow-sm"><i class="bi bi-key me-1"></i> Cambiar Contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
