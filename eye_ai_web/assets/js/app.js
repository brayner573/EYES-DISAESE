// Mobile Sidebar Toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.classList.toggle('show');
}

// Drag and Drop Upload
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('image');
const previewImg = document.getElementById('previewImg');
const uploadText = document.getElementById('uploadText');

if (uploadArea && fileInput) {
    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFilePreview(fileInput.files[0]);
        }
    });

    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleFilePreview(this.files[0]);
        }
    });
}

function handleFilePreview(file) {
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.classList.remove('d-none');
            uploadText.classList.add('d-none');
        }
        reader.readAsDataURL(file);
    }
}

// Loading state for forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn && !btn.classList.contains('no-loader')) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
            btn.disabled = true;
            
            // Allow form submission to continue
            setTimeout(() => {
                if (form.classList.contains('prediction-form')) {
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Analizando con IA...';
                }
            }, 100);
        }
    });
});

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert:not(.alert-important)').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
