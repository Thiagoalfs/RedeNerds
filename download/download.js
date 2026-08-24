/**
 * download.js
 * Busca os servidores habilitados em /api/servidores_api.php (mesma API usada
 * na home) e monta os cards dentro de .downloads-list, com o mesmo visual
 * dos cards estáticos originais — usando modpackurl e ip vindos do banco.
 */

// Caminho absoluto: este arquivo roda em ~/download, mas a API fica na raiz do site.
const SERVIDORES_API_URL = '/api/servidores_api.php';

document.addEventListener('DOMContentLoaded', carregarDownloads);

async function carregarDownloads() {
    const lista = document.querySelector('.downloads-list');
    if (!lista) return;

    try {
        const resposta = await fetch(SERVIDORES_API_URL);
        if (!resposta.ok) throw new Error('Falha na requisição');

        const dados = await resposta.json();
        if (!dados.success || !Array.isArray(dados.servidores)) {
            throw new Error('Resposta inválida da API');
        }

        if (dados.servidores.length === 0) {
            lista.innerHTML = '';
            return;
        }

        // Só substitui os cards estáticos depois que os dinâmicos carregaram com sucesso
        lista.innerHTML = '';
        dados.servidores.forEach(servidor => {
            lista.appendChild(criarCardDownload(servidor));
        });
    } catch (erro) {
        // Em caso de falha, mantém os cards estáticos do HTML como fallback
        console.error('Não foi possível carregar os downloads dinamicamente:', erro);
    }
}

function criarCardDownload(servidor) {
    const cor = servidor.themecolor || '#7DB9DF';

    const card = document.createElement('article');
    card.className = 'download-card';

    card.style.setProperty('--card-color', cor);
    card.style.setProperty('--card-shadow', hexParaRgba(cor, 0.15));
    card.style.setProperty('--card-hover-shadow', hexParaRgba(cor, 0.45));
    card.style.setProperty('--card-hover-border', clarear(cor, 25));
    card.style.setProperty('--card-gradient', `linear-gradient(135deg, ${escurecer(cor, 45)} 0%, ${cor} 100%)`);

    // Imagem/ícone
    const imagem = document.createElement('div');
    imagem.className = 'download-card-image';

    if (servidor.icon_type === 'img' && servidor.icon) {
        const img = document.createElement('img');
        img.src = servidor.icon;
        img.alt = servidor.servername;
        img.onerror = () => { img.style.display = 'none'; };
        imagem.appendChild(img);
    } else {
        const icone = document.createElement('i');
        icone.className = servidor.icon || 'fa-solid fa-server';
        imagem.appendChild(icone);
    }

    // Texto
    const texto = document.createElement('div');
    texto.className = 'download-card-text';

    const titulo = document.createElement('h3');
    titulo.className = 'download-card-title';
    titulo.textContent = servidor.servername;

    const desc = document.createElement('p');
    desc.className = 'download-card-desc';
    desc.textContent = servidor.descricao;

    texto.appendChild(titulo);
    texto.appendChild(desc);

    // Botões
    const botoes = document.createElement('div');
    botoes.className = 'download-card-buttons';

    const btnBaixar = document.createElement('a');
    btnBaixar.href = servidor.modpackurl;
    btnBaixar.target = '_blank';
    btnBaixar.rel = 'noopener';
    btnBaixar.className = 'download-card-btn';
    btnBaixar.innerHTML = 'Baixar modpack <i class="fa-solid fa-arrow-up-right-from-square"></i>';

    const btnCopiar = document.createElement('button');
    btnCopiar.type = 'button';
    btnCopiar.className = 'download-card-btn copy-ip-btn';
    btnCopiar.dataset.copyIp = servidor.ip;
    btnCopiar.innerHTML = 'Copiar IP <i class="fa-solid fa-copy"></i>';
    btnCopiar.addEventListener('click', () => copiarIp(servidor.ip, btnCopiar));

    botoes.appendChild(btnBaixar);
    botoes.appendChild(btnCopiar);

    card.appendChild(imagem);
    card.appendChild(texto);
    card.appendChild(botoes);

    return card;
}

/**
 * Copia o IP para a área de transferência e dá um feedback visual no botão
 * (não depende do shared/copy-ip.js, já que os botões são criados depois
 * que aquele script já rodou sua checagem inicial de elementos).
 */
function copiarIp(ip, botao) {
    const textoOriginal = botao.innerHTML;

    const aoCopiar = () => {
        botao.classList.add('copied');
        botao.innerHTML = 'IP copiado! <i class="fa-solid fa-check"></i>';
        setTimeout(() => {
            botao.classList.remove('copied');
            botao.innerHTML = textoOriginal;
        }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ip).then(aoCopiar).catch(() => copiarIpFallback(ip, aoCopiar));
    } else {
        copiarIpFallback(ip, aoCopiar);
    }
}

function copiarIpFallback(ip, aoCopiar) {
    const campo = document.createElement('textarea');
    campo.value = ip;
    campo.style.position = 'fixed';
    campo.style.opacity = '0';
    document.body.appendChild(campo);
    campo.select();
    try {
        document.execCommand('copy');
        aoCopiar();
    } catch (e) {
        console.error('Não foi possível copiar o IP:', e);
    }
    document.body.removeChild(campo);
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
