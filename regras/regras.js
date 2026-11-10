/**
 * Regras - Scrollspy e Navegação por Categorias
 * Sincroniza a sidebar fixa à esquerda com a rolagem das seções à direita.
 */
document.addEventListener("DOMContentLoaded", function () {
    initRegrasScrollspy();
});

function initRegrasScrollspy() {
    const sections = Array.from(document.querySelectorAll('.regra-section'));
    const navLinks = Array.from(document.querySelectorAll('.sidebar-link'));

    if (!sections.length || !navLinks.length) return;

    let isManualScrolling = false;
    let manualScrollTimer = null;

    // 1. Clique nos links da sidebar: rolagem suave até a seção
    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const targetSection = document.getElementById(targetId);

            if (targetSection) {
                isManualScrolling = true;
                clearTimeout(manualScrollTimer);

                // Destaca imediatamente o link clicado
                setActiveCategory(targetId);

                // Rolagem nativa suave com suporte a scroll-margin-top
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + targetId);
                }

                // Libera o observer após a animação de rolagem terminar
                manualScrollTimer = setTimeout(() => {
                    isManualScrolling = false;
                }, 800);
            }
        });
    });

    // 2. Função para atualizar o estado ativo na sidebar
    function setActiveCategory(activeId) {
        navLinks.forEach(link => {
            const isMatch = link.getAttribute('data-target') === activeId;
            link.classList.toggle('active', isMatch);
            link.setAttribute('aria-current', isMatch ? 'true' : 'false');

            // No mobile (barra horizontal), rola suavemente apenas o container horizontal da barra
            if (isMatch && window.innerWidth <= 960) {
                const navBar = document.getElementById('regras-nav');
                if (navBar) {
                    const scrollTarget = link.offsetLeft - (navBar.offsetWidth / 2) + (link.offsetWidth / 2);
                    navBar.scrollTo({ left: Math.max(0, scrollTarget), behavior: 'smooth' });
                }
            }
        });
    }

    // 3. Scrollspy inteligente: calcula qual seção está no terço superior da janela
    function updateActiveOnScroll() {
        if (isManualScrolling) return;

        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const scrollPosition = window.scrollY || window.pageYOffset;
        const docHeight = document.documentElement.scrollHeight;

        // Se estiver no final da página, ativa a última categoria
        if (windowHeight + scrollPosition >= docHeight - 120) {
            const lastSection = sections[sections.length - 1];
            if (lastSection) {
                setActiveCategory(lastSection.id);
                return;
            }
        }

        // Ponto de detecção na tela (abaixo da navbar fixa)
        const detectionPoint = 140;

        let currentActiveId = sections[0].id;

        for (let i = 0; i < sections.length; i++) {
            const rect = sections[i].getBoundingClientRect();
            // A seção que intercepta o ponto de leitura ativo
            if (rect.top <= detectionPoint) {
                currentActiveId = sections[i].id;
            } else {
                break;
            }
        }

        setActiveCategory(currentActiveId);
    }

    // Event listener com requestAnimationFrame para performance 60fps
    let ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                updateActiveOnScroll();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // 4. Suporte à âncora inicial na URL (ex: /regras/#cat-3)
    if (window.location.hash) {
        const hashId = window.location.hash.replace('#', '');
        const initialEl = document.getElementById(hashId);
        if (initialEl) {
            setTimeout(() => {
                initialEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActiveCategory(hashId);
            }, 150);
        }
    } else {
        // Inicializa com a primeira categoria ativa
        setActiveCategory(sections[0].id);
    }
}
