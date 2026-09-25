// js/login_animation.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = errorMessage.querySelector('span');
    const submitBtn = loginForm.querySelector('.btn-login');

    loginForm.addEventListener('input', () => {
        // Restaurar estado original si el usuario empieza a corregir sus datos
        const formGroups = loginForm.querySelectorAll('.form-group');
        formGroups.forEach(group => group.classList.remove('is-invalid', 'is-valid'));
        submitBtn.style.backgroundColor = '';
        submitBtn.textContent = 'Ingresar';
        errorMessage.classList.add('hidden');
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // Detiene el comportamiento clásico del formulario

        // Limpiar errores previos
        errorMessage.classList.add('hidden');

        // Activar estado de carga en el botón
        submitBtn.classList.add('is-loading');

        // Capturar los datos
        const formData = {
            usuario: loginForm.usuario.value,
            password: loginForm.password.value
        };

        try {
            // Petición AJAX mediante Fetch API nativa hacia el archivo separado
            const response = await fetch('login_process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }

            const result = await response.json();

            if (result.success) {
                // 1) Quitar el estado de carga del botón
                submitBtn.classList.remove('is-loading');
                submitBtn.style.backgroundColor = '#28a745'; // Cambiar botón a verde
                submitBtn.textContent = 'Autorizado'; // Mensaje de éxito

                // 2) Poner en verde las cajas y mostrar íconos
                const formGroups = loginForm.querySelectorAll('.form-group');
                formGroups.forEach(group => {
                    group.classList.remove('is-invalid');
                    group.classList.add('is-valid');
                });

                // 3) Esperar un poco para que el usuario vea la validación verde
                setTimeout(() => {
                    // Aplicar Blur Fade al contenedor completo
                    document.body.classList.add('blur-fade-out');

                    // Esperar a que la pantalla se desvanezca antes de redirigir (600ms)
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 600);
                }, 800); // Pausa de 800ms para apreciar los íconos verdes
            } else {
                // Si las credenciales fallan, quitamos el estado de carga y mostramos el error
                submitBtn.classList.remove('is-loading');

                // Mostrar colores de error
                submitBtn.style.backgroundColor = '#dc3545';
                submitBtn.textContent = 'Denegado';

                const formGroups = loginForm.querySelectorAll('.form-group');
                formGroups.forEach(group => {
                    group.classList.remove('is-valid');
                    group.classList.add('is-invalid');
                });

                showError(result.message);
            }
        } catch (error) {
            submitBtn.classList.remove('is-loading');
            showError('Ocurrió un error de red. Inténtalo más tarde.');
            console.error('Fetch error:', error);
        }
    });

    // Función auxiliar para desplegar los errores en pantalla
    function showError(message) {
        if (errorText && errorMessage) {
            errorText.textContent = message;
            errorMessage.classList.remove('hidden');
        }
    }
});
