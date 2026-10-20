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

    let text = String(rawText).replace(/\\n/g, "\n");

    // Processa Callouts (Dica, Aviso, Perigo, Comando) antes do parser Markdown
    text = text.replace(/^>\s*\[!(TIP|DICA)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-tip"><div class="callout-header"><i class="fa-solid fa-lightbulb"></i> Dica</div><div class="callout-body">${body}</div></div>\n\n`;
    }).replace(/^>\s*\[!(WARNING|AVISO|ATENCAO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-warning"><div class="callout-header"><i class="fa-solid fa-triangle-exclamation"></i> Atenção</div><div class="callout-body">${body}</div></div>\n\n`;
    }).replace(/^>\s*\[!(DANGER|PERIGO|BANIDO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-danger"><div class="callout-header"><i class="fa-solid fa-shield-halved"></i> Perigo</div><div class="callout-body">${body}</div></div>\n\n`;
    }).replace(/^>\s*\[!(COMMAND|COMANDO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-command"><div class="callout-header"><i class="fa-solid fa-terminal"></i> Comando In-game</div><div class="callout-body"><code>${body}</code></div></div>\n\n`;
    });

    // Detecta imagens soltas (linhas que contêm apenas uma URL de imagem avulsa, para retrocompatibilidade)
    const standaloneImgRegex = /^\s*(https?:\/\/[^\s\)]+\.(?:png|jpg|jpeg|gif|webp)(?:\?[^\s\)]*)?|\.\.\/[^\s\)]+\.(?:png|jpg|jpeg|gif|webp))\s*$/gim;
    const standaloneImages = [];
    text = text.replace(standaloneImgRegex, (match, url) => {
        if (url) standaloneImages.push(url.trim());
        return "";
    });

    if (typeof marked !== "undefined") {
        if (typeof marked.setOptions === "function") {
            marked.setOptions({ breaks: true, gfm: true });
        } else if (typeof marked.use === "function") {
            marked.use({ breaks: true, gfm: true });
        }
    }

    let html = typeof marked !== "undefined" && typeof marked.parse === "function" 
        ? marked.parse(text) 
        : text;

    if (standaloneImages.length > 0) {
        html += standaloneImages.map(url =>
            `<div class="img-container"><img src="${escapeHTML(url)}" alt="Imagem da notícia" loading="lazy"></div>`
        ).join("");
    }

    return html;
}

document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("novidade-container");
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id");

    if (!id) {
        container.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma atualização especificada.</p>`;
        return;
    }

    const categoryLabels = {
        "NerdSky": "NerdSky",
        "Potato Nerd": "Potato Nerd",
        "NerdDead": "Nerd Dead",
        "CobbleNerd": "CobbleNerd",
        "Sistemas": "Sistemas",
        "Potato Sky": "Potato Sky"
    };

    const toCategoryKey = value => String(value || "").trim();

    const renderNewsArticle = (news) => {
        if (!news || news.erro) {
            container.innerHTML = `<p style="text-align:center; padding: 20px;">Atualização não encontrada.</p>`;
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
            <a href="/#atualizacoes-section" id="voltar">
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
    };

    const apiUrl = `/api/novidades_api.php?id=${id}`;
    if (window.AppCache) {
        AppCache.fetchWithCache('novidades_id_' + id, apiUrl, {
            onCached: renderNewsArticle,
            onFresh: renderNewsArticle,
            onError: (err) => {
                console.error("Erro ao carregar atualização:", err);
                if (!container.children.length) {
                    container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar atualização.</p>`;
                }
            }
        });
    } else {
        fetch(apiUrl)
            .then(res => res.json())
            .then(renderNewsArticle)
            .catch(err => {
                console.error("Erro ao carregar atualização:", err);
                container.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar atualização.</p>`;
            });
    }
});