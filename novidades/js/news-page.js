function parseDescription(text) {
    const imageRegex = /https?:\/\/\S+\.(?:png|jpg|jpeg|gif|webp)|(?<=\s|^)\.\.\/[^\s]+\.(?:png|jpg|jpeg|gif|webp)/gi;

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

    if (!Array.isArray(window.NOVIDADES)) {
        const message = "Lista de novidades não encontrada.";
        console.error(message);
        container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">${message}</p>`;
        return;
    }

    const news = window.NOVIDADES.find(item => String(item.id) === String(id));

    if (!news) {
        container.innerHTML = `<p style="text-align:center; padding: 20px;">Notícia não encontrada.</p>`;
        return;
    }

    document.title = news.title;

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();
    const categoryKey = toCategoryKey(news.category);
    const categoryLabel = categoryLabels[categoryKey] || news.category || "";

    container.innerHTML = `
        <a href="novidades.html" id="voltar">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
        <article class="novidade-article" data-category="${categoryKey}">
            <div class="novidade-banner">
                <img src="${news.image}" alt="${news.title}">
            </div>
            <div class="novidade-meta">
                ${categoryLabel ? `<span class="novidade-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                <h1>${news.title}</h1>
                <div class="novidade-info">
                    <div class="author">
                        <img class="author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                        <p>${news.author}</p>
                    </div>
                    <p class="date">${news.date}</p>
                </div>
            </div>
            <div class="novidade-body">
                ${parseDescription(news.description)}
            </div>
        </article>
    `;
});