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
        container.innerHTML = `
            <div class="novidade-nav-bar">
                <a href="/#atualizacoes-section" class="btn-voltar">
                    <i class="fa-solid fa-arrow-left"></i> <span>Voltar para novidades</span>
                </a>
            </div>
            <div class="novidade-article" style="padding: 48px 24px; text-align: center;">
                <i class="fa-solid fa-newspaper" style="font-size: 2.4rem; color: #64748b; margin-bottom: 16px;"></i>
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.35rem; color: #fff; margin: 0 0 8px 0;">Nenhuma atualização especificada</h2>
                <p style="color: #94a3b8; font-size: 0.95rem; margin: 0 0 24px 0;">Selecione uma notícia na página inicial para visualizá-la.</p>
                <a href="/#atualizacoes-section" class="btn-voltar" style="display: inline-flex;">
                    <i class="fa-solid fa-arrow-left"></i> <span>Ver novidades</span>
                </a>
            </div>
        `;
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
            container.innerHTML = `
                <div class="novidade-nav-bar">
                    <a href="/#atualizacoes-section" class="btn-voltar">
                        <i class="fa-solid fa-arrow-left"></i> <span>Voltar para novidades</span>
                    </a>
                </div>
                <div class="novidade-article" style="padding: 48px 24px; text-align: center;">
                    <i class="fa-solid fa-file-circle-xmark" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 16px;"></i>
                    <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.35rem; color: #fff; margin: 0 0 8px 0;">Atualização não encontrada</h2>
                    <p style="color: #94a3b8; font-size: 0.95rem; margin: 0 0 24px 0;">Esta notícia pode ter sido removida ou o link informado está incorreto.</p>
                    <a href="/#atualizacoes-section" class="btn-voltar" style="display: inline-flex;">
                        <i class="fa-solid fa-arrow-left"></i> <span>Ver todas as novidades</span>
                    </a>
                </div>
            `;
            return;
        }

        document.title = (news.titulo || "Novidade") + " - Rede Nerd";

        const safeSetMeta = (sel, attr, val) => {
            const el = document.querySelector(sel);
            if (el) el.setAttribute(attr, val);
        };
        const rawConteudo = String(news.conteudo || "").replace(/<[^>]+>/g, "").replace(/\\n/g, " ");
        const descricaoCurta = rawConteudo.substring(0, 150);
        const safeCapa = sanitizeUrl(news.capa || "", "/assets/images/fundo.webp");
        const safeAutor = escapeHTML(news.autor || "Admin");
        const safeNick = encodeURIComponent(String(news.autor || "Steve").trim());
        const safeTitulo = escapeHTML(news.titulo || "");

        safeSetMeta('meta[name="description"]', "content", descricaoCurta);
        safeSetMeta('meta[property="og:title"]', "content", news.titulo || "");
        safeSetMeta('meta[property="og:description"]', "content", descricaoCurta);
        safeSetMeta('meta[property="og:image"]', "content", safeCapa);

        const categoryKey = toCategoryKey(news.category);
        const categoryLabel = categoryLabels[categoryKey] || categoryKey;

        const words = rawConteudo.trim().split(/\s+/).filter(Boolean).length;
        const readTimeMinutes = Math.max(1, Math.ceil(words / 180));

        let dataFormatada = "";
        let dataIso = "";
        if (news.criado_em) {
            try {
                const dateObj = new Date(String(news.criado_em).replace(" ", "T"));
                dataIso = dateObj.toISOString();
                dataFormatada = dateObj.toLocaleDateString("pt-BR", {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
            } catch (_) {
                dataFormatada = String(news.criado_em);
            }
        }

        let cargoNome = news.autor_cargo || '';
        let cargoCor = news.autor_cargo_cor || '';

        function matchMemberCargo(list, nick) {
            if (!Array.isArray(list) || !nick) return null;
            const cleanNick = String(nick).trim().toLowerCase();
            for (const grupo of list) {
                if (grupo && Array.isArray(grupo.members)) {
                    const match = grupo.members.find(m => {
                        const mClean = String(m).trim().toLowerCase();
                        return mClean === cleanNick ||
                               mClean.replace(/^x+|x+$/g, '') === cleanNick.replace(/^x+|x+$/g, '') ||
                               mClean.replace(/4/g, 'a') === cleanNick.replace(/4/g, 'a');
                    });
                    if (match) {
                        return { cargo: grupo.categoryTitle, cor: grupo.cor || '#B971DA' };
                    }
                }
            }
            return null;
        }

        if (!cargoNome && window.AppCache) {
            const cachedEquipe = AppCache.get('equipe');
            const equipeList = (cachedEquipe && Array.isArray(cachedEquipe.data)) ? cachedEquipe.data : (Array.isArray(cachedEquipe) ? cachedEquipe : []);
            const found = matchMemberCargo(equipeList, news.autor);
            if (found) {
                cargoNome = found.cargo;
                cargoCor = found.cor;
            }
        }

        const cargoDisplay = cargoNome;
        const corDisplay = cargoCor || 'var(--cat-accent, #B971DA)';

        container.innerHTML = `
            <div class="novidade-nav-bar">
                <a href="/#atualizacoes-section" class="btn-voltar" id="voltar">
                    <i class="fa-solid fa-arrow-left"></i> <span>Voltar para novidades</span>
                </a>
                <button type="button" class="btn-share-top" id="btn-share-top" title="Copiar link da novidade">
                    <i class="fa-solid fa-link"></i> <span>Compartilhar</span>
                </button>
            </div>

            <article class="novidade-article" data-category="${escapeHTML(categoryKey)}">
                <div class="novidade-banner">
                    <img src="${escapeHTML(safeCapa)}" alt="${safeTitulo}" loading="eager" onerror="this.onerror=null; this.src='/assets/images/fundo.webp';">
                </div>

                <header class="novidade-header">
                    <div class="novidade-meta-top">
                        ${categoryLabel ? `<span class="novidade-tag" data-category="${escapeHTML(categoryKey)}">${escapeHTML(categoryLabel)}</span>` : ""}
                        <div class="novidade-read-time" title="Tempo estimado de leitura">
                            <i class="fa-regular fa-clock"></i>
                            <span>~${readTimeMinutes} min de leitura</span>
                        </div>
                    </div>

                    <h1 class="novidade-title">${safeTitulo}</h1>

                    <div class="novidade-author-bar">
                        <div class="author-info">
                            <img class="author-head" src="https://mc-heads.net/avatar/${safeNick}" alt="${safeAutor}" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve'">
                            <div class="author-details">
                                <span class="author-name">${safeAutor}</span>
                                <span class="author-role" style="color: ${escapeHTML(corDisplay)}; ${!cargoDisplay ? 'display: none;' : ''}">${escapeHTML(cargoDisplay || '')}</span>
                            </div>
                        </div>
                        ${dataFormatada ? `
                        <div class="date-info">
                            <i class="fa-regular fa-calendar"></i>
                            <time datetime="${escapeHTML(dataIso)}">${dataFormatada}</time>
                        </div>` : ''}
                    </div>
                </header>

                <div class="novidade-divider"></div>

                <div class="novidade-body">
                    ${parseDescription(news.conteudo)}
                </div>

                <footer class="novidade-footer">
                    <div class="footer-actions">
                        <a href="/#atualizacoes-section" class="btn-footer-back">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Ver todas as novidades</span>
                        </a>
                    </div>
                </footer>
            </article>
        `;

        // Se o cargo não veio pré-carregado no payload, busca na API de equipe para atualizar dinamicamente
        if (!cargoNome) {
            fetch('/api/equipe_api.php')
                .then(r => r.json())
                .then(data => {
                    const list = (data && Array.isArray(data.data)) ? data.data : (Array.isArray(data) ? data : []);
                    const found = matchMemberCargo(list, news.autor);
                    if (found) {
                        const roleEl = container.querySelector('.author-role');
                        if (roleEl) {
                            roleEl.textContent = found.cargo;
                            roleEl.style.color = found.cor;
                            roleEl.style.display = '';
                        }
                    }
                })
                .catch(() => {});
        }

        setupCopyButtons();
    };

    function setupCopyButtons() {
        const copyCurrentUrl = async (btn) => {
            if (!btn) return;
            const originalHtml = btn.innerHTML;
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(window.location.href);
                } else {
                    const temp = document.createElement("input");
                    temp.value = window.location.href;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand("copy");
                    document.body.removeChild(temp);
                }
                btn.classList.add("copied");
                btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Link copiado!</span>';
                setTimeout(() => {
                    btn.classList.remove("copied");
                    btn.innerHTML = originalHtml;
                }, 2200);
            } catch (err) {
                console.warn("Falha ao copiar link:", err);
            }
        };

        const topBtn = document.getElementById("btn-share-top");
        if (topBtn) topBtn.addEventListener("click", () => copyCurrentUrl(topBtn));
    }

    const apiUrl = `/api/novidades_api.php?id=${encodeURIComponent(id)}`;
    if (window.AppCache) {
        AppCache.fetchWithCache('novidades_artigo_v1_' + id, apiUrl, {
            onCached: (cached) => {
                if (cached && typeof cached.autor_cargo === 'undefined') {
                    return;
                }
                renderNewsArticle(cached);
            },
            onFresh: renderNewsArticle,
            onError: (err) => {
                console.error("Erro ao carregar atualização:", err);
                if (!container.children.length) {
                    renderNewsArticle({ erro: true });
                }
            }
        });
    } else {
        fetch(apiUrl)
            .then(res => res.json())
            .then(renderNewsArticle)
            .catch(err => {
                console.error("Erro ao carregar atualização:", err);
                renderNewsArticle({ erro: true });
            });
    }
});
