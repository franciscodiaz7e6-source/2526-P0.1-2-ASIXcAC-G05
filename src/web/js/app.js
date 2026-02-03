/**
 * Extagram - JavaScript Frontend
 * Validacion de formularios y eventos de usuario
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Extagram Board cargado');

    // ===== VALIDACION DE FORMULARIO =====
    // Ahora permite enviar solo imagen SIN texto
    const form = document.querySelector('.post-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const textarea = form.querySelector('textarea[name="post"]');
            const fileInput = form.querySelector('input[type="file"]');
            
            const hasText = textarea && textarea.value.trim().length > 0;
            const hasImage = fileInput && fileInput.files.length > 0;
            
            // Debe tener al menos texto O imagen
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
                // Validar tipo
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Solo se permiten imágenes JPG, PNG, GIF o WEBP');
                    e.target.value = '';
                    if (uploadLabel) {
                        uploadLabel.innerHTML = '<i class="fas fa-camera"></i> Adjuntar imagen';
                        uploadLabel.style.color = '';
                    }
                    return;
                }
               
                // Validar tamaño (5MB)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('La imagen no puede superar 5MB');
                    e.target.value = '';
                    if (uploadLabel) {
                        uploadLabel.innerHTML = '<i class="fas fa-camera"></i> Adjuntar imagen';
                        uploadLabel.style.color = '';
                    }
                    return;
                }

                // Actualizar label con nombre del archivo
                if (uploadLabel) {
                    uploadLabel.innerHTML = '<i class="fas fa-check-circle"></i> ' + file.name;
                    uploadLabel.style.color = '#28a745';
                }
                
                console.log('Imagen seleccionada: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + 'MB)');
            } else {
                // Si se cancela la selección
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

    // ===== BOTÓN CERRAR ALERTAS =====
    document.querySelectorAll('.alert-close').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.parentElement.style.transition = 'opacity 0.3s';
            btn.parentElement.style.opacity = '0';
            setTimeout(() => btn.parentElement.remove(), 300);
        });
    });
});
