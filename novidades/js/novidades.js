document.addEventListener("DOMContentLoaded", () => {
    const newsContainer = document.getElementById("news-container");

    if (!newsContainer) {
        console.error("ERRO: O id 'news-container' não foi encontrado no HTML.");
        return;
    }

    newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Carregando novidades...</p>`;

    if (!Array.isArray(window.NOVIDADES)) {
        const message = "Lista de novidades não encontrada.";
        console.error(message);
        newsContainer.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">${message}</p>`;
        return;
    }

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();

    // Mais recente primeiro; limita a 3 cards no novidades.html (demais ficam no mais.html)
    const entries = window.NOVIDADES.slice().reverse().slice(0, 3);

    if (entries.length === 0) {
        newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma novidade por enquanto.</p>`;
        return;
    }

    newsContainer.innerHTML = entries.map(news => {
        const categoryKey = toCategoryKey(news.category);
        const categoryLabel = categoryLabels[categoryKey] || news.category || "";
        return `
        <a class="news-div" href="novidade.html?id=${news.id}" data-category="${categoryKey}">
            <div class="news-div-banner">
                <img class="news-img" src="${news.image}" alt="${news.title}">
            </div>
            <div class="news-div-content">
                ${categoryLabel ? `<span class="news-div-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                <h3 class="news-div-title">${news.title}</h3>
                <div class="news-div-footer">
                    <div class="author">
                        <img class="author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                        <p>${news.author}</p>
                    </div>
                    <div class="date">
                        <p>${news.date}</p>
                    </div>
                </div>
                <span class="news-div-link">
                    Ler mais <i class="fa-solid fa-arrow-right"></i>
                </span>
            </div>
        </a>
    `;
    }).join("");
});