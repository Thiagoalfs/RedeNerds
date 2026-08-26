/**
 * server-loader.js - Controlador da Página de Detalhes do Servidor (Rede Nerds)
 * Carrega dinamicamente as informações do servidor a partir da API oficial.
 */

document.addEventListener("DOMContentLoaded", function () {
    carregarServidor();
});

const SERVIDORES_API_URL = "/api/servidores_api.php";

async function carregarServidor() {
    const urlParams = new URLSearchParams(window.location.search);
    const key = (urlParams.get("servidor") || "").toLowerCase().trim();

    try {
        const response = await fetch(SERVIDORES_API_URL);
        if (!response.ok) throw new Error("Erro ao carregar dados dos servidores.");

        const dados = await response.json();
        if (!dados.success || !Array.isArray(dados.servidores)) {
            throw new Error("Resposta inválida da API.");
        }

        const servidores = dados.servidores;
        if (servidores.length === 0) {
            throw new Error("Nenhum servidor disponível no momento.");
        }

        // Busca pelo slug (nome) vindo da URL; se não encontrar, usa o primeiro servidor ativo
        const server = servidores.find(s => {
            const sSlug = String(s.nome || "").toLowerCase().trim();
            const sName = String(s.servername || "").toLowerCase().replace(/[^a-z0-9]/g, "");
            const cleanKey = key.replace(/[^a-z0-9]/g, "");
            return sSlug === key || sName === cleanKey;
        }) || servidores[0];

        // 1. Atualiza o título da página
        document.title = `${server.servername} - Rede Nerds`;

        // 2. Injeta as cores dinâmicas no :root CSS
        const themeColor = server.themecolor || "#7DB9DF";
        document.documentElement.style.setProperty("--theme-color", themeColor);
        document.documentElement.style.setProperty("--theme-shadow-color", hexParaRgba(themeColor, 0.2));
        document.documentElement.style.setProperty("--theme-hover-bg", hexParaRgba(themeColor, 0.12));

        // 3. Cabeçalho & Título
        const titleEl = document.getElementById("server-section-title");
        if (titleEl) titleEl.textContent = server.servername;

        // Badge descritiva
        const catBadge = document.getElementById("server-category-badge");
        if (catBadge) {
            if (server.servername.toLowerCase().includes("potato")) {
                catBadge.textContent = "Modpack Tech & Automação";
            } else if (server.servername.toLowerCase().includes("dead")) {
                catBadge.textContent = "Hardcore Survival & Apocalipse";
            } else {
                catBadge.textContent = "Servidor Oficial";
            }
        }

        // Ícone do Servidor
        atualizarIconeServidor(server);

        // IP de Conexão no Hero
        const ipDisplay = document.getElementById("server-ip-display");
        if (ipDisplay) ipDisplay.textContent = server.ip;

        // 4. Descrição / Sobre o Servidor
        const aboutEl = document.getElementById("server-about");
        if (aboutEl) aboutEl.textContent = server.descricao;

        // 5. Recursos & Destaques (Features)
        const featuresContainer = document.getElementById("server-features");
        if (featuresContainer) {
            const features = Array.isArray(server.features) ? server.features : [];
            if (features.length > 0) {
                featuresContainer.innerHTML = features
                    .map(f => `<li><i class="fa-solid fa-check"></i> <span>${escapeHtml(f)}</span></li>`)
                    .join("");
            } else {
                featuresContainer.innerHTML = `<li><i class="fa-solid fa-check"></i> <span>Experiência multiplayer estável e otimizada</span></li>`;
            }
        }

        // 6. Botões de Ação
        const btnModpack = document.getElementById("btn-modpack");
        if (btnModpack) {
            btnModpack.href = server.modpackurl || "/download";
        }

        const btnHeroCopy = document.getElementById("btn-hero-copy-ip");
        if (btnHeroCopy) {
            btnHeroCopy.addEventListener("click", () => copiarIp(server.ip, btnHeroCopy, true));
        }

        const btnSidebarCopy = document.getElementById("btn-copy-ip");
        if (btnSidebarCopy) {
            btnSidebarCopy.setAttribute("data-copy-ip", server.ip);
            btnSidebarCopy.addEventListener("click", () => copiarIp(server.ip, btnSidebarCopy, false));
        }

        // Link para a Loja VIP
        const btnVipStore = document.getElementById("btn-server-vip-store");
        if (btnVipStore) {
            btnVipStore.href = `/loja?servidor=${encodeURIComponent(server.nome || 'potatonerds')}`;
        }

    } catch (error) {
        console.error("Erro ao carregar servidor:", error);
    }
}

/**
 * Atualiza o ícone do servidor (suporta imagem ou classe FontAwesome)
 */
function atualizarIconeServidor(server) {
    const imgEl = document.getElementById("server-icon-img");
    const faEl = document.getElementById("server-icon-fa");

    if (!imgEl || !faEl) return;

    if (server.icon_type === "img" || (server.icon && server.icon.startsWith("/"))) {
        imgEl.src = server.icon;
        imgEl.alt = server.servername;
        imgEl.style.display = "block";
        faEl.style.display = "none";
        imgEl.onerror = () => {
            imgEl.style.display = "none";
            faEl.style.display = "block";
            faEl.className = "fa-solid fa-server server-hero-icon-fa";
        };
    } else {
        imgEl.style.display = "none";
        faEl.style.display = "block";
        faEl.className = `${server.icon || "fa-solid fa-server"} server-hero-icon-fa`;
    }
}

/**
 * Copia o IP para a área de transferência com feedback visual
 */
function copiarIp(ip, botao, isIconOnly = false) {
    const conteudoOriginal = botao.innerHTML;

    const aoCopiar = () => {
        botao.classList.add("copied");
        if (isIconOnly) {
            botao.innerHTML = '<i class="fa-solid fa-check"></i>';
        } else {
            botao.innerHTML = '<i class="fa-solid fa-check"></i> <span>IP Copiado com Sucesso!</span>';
        }
        setTimeout(() => {
            botao.classList.remove("copied");
            botao.innerHTML = conteudoOriginal;
        }, 2200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ip).then(aoCopiar).catch(() => copiarIpFallback(ip, aoCopiar));
    } else {
        copiarIpFallback(ip, aoCopiar);
    }
}

function copiarIpFallback(ip, aoCopiar) {
    const campo = document.createElement("textarea");
    campo.value = ip;
    campo.style.position = "fixed";
    campo.style.opacity = "0";
    document.body.appendChild(campo);
    campo.select();
    try {
        document.execCommand("copy");
        aoCopiar();
    } catch (e) {
        console.error("Não foi possível copiar o IP:", e);
    }
    document.body.removeChild(campo);
}

function escapeHtml(texto) {
    const div = document.createElement("div");
    div.textContent = texto;
    return div.innerHTML;
}

function hexParaRgba(hex, alpha) {
    const limpo = (hex || "#7DB9DF").replace("#", "");
    const bigint = parseInt(limpo, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
