<?php
/**
 * wiki_sidebar.php - Barra Lateral Esquerda de Navegação da Wiki por Servidor
 * As categorias aparecem na esquerda e os artigos só abrem ao clicar na categoria-mãe.
 */

$categoriaAtivaId = $categoriaAtivaId ?? ($categorias[0]['id'] ?? 0);
$artigoAtivoId = $artigoAtivoId ?? 0;
?>

<aside class="wiki-docs-sidebar">
    <!-- MINI CARD DO SERVIDOR -->
    <div class="wiki-sidebar-server-info">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="wiki-sidebar-icon" style="color: <?php echo htmlspecialchars($servidorAtual['themecolor'] ?? '#2563eb', ENT_QUOTES, 'UTF-8'); ?>;">
                <?php echo renderIconeWiki($servidorAtual['icon'] ?? '', 'fa-solid fa-server'); ?>
            </div>
            <div class="min-w-0">
                <h6 class="fw-bold mb-0 text-truncate"><?php echo htmlspecialchars($servidorAtual['servername'], ENT_QUOTES, 'UTF-8'); ?></h6>
                <span class="text-muted small font-monospace" style="font-size: 0.7rem;"><?php echo htmlspecialchars($servidorAtual['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <!-- CAIXA DE PESQUISA DO SERVIDOR -->
        <div class="wiki-sidebar-search mt-2">
            <div class="wiki-sidebar-search-box">
                <i class="fa-solid fa-magnifying-glass wiki-sidebar-search-icon"></i>
                <input type="text" id="wiki-sidebar-search-input" class="wiki-sidebar-search-input" placeholder="Pesquisar tópicos..." autocomplete="off">
                <button type="button" id="wiki-sidebar-search-clear" class="wiki-sidebar-search-clear" style="display: none;" title="Limpar pesquisa">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- TÍTULO DA NAVEGAÇÃO -->
    <div class="wiki-sidebar-heading">
        <span>Categorias de Guias</span>
    </div>

    <!-- ÁRVORE DE CATEGORIAS (ACCORDION) -->
    <nav class="wiki-tree-nav" id="wiki-tree-nav">
        <?php foreach ($categorias as $cat): 
            $catId = (int)$cat['id'];
            $artigosLista = $artigosPorCategoria[$catId] ?? [];
            $isOpen = ($categoriaAtivaId === $catId);
        ?>
            <div class="wiki-tree-item <?php echo $isOpen ? 'open' : ''; ?>" data-cat-id="<?php echo $catId; ?>">
                <!-- CABEÇALHO DA CATEGORIA MÃE (CLICÁVEL) -->
                <button type="button" class="wiki-cat-btn <?php echo ($categoriaAtivaId === $catId && !$artigoAtivoId) ? 'selected' : ''; ?>" onclick="toggleCategoriaWiki(<?php echo $catId; ?>, '<?php echo urlencode($servidorAtual['nome']); ?>', '<?php echo urlencode($cat['slug']); ?>')">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <i class="<?php echo htmlspecialchars($cat['icone'], ENT_QUOTES, 'UTF-8'); ?> cat-icon"></i>
                        <span class="cat-name text-truncate"><?php echo htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="cat-count"><?php echo count($artigosLista); ?></span>
                        <i class="fa-solid fa-chevron-right chevron-icon"></i>
                    </div>
                </button>

                <!-- LISTA DE ARTIGOS FILHOS (SÓ APARECE AO CLICAR NA MÃE) -->
                <div class="wiki-articles-sublist" id="cat-sublist-<?php echo $catId; ?>">
                    <?php if (empty($artigosLista)): ?>
                        <div class="wiki-sublist-empty">
                            <span>Nenhum artigo publicado</span>
                        </div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($artigosLista as $art): 
                                $isArtActive = ($artigoAtivoId === (int)$art['id']);
                            ?>
                                <li>
                                    <a href="artigo.php?s=<?php echo urlencode($servidorAtual['nome']); ?>&slug=<?php echo urlencode($art['slug']); ?>" 
                                       class="wiki-sublist-link <?php echo $isArtActive ? 'active' : ''; ?>">
                                        <i class="fa-regular fa-file-lines me-1"></i>
                                        <span class="text-truncate"><?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
function toggleCategoriaWiki(catId, servidorSlug, catSlug) {
    const item = document.querySelector(`.wiki-tree-item[data-cat-id="${catId}"]`);
    if (!item) return;

    const wasOpen = item.classList.contains('open');

    // Se estivermos na página do servidor (servidor.php), podemos selecionar a categoria
    if (window.location.pathname.includes('servidor.php')) {
        const searchInput = document.getElementById('wiki-sidebar-search-input');
        if (searchInput && searchInput.value.trim() !== '') {
            searchInput.value = '';
            if (typeof window.executarBuscaWiki === 'function') {
                window.executarBuscaWiki('');
            }
        }
        window.location.href = `servidor.php?s=${servidorSlug}&cat=${catSlug}`;
        return;
    }

    // Se estivermos no leitor de artigo (artigo.php), apenas abre/fecha o accordion
    if (wasOpen) {
        item.classList.remove('open');
    } else {
        document.querySelectorAll('.wiki-tree-item').forEach(el => el.classList.remove('open'));
        item.classList.add('open');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('wiki-sidebar-search-input');
    const searchClear = document.getElementById('wiki-sidebar-search-clear');
    const treeItems = document.querySelectorAll('.wiki-tree-item');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (searchClear) {
                searchClear.style.display = query ? 'flex' : 'none';
            }

            // Filtro dinâmico na árvore de categorias da sidebar
            treeItems.forEach(item => {
                const catBtn = item.querySelector('.wiki-cat-btn');
                const catName = catBtn ? catBtn.querySelector('.cat-name').textContent.toLowerCase() : '';
                const links = item.querySelectorAll('.wiki-sublist-link');
                let matchCount = 0;

                links.forEach(link => {
                    const title = link.textContent.toLowerCase();
                    if (!query || title.includes(query) || catName.includes(query)) {
                        link.parentElement.style.display = '';
                        matchCount++;
                    } else {
                        link.parentElement.style.display = 'none';
                    }
                });

                if (!query) {
                    item.style.display = '';
                    const isOriginalOpen = item.dataset.catId === "<?php echo (int)$categoriaAtivaId; ?>";
                    item.classList.toggle('open', isOriginalOpen);
                } else if (matchCount > 0 || catName.includes(query)) {
                    item.style.display = '';
                    item.classList.add('open');
                } else {
                    item.style.display = 'none';
                }
            });

            // Dispara busca na área principal de servidor.php se disponível
            if (typeof window.executarBuscaWiki === 'function') {
                window.executarBuscaWiki(query);
            }
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }
});
</script>