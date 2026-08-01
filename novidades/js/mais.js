document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("all-news-container");

    if (!container) {
        console.error("ERRO: O id 'all-news-container' não foi encontrado no HTML.");
        return;
    }

    container.innerHTML = `<p class="all-news-empty">Carregando novidades...</p>`;

    const categoryLabels = {
        atm10: "NerdSky",
        xomaps: "Potato Nerd",
        nerddead: "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim().toLowerCase();

    fetch("https://redenerds.com.br/novidades.php")
        .then(res => res.json())
        .then(entries => {
            if (entries.length === 0) {
                container.innerHTML = `<p class="all-news-empty">Nenhuma novidade por enquanto.</p>`;
                return;
            }

            container.innerHTML = entries.map(news => {
                const categoryKey = toCategoryKey(news.category);
                const categoryLabel = categoryLabels[categoryKey] || news.category || "";
                const firstParagraph = String(news.conteudo || "").split("\n")[0].trim();

                return `
                <a class="all-news-card" href="novidade.html?id=${news.id}" data-category="${categoryKey}">
                    <div class="all-news-image">
                        <img src="${news.capa}" alt="${news.titulo}">
                    </div>
                    <div class="all-news-content">
                        ${categoryLabel ? `<span class="all-news-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                        <h3 class="all-news-title">${news.titulo}</h3>
                        <p class="all-news-desc">${firstParagraph}</p>
                        <div class="all-news-footer">
                            <div class="all-news-author">
                                <img class="all-news-author-head" src="https://mc-heads.net/avatar/${news.autor}" alt="${news.autor}">
                                <p>${news.autor}</p>
                            </div>
                            <p class="all-news-date">${new Date(news.criado_em).toLocaleDateString("pt-BR")}</p>
                        </div>
                        <span class="all-news-link">
                            Ler mais <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
                `;
            }).join("");
        })
        .catch(err => {
            console.error("Erro ao carregar novidades:", err);
            container.innerHTML = `<p class="all-news-empty" style="color: red;">Erro ao carregar novidades.</p>`;
        });
});