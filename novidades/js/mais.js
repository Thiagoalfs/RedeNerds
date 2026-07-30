document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("all-news-container");

    if (!container) {
        console.error("ERRO: O id 'all-news-container' não foi encontrado no HTML.");
        return;
    }

    container.innerHTML = `<p class="all-news-empty">Carregando novidades...</p>`;

    if (!Array.isArray(window.NOVIDADES)) {
        const message = "Lista de novidades não encontrada.";
        console.error(message);
        container.innerHTML = `<p class="all-news-empty" style="color: red;">${message}</p>`;
        return;
    }

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();

    // Mais recente primeiro (assume ordem cronológica crescente no array)
    const entries = window.NOVIDADES.slice().reverse();

    if (entries.length === 0) {
        container.innerHTML = `<p class="all-news-empty">Nenhuma novidade por enquanto.</p>`;
        return;
    }

    container.innerHTML = entries.map(news => {
        const categoryKey = toCategoryKey(news.category);
        const categoryLabel = categoryLabels[categoryKey] || news.category || "";

        // Pega só o primeiro parágrafo da descrição para o excerpt
        const firstParagraph = String(news.description || "").split("\n")[0].trim();

        return `
        <a class="all-news-card" href="novidade.html?id=${news.id}" data-category="${categoryKey}">
            <div class="all-news-image">
                <img src="${news.image}" alt="${news.title}">
            </div>
            <div class="all-news-content">
                ${categoryLabel ? `<span class="all-news-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                <h3 class="all-news-title">${news.title}</h3>
                <p class="all-news-desc">${firstParagraph}</p>
                <div class="all-news-footer">
                    <div class="all-news-author">
                        <img class="all-news-author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                        <p>${news.author}</p>
                    </div>
                    <p class="all-news-date">${news.date}</p>
                </div>
                <span class="all-news-link">
                    Ler mais <i class="fa-solid fa-arrow-right"></i>
                </span>
            </div>
        </a>
    `;
    }).join("");
});
