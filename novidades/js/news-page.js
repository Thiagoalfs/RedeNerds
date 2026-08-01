function parseDescription(text) {
    const imageRegex = /https?:\/\/\S+\.(?:png|jpg|jpeg|gif|webp)|(?<=\s|^)\.\.\/[^\s]+\.(?:png|jpg|jpeg|gif|webp)/gi;

    text = text.replace(/\\n/g, "\n");

    const images = [];
    const sanitized = text.replace(imageRegex, match => {
        const url = match.trim();
        if (url) images.push(url);
        return "";
    });

    const paragraphs = sanitized
        .split("\n")
        .map(line => line.trim())
        .filter(line => line !== "");

    let html = paragraphs.map(line => `<p>${line}</p>`).join("");

    html += images.map(url =>
        `<div class="img-container"><img src="${url}" alt="Imagem da notícia" style="display:block; margin: 16px 0; border-radius: 8px;"></div>`
    ).join("");

    return html;
}
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("novidade-container");
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id");

    if (!id) {
        container.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma notícia especificada.</p>`;
        return;
    }

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();

    fetch(`https://redenerds.com.br/novidades.php?id=${id}`)
        .then(res => res.json())
        .then(news => {
            if (!news || news.erro) {
                container.innerHTML = `<p style="text-align:center; padding: 20px;">Notícia não encontrada.</p>`;
                return;
            }

            document.title = news.titulo + " - RedeNerds";
            document.querySelector('meta[name="description"]').setAttribute("content", news.conteudo.substring(0, 150));
            document.querySelector('meta[property="og:title"]').setAttribute("content", news.titulo);
            document.querySelector('meta[property="og:image"]').setAttribute("content", news.capa);

            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || news.category || "";

            container.innerHTML = `
                <a href="novidades.html" id="voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <article class="novidade-article" data-category="${categoryKey}">
                    <div class="novidade-banner">
                        <img src="${news.capa}" alt="${news.titulo}">
                    </div>
                    <div class="novidade-meta">
                        ${categoryLabel ? `<span class="novidade-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                        <h1>${news.titulo}</h1>
                        <div class="novidade-info">
                            <div class="author">
                                <img class="author-head" src="https://mc-heads.net/avatar/${news.autor}" alt="${news.autor}">
                                <p>${news.autor}</p>
                            </div>
                            <p class="date">${new Date(news.criado_em).toLocaleDateString("pt-BR")}</p>
                        </div>
                    </div>
                    <div class="novidade-body">
                        ${parseDescription(news.conteudo)}
                    </div>
                </article>
            `;
        })
        .catch(err => {
            console.error("Erro ao carregar notícia:", err);
            container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar notícia.</p>`;
        });
});