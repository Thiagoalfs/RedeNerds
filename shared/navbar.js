document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("navbar-container");

    if (!container) {
        console.error("ERRO: O elemento <div id='navbar-container'></div> não foi encontrado na página HTML!");
        return;
    }

    // Tenta carregar o navbar.html da raiz
    fetch("/shared/navbar.html")
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
            }
            return response.text();
        })
        .then(data => {
            container.innerHTML = data;
            if (typeof initNavbar === "function") {
                initNavbar();
            }
        })
        .catch(error => {
            console.error(error);
            // Mostra o erro direto na página para facilitar o diagnóstico
            container.innerHTML = `<div style="color: red; padding: 10px; background: #fee; border: 1px solid red; text-align: center;">
                ⚠️ Não foi possível carregar a navbar: <strong>${error.message}</strong>
            </div>`;
        });
});

document.addEventListener("DOMContentLoaded", function () {
    const footerContainer = document.getElementById("footer");

    if (!footerContainer) {
        console.error("ERRO: O elemento <div id='footer'></div> não foi encontrado na página HTML!");
        return;
    }

    fetch("/shared/footer.html")
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
            }
            return response.text();
        })
        .then(data => {
            footerContainer.innerHTML = data;
        })
        .catch(error => {
            console.error(error);
            footerContainer.innerHTML = `<div style="color: red; padding: 10px; background: #fee; border: 1px solid red; text-align: center;">
                ⚠️ Não foi possível carregar o footer: <strong>${error.message}</strong>
            </div>`;
        });
});

function initNavbar() {
    const nav = document.querySelector('nav');
    const toggle = document.getElementById('navbar-toggle');
    const navbar = document.getElementById('navbar');

    // Se os elementos não existirem na página carregada, interrompe
    if (!nav || !toggle || !navbar) return;

    const openIcon = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
    const closeIcon = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

    function setOpen(isOpen) {
        nav.classList.toggle('navbar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
        toggle.innerHTML = isOpen ? closeIcon : openIcon;
    }

    // Alternar menu ao clicar no botão do hambúrguer
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = nav.classList.contains('navbar-open');
        setOpen(!isOpen);
    });

    // Fechar ao clicar em qualquer link
    navbar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setOpen(false);
        });
    });

    // Fechar ao clicar fora da navbar
    document.addEventListener('click', function (e) {
        if (!nav.classList.contains('navbar-open')) return;
        if (!nav.contains(e.target)) setOpen(false);
    });

    // Fechar ao pressionar a tecla ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('navbar-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Garante fechamento do menu mobile se redimensionar para desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && nav.classList.contains('navbar-open')) {
            setOpen(false);
        }
    });
}