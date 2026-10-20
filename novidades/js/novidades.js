function initNovidades() {
    // Views
    const viewLanding = document.getElementById("view-landing") || document.getElementById("view-novidades");
    const viewMais = document.getElementById("view-mais");
    
    // Botões de navegação
    const btnMostrarMais = document.getElementById("btn-mostrar-mais");
    const btnVoltar = document.getElementById("voltar");

    // Elementos da View Novidades
    const newsContainer = document.getElementById("news-container");

    // Elementos da View Mais
    const allNewsContainer = document.getElementById("all-news-container");
    const searchInput = document.getElementById("news-search");
    const searchClear = document.getElementById("news-search-clear");
    const filterBtn = document.getElementById("news-filter-btn");
    const filterLabel = document.getElementById("news-filter-label");
    const filterDropdown = document.getElementById("news-filter-dropdown");
    const resultsInfo = document.getElementById("news-results-info");
    const resultsCount = document.getElementById("news-results-count");
    const pagination = document.getElementById("news-pagination");

    const PER_PAGE = 5;

    const categoryLabels = {
        "NerdSky": "NerdSky",
        "Potato Nerd": "Potato Nerd",
        "NerdDead": "Nerd Dead",
        "CobbleNerd": "CobbleNerd",
        "Sistemas": "Sistemas",
        "Potato Sky": "Potato Sky"
    };

    const toCategoryKey = value => String(value || "").trim();

    const stripMarkdownSnippet = (text) => {
        return String(text || "")
            .replace(/<[^>]+>/g, "")
            .replace(/\\n/g, "\n")
            .replace(/^>\s*\[!.*?\]\s*/gim, "")
            .replace(/!\[.*?\]\(.*?\)/g, "")
            .replace(/\[(.*?)\]\(.*?\)/g, "$1")
            .replace(/^#+\s+/gm, "")
            .replace(/[*_~`]/g, "")
            .replace(/\s+/g, " ")
            .trim();
    };


    const state = {
        category: "all",
        query: "",
        page: 1
    };

    const fetchJSON = async (url) => {
        if (window.AppCache) {
            return await AppCache.fetchWithCache('novidades_query_' + encodeURIComponent(url), url);
        }
        const res = await fetch(url);
        const contentType = res.headers.get("content-type") || "";
        
        if (!res.ok || !contentType.includes("application/json")) {
            const errorText = await res.text();
            console.error("Resposta inválida do servidor:", errorText);
            throw new Error("A resposta do servidor não é um JSON válido.");
        }
        
        return await res.json();
    };

    const sanitizeUrl = (url, fallback = "") => {
        if (!url || typeof url !== "string") return fallback;
        const trimmed = url.trim();
        if (/^(https?:\/\/|\/|\.\.\/|data:image\/|#)/i.test(trimmed)) {
            return trimmed;
        }
        return fallback;
    };

    const escapeHTML = str => String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    // ========================================================
    // NAVEGAÇÃO SPA
    // ========================================================
    if (btnMostrarMais) {
        btnMostrarMais.addEventListener("click", () => {
            if (viewLanding) viewLanding.hidden = true;
            if (viewMais) viewMais.hidden = false;
            window.scrollTo({ top: 0, behavior: "smooth" });
            state.page = 1;
            loadAllNews();
        });
    }

    if (btnVoltar) {
        btnVoltar.addEventListener("click", () => {
            if (viewMais) viewMais.hidden = true;
            if (viewLanding) viewLanding.hidden = false;
            const atualizacoesSec = document.getElementById("atualizacoes-section") || document.getElementById("novidades-section");
            if (atualizacoesSec) {
                atualizacoesSec.scrollIntoView({ behavior: "smooth" });
            } else {
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });
    }

    // Ao clicar em links da navbar enquanto estiver no view-mais, restaura o view-landing
    document.addEventListener("click", e => {
        const a = e.target.closest("a");
        if (a && a.getAttribute("href") && (a.getAttribute("href").includes("#") || a.getAttribute("href") === "/")) {
            if (viewMais && !viewMais.hidden && viewLanding) {
                viewMais.hidden = true;
                viewLanding.hidden = false;
            }
        }
    });

    // ========================================================
    // 1. CARREGAMENTO DA TELA PRINCIPAL (Top 3)
    // ========================================================
    const renderTopNews = (data) => {
        const atualizacoesSec = document.getElementById("atualizacoes-section") || document.getElementById("novidades-section");
        if (!newsContainer) return;

        if (data && data.erro) {
            console.error("Erro retornado do backend:", data.erro);
            if (atualizacoesSec && !newsContainer.children.length) atualizacoesSec.hidden = true;
            return;
        }

        const entries = Array.isArray(data) ? data : (data && data.data && Array.isArray(data.data) ? data.data : []);

        if (entries.length === 0) {
            if (atualizacoesSec && !newsContainer.children.length) atualizacoesSec.hidden = true;
            return;
        }

        newsContainer.innerHTML = entries.map(news => {
            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || news.category || "";
            const rawDesc = stripMarkdownSnippet(news.conteudo);
            const descSnippet = rawDesc || "Clique para ver todos os detalhes e informações desta atualização.";
            const dataObj = news.criado_em ? new Date(String(news.criado_em).replace(" ", "T")) : new Date();
            const dataFormatada = !isNaN(dataObj.getTime()) ? dataObj.toLocaleDateString("pt-BR") : "";

            return `
            <a class="news-div" href="/novidades/novidade-page/?id=${encodeURIComponent(parseInt(news.id, 10) || 0)}" data-category="${escapeHTML(categoryKey)}">
                <div class="news-div-banner">
                    <img class="news-img" src="${escapeHTML(sanitizeUrl(news.capa, '/assets/images/logo.webp'))}" alt="${escapeHTML(news.titulo)}">
                </div>
                <div class="news-div-content">
                    <div class="news-div-meta">
                        <span class="news-div-date">${dataFormatada}</span>
                        ${categoryLabel ? `<span class="news-div-tag" data-category="${categoryKey}">${escapeHTML(categoryLabel)}</span>` : ""}
                    </div>
                    <h3 class="news-div-title">${escapeHTML(news.titulo)}</h3>
                    <p class="news-div-desc">${escapeHTML(descSnippet)}</p>
                    <div class="news-div-footer">
                        <div class="author">
                            <img class="author-head" src="https://mc-heads.net/avatar/${encodeURIComponent(String(news.autor || 'Steve').trim())}/32" alt="${escapeHTML(news.autor)}">
                            <span>${escapeHTML(news.autor)}</span>
                        </div>
                    </div>
                </div>
            </a>
            `;
        }).join("");

        // Revela a seção completa somente agora após carregar as notícias com sucesso
        if (atualizacoesSec) {
            atualizacoesSec.removeAttribute("hidden");
            atualizacoesSec.hidden = false;
        }

        requestAnimationFrame(() => {
            inicializarCarrosselNovidades();
        });
    };

    const loadTopNews = () => {
        const atualizacoesSec = document.getElementById("atualizacoes-section") || document.getElementById("novidades-section");
        if (!newsContainer) return;

        // Mantém a seção inteira oculta inicialmente (sem piscar nem mostrar 'carregando...')
        if (atualizacoesSec && !newsContainer.children.length) {
            atualizacoesSec.hidden = true;
        }

        if (window.AppCache) {
            AppCache.fetchWithCache('novidades_limit_3', '/api/novidades_api.php?limit=3', {
                onCached: renderTopNews,
                onFresh: renderTopNews,
                onError: (err) => {
                    console.error("Erro ao carregar atualizações:", err);
                    if (atualizacoesSec && !newsContainer.children.length) atualizacoesSec.hidden = true;
                }
            });
        } else {
            fetch("/api/novidades_api.php?limit=3")
                .then(res => {
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                    return res.json();
                })
                .then(renderTopNews)
                .catch(err => {
                    console.error("Erro ao carregar atualizações:", err);
                    if (atualizacoesSec && !newsContainer.children.length) atualizacoesSec.hidden = true;
                });
        }
    };

    // ========================================================
    // 2. CARREGAMENTO DA TELA "VER MAIS"
    // ========================================================
    const escapeRegex = str => String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    const highlight = (text, terms) => {
        if (!text) return "";
        const validTerms = Array.isArray(terms) ? terms.map(t => String(t || "").trim()).filter(Boolean) : [];
        if (!validTerms.length) return escapeHTML(text);

        const pattern = new RegExp("(" + validTerms.map(escapeRegex).join("|") + ")", "gi");
        const parts = String(text).split(pattern);

        return parts.map(part => {
            if (!part) return "";
            if (validTerms.some(t => t.toLowerCase() === part.toLowerCase())) {
                return `<mark>${escapeHTML(part)}</mark>`;
            }
            return escapeHTML(part);
        }).join("");
    };

    const renderAllCards = (entries, terms) => {
        if (entries.length === 0) {
            allNewsContainer.innerHTML = `<p class="all-news-empty filtered">
                <i class="fa-solid fa-circle-exclamation" style="color:#B971DA; margin-right:6px;"></i>
                Nenhuma atualização encontrada com esses filtros.
            </p>`;
            return;
        }

        allNewsContainer.innerHTML = entries.map(news => {
            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || news.category || "";
            const firstParagraph = stripMarkdownSnippet(news.conteudo);

            return `
            <a class="all-news-card" href="/novidades/novidade-page/?id=${encodeURIComponent(parseInt(news.id, 10) || 0)}" data-category="${escapeHTML(categoryKey)}">
                <div class="all-news-image">
                    <img src="${escapeHTML(sanitizeUrl(news.capa, '/assets/images/logo.webp'))}" alt="${escapeHTML(news.titulo)}">
                </div>
                <div class="all-news-content">
                    ${categoryLabel ? `<span class="all-news-tag" data-category="${categoryKey}">${escapeHTML(categoryLabel)}</span>` : ""}
                    <h3 class="all-news-title">${highlight(news.titulo, terms)}</h3>
                    <p class="all-news-desc">${highlight(firstParagraph, terms)}</p>
                    <div class="all-news-footer">
                        <div class="all-news-author">
                            <img class="all-news-author-head" src="https://mc-heads.net/avatar/${encodeURIComponent(String(news.autor || 'Steve').trim())}/32" alt="${escapeHTML(news.autor)}">
                            <span class="all-news-author-name">${highlight(news.autor, terms)}</span>
                            <span class="all-news-date">${new Date(news.criado_em).toLocaleDateString("pt-BR")}</span>
                        </div>
                        <span class="all-news-link" style="margin-top: 0; margin-left: auto;">
                            Ler mais <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </a>
            `;
        }).join("");
    };

    const renderPagination = (page, totalPages) => {
        if (!pagination) return;

        if (!totalPages || totalPages <= 1) {
            pagination.hidden = true;
            pagination.innerHTML = "";
            return;
        }

        const makeBtn = (label, targetPage, { disabled = false, active = false, ariaLabel = null } = {}) => {
            const classes = ["news-page-btn"];
            if (active) classes.push("active");
            return `<button type="button" class="${classes.join(" ")}"
                        data-page="${targetPage}"
                        ${disabled ? "disabled" : ""}
                        ${active ? 'aria-current="page"' : ""}
                        aria-label="${ariaLabel || ("Página " + targetPage)}">${label}</button>`;
        };

        const windowSize = 2;
        let start = Math.max(1, page - windowSize);
        let end = Math.min(totalPages, page + windowSize);

        const items = [];

        items.push(makeBtn('<i class="fa-solid fa-chevron-left"></i>', page - 1, {
            disabled: page <= 1,
            ariaLabel: "Página anterior"
        }));

        if (start > 1) {
            items.push(makeBtn("1", 1));
            if (start > 2) items.push(`<span class="news-page-ellipsis">…</span>`);
        }

        for (let p = start; p <= end; p++) {
            items.push(makeBtn(String(p), p, { active: p === page }));
        }

        if (end < totalPages) {
            if (end < totalPages - 1) items.push(`<span class="news-page-ellipsis">…</span>`);
            items.push(makeBtn(String(totalPages), totalPages));
        }

        items.push(makeBtn('<i class="fa-solid fa-chevron-right"></i>', page + 1, {
            disabled: page >= totalPages,
            ariaLabel: "Próxima página"
        }));

        pagination.innerHTML = items.join("");
        pagination.hidden = false;

        pagination.querySelectorAll(".news-page-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const targetPage = parseInt(btn.dataset.page, 10);
                if (!targetPage || targetPage === state.page) return;
                state.page = targetPage;
                loadAllNews();
                allNewsContainer.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        });
    };

    let loadingAll = false;
    const loadAllNews = async () => {
        if (loadingAll || !allNewsContainer) return;
        loadingAll = true;

        allNewsContainer.innerHTML = `<p class="all-news-empty">Carregando atualizações...</p>`;
        resultsInfo.hidden = true;
        if (pagination) pagination.hidden = true;

        const params = new URLSearchParams();
        if (state.category && state.category !== "all") {
            params.set("category", state.category);
        }
        if (state.query.trim()) {
            params.set("q", state.query.trim());
        }
        params.set("page", state.page);
        params.set("per_page", PER_PAGE);
        
        const url = "/api/novidades_api.php" + (params.toString() ? "?" + params.toString() : "");

        try {
            const payload = await fetchJSON(url);

            if (payload && payload.erro) {
                allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro: ${escapeHTML(payload.erro)}</p>`;
                renderPagination(1, 0);
                return;
            }

            if (!payload || !Array.isArray(payload.data)) {
                allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Resposta inválida do servidor.</p>`;
                renderPagination(1, 0);
                return;
            }

            if (payload.data.length === 0 && payload.page > 1 && payload.total > 0) {
                state.page = 1;
                loadingAll = false;
                await loadAllNews();
                return;
            }

            const terms = state.query.trim().split(/\s+/).filter(Boolean);
            renderAllCards(payload.data, terms);

            resultsCount.textContent = payload.total;
            resultsInfo.hidden = payload.total === 0;

            state.page = payload.page;
            renderPagination(payload.page, payload.total_pages);
        } catch (err) {
            console.error("Erro ao carregar atualizações:", err);
            allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro ao carregar atualizações.</p>`;
            renderPagination(1, 0);
        } finally {
            loadingAll = false;
        }
    };

    // ----- Input de Pesquisa -----
    if (searchInput) {
        let searchTimer = null;
        searchInput.addEventListener("input", () => {
            const value = searchInput.value;
            if (searchClear) searchClear.classList.toggle("visible", value.length > 0);

            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.query = value;
                state.page = 1;
                loadAllNews();
            }, 300);
        });

        searchInput.addEventListener("keydown", e => {
            if (e.key === "Escape" && searchInput.value) {
                e.preventDefault();
                searchInput.value = "";
                if (searchClear) searchClear.classList.remove("visible");
                state.query = "";
                state.page = 1;
                loadAllNews();
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener("click", () => {
            if (searchInput) searchInput.value = "";
            searchClear.classList.remove("visible");
            state.query = "";
            state.page = 1;
            loadAllNews();
            if (searchInput) searchInput.focus();
        });
    }

    // ----- Dropdown de Filtros -----
    if (filterBtn && filterDropdown) {
        const closeDropdown = () => {
            filterDropdown.hidden = true;
            filterBtn.setAttribute("aria-expanded", "false");
        };

        const openDropdown = () => {
            filterDropdown.hidden = false;
            filterBtn.setAttribute("aria-expanded", "true");
        };

        filterBtn.addEventListener("click", e => {
            e.stopPropagation();
            const expanded = filterBtn.getAttribute("aria-expanded") === "true";
            if (expanded) closeDropdown();
            else openDropdown();
        });

        document.addEventListener("click", e => {
            if (!filterDropdown.contains(e.target) && e.target !== filterBtn) {
                closeDropdown();
            }
        });

        document.addEventListener("keydown", e => {
            if (e.key === "Escape") closeDropdown();
        });

        filterDropdown.querySelectorAll("li").forEach(li => {
            li.addEventListener("click", () => {
                const category = li.dataset.category;
                const label = li.textContent.trim();

                state.category = category;
                state.page = 1;
                if (filterLabel) filterLabel.textContent = label;

                filterDropdown.querySelectorAll("li").forEach(item => item.removeAttribute("aria-selected"));
                li.setAttribute("aria-selected", "true");

                closeDropdown();
                loadAllNews();
            });
        });
    }

    loadTopNews();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNovidades);
} else {
    initNovidades();
}

function inicializarCarrosselNovidades() {
    const track = document.getElementById("news-container");
    const prevBtn = document.getElementById("news-carousel-prev");
    const nextBtn = document.getElementById("news-carousel-next");
    const dotsContainer = document.getElementById("news-carousel-dots");
    const wrapper = document.querySelector(".news-carousel-wrapper");

    if (!track) return;

    function getCards() {
        return track.querySelectorAll(".news-div");
    }

    function getScrollStep() {
        const currentCards = getCards();
        if (currentCards.length > 0) {
            const card = currentCards[0];
            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.gap) || 24;
            return (card.offsetWidth || 350) + gap;
        }
        return 360;
    }

    function atualizarEstadoCarrossel() {
        const currentCards = getCards();
        if (currentCards.length === 0) return;

        const style = window.getComputedStyle(track);
        const gap = parseFloat(style.gap) || 24;
        let totalCardsWidth = 0;
        currentCards.forEach((c, idx) => {
            totalCardsWidth += c.offsetWidth;
            if (idx > 0) totalCardsWidth += gap;
        });

        const availableWidth = track.clientWidth;
        const canScroll = totalCardsWidth > (availableWidth + 5);

        if (wrapper) {
            if (canScroll) {
                const eraSemOverflow = !wrapper.classList.contains("has-overflow");
                wrapper.classList.add("has-overflow");
                if (eraSemOverflow && track.scrollLeft !== 0) {
                    track.scrollLeft = 0;
                }
            } else {
                wrapper.classList.remove("has-overflow");
                track.scrollLeft = 0;
            }
        }

        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);

        if (prevBtn) {
            prevBtn.disabled = track.scrollLeft <= 5;
            prevBtn.style.display = canScroll && window.innerWidth >= 768 ? "flex" : "none";
        }
        if (nextBtn) {
            nextBtn.disabled = track.scrollLeft >= maxScroll - 5;
            nextBtn.style.display = canScroll && window.innerWidth >= 768 ? "flex" : "none";
        }

        if (dotsContainer) {
            if (!canScroll) {
                dotsContainer.style.display = "none";
            } else {
                dotsContainer.style.display = "flex";
                atualizarDots();
            }
        }
    }

    function criarDots() {
        if (!dotsContainer) return;
        dotsContainer.innerHTML = "";
        const currentCards = getCards();
        if (currentCards.length <= 1) return;

        currentCards.forEach((card, index) => {
            const dot = document.createElement("button");
            dot.type = "button";
            dot.className = "news-carousel-dot" + (index === 0 ? " active" : "");
            dot.setAttribute("aria-label", `Ir para a notícia ${index + 1}`);
            dot.addEventListener("click", () => {
                const cardLeft = card.offsetLeft - track.offsetLeft;
                track.scrollTo({ left: cardLeft, behavior: "smooth" });
            });
            dotsContainer.appendChild(dot);
        });
    }

    function atualizarDots() {
        if (!dotsContainer) return;
        const dots = dotsContainer.querySelectorAll(".news-carousel-dot");
        const currentCards = getCards();
        if (!dots.length || !currentCards.length) return;

        let activeIndex = 0;
        let minDiff = Infinity;
        const currentScroll = track.scrollLeft;

        currentCards.forEach((card, index) => {
            const cardLeft = card.offsetLeft - track.offsetLeft;
            const diff = Math.abs(cardLeft - currentScroll);
            if (diff < minDiff) {
                minDiff = diff;
                activeIndex = index;
            }
        });

        dots.forEach((dot, idx) => {
            if (idx === activeIndex) {
                dot.classList.add("active");
            } else {
                dot.classList.remove("active");
            }
        });
    }

    if (prevBtn && !prevBtn.dataset.listenerAttached) {
        prevBtn.dataset.listenerAttached = "true";
        prevBtn.addEventListener("click", () => {
            const step = getScrollStep();
            track.scrollBy({ left: -step, behavior: "smooth" });
        });
    }

    if (nextBtn && !nextBtn.dataset.listenerAttached) {
        nextBtn.dataset.listenerAttached = "true";
        nextBtn.addEventListener("click", () => {
            const step = getScrollStep();
            track.scrollBy({ left: step, behavior: "smooth" });
        });
    }

    if (!track.dataset.carouselEventsAttached) {
        track.dataset.carouselEventsAttached = "true";

        track.addEventListener("scroll", () => {
            atualizarEstadoCarrossel();
        }, { passive: true });

        // Arraste com o mouse no Desktop
        let isDown = false;
        let startX = 0;
        let scrollLeftStart = 0;
        let hasDragged = false;

        track.addEventListener("mousedown", (e) => {
            if (e.button !== 0) return;
            if (!wrapper || !wrapper.classList.contains("has-overflow")) return;
            isDown = true;
            hasDragged = false;
            track.classList.add("is-dragging");
            startX = e.pageX - track.offsetLeft;
            scrollLeftStart = track.scrollLeft;
        });

        window.addEventListener("mouseup", () => {
            if (!isDown) return;
            isDown = false;
            track.classList.remove("is-dragging");
            setTimeout(() => {
                hasDragged = false;
            }, 60);
        });

        track.addEventListener("mousemove", (e) => {
            if (!isDown) return;
            const x = e.pageX - track.offsetLeft;
            const walk = (x - startX) * 1.3;
            if (Math.abs(walk) > 6) hasDragged = true;
            track.scrollLeft = scrollLeftStart - walk;
        });

        // Delegação de evento para prevenir clique acidental após arrastar
        track.addEventListener("click", (e) => {
            if (hasDragged) {
                e.preventDefault();
                e.stopPropagation();
                hasDragged = false;
            }
        }, true);

        window.addEventListener("resize", () => {
            atualizarEstadoCarrossel();
        });
    }

    criarDots();
    atualizarEstadoCarrossel();
}

