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

    const categoryLabels = {
        "NerdSky": "NerdSky",
        "Potato Nerd": "Potato Nerd",
        "NerdDead": "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim();

    const state = {
        category: "all",
        query: ""
    };

    // ========================================================
    // NAVEGAÇÃO ENTRE AS TELAS (SPA)
    // ========================================================
    if (btnMostrarMais) {
        btnMostrarMais.addEventListener("click", () => {
            viewNovidades.hidden = true;
            viewMais.hidden = false;
            window.scrollTo({ top: 0, behavior: "smooth" });
            loadAllNews(); // Carrega a lista completa
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

        fetch("/novidades/js/novidades.php")
            .then(res => res.json())
            .then(data => {
                const entries = data.slice(0, 3);

                if (entries.length === 0) {
                    newsContainer.innerHTML = `<p style="text-align:center; padding: 20px;">Nenhuma novidade por enquanto.</p>`;
                    return;
                }

                newsContainer.innerHTML = entries.map(news => {
                    const categoryKey = toCategoryKey(news.category);
                    const categoryLabel = categoryLabels[categoryKey] || news.category || "";
                    return `
                    <a class="news-div" href="novidade.html?id=${news.id}" data-category="${categoryKey}">
                        <div class="news-div-banner">
                            <img class="news-img" src="${news.capa}" alt="${news.titulo}">
                        </div>
                        <div class="news-div-content">
                            ${categoryLabel ? `<span class="news-div-tag" data-category="${categoryKey}">${categoryLabel}</span>` : ""}
                            <h3 class="news-div-title">${news.titulo}</h3>
                            <div class="news-div-footer">
                                <div class="author">
                                    <img class="author-head" src="https://mc-heads.net/avatar/${news.autor}" alt="${news.autor}">
                                    <p>${news.autor}</p>
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
                newsContainer.innerHTML = `<p style="color: red; text-align: center; padding: 20px;">Erro ao carregar novidades.</p>`;
            });
    };

    // ========================================================
    // 2. CARREGAMENTO DA TELA "VER MAIS" (Filtros e Busca)
    // ========================================================
    const escapeHTML = str => String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

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
            resultsInfo.hidden = true;
            return;
        }

        allNewsContainer.innerHTML = entries.map(news => {
            const categoryKey = toCategoryKey(news.category);
            const categoryLabel = categoryLabels[categoryKey] || news.category || "";
            const firstParagraph = String(news.conteudo || "").replace(/\\n/g, "\n").split("\n")[0].trim();

            return `
            <a class="all-news-card" href="novidade.html?id=${news.id}" data-category="${categoryKey}">
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

        resultsCount.textContent = entries.length;
        resultsInfo.hidden = false;
    };

    let loadingAll = false;
    const loadAllNews = async () => {
        if (loadingAll || !allNewsContainer) return;
        loadingAll = true;

        allNewsContainer.innerHTML = `<p class="all-news-empty">Carregando novidades...</p>`;
        resultsInfo.hidden = true;

        const params = new URLSearchParams();
        if (state.category && state.category !== "all") {
            params.set("category", state.category);
        }
        if (state.query.trim()) {
            params.set("q", state.query.trim());
        }

        const url = "/novidades/js/novidades.php" + (params.toString() ? "?" + params.toString() : "");

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (!Array.isArray(data)) {
                allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro: ${escapeHTML(data?.erro || "resposta inválida")}</p>`;
                return;
            }

            const terms = state.query.trim().split(/\s+/).filter(Boolean);
            renderAllCards(data, terms);
        } catch (err) {
            console.error("Erro ao carregar novidades:", err);
            allNewsContainer.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro ao carregar novidades.</p>`;
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
                loadAllNews();
            }, 300);
        });

        searchInput.addEventListener("keydown", e => {
            if (e.key === "Escape" && searchInput.value) {
                e.preventDefault();
                searchInput.value = "";
                if (searchClear) searchClear.classList.remove("visible");
                state.query = "";
                loadAllNews();
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener("click", () => {
            if (searchInput) searchInput.value = "";
            searchClear.classList.remove("visible");
            state.query = "";
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
                if (filterLabel) filterLabel.textContent = label;

                filterDropdown.querySelectorAll("li").forEach(item => item.removeAttribute("aria-selected"));
                li.setAttribute("aria-selected", "true");

                closeDropdown();
                loadAllNews();
            });
        });
    }

    // Carregamento inicial da página
    loadTopNews();
});