/**
 * Extagram - JavaScript Frontend
 * Validación, eventos y actualización de tiempo en vivo
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Extagram Board cargado');

    // ===== VALIDACIÓN DE FORMULARIO =====
    const form = document.querySelector('.post-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const textarea = form.querySelector('textarea[name="post"]');
            const fileInput = form.querySelector('input[type="file"]');
            
            const hasText = textarea && textarea.value.trim().length > 0;
            const hasImage = fileInput && fileInput.files.length > 0;
            
            if (!hasText && !hasImage) {
                e.preventDefault();
                alert('Debes escribir un mensaje o adjuntar una imagen');
                textarea.focus();
                return false;
            }
        });
    }

    // ===== CONTADOR DE CARACTERES =====
    const textarea = document.querySelector('textarea[name="post"]');
    const counter = document.querySelector('.char-counter');
    if (textarea && counter) {
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            counter.textContent = length + '/500';
            counter.style.color = length > 450 ? '#ed4956' : '#8e8e8e';
        });
    }

    // ===== PREVIEW DE IMAGEN =====
    const fileInput = document.querySelector('input[type="file"]');
    const uploadLabel = document.querySelector('.upload-label');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Solo se permiten imágenes JPG, PNG, GIF o WEBP');
                    e.target.value = '';
                    return;
                }
               
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('La imagen no puede superar 5MB');
                    e.target.value = '';
                    return;
                }

                if (uploadLabel) {
                    uploadLabel.innerHTML = '<i class="fas fa-check-circle"></i> ' + file.name;
                    uploadLabel.style.color = '#28a745';
                }
            } else {
                if (uploadLabel) {
                    uploadLabel.innerHTML = '<i class="fas fa-camera"></i> Adjuntar imagen';
                    uploadLabel.style.color = '';
                }
            }
        });
    }

    // ===== AUTO-CERRAR ALERTAS =====
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // ===== NUEVO: ACTUALIZACIÓN DE TIEMPO EN VIVO =====
    updateRelativeTimes();
    setInterval(updateRelativeTimes, 30000); // Cada 30 segundos

    function updateRelativeTimes() {
        const timeElements = document.querySelectorAll('.board-time');
        timeElements.forEach(el => {
            const timeText = el.textContent.trim();
            const match = timeText.match(/(\d+)([smhd])$/i);
            
            if (match) {
                const value = parseInt(match[1]);
                const unit = match[2].toLowerCase();
                
                // Si es "1h" fijo, intentar parsear fecha del atributo data-time
                const timestamp = el.getAttribute('data-timestamp');
                if (timestamp) {
                    const date = new Date(parseInt(timestamp) * 1000);
                    el.textContent = getRelativeTime(date);
                    return;
                }
                
                // Fallback: mejorar formato simple
                if (value === 1 && unit === 'h') {
                    el.innerHTML = '<i class="far fa-clock"></i> Hace 1 hora';
                }
            }
        });
    }

    function getRelativeTime(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMin = Math.floor(diffMs / 60000);
        const diffH = Math.floor(diffMin / 60);
        const diffD = Math.floor(diffH / 24);

        if (diffMin < 1) return 'Ahora mismo';
        if (diffMin < 60) return `Hace ${diffMin} min`;
        if (diffH < 24) return `Hace ${diffH} h`;
        if (diffD < 7) return `Hace ${diffD} días`;
        return date.toLocaleDateString('es-ES');
    }
});
