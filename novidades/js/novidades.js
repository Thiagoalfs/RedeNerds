document.addEventListener("DOMContentLoaded", () => {
    const newsContainer = document.getElementById("news-container");

    if (!newsContainer) {
        console.error("ERRO: O id 'news-container' não foi encontrado no HTML.");
        return;
    }

    newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Carregando novidades...</p>`;

    fetch("/novidades/json/novidades.json")
        .then(response => {
            if (!response.ok) {
                throw new Error(`Não encontrei o arquivo JSON. Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const entries = Object.entries(data).reverse();

            if (entries.length === 0) {
                newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma novidade por enquanto.</p>`;
                return;
            }

            newsContainer.innerHTML = entries.map(([id, news]) => `
                <a class="news-div" href="novidade.html?id=${id}">
                    <img class="news-img" src="${news.image}" alt="${news.title}">
                    <div class="news-header">
                        <h3>${news.title}</h3>
                        <h4 class="news-tag">${news.tag}</h4>
                    </div>
                    <div class="news-footer">
                        <div class="author">
                            <img class="author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                            <p>${news.author}</p>
                        </div>
                        <div class="date">
                            <p>${news.date}</p>
                        </div>
                    </div>
                </a>
            `).join("");
        })
        .catch(error => {
            console.error(error);
            newsContainer.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar novidades: ${error.message}</p>`;
        });
});