function parseDescription(text) {
    const imageRegex = /https?:\/\/\S+\.(?:png|jpg|jpeg|gif|webp)|\.\.\/[^\s]+\.(?:png|jpg|jpeg|gif|webp)/gi;

    const parts = text.split(imageRegex);
    const images = text.match(imageRegex) || [];

    let html = "";

    parts.forEach((part, index) => {
        if (part) {
            const lines = part.split("\n").map(line => line.trim()).filter(line => line !== "");
            lines.forEach(line => {
                html += `<p>${line}</p>`;
            });
        }

        if (images[index]) {
            html += `<div class="img-container"><img src="${images[index]}" alt="Imagem da notícia" style="display:block; margin: 16px 0; border-radius: 8px;"></div>`;
        }
    });

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

    fetch("/novidades/json/novidades.json")
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
                <div id="novidade-container">
                    <a href="novidades.html" id="voltar">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </a>
                    <div id="novidade-img-div">
                        <img id="novidade-img" src="${news.image}" alt="${news.title}">
                    </div>
                    <div id="novidade-meta">
                        <div id="novidade-header">
                            <h1>${news.title}</h1>
                            <p class="date">${news.date}</p>
                        </div>
                        <div id="novidade-info">
                            <div class="author inline">
                                <img class="author-head" src="https://mc-heads.net/avatar/${news.author}" alt="${news.author}">
                                <p>${news.author}</p>
                            </div>
                        </div>
                    </div>
                    <div id="novidade-body">
                        ${parseDescription(news.description)}
                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error(error);
            container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro: ${error.message}</p>`;
        });
});