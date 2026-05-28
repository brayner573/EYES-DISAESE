<?php
$pageTitle = 'Iniciar Sesión';
require VIEWS_PATH . '/layouts/header.php';
?>
<div class="auth-card animate-fade-in">
    <div class="auth-header">
        <i class="bi bi-eye-fill display-4 mb-2"></i>
        <h2 class="h3 fw-bold mb-0">Eye Disease AI</h2>
        <p class="mb-0 text-white-50">Acceso al sistema</p>
    </div>
    <div class="p-4 p-md-5">
        <form action="<?= BASE_URL ?>/auth/login/submit" method="POST">
            <?= csrfField() ?>
            
            <div class="mb-4">
                <label for="email" class="form-label fw-medium">Correo Electrónico</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control bg-light border-start-0" id="email" name="email" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label fw-medium">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control bg-light border-start-0" id="password" name="password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                Iniciar Sesión <i class="bi bi-arrow-right ms-1"></i>
            </button>
            
            <div class="text-center mt-4 text-muted">
                ¿No tienes cuenta? <a href="<?= BASE_URL ?>/auth/register" class="text-primary text-decoration-none fw-medium">Regístrate aquí</a>
            </div>
        </form>
    </div>
</div>
<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
