/**
 * news-page.js - Visualizador seguro de novidades com sanitização anti-XSS rigorosa
 */

function escapeHTML(str) {
    return String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function sanitizeUrl(url, fallback = "") {
    if (!url || typeof url !== "string") return fallback;
    const trimmed = url.trim();
    // Permite apenas protocolos seguros, caminhos relativos ou âncoras
    if (/^(https?:\/\/|\/|\.\.\/|data:image\/|#)/i.test(trimmed)) {
        return trimmed;
    }
    return fallback;
}

function sanitizeHTML(dirtyHtml) {
    if (!dirtyHtml) return "";

    // Camada 1: DOMPurify (padrão ouro da indústria)
    if (typeof DOMPurify !== "undefined" && typeof DOMPurify.sanitize === "function") {
        return DOMPurify.sanitize(dirtyHtml, {
            USE_PROFILES: { html: true },
            ALLOWED_TAGS: [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'hr',
                'strong', 'b', 'em', 'i', 's', 'del', 'u', 'strike',
                'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'a', 'img', 'div', 'span', 'mark'
            ],
            ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'target', 'rel', 'loading', 'data-category'],
            FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'base', 'meta', 'link'],
            FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur', 'onchange', 'onsubmit', 'onkeydown', 'onkeypress', 'onkeyup'],
            ALLOW_DATA_ATTR: false
        });
    }

    // Camada 2: Fallback nativo via DOMParser isolado (sem execução de scripts)
    try {
        const doc = new DOMParser().parseFromString(dirtyHtml, 'text/html');
        const allowedTags = new Set([
            'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'BR', 'HR',
            'STRONG', 'B', 'EM', 'I', 'S', 'DEL', 'U', 'STRIKE',
            'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE',
            'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD',
            'A', 'IMG', 'DIV', 'SPAN', 'MARK'
        ]);
        const allowedAttrs = new Set(['href', 'src', 'alt', 'title', 'class', 'target', 'rel', 'loading', 'data-category']);

        function sanitizeNode(node) {
            const children = Array.from(node.childNodes);
            for (const child of children) {
                if (child.nodeType === Node.ELEMENT_NODE) {
                    const tag = child.tagName.toUpperCase();
                    if (!allowedTags.has(tag)) {
                        child.remove();
                        continue;
                    }

                    // Limpa e valida atributos
                    const attrs = Array.from(child.attributes);
                    for (const attr of attrs) {
                        const name = attr.name.toLowerCase();
                        const val = attr.value.trim().toLowerCase();

                        if (!allowedAttrs.has(name) || name.startsWith('on')) {
                            child.removeAttribute(attr.name);
                        } else if ((name === 'href' || name === 'src') && !/^(https?:\/\/|\/|\.\.\/|data:image\/|#)/i.test(val)) {
                            // Bloqueia javascript:, vbscript:, data:text/html, etc.
                            child.removeAttribute(attr.name);
                        }
                    }

                    // Protege links externos
                    if (tag === 'A' && child.hasAttribute('href')) {
                        const href = child.getAttribute('href') || '';
                        if (/^https?:\/\//i.test(href)) {
                            child.setAttribute('target', '_blank');
                            child.setAttribute('rel', 'noopener noreferrer');
                        }
                    }

                    sanitizeNode(child);
                }
            }
        }

        sanitizeNode(doc.body);
        return doc.body.innerHTML;
    } catch (e) {
        console.error("Erro no sanitizador de HTML:", e);
        return escapeHTML(dirtyHtml);
    }
}

function parseDescription(rawText) {
    if (!rawText) return "";

    let text = String(rawText).replace(/\\n/g, "\n");

    // Processa Callouts Gamer (Dica, Aviso, Perigo, Comando)
    text = text.replace(/^>\s*\[!(TIP|DICA)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-tip"><div class="callout-header"><i class="fa-solid fa-lightbulb"></i> Dica</div><div class="callout-body">${escapeHTML(body)}</div></div>\n\n`;
    }).replace(/^>\s*\[!(WARNING|AVISO|ATENCAO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-warning"><div class="callout-header"><i class="fa-solid fa-triangle-exclamation"></i> Atenção</div><div class="callout-body">${escapeHTML(body)}</div></div>\n\n`;
    }).replace(/^>\s*\[!(DANGER|PERIGO|BANIDO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-danger"><div class="callout-header"><i class="fa-solid fa-shield-halved"></i> Perigo</div><div class="callout-body">${escapeHTML(body)}</div></div>\n\n`;
    }).replace(/^>\s*\[!(COMMAND|COMANDO)\]\s*\n((?:>.*\n?)+)/gim, (match, type, content) => {
        const body = content.replace(/^>\s?/gm, '').trim();
        return `<div class="novidade-callout callout-command"><div class="callout-header"><i class="fa-solid fa-terminal"></i> Comando In-game</div><div class="callout-body"><code>${escapeHTML(body)}</code></div></div>\n\n`;
    });

    // Detecta imagens soltas (linhas avulsas com apenas a URL, para retrocompatibilidade)
    const standaloneImgRegex = /^\s*(https?:\/\/[^\s\)]+\.(?:png|jpg|jpeg|gif|webp)(?:\?[^\s\)]*)?|\.\.\/[^\s\)]+\.(?:png|jpg|jpeg|gif|webp))\s*$/gim;
    const standaloneImages = [];
    text = text.replace(standaloneImgRegex, (match, url) => {
        const safe = sanitizeUrl(url);
        if (safe) standaloneImages.push(safe);
        return "";
    });

    // Configura parser Markdown
    if (typeof marked !== "undefined") {
        if (typeof marked.setOptions === "function") {
            marked.setOptions({ breaks: true, gfm: true });
        } else if (typeof marked.use === "function") {
            marked.use({ breaks: true, gfm: true });
        }
    }

    let parsedHtml = typeof marked !== "undefined" && typeof marked.parse === "function" 
        ? marked.parse(text) 
        : escapeHTML(text);

    if (standaloneImages.length > 0) {
        parsedHtml += standaloneImages.map(url =>
            `<div class="img-container"><img src="${escapeHTML(url)}" alt="Imagem da notícia" loading="lazy"></div>`
        ).join("");
    }

    // APLICAÇÃO RIGOROSA DE SANITIZAÇÃO ANTI-XSS
    return sanitizeHTML(parsedHtml);
}

document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("novidade-container");
    const params = new URLSearchParams(window.location.search);
    const rawId = params.get("id");
    const id = rawId ? rawId.replace(/[^0-9]/g, "") : null;

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

        document.title = (news.titulo || "Novidade") + " - RedeNerds";

        const safeSetMeta = (sel, attr, val) => {
            const el = document.querySelector(sel);
            if (el) el.setAttribute(attr, val);
        };
        const rawConteudo = String(news.conteudo || "").replace(/<[^>]+>/g, "").replace(/\\n/g, " ");
        const descricaoCurta = rawConteudo.substring(0, 150);
        const safeCapa = sanitizeUrl(news.capa || "", "");
        const safeAutor = escapeHTML(news.autor || "Admin");
        const safeNick = encodeURIComponent(String(news.autor || "Steve").trim());
        const safeTitulo = escapeHTML(news.titulo || "");

        safeSetMeta('meta[name="description"]', "content", descricaoCurta);
        safeSetMeta('meta[property="og:title"]', "content", news.titulo || "");
        safeSetMeta('meta[property="og:description"]', "content", descricaoCurta);
        safeSetMeta('meta[property="og:image"]', "content", safeCapa);

        const categoryKey = toCategoryKey(news.category);
        const categoryLabel = categoryLabels[categoryKey] || categoryKey;

        const dataFormatada = news.criado_em
            ? new Date(String(news.criado_em).replace(" ", "T")).toLocaleDateString("pt-BR")
            : "";

        container.innerHTML = `
            <a href="/#atualizacoes-section" id="voltar">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
            <article class="novidade-article" data-category="${escapeHTML(categoryKey)}">
                <div class="novidade-banner">
                    <img src="${escapeHTML(safeCapa)}" alt="${safeTitulo}" onerror="this.style.display='none'">
                </div>
                <div class="novidade-meta">
                    ${categoryLabel ? `<span class="novidade-tag" data-category="${escapeHTML(categoryKey)}">${escapeHTML(categoryLabel)}</span>` : ""}
                    <h1>${safeTitulo}</h1>
                    <div class="novidade-info">
                        <div class="author">
                            <img class="author-head" src="https://mc-heads.net/avatar/${safeNick}/32" alt="${safeAutor}" onerror="this.style.display='none'">
                            <p>${safeAutor}</p>
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

    const apiUrl = `/api/novidades_api.php?id=${encodeURIComponent(id)}`;
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
