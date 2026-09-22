// js/login_animation.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = errorMessage.querySelector('span');
    const submitBtn = loginForm.querySelector('.btn-login');

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
                // EFECTO "APERTURA DE TELÓN" (EXPANDING CANVAS)
                const rect = submitBtn.getBoundingClientRect();
                const expander = document.createElement('div');
                expander.className = 'curtain-reveal';
                
                // Centrar exactamente en medio del botón
                expander.style.top = (rect.top + rect.height / 2) + 'px';
                expander.style.left = (rect.left + rect.width / 2) + 'px';
                expander.style.width = '20px';
                expander.style.height = '20px';
                
                document.body.appendChild(expander);

                // Forzar un reflow para que la transición CSS se ejecute
                void expander.offsetWidth;
                
                // Disparar la expansión de la cortina azul
                expander.classList.add('expand');
                
                // Esperar a que la pantalla se cubra antes de redirigir (450ms)
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 450);
            } else {
                // Si las credenciales fallan, quitamos el estado de carga y mostramos el error
                submitBtn.classList.remove('is-loading');
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
