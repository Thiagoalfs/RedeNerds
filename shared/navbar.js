document.addEventListener("DOMContentLoaded", function () {
    // 1. Carrega o Navbar
    const navContainer = document.getElementById("navbar-container");
    if (navContainer) {
        fetch("/shared/navbar.html?v=2")
            .then(response => {
                if (!response.ok) throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
                return response.text();
            })
            .then(data => {
                navContainer.innerHTML = data;
                initNavbar();
                marcarLinkAtivo();
                verificarStatusWikiNavbar();
            })
            .catch(error => {
                console.error("Erro ao carregar navbar:", error);
            });
    } else {
        verificarStatusWikiNavbar();
    }

    // 2. Carrega o Footer
    const footerContainer = document.getElementById("footer");
    if (footerContainer) {
        fetch("/shared/footer.html?v=2")
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
});

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
        const url = new URL(link.href, window.location.origin);
        const linkPath = url.pathname.replace(/\/$/, '') || '/';
        const linkHash = url.hash;

        // Se o link possui uma âncora/hash (ex: /#servers-section),
        // ele não deve ser ativado apenas pela equivalência da rota '/'
        if (linkHash) {
            return;
        }

        if (linkPath === currentPath) {
            link.parentElement.classList.add('active');
        }
    });

    configurarScrollServersSection();
}

function configurarScrollServersSection() {
    const serversSection = document.getElementById('servers-section');
    const serversLink = document.querySelector('#navbar li a[href*="#servers-section"]');
    if (!serversSection || !serversLink) return;

    const serversLi = serversLink.parentElement;

    function checkServersSectionActive() {
        const rect = serversSection.getBoundingClientRect();
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const triggerPoint = Math.max(100, windowHeight * 0.35);

        const isActive = (rect.top <= triggerPoint && rect.bottom >= triggerPoint);
        if (isActive) {
            serversLi.classList.add('active');
        } else {
            serversLi.classList.remove('active');
        }
    }

    serversLink.addEventListener('click', function (e) {
        const isRoot = window.location.pathname.replace(/\/$/, '') === '' || window.location.pathname === '/';
        if (isRoot) {
            e.preventDefault();
            serversSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (window.history && window.history.pushState) {
                window.history.pushState(null, '', '#servers-section');
            } else {
                window.location.hash = '#servers-section';
            }
            setTimeout(checkServersSectionActive, 200);
        }
    });

    let ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                checkServersSectionActive();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    window.addEventListener('resize', checkServersSectionActive, { passive: true });
    window.addEventListener('hashchange', checkServersSectionActive);

    // Verificação inicial
    checkServersSectionActive();
}

function verificarStatusWikiNavbar() {
    fetch("/api/status_wiki.php")
        .then(res => {
            if (!res.ok) throw new Error("Erro ao buscar status");
            return res.json();
        })
        .then(data => {
            if (data && data.wiki_habilitada === false) {
                document.querySelectorAll(".nav-wiki-item, #navbar a[href*='/wiki/']").forEach(el => {
                    const li = el.closest('li') || el;
                    li.style.display = 'none';
                });
            }
        })
        .catch(() => {});
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