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
                initFooterCopyIP();
            })
            .catch(error => {
                console.error("Erro ao carregar footer:", error);
            });
    }

    // 3. Inicia animações de Scroll Reveal (em todas as páginas)
    initScrollReveal();
});

function initScrollReveal() {
    const selector = 'section, .section, .servers-grid > *, .news-div, .parceiro, .mv-card, .valor-item, .features-list > li, .downloads-grid > *, .discord-grid > *, .canal-card, .faq-item, .dica-card, .regra-card, .regra-item';

    function observeElements() {
        const elements = document.querySelectorAll(selector);
        if (!('IntersectionObserver' in window)) {
            elements.forEach(el => el.classList.add('is-revealed'));
            return;
        }

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    obs.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '0px 0px -40px 0px',
            threshold: 0.08
        });

        elements.forEach(el => {
            if (!el.classList.contains('reveal-on-scroll')) {
                el.classList.add('reveal-on-scroll');
                observer.observe(el);
            }
        });
    }

    observeElements();

    // Re-observa elementos adicionados dinamicamente via fetch (ex: notícias, servidores)
    const target = document.getElementById('page-wrapper') || document.body;
    if (window.MutationObserver && target) {
        let debounceTimer;
        const mutObs = new MutationObserver(() => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(observeElements, 100);
        });
        mutObs.observe(target, { childList: true, subtree: true });
    }
}

function initFooterCopyIP() {
    const ipBox = document.getElementById('footer-ip-box');
    const feedback = document.getElementById('footer-copy-feedback');
    const ipValue = document.getElementById('footer-ip-value');
    const btn = document.getElementById('footer-btn-copy-ip');

    if (!ipBox || !ipValue) return;

    const copyText = ipValue.textContent.trim();

    function triggerCopy(e) {
        if (e) e.preventDefault();
        navigator.clipboard.writeText(copyText).then(() => {
            if (feedback) feedback.textContent = 'Copiado!';
            if (btn) btn.classList.add('copied');
            setTimeout(() => {
                if (feedback) feedback.textContent = 'Copiar';
                if (btn) btn.classList.remove('copied');
            }, 2000);
        }).catch(() => {
            if (feedback) feedback.textContent = 'Copiado!';
            setTimeout(() => {
                if (feedback) feedback.textContent = 'Copiar';
            }, 2000);
        });
    }

    ipBox.addEventListener('click', triggerCopy);
    ipBox.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            triggerCopy(e);
        }
    });
}

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