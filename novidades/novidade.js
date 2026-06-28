document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("novidade-container");
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id");

    if (!id) {
        container.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma notícia especificada.</p>`;
        return;
    }

    fetch("novidades.json")
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro ao buscar o JSON. Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const news = data[id];

            if (!news) {
                container.innerHTML = `<p style="text-align:center; padding: 20px;">Notícia não encontrada.</p>`;
                return;
            }

            // Atualiza o título da aba do navegador
            document.title = news.title;

            container.innerHTML = `
                <div id="novidade-header">
                    <img id="novidade-img" src="${news.image}" alt="${news.title}">
                    <div id="novidade-meta">
                        <span class="news-tag">${news.tag}</span>
                        <h1>${news.title}</h1>
                        <div id="novidade-info">
                            <div class="author">
                                <img class="author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                                <p>${news.author}</p>
                            </div>
                            <div class="date">
                                <p>${news.date}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="novidade-body">
                    <p>${news.description}</p>
                </div>
                <a href="novidades.html" id="voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar para novidades
                </a>
            `;
        })
        .catch(error => {
            console.error(error);
            container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro: ${error.message}</p>`;
        });
});