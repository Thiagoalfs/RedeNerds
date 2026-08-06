document.addEventListener("DOMContentLoaded", function () {
    carregarServidor();
});

async function carregarServidor() {
    // 1. Captura o parâmetro 'servidor' da URL (ex: ?servidor=nerdsky)
    const urlParams = new URLSearchParams(window.location.search);
    let key = urlParams.get("servidor");

    try {
        const response = await fetch("servidores.json");
        if (!response.ok) throw new Error("Erro ao carregar dados dos servidores.");
        
        const servidores = await response.json();

        // Se o parâmetro não existir ou não estiver no JSON, define 'nerdsky' como padrão
        if (!key || !servidores[key]) {
            key = "nerdsky";
        }

        const server = servidores[key];

        // 2. Atualiza o título da página
        document.title = server.title;

        // 3. Injeta as cores dinamicamente no :root CSS
        document.documentElement.style.setProperty("--theme-color", server.themeColor);
        document.documentElement.style.setProperty("--theme-shadow-color", server.themeShadow);
        document.documentElement.style.setProperty("--theme-hover-bg", server.themeHoverBg);

        // 5. Preenche a Seção 'Sobre o Servidor'
        const titleContainer = document.getElementById("server-section-title");
        titleContainer.querySelector("span").textContent = server.serverName;
        
        const iconEl = document.getElementById("server-icon");
        iconEl.className = server.icon;

        document.getElementById("server-about").textContent = server.aboutText;

        // 6. Preenche a lista de features
        const featuresList = document.getElementById("server-features");
        featuresList.innerHTML = server.features
            .map(feature => `<li><i class="fa-solid fa-check"></i> ${feature}</li>`)
            .join("");

        // 7. Configura os botões de ação
        document.getElementById("btn-modpack").href = server.modpackUrl;
        
        const copyIpBtn = document.getElementById("btn-copy-ip");
        copyIpBtn.setAttribute("data-copy-ip", server.ip);

    } catch (error) {
        console.error("Erro ao carregar servidor:", error);
    }
}