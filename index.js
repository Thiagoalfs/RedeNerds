/**
 * index.js
 * Busca os servidores habilitados em /api/servidores_api.php e monta os
 * cards dentro de .servers-grid, com o mesmo visual dos cards estáticos
 * originais — mas com a cor de cada servidor vindo do banco (themecolor).
 */

const SERVIDORES_API_URL = '/api/servidores_api.php';

document.addEventListener('DOMContentLoaded', carregarServidores);

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
        dados.servidores.forEach(servidor => {
            grid.appendChild(criarCardServidor(servidor));
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

    // Cores dinâmicas via CSS custom properties (ver index.css)
    card.style.setProperty('--card-color', cor);
    card.style.setProperty('--card-shadow', hexParaRgba(cor, 0.15));
    card.style.setProperty('--card-hover-shadow', hexParaRgba(cor, 0.45));
    card.style.setProperty('--card-hover-border', clarear(cor, 25));
    card.style.setProperty('--card-gradient', `linear-gradient(135deg, ${escurecer(cor, 45)} 0%, ${cor} 100%)`);

    // Banner: usa o ícone do servidor em destaque sobre um gradiente na cor do tema
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
    link.innerHTML = 'Ver detalhes <i class="fa-solid fa-arrow-right"></i>';

    content.appendChild(titulo);
    content.appendChild(descricao);
    content.appendChild(link);

    card.appendChild(banner);
    card.appendChild(content);

    return card;
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
