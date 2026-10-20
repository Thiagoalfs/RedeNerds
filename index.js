/**
 * index.js
 * Busca os servidores habilitados em /api/servidores_api.php e monta os
 * cards dentro de .servers-grid, com o mesmo visual dos cards estáticos
 * originais — mas com a cor de cada servidor vindo do banco (themecolor).
 */

const SERVIDORES_API_URL = '/api/servidores_api.php';
const PARCEIROS_API_URL = '/api/parceiros_api.php';

document.addEventListener('DOMContentLoaded', () => {
    carregarServidores();
    carregarParceiros();
    inicializarCarrosselServidores();
});


async function carregarServidores() {
    const grid = document.querySelector('.servers-grid');
    if (!grid) return;

    const render = (dados) => {
        if (!dados || !dados.success || !Array.isArray(dados.servidores)) return;
        if (dados.servidores.length === 0) {
            grid.innerHTML = '';
            return;
        }
        grid.innerHTML = '';
        dados.servidores.forEach((servidor) => {
            const cardEl = criarCardServidor(servidor);
            grid.appendChild(cardEl);
        });
        requestAnimationFrame(() => {
            inicializarCarrosselServidores();
        });
    };

    if (window.AppCache) {
        await AppCache.fetchWithCache('servidores', SERVIDORES_API_URL, {
            onCached: render,
            onFresh: render,
            onError: (erro) => console.error('Não foi possível carregar os servidores dinamicamente:', erro)
        });
    } else {
        try {
            const resposta = await fetch(SERVIDORES_API_URL);
            if (!resposta.ok) throw new Error('Falha na requisição');
            const dados = await resposta.json();
            render(dados);
        } catch (erro) {
            console.error('Não foi possível carregar os servidores dinamicamente:', erro);
        }
    }
}

function criarCardServidor(servidor) {
    const cor = servidor.themecolor || '#7DB9DF';

    const card = document.createElement('a');
    card.href = `servidores/?servidor=${encodeURIComponent(servidor.nome)}`;
    card.className = 'server-card';
    card.style.setProperty('--card-color', cor);
    card.style.setProperty('--card-shadow', hexParaRgba(cor, 0.15));
    card.style.setProperty('--card-hover-shadow', hexParaRgba(cor, 0.4));

    // Banner
    const banner = document.createElement('div');
    banner.className = 'server-card-banner';

    if (servidor.icon_type === 'img' && servidor.icon) {
        const img = document.createElement('img');
        img.src = servidor.icon;
        img.alt = servidor.servername;
        img.onerror = () => { img.style.display = 'none'; };
        banner.appendChild(img);
    } else {
        const icone = document.createElement('i');
        icone.className = servidor.icon || 'fa-solid fa-server';
        banner.appendChild(icone);
    }

    // Conteúdo
    const content = document.createElement('div');
    content.className = 'server-card-content';

    const titulo = document.createElement('h3');
    titulo.textContent = servidor.servername;

    const descricao = document.createElement('p');
    descricao.textContent = limitarNoSegundoPonto(servidor.descricao);

    const link = document.createElement('span');
    link.className = 'server-card-link';
    link.innerHTML = 'VER DETALHES <i class="fa-solid fa-arrow-right"></i>';

    content.appendChild(titulo);
    content.appendChild(descricao);
    content.appendChild(link);

    card.appendChild(banner);
    card.appendChild(content);

    return card;
}

async function carregarParceiros() {
    const grid = document.querySelector('.parceiros-grid');
    if (!grid) return;

    const render = (dados) => {
        if (!dados || !dados.success || !Array.isArray(dados.parceiros)) return;
        if (dados.parceiros.length === 0) {
            grid.innerHTML = '<p class="text-muted text-center" style="grid-column: 1/-1; color: var(--color-text-muted);">Nenhum parceiro cadastrado no momento.</p>';
            return;
        }
        grid.innerHTML = '';

        const track = document.createElement('div');
        track.className = 'parceiros-track';

        // Cards originais
        dados.parceiros.forEach((parceiro) => {
            const cardEl = criarCardParceiro(parceiro);
            track.appendChild(cardEl);
        });

        // Clona os itens para permitir animação contínua infinita (marquee) no mobile
        const totalClones = Math.max(1, Math.ceil(8 / dados.parceiros.length));
        for (let i = 0; i < totalClones; i++) {
            dados.parceiros.forEach((parceiro) => {
                const cloneEl = criarCardParceiro(parceiro);
                cloneEl.classList.add('parceiro-clone');
                cloneEl.setAttribute('aria-hidden', 'true');
                track.appendChild(cloneEl);
            });
        }

        grid.appendChild(track);
    };

    if (window.AppCache) {
        await AppCache.fetchWithCache('parceiros', PARCEIROS_API_URL, {
            onCached: render,
            onFresh: render,
            onError: (erro) => console.error('Não foi possível carregar os parceiros dinamicamente:', erro)
        });
    } else {
        try {
            const resposta = await fetch(PARCEIROS_API_URL);
            if (!resposta.ok) throw new Error('Falha na requisição');
            const dados = await resposta.json();
            render(dados);
        } catch (erro) {
            console.error('Não foi possível carregar os parceiros dinamicamente:', erro);
        }
    }
}

function criarCardParceiro(parceiro) {
    const card = document.createElement('div');
    card.className = 'parceiro';

    // Avatar / Foto
    const avatarDiv = document.createElement('div');
    avatarDiv.className = 'parceiro-avatar';

    const img = document.createElement('img');
    img.src = parceiro.foto || '/assets/images/logo.webp';
    img.alt = parceiro.nome;
    img.onerror = () => { img.src = '/assets/images/logo.webp'; };
    avatarDiv.appendChild(img);

    // Nome
    const nomeP = document.createElement('p');
    nomeP.className = 'parceiro-nome';
    nomeP.textContent = parceiro.nome;

    // Redes Sociais
    const socialDiv = document.createElement('div');
    socialDiv.className = 'parceiro-social';

    if (Array.isArray(parceiro.redes)) {
        parceiro.redes.forEach((rede) => {
            if (rede && rede.url) {
                const link = document.createElement('a');
                link.href = rede.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.setAttribute('aria-label', rede.label || rede.tipo);
                link.className = `parceiro-social-link ${rede.classe || rede.tipo}`;

                const icon = document.createElement('i');
                icon.className = rede.icone || `fa-brands fa-${rede.tipo}`;
                link.appendChild(icon);

                socialDiv.appendChild(link);
            }
        });
    }

    card.appendChild(avatarDiv);
    card.appendChild(nomeP);
    card.appendChild(socialDiv);

    return card;
}


function escapeHTML(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function limitarNoSegundoPonto(texto) {
    if (!texto) return '';
    const primeiro = texto.indexOf('.');
    if (primeiro === -1) return texto;
    const segundo = texto.indexOf('.', primeiro + 1);
    if (segundo === -1) return texto;
    return texto.substring(0, segundo + 1).trim();
}

/* ===== Helpers de cor (recebem hex "#RRGGBB") ===== */

function hexParaRgb(hex) {
    const limpo = hex.replace('#', '');
    const bigint = parseInt(limpo, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
    };
}

function hexParaRgba(hex, alpha) {
    const { r, g, b } = hexParaRgb(hex);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function clarear(hex, porcentagem) {
    const { r, g, b } = hexParaRgb(hex);
    const ajustar = (canal) => Math.round(canal + (255 - canal) * (porcentagem / 100));
    return `rgb(${ajustar(r)}, ${ajustar(g)}, ${ajustar(b)})`;
}

function escurecer(hex, porcentagem) {
    const { r, g, b } = hexParaRgb(hex);
    const ajustar = (canal) => Math.round(canal * (1 - porcentagem / 100));
    return `rgb(${ajustar(r)}, ${ajustar(g)}, ${ajustar(b)})`;
}

/**
 * Inicializa o Carrossel interativo de servidores para Desktop e Mobile
 */
function inicializarCarrosselServidores() {
    const track = document.getElementById('servers-carousel-track') || document.querySelector('.servers-grid');
    const prevBtn = document.getElementById('servers-carousel-prev');
    const nextBtn = document.getElementById('servers-carousel-next');
    const dotsContainer = document.getElementById('servers-carousel-dots');
    const wrapper = document.querySelector('.servers-carousel-wrapper');

    if (!track) return;

    function getCards() {
        return track.querySelectorAll('.server-card');
    }

    function getScrollStep() {
        const currentCards = getCards();
        if (currentCards.length > 0) {
            const card = currentCards[0];
            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.gap) || 24;
            return (card.offsetWidth || 350) + gap;
        }
        return 360;
    }

    function atualizarEstadoCarrossel() {
        const currentCards = getCards();
        if (currentCards.length === 0) return;

        // Medição precisa da largura total demandada pelos cards (incluindo gaps)
        const style = window.getComputedStyle(track);
        const gap = parseFloat(style.gap) || 24;
        let totalCardsWidth = 0;
        currentCards.forEach((c, idx) => {
            totalCardsWidth += c.offsetWidth;
            if (idx > 0) totalCardsWidth += gap;
        });

        const availableWidth = track.clientWidth;
        // Há overflow se a largura demandada pelos cards for maior que o container disponível
        const canScroll = totalCardsWidth > (availableWidth + 5);

        if (wrapper) {
            if (canScroll) {
                const eraSemOverflow = !wrapper.classList.contains('has-overflow');
                wrapper.classList.add('has-overflow');
                // Se acabou de mudar para overflow, garante início no primeiro card
                if (eraSemOverflow && track.scrollLeft !== 0) {
                    track.scrollLeft = 0;
                }
            } else {
                wrapper.classList.remove('has-overflow');
                track.scrollLeft = 0;
            }
        }

        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);

        if (prevBtn) {
            prevBtn.disabled = track.scrollLeft <= 5;
            prevBtn.style.display = canScroll && window.innerWidth >= 768 ? 'flex' : 'none';
        }
        if (nextBtn) {
            nextBtn.disabled = track.scrollLeft >= maxScroll - 5;
            nextBtn.style.display = canScroll && window.innerWidth >= 768 ? 'flex' : 'none';
        }

        if (dotsContainer) {
            if (!canScroll) {
                dotsContainer.style.display = 'none';
            } else {
                dotsContainer.style.display = 'flex';
                atualizarDots();
            }
        }
    }

    function criarDots() {
        if (!dotsContainer) return;
        dotsContainer.innerHTML = '';
        const currentCards = getCards();
        if (currentCards.length <= 1) return;

        currentCards.forEach((card, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'servers-carousel-dot' + (index === 0 ? ' active' : '');
            dot.setAttribute('aria-label', `Ir para o servidor ${index + 1}`);
            dot.addEventListener('click', () => {
                const cardLeft = card.offsetLeft - track.offsetLeft;
                track.scrollTo({ left: cardLeft, behavior: 'smooth' });
            });
            dotsContainer.appendChild(dot);
        });
    }

    function atualizarDots() {
        if (!dotsContainer) return;
        const dots = dotsContainer.querySelectorAll('.servers-carousel-dot');
        const currentCards = getCards();
        if (!dots.length || !currentCards.length) return;

        let activeIndex = 0;
        let minDiff = Infinity;
        const currentScroll = track.scrollLeft;

        currentCards.forEach((card, index) => {
            const cardLeft = card.offsetLeft - track.offsetLeft;
            const diff = Math.abs(cardLeft - currentScroll);
            if (diff < minDiff) {
                minDiff = diff;
                activeIndex = index;
            }
        });

        dots.forEach((dot, idx) => {
            if (idx === activeIndex) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    // Botões Anterior / Próximo (atribui listeners uma única vez com passo dinâmico)
    if (prevBtn && !prevBtn.dataset.listenerAttached) {
        prevBtn.dataset.listenerAttached = 'true';
        prevBtn.addEventListener('click', () => {
            const step = getScrollStep();
            track.scrollBy({ left: -step, behavior: 'smooth' });
        });
    }

    if (nextBtn && !nextBtn.dataset.listenerAttached) {
        nextBtn.dataset.listenerAttached = 'true';
        nextBtn.addEventListener('click', () => {
            const step = getScrollStep();
            track.scrollBy({ left: step, behavior: 'smooth' });
        });
    }

    if (!track.dataset.carouselEventsAttached) {
        track.dataset.carouselEventsAttached = 'true';

        track.addEventListener('scroll', () => {
            atualizarEstadoCarrossel();
        }, { passive: true });

        // Arraste com o mouse no Desktop
        let isDown = false;
        let startX = 0;
        let scrollLeftStart = 0;
        let hasDragged = false;

        track.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            if (!wrapper || !wrapper.classList.contains('has-overflow')) return;
            isDown = true;
            hasDragged = false;
            track.classList.add('is-dragging');
            startX = e.pageX - track.offsetLeft;
            scrollLeftStart = track.scrollLeft;
        });

        window.addEventListener('mouseup', () => {
            if (!isDown) return;
            isDown = false;
            track.classList.remove('is-dragging');
            setTimeout(() => {
                hasDragged = false;
            }, 60);
        });

        track.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            const x = e.pageX - track.offsetLeft;
            const walk = (x - startX) * 1.3;
            if (Math.abs(walk) > 6) hasDragged = true;
            track.scrollLeft = scrollLeftStart - walk;
        });

        // Delegação de evento: intercepta cliques para não navegar se tiver ocorrido arraste
        track.addEventListener('click', (e) => {
            if (hasDragged) {
                e.preventDefault();
                e.stopPropagation();
                hasDragged = false;
            }
        }, true);

        window.addEventListener('resize', () => {
            atualizarEstadoCarrossel();
        });
    }

    criarDots();
    atualizarEstadoCarrossel();
}
