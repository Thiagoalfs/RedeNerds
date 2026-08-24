document.addEventListener("DOMContentLoaded", function () {
    carregarServidor();
});

// Caminho absoluto: esta página roda em ~/servidores, mas a API fica na raiz do site.
const SERVIDORES_API_URL = "/api/servidores_api.php";

async function carregarServidor() {
    // 1. Captura o parâmetro 'servidor' da URL (ex: ?servidor=nerdsky)
    const urlParams = new URLSearchParams(window.location.search);
    const key = urlParams.get("servidor");

    try {
        const response = await fetch(SERVIDORES_API_URL);
        if (!response.ok) throw new Error("Erro ao carregar dados dos servidores.");

        const dados = await response.json();
        if (!dados.success || !Array.isArray(dados.servidores)) {
            throw new Error("Resposta inválida da API.");
        }

        const servidores = dados.servidores;
        if (servidores.length === 0) {
            throw new Error("Nenhum servidor disponível.");
        }

        // Busca pelo slug (nome) vindo da URL; se não existir/não for encontrado, cai no primeiro servidor habilitado
        const server = servidores.find(s => s.nome === key) || servidores[0];

        // 2. Atualiza o título da página
        document.title = server.title;

        // 3. Injeta as cores dinamicamente no :root CSS, calculadas a partir do themecolor (hex)
        document.documentElement.style.setProperty("--theme-color", server.themecolor);
        document.documentElement.style.setProperty("--theme-shadow-color", hexParaRgba(server.themecolor, 0.2));
        document.documentElement.style.setProperty("--theme-hover-bg", hexParaRgba(server.themecolor, 0.12));

        // 5. Preenche a Seção 'Sobre o Servidor'
        const titleContainer = document.getElementById("server-section-title");
        titleContainer.querySelector("span").textContent = server.servername;

        atualizarIcone(server);

        document.getElementById("server-about").textContent = server.descricao;

        // 6. Preenche a lista de features
        const featuresList = document.getElementById("server-features");
        featuresList.innerHTML = server.features
            .map(feature => `<li><i class="fa-solid fa-check"></i> ${escapeHtml(feature)}</li>`)
            .join("");

        // 7. Configura os botões de ação
        document.getElementById("btn-modpack").href = server.modpackurl;

        const copyIpBtn = document.getElementById("btn-copy-ip");
        copyIpBtn.setAttribute("data-copy-ip", server.ip);
        copyIpBtn.addEventListener("click", () => copiarIp(server.ip, copyIpBtn));

    } catch (error) {
        console.error("Erro ao carregar servidor:", error);
    }
}

/**
 * Troca o ícone entre <i class="..."> (FontAwesome) e <img src="..."> (upload/link)
 * mantendo o mesmo id "server-icon" para o restante do código/CSS continuar funcionando.
 */
function atualizarIcone(server) {
    const atual = document.getElementById("server-icon");

    if (server.icon_type === "img" && server.icon) {
        const img = document.createElement("img");
        img.id = "server-icon";
        img.src = server.icon;
        img.alt = server.servername;
        img.onerror = () => { img.style.display = "none"; };
        atual.replaceWith(img);
    } else {
        const icone = document.createElement("i");
        icone.id = "server-icon";
        icone.className = server.icon || "fa-solid fa-server";
        atual.replaceWith(icone);
    }
}

/**
 * Copia o IP para a área de transferência e dá um feedback visual no botão.
 * Feito aqui diretamente (em vez de depender só do shared/copy-ip.js) porque
 * o valor do data-copy-ip só é conhecido depois que a API responde.
 */
function copiarIp(ip, botao) {
    const textoOriginal = botao.innerHTML;

    const aoCopiar = () => {
        botao.classList.add("copied");
        botao.innerHTML = '<i class="fa-solid fa-check"></i> IP copiado!';
        setTimeout(() => {
            botao.classList.remove("copied");
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
