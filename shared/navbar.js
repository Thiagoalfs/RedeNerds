document.addEventListener("DOMContentLoaded", function () {
    // 1. Carrega o Navbar
    const navContainer = document.getElementById("navbar-container");
    if (navContainer) {
        fetch("/shared/navbar.html?v=1")
            .then(response => {
                if (!response.ok) throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
                return response.text();
            })
            .then(data => {
                navContainer.innerHTML = data;
                initNavbar();
                marcarLinkAtivo();
            })
            .catch(error => {
                console.error("Erro ao carregar navbar:", error);
            });
    }

    // 2. Carrega o Footer
    const footerContainer = document.getElementById("footer");
    if (footerContainer) {
        fetch("/shared/footer.html?v=1")
            .then(response => {
                if (!response.ok) throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
                return response.text();
            })
            .then(data => {
                footerContainer.innerHTML = data;
            })
            .catch(error => {
                console.error("Erro ao carregar footer:", error);
            });
    }
});

function marcarLinkAtivo() {
    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
    const navLinks = document.querySelectorAll('#navbar li a');
    navLinks.forEach(link => {
        const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
        if (linkPath === currentPath) {
            link.parentElement.classList.add('active');
        }
    });
}

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