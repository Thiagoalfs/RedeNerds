function escapeHTML(str) {
    return String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function parseDescription(rawText) {
    if (!rawText) return "";

    // Garante que sempre teremos \n real para fazer o split (banco pode mandar \\n escapado)
    const text = String(rawText).replace(/\\n/g, "\n");

    // Regex para URLs de imagem (http ou caminhos relativos ../)
    const imageRegex = /https?:\/\/[^\s]+\.(?:png|jpg|jpeg|gif|webp)(?:\?[^\s]*)?|(?<=\s|^)\.\.\/[^\s]+\.(?:png|jpg|jpeg|gif|webp)/gi;

    const images = [];
    const textWithoutImages = text.replace(imageRegex, match => {
        const url = match.trim();
        if (url) images.push(url);
        return "";
    });

    const paragraphs = textWithoutImages
        .split("\n")
        .map(line => line.trim())
        .filter(line => line !== "");

    let html = paragraphs.map(line => `<p>${escapeHTML(line)}</p>`).join("");

    html += images.map(url =>
        `<div class="img-container"><img src="${escapeHTML(url)}" alt="Imagem da notícia" loading="lazy"></div>`
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
        "NerdSky": "NerdSky",
        "Potato Nerd": "Potato Nerd",
        "NerdDead": "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim();

    fetch(`https://redenerds.com.br/novidades.php?id=${id}`)
        .then(res => res.json())
        .then(news => {
            if (!news || news.erro) {
                container.innerHTML = `<p style="text-align:center; padding: 20px;">Notícia não encontrada.</p>`;
                return;
            }

            document.title = news.titulo + " - RedeNerds";
            const safeSetMeta = (sel, attr, val) => {
                const el = document.querySelector(sel);
                if (el) el.setAttribute(attr, val);
            };
            const descricaoCurta = String(news.conteudo || "").substring(0, 150);
            safeSetMeta('meta[name="description"]', "content", descricaoCurta);
            safeSetMeta('meta[property="og:title"]', "content", news.titulo || "");
            safeSetMeta('meta[property="og:description"]', "content", descricaoCurta);
            safeSetMeta('meta[property="og:image"]', "content", news.capa || "");

            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || categoryKey;

                        // Data: backend pode mandar "YYYY-MM-DD HH:MM:SS" → troca espaço por "T" pro Safari
            const dataFormatada = news.criado_em
                ? new Date(String(news.criado_em).replace(" ", "T")).toLocaleDateString("pt-BR")
                : "";

            container.innerHTML = `
                <a href="novidades.html" id="voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <article class="novidade-article" data-category="${escapeHTML(categoryKey)}">
                    <div class="novidade-banner">
                        <img src="${escapeHTML(news.capa || "")}" alt="${escapeHTML(news.titulo || "")}" onerror="this.style.display='none'">
                    </div>
                    <div class="novidade-meta">
                        ${categoryLabel ? `<span class="novidade-tag" data-category="${escapeHTML(categoryKey)}">${escapeHTML(categoryLabel)}</span>` : ""}
                        <h1>${escapeHTML(news.titulo || "")}</h1>
                        <div class="novidade-info">
                            <div class="author">
                                <img class="author-head" src="https://mc-heads.net/avatar/${escapeHTML(news.autor || "")}" alt="${escapeHTML(news.autor || "")}" onerror="this.style.display='none'">
                                <p>${escapeHTML(news.autor || "")}</p>
                            </div>
                            <p class="date">${dataFormatada}</p>
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