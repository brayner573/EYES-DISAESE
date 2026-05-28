<?php
$pageTitle = 'Registro';
require VIEWS_PATH . '/layouts/header.php';
?>
<div class="auth-card animate-fade-in" style="max-width: 500px;">
    <div class="auth-header bg-dark">
        <i class="bi bi-person-plus-fill display-4 mb-2 text-primary"></i>
        <h2 class="h3 fw-bold mb-0">Crear Cuenta</h2>
        <p class="mb-0 text-white-50">Únete a Eye Disease AI</p>
    </div>
    <div class="p-4 p-md-5">
        <form action="<?= BASE_URL ?>/auth/register/submit" method="POST">
            <?= csrfField() ?>
            
            <div class="mb-3">
                <label for="name" class="form-label fw-medium">Nombre Completo</label>
                <input type="text" class="form-control bg-light" id="name" name="name" required>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label fw-medium">Correo Electrónico</label>
                <input type="email" class="form-control bg-light" id="email" name="email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label fw-medium">Contraseña</label>
                <input type="password" class="form-control bg-light" id="password" name="password" minlength="6" required>
            </div>
            
            <div class="mb-4">
                <label for="password_confirm" class="form-label fw-medium">Confirmar Contraseña</label>
                <input type="password" class="form-control bg-light" id="password_confirm" name="password_confirm" minlength="6" required>
            </div>
            
            <button type="submit" class="btn btn-dark w-100 py-2 fw-bold shadow-sm">
                Registrarse <i class="bi bi-check-circle ms-1"></i>
            </button>
            
            <div class="text-center mt-4 text-muted">
                ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/auth/login" class="text-primary text-decoration-none fw-medium">Inicia sesión</a>
            </div>
        </form>
    </div>
</div>
<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
