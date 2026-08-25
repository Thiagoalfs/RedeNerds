document.addEventListener("DOMContentLoaded", () => {
    // Views
    const viewNovidades = document.getElementById("view-novidades");
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
        "NerdDead": "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim();

    const state = {
        category: "all",
        query: "",
        page: 1
    };

    const fetchJSON = async (url) => {
        const res = await fetch(url);
        const contentType = res.headers.get("content-type") || "";
        
        if (!res.ok || !contentType.includes("application/json")) {
            const errorText = await res.text();
            console.error("Resposta inválida do servidor:", errorText);
            throw new Error("A resposta do servidor não é um JSON válido.");
        }
        
        return await res.json();
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
            viewNovidades.hidden = true;
            viewMais.hidden = false;
            window.scrollTo({ top: 0, behavior: "smooth" });
            state.page = 1;
            loadAllNews();
        });
    }

    if (btnVoltar) {
        btnVoltar.addEventListener("click", () => {
            viewMais.hidden = true;
            viewNovidades.hidden = false;
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    // ========================================================
    // 1. CARREGAMENTO DA TELA PRINCIPAL (Top 3)
    // ========================================================
    const loadTopNews = () => {
        if (!newsContainer) return;

        newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Carregando novidades...</p>`;
        fetch("/api/novidades_api.php?limit=3")
            .then(res => res.json())
            .then(data => {
                if (data && data.erro) {
                    console.error("Erro retornado do PHP:", data.erro);
                    newsContainer.innerHTML = `<p style="color: #E85D5D; text-align: center; padding: 20px;">Erro: ${escapeHTML(data.erro)}</p>`;
                    return;
                }

                const entries = Array.isArray(data) ? data : [];

                if (entries.length === 0) {
                    newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma novidade por enquanto.</p>`;
                    return;
                }

                newsContainer.innerHTML = entries.map(news => {
                    const categoryKey = toCategoryKey(news.category);
                    const categoryLabel = categoryLabels[categoryKey] || news.category || "";
                    return `
                    <a class="news-div" href="/novidades/novidade-page/?id=${news.id}" data-category="${categoryKey}">
                        <div class="news-div-banner">
                            <img class="news-img" src="${escapeHTML(news.capa)}" alt="${escapeHTML(news.titulo)}">
                        </div>
                        <div class="news-div-content">
                            ${categoryLabel ? `<span class="news-div-tag" data-category="${categoryKey}">${escapeHTML(categoryLabel)}</span>` : ""}
                            <h3 class="news-div-title">${escapeHTML(news.titulo)}</h3>
                            <div class="news-div-footer">
                                <div class="author">
                                    <img class="author-head" src="https://mc-heads.net/avatar/${escapeHTML(news.autor)}" alt="${escapeHTML(news.autor)}">
                                    <p>${escapeHTML(news.autor)}</p>
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
                newsContainer.innerHTML = `<p style="color: #E85D5D; text-align: center; padding: 20px;">Erro ao carregar novidades.</p>`;
            });
    };

    // ========================================================
    // 2. CARREGAMENTO DA TELA "VER MAIS"
    // ========================================================
    const escapeRegex = str => String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    const highlight = (text, terms) => {
        if (!terms.length || !text) return escapeHTML(text);
        const safe = escapeHTML(text);
        const pattern = new RegExp("(" + terms.map(t => escapeRegex(t)).join("|") + ")", "gi");
        return safe.replace(pattern, "<mark>$1</mark>");
    };

    const renderAllCards = (entries, terms) => {
        if (entries.length === 0) {
            allNewsContainer.innerHTML = `<p class="all-news-empty filtered">
                <i class="fa-solid fa-circle-exclamation" style="color:#B971DA; margin-right:6px;"></i>
                Nenhuma novidade encontrada com esses filtros.
            </p>`;
            return;
        }

        allNewsContainer.innerHTML = entries.map(news => {
            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || news.category || "";
            const firstParagraph = String(news.conteudo || "").replace(/\\n/g, "\n").split("\n")[0].trim();

            return `
            <a class="all-news-card" href="/novidades/novidade-page/?id=${news.id}" data-category="${categoryKey}">
                <div class="all-news-image">
                    <img src="${escapeHTML(news.capa)}" alt="${escapeHTML(news.titulo)}">
                </div>
                <div class="all-news-content">
                    ${categoryLabel ? `<span class="all-news-tag" data-category="${categoryKey}">${escapeHTML(categoryLabel)}</span>` : ""}
                    <h3 class="all-news-title">${highlight(news.titulo, terms)}</h3>
                    <p class="all-news-desc">${highlight(firstParagraph, terms)}</p>
                    <div class="all-news-footer">
                        <div class="all-news-author">
                            <img class="all-news-author-head" src="https://mc-heads.net/avatar/${escapeHTML(news.autor)}" alt="${escapeHTML(news.autor)}">
                            <p>${highlight(news.autor, terms)}</p>
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

        allNewsContainer.innerHTML = `<p class="all-news-empty">Carregando novidades...</p>`;
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
            console.error("Erro ao carregar novidades:", err);
            allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro ao carregar novidades.</p>`;
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
});