document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("all-news-container");
    const searchInput = document.getElementById("news-search");
    const searchClear = document.getElementById("news-search-clear");
    const filterBtn = document.getElementById("news-filter-btn");
    const filterLabel = document.getElementById("news-filter-label");
    const filterDropdown = document.getElementById("news-filter-dropdown");
    const resultsInfo = document.getElementById("news-results-info");
    const resultsCount = document.getElementById("news-results-count");

    if (!container) {
        console.error("ERRO: O id 'all-news-container' não foi encontrado no HTML.");
        return;
    }

        // Valores EXATOS salvos no banco (case-sensitive!)
    const categoryLabels = {
        "NerdSky": "NerdSky",
        "Potato Nerd": "Potato Nerd",
        "NerdDead": "Nerd Dead"
    };

    const toCategoryKey = value => String(value || "").trim();

    // Estado dos filtros (re-aplicado a cada requisição)
    const state = {
        category: "all",
        query: ""
    };

    // ---------- Renderização ----------

    const escapeHTML = str => String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    const escapeRegex = str => String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    // Destaca o termo buscado (case-insensitive) em texto seguro
    const highlight = (text, terms) => {
        if (!terms.length || !text) return escapeHTML(text);
        const safe = escapeHTML(text);
        const pattern = new RegExp(
            "(" + terms.map(t => escapeRegex(t)).join("|") + ")",
            "gi"
        );
        return safe.replace(pattern, "<mark>$1</mark>");
    };

    const renderCards = (entries, terms) => {
        if (entries.length === 0) {
            container.innerHTML = `<p class="all-news-empty filtered">
                <i class="fa-solid fa-circle-exclamation" style="color:#B971DA; margin-right:6px;"></i>
                Nenhuma novidade encontrada com esses filtros.
            </p>`;
            resultsInfo.hidden = true;
            return;
        }

        container.innerHTML = entries.map(news => {
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

        // Atualiza contador
        resultsCount.textContent = entries.length;
        resultsInfo.hidden = false;
    };

    // ---------- Carregamento do backend ----------

    let loading = false;
    const loadNews = async () => {
        if (loading) return;
        loading = true;

        container.innerHTML = `<p class="all-news-empty">Carregando novidades...</p>`;
        resultsInfo.hidden = true;

        const params = new URLSearchParams();
        if (state.category && state.category !== "all") {
            params.set("category", state.category);
        }
        if (state.query.trim()) {
            params.set("q", state.query.trim());
        }

        const url = "https://redenerds.com.br/novidades.php" + (params.toString() ? "?" + params.toString() : "");

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (!Array.isArray(data)) {
                container.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro: ${escapeHTML(data?.erro || "resposta inválida")}</p>`;
                return;
            }

            const terms = state.query.trim().split(/\s+/).filter(Boolean);
            renderCards(data, terms);
        } catch (err) {
            console.error("Erro ao carregar novidades:", err);
            container.innerHTML = `<p class="all-news-empty" style="color:#E85D5D;">Erro ao carregar novidades.</p>`;
        } finally {
            loading = false;
        }
    };

    // ---------- Searchbar ----------

    let searchTimer = null;
    const handleSearch = () => {
        const value = searchInput.value;
        searchClear.classList.toggle("visible", value.length > 0);

        clearTimeout(searchTimer);
        // Debounce: 300ms antes de chamar o backend
        searchTimer = setTimeout(() => {
            state.query = value;
            loadNews();
        }, 300);
    };

    searchInput.addEventListener("input", handleSearch);

    searchClear.addEventListener("click", () => {
        searchInput.value = "";
        searchClear.classList.remove("visible");
        state.query = "";
        loadNews();
        searchInput.focus();
    });

    // Atalho: ESC limpa a busca
    searchInput.addEventListener("keydown", e => {
        if (e.key === "Escape" && searchInput.value) {
            e.preventDefault();
            searchInput.value = "";
            searchClear.classList.remove("visible");
            state.query = "";
            loadNews();
        }
    });

    // ---------- Filter dropdown ----------

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

    // Fechar ao clicar fora
    document.addEventListener("click", e => {
        if (!filterDropdown.contains(e.target) && e.target !== filterBtn) {
            closeDropdown();
        }
    });

    // Fechar com ESC
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") closeDropdown();
    });

    // Selecionar categoria
    filterDropdown.querySelectorAll("li").forEach(li => {
        li.addEventListener("click", () => {
            const category = li.dataset.category;
            const label = li.textContent.trim();

            state.category = category;
            filterLabel.textContent = label;

            // Marca visualmente o item selecionado
            filterDropdown.querySelectorAll("li").forEach(item => item.removeAttribute("aria-selected"));
            li.setAttribute("aria-selected", "true");

            closeDropdown();
            loadNews();
        });
    });

    // ---------- Inicial ----------
    loadNews();
});