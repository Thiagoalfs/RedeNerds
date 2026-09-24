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
});


async function carregarServidores() {
    const grid = document.querySelector('.servers-grid');
    if (!grid) return;

    try {
        const resposta = await fetch(SERVIDORES_API_URL);
        if (!resposta.ok) throw new Error('Falha na requisição');

        const dados = await resposta.json();
        if (!dados.success || !Array.isArray(dados.servidores)) {
            throw new Error('Resposta inválida da API');
        }

        if (dados.servidores.length === 0) {
            // Nenhum servidor habilitado: mantém o grid vazio silenciosamente
            grid.innerHTML = '';
            return;
        }

        // Só substitui os cards estáticos depois que os dinâmicos carregaram com sucesso
        grid.innerHTML = '';
        dados.servidores.forEach((servidor) => {
            const cardEl = criarCardServidor(servidor);
            grid.appendChild(cardEl);
        });
    } catch (erro) {
        // Em caso de falha, mantém os cards estáticos do HTML como fallback
        console.error('Não foi possível carregar os servidores dinamicamente:', erro);
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

    try {
        const resposta = await fetch(PARCEIROS_API_URL);
        if (!resposta.ok) throw new Error('Falha na requisição');

        const dados = await resposta.json();
        if (!dados.success || !Array.isArray(dados.parceiros)) {
            throw new Error('Resposta inválida da API');
        }

        if (dados.parceiros.length === 0) {
            grid.innerHTML = '<p class="text-muted text-center" style="grid-column: 1/-1; color: var(--color-text-muted);">Nenhum parceiro cadastrado no momento.</p>';
            return;
        }

        grid.innerHTML = '';
        dados.parceiros.forEach((parceiro) => {
            const cardEl = criarCardParceiro(parceiro);
            grid.appendChild(cardEl);
        });
    } catch (erro) {
        console.error('Não foi possível carregar os parceiros dinamicamente:', erro);
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
