document.addEventListener("DOMContentLoaded", function () {
    // 1. Carrega o Navbar
    const navContainer = document.getElementById("navbar-container");
    if (navContainer) {
        fetch("/shared/navbar.html?v=11")
            .then(response => {
                if (!response.ok) throw new Error(`Erro ${response.status} ao buscar ${response.url}`);
                return response.text();
            })
            .then(data => {
                navContainer.innerHTML = data;
                initNavbar();
                marcarLinkAtivo();
                verificarStatusWikiNavbar();
                initNavbarScroll();
            })
            .catch(error => {
                console.error("Erro ao carregar navbar:", error);
            });
    } else {
        initNavbar();
        verificarStatusWikiNavbar();
        initNavbarScroll();
    }

    // 2. Carrega o Footer
    const footerContainer = document.getElementById("footer");
    if (footerContainer) {
        fetch("/shared/footer.html?v=3")
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

        // Se o link possui uma âncora/hash (ex: /#big-header-div ou /#servers-section),
        // o estado ativo é gerenciado pela posição do scroll na home
        if (linkHash) {
            return;
        }

        if (linkPath === currentPath) {
            link.parentElement.classList.add('active');
        }
    });

    configurarScrollNavegacaoHome();
}

/**
 * Controla os estados ativos e a rolagem suave das seções da Home (Início e Servidores):
 * - "Início" só fica ativo enquanto o usuário estiver vendo a div do hero (#big-header-div).
 * - "Servidores" fica ativo enquanto a seção de servidores estiver no campo de visão.
 * - Ao passar da seção de servidores, nenhum dos dois fica ativo.
 */
function configurarScrollNavegacaoHome() {
    const serversSection = document.getElementById('servers-section');
    const serversLink = document.querySelector('#navbar li a[href*="#servers-section"]');
    const inicioLi = document.querySelector('#navbar li.nav-inicio-item');
    const inicioLink = document.querySelector('#navbar li.nav-inicio-item a, #navbar li a[href*="#big-header-div"]');
    const heroSection = document.getElementById('big-header-div');
    const isRoot = window.location.pathname.replace(/\/$/, '') === '' || window.location.pathname === '/';

    if (!isRoot) return;

    function checkActiveSection() {
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const triggerPoint = Math.max(100, windowHeight * 0.35);

        // 1. Verifica se a seção de servidores está ativa
        let isServersActive = false;
        if (serversSection) {
            const rect = serversSection.getBoundingClientRect();
            isServersActive = (rect.top <= triggerPoint && rect.bottom >= triggerPoint);
        }

        // 2. O "Início" só fica ativo se o usuário estiver vendo a div do hero
        let isHeroActive = false;
        if (heroSection) {
            const heroRect = heroSection.getBoundingClientRect();
            // A div do hero está visível se sua parte inferior ainda está no campo de visão
            isHeroActive = (heroRect.bottom > 80);
        }

        if (isServersActive) {
            if (serversLink) serversLink.parentElement.classList.add('active');
            if (inicioLi) inicioLi.classList.remove('active');
        } else if (isHeroActive) {
            if (serversLink) serversLink.parentElement.classList.remove('active');
            if (inicioLi) inicioLi.classList.add('active');
        } else {
            // Passou de servidores ou saiu do hero: nenhum dos dois fica destacado
            if (serversLink) serversLink.parentElement.classList.remove('active');
            if (inicioLi) inicioLi.classList.remove('active');
        }
    }

    const brandLink = document.getElementById('navbar-brand') || document.querySelector('.navbar-brand');
    if (brandLink) {
        brandLink.addEventListener('click', function (e) {
            if (isRoot) {
                e.preventDefault();
                if (heroSection) {
                    heroSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, '', '/');
                }
                setTimeout(checkActiveSection, 200);
            }
        });
    }

    if (inicioLink) {
        inicioLink.addEventListener('click', function (e) {
            e.preventDefault();
            if (heroSection) {
                heroSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            if (window.history && window.history.pushState) {
                window.history.pushState(null, '', '/');
            }
            setTimeout(checkActiveSection, 200);
        });
    }

    const serversLinks = document.querySelectorAll('#navbar a[href*="#servers-section"], #navbar a[href*="#servidores"], .nav-download-btn');
    if (serversSection) {
        serversLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (isRoot) {
                    e.preventDefault();
                    serversSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    const targetHash = link.getAttribute('href').includes('#') ? ('#' + link.getAttribute('href').split('#')[1]) : '#servidores';
                    if (window.history && window.history.pushState) {
                        window.history.pushState(null, '', targetHash);
                    } else {
                        window.location.hash = targetHash;
                    }
                    setTimeout(checkActiveSection, 200);
                }
            });
        });
    }

    let ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                checkActiveSection();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    window.addEventListener('resize', checkActiveSection, { passive: true });
    window.addEventListener('hashchange', checkActiveSection);

    // Verificação inicial
    checkActiveSection();

    // Compensação de Layout Shift se a página foi acessada com âncora (#servidores ou #servers-section)
    const currentHash = window.location.hash;
    if (currentHash === '#servidores' || currentHash === '#servers-section') {
        const target = serversSection || document.getElementById('servidores');
        const atualizacoes = document.getElementById('atualizacoes-section');

        let userHasScrolled = false;
        const onUserGesture = () => { userHasScrolled = true; };
        window.addEventListener('wheel', onUserGesture, { once: true, passive: true });
        window.addEventListener('touchmove', onUserGesture, { once: true, passive: true });

        const reajustarScroll = () => {
            if (!userHasScrolled && target) {
                target.scrollIntoView({ behavior: 'instant', block: 'start' });
            }
        };

        // Reajuste rápido pós-parse inicial
        setTimeout(reajustarScroll, 60);

        // Observa a div de atualizações caso ela expanda com o retorno da API
        if (atualizacoes) {
            const observer = new MutationObserver(() => {
                if (!atualizacoes.hidden) {
                    requestAnimationFrame(reajustarScroll);
                }
            });
            observer.observe(atualizacoes, { attributes: true, attributeFilter: ['hidden', 'style', 'class'] });
            setTimeout(() => observer.disconnect(), 3500);
        }
    }
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

/**
 * Controla a transição dinâmica da navbar:
 * - No topo da página: transparente.
 * - Ao rolar para baixo: cor de fundo (#030407) com opacidade suave e blur.
 */
function initNavbarScroll() {
    const nav = document.querySelector('#navbar-container nav, nav.navbar-main, nav');
    if (!nav) return;

    let ticking = false;

    function handleScroll() {
        const isScrolled = window.scrollY > 15;
        nav.classList.toggle('scrolled', isScrolled);
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(handleScroll);
            ticking = true;
        }
    }, { passive: true });

    // Verificação inicial imediata (ex: recarga com scroll preservado)
    handleScroll();
}
