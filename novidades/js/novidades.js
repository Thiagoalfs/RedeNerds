document.addEventListener("DOMContentLoaded", () => {
    const newsContainer = document.getElementById("news-container");

    if (!newsContainer) {
        console.error("ERRO: O id 'news-container' não foi encontrado no HTML.");
        return;
    }

    newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Carregando novidades...</p>`;

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();

    fetch("https://redenerds.com.br/novidades.php")
        .then(res => res.json())
        .then(data => {
            const entries = data.slice(0, 3);

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
                        <img class="news-img" src="${news.capa}" alt="${news.titulo}">
                    </div>
                    <div class="news-div-content">
                        ${categoryLabel ? `<span class="news-div-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                        <h3 class="news-div-title">${news.titulo}</h3>
                        <div class="news-div-footer">
                            <div class="author">
                                <img class="author-head" src="https://mc-heads.net/avatar/${news.autor}" alt="${news.autor}">
                                <p>${news.autor}</p>
                            </div>
                            <div class="date">
                                <p>${new Date(news.criado_em).toLocaleDateString("pt-BR")}</p>
                            </div>
                        </div>
                        <span class="news-div-link">
                            Ler mais <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
                `;
            }).join("");
        })
        .catch(err => {
            console.error("Erro ao carregar novidades:", err);
            newsContainer.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar novidades.</p>`;
        });
});