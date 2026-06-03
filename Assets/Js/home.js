document.addEventListener("DOMContentLoaded", function () {

    // Estandarización de nombres a camelCase y uso de const
    const allMenuBtns = document.querySelectorAll(".menu-btn");
    allMenuBtns.forEach(function (btn) {
        btn.onclick = function (e) {
            
            if (this.classList.contains('user-welcome')) {
                const isViewsDir = window.location.pathname.includes('/views/');
                window.location.href = isViewsDir ? 'perfil.php' : 'views/perfil.php';
                return;
            }

            e.stopPropagation();
            
            document.querySelectorAll(".menu-content.show").forEach(function (openContent) {
                if (openContent !== this.nextElementSibling) {
                    openContent.classList.remove("show");
                    openContent.previousElementSibling.setAttribute("aria-expanded", false);
                }
            }.bind(this));

            const content = this.nextElementSibling;
            if (content && content.classList) {
                const isExpanded = content.classList.toggle("show");
                this.setAttribute("aria-expanded", isExpanded);
            }
        };
    });

    // Uso de const y let bajo estándares de ES6+
    const modal = document.getElementById("miModal");
    const btnAbrirModal = document.getElementById("abrirModalBtn");
    const btnAbrirRegistro = document.getElementById("btn_registrarse");
    const spanCerrar = document.getElementsByClassName("cerrar");
    
    const vistaRegistro = document.getElementById("vistaRegistro");
    const vistaLogin = document.getElementById("vistaLogin");
    const mostrarLoginBtn = document.getElementById("mostrarLoginBtn");
    const mostrarRegistroBtn = document.getElementById("mostrarRegistroBtn");

    // Lógica del modal de inicio de sesión y registro
    if (btnAbrirModal) {
        btnAbrirModal.onclick = function () {
            if (modal) modal.style.display = "flex";
            if (vistaRegistro) vistaRegistro.style.display = "none"; 
            if (vistaLogin) vistaLogin.style.display = "block";
        };
    }

    if (btnAbrirRegistro) {
        btnAbrirRegistro.onclick = function () {
            if (modal) modal.style.display = "flex";
            if (vistaLogin) vistaLogin.style.display = "none"; 
            if (vistaRegistro) vistaRegistro.style.display = "block";
        };
    }

    for (let i = 0; i < spanCerrar.length; i++) {
        spanCerrar[i].onclick = function () {
            if (modal) modal.style.display = "none";
        };
    }

    if (mostrarLoginBtn) {
        mostrarLoginBtn.onclick = function () {
            if (vistaRegistro) vistaRegistro.style.display = "none"; 
            if (vistaLogin) vistaLogin.style.display = "block";
        };
    }

    if (mostrarRegistroBtn) {
        mostrarRegistroBtn.onclick = function () {
            if (vistaLogin) vistaLogin.style.display = "none"; 
            if (vistaRegistro) vistaRegistro.style.display = "block";
        };
    }

    // Funcionalidad para revelar contraseñas
    const toggleIcons = document.querySelectorAll(".pass_icon");
    toggleIcons.forEach(function (icon) {
        icon.onclick = function () {
            const input = icon.previousElementSibling;
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        };
    });

    const toggleIconsSecond = document.querySelectorAll(".pass_icon_2");
    toggleIconsSecond.forEach(function (icon) {
        icon.onclick = function () {
            const input = icon.previousElementSibling;
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        };
    });

    // Cerrar componentes flotantes al dar clic fuera
    window.onclick = function (event) {
        if (event.target == modal) {
            if (modal) modal.style.display = "none";
        }

        if (!event.target.matches('.menu-btn')) {
            document.querySelectorAll(".menu-content.show").forEach(function (content) {
                content.classList.remove('show');
                const correspondingBtn = content.previousElementSibling;
                if (correspondingBtn) { 
                    correspondingBtn.setAttribute("aria-expanded", false);
                }
            });
        }
    };

    // Funciones globales de feedback visual
    window.setError = (input, message) => {
        if (!input) return;

        input.classList.add('error');
        input.classList.remove('success');

        const wrapper = input.closest('.password-wrapper') || input;
        const errorDiv = wrapper.nextElementSibling;
        if (errorDiv) errorDiv.innerText = message;
    };

    window.setSuccess = (input) => {
        if (!input) return;
        
        input.classList.add('success');
        input.classList.remove('error');

        const wrapper = input.closest('.password-wrapper') || input;
        const errorDiv = wrapper.nextElementSibling;
        if (errorDiv) errorDiv.innerText = '';
    };

    window.isValidEmail = (email) => {
        const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    };

});