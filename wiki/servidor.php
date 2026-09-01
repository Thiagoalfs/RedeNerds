<?php
$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

require_once __DIR__ . "/wiki_helper.php";

$slug = trim($_GET['s'] ?? '');
$servidorAtual = (isset($pdo) && $pdo instanceof PDO) ? getServidorWikiPorSlug($pdo, $slug) : null;

if (!$servidorAtual) {
    header("Location: index.php");
    exit;
}

$tituloPagina = "Wiki " . $servidorAtual['servername'];
require_once __DIR__ . "/includes/wiki_header.php";

// Busca categorias do servidor
$categorias = getCategoriasServidorWiki($pdo, (int)$servidorAtual['id']);

// Busca artigos por categoria e lista global para busca
$artigosPorCategoria = [];
$todosArtigosServidor = [];
try {
    $stmtArtigos = $pdo->prepare("
        SELECT a.id, a.categoria_id, a.titulo, a.slug, a.resumo, a.visualizacoes, a.criado_em,
               c.nome AS categoria_nome, c.slug AS categoria_slug, c.icone AS categoria_icone
        FROM wiki_artigos a 
        JOIN wiki_categorias c ON c.id = a.categoria_id 
        WHERE a.servidor_id = :servidor_id AND a.publicado = 1 
        ORDER BY c.ordem ASC, a.ordem ASC, a.id ASC
    ");
    $stmtArtigos->execute([':servidor_id' => $servidorAtual['id']]);
    $artigosDb = $stmtArtigos->fetchAll(PDO::FETCH_ASSOC);
    foreach ($artigosDb as $art) {
        $artigosPorCategoria[$art['categoria_id']][] = $art;
        $todosArtigosServidor[] = $art;
    }
} catch (Exception $e) {}

// Determina qual categoria está ativa (via parâmetro ?cat=slug ou padrão da primeira)
$catSlugParam = trim($_GET['cat'] ?? '');
$categoriaAtiva = null;
if (!empty($catSlugParam)) {
    foreach ($categorias as $c) {
        if ($c['slug'] === $catSlugParam) {
            $categoriaAtiva = $c;
            break;
        }
    }
}
if (!$categoriaAtiva && !empty($categorias)) {
    $categoriaAtiva = $categorias[0];
}
$categoriaAtivaId = $categoriaAtiva ? (int)$categoriaAtiva['id'] : 0;
$artigosDaCategoriaAtiva = $artigosPorCategoria[$categoriaAtivaId] ?? [];
?>

<div class="wiki-layout-docs">
    <!-- SIDEBAR ESQUERDA (CATEGORIAS ACCORDION & BUSCA) -->
    <?php require __DIR__ . "/includes/wiki_sidebar.php"; ?>

    <!-- ÁREA PRINCIPAL À DIREITA -->
    <main class="wiki-docs-main">
        <div class="wiki-content-card">
            <!-- CONTEÚDO PADRÃO POR CATEGORIA -->
            <div id="wiki-category-content">
                <!-- BREADCRUMB -->
                <div class="wiki-breadcrumb">
                    <a href="index.php"><i class="fa-solid fa-house"></i> Wiki</a>
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                    <span><?php echo htmlspecialchars($servidorAtual['servername'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($categoriaAtiva): ?>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                        <span class="text-primary fw-semibold"><?php echo htmlspecialchars($categoriaAtiva['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($categoriaAtiva): ?>
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                        <div>
                            <h2 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
                                <i class="<?php echo htmlspecialchars($categoriaAtiva['icone'], ENT_QUOTES, 'UTF-8'); ?> text-primary"></i>
                                <span><?php echo htmlspecialchars($categoriaAtiva['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </h2>
                            <p class="text-muted small mb-0">Tutoriais e guias disponíveis nesta seção.</p>
                        </div>
                        <span class="badge bg-light text-dark border font-monospace"><?php echo count($artigosDaCategoriaAtiva); ?> artigo(s)</span>
                    </div>

                    <?php if (empty($artigosDaCategoriaAtiva)): ?>
                        <div class="p-4 text-center text-muted border rounded bg-light">
                            <i class="fa-solid fa-inbox fs-3 text-muted mb-2 d-block"></i>
                            Nenhum tutorial publicado nesta categoria ainda.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($artigosDaCategoriaAtiva as $art): ?>
                                <a href="artigo.php?s=<?php echo urlencode($servidorAtual['nome']); ?>&slug=<?php echo urlencode($art['slug']); ?>" 
                                   class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <?php if (!empty($art['resumo'])): ?>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($art['resumo'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-primary small fw-semibold text-nowrap ms-3">Ler guia <i class="fa-solid fa-arrow-right"></i></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- RESULTADOS DA BUSCA (EXIBIDO DINAMICAMENTE) -->
            <div id="wiki-search-results-content" style="display: none;">
                <!-- BREADCRUMB BUSCA -->
                <div class="wiki-breadcrumb">
                    <a href="index.php"><i class="fa-solid fa-house"></i> Wiki</a>
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                    <a href="servidor.php?s=<?php echo urlencode($servidorAtual['nome']); ?>"><?php echo htmlspecialchars($servidorAtual['servername'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                    <span class="text-primary fw-semibold">Pesquisa</span>
                </div>

                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <h2 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass text-primary"></i>
                            <span>Resultados da Pesquisa</span>
                        </h2>
                        <p class="text-muted small mb-0" id="wiki-search-label">Buscando tópicos...</p>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace" id="wiki-search-count">0 tópico(s)</span>
                </div>

                <div id="wiki-search-results-container" class="list-group"></div>
            </div>
        </div>
    </main>
</div>

<script>
window.wikiTodosArtigos = <?php echo json_encode($todosArtigosServidor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
window.wikiServidorSlug = <?php echo json_encode($servidorAtual['nome'], JSON_UNESCAPED_UNICODE); ?>;

window.executarBuscaWiki = function(query) {
    const categoryContent = document.getElementById('wiki-category-content');
    const searchContent = document.getElementById('wiki-search-results-content');
    const searchLabel = document.getElementById('wiki-search-label');
    const searchCount = document.getElementById('wiki-search-count');
    const resultsContainer = document.getElementById('wiki-search-results-container');

    if (!query) {
        if (categoryContent) categoryContent.style.display = '';
        if (searchContent) searchContent.style.display = 'none';
        return;
    }

    if (categoryContent) categoryContent.style.display = 'none';
    if (searchContent) searchContent.style.display = '';

    const termo = query.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
    
    const filtrados = (window.wikiTodosArtigos || []).filter(art => {
        const tit = (art.titulo || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
        const res = (art.resumo || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
        const cat = (art.categoria_nome || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
        return tit.includes(termo) || res.includes(termo) || cat.includes(termo);
    });

    if (searchLabel) {
        searchLabel.innerHTML = `Mostrando resultados para: <strong>"${escapeHtml(query)}"</strong>`;
    }
    if (searchCount) {
        searchCount.textContent = `${filtrados.length} tópico(s)`;
    }

    if (!resultsContainer) return;

    if (filtrados.length === 0) {
        resultsContainer.innerHTML = `
            <div class="p-4 text-center text-muted border rounded bg-light">
                <i class="fa-solid fa-magnifying-glass fs-3 text-muted mb-2 d-block"></i>
                Nenhum tópico encontrado com o termo "<strong>${escapeHtml(query)}</strong>".
                <p class="small text-muted mt-1 mb-0">Tente buscar por outras palavras-chave ou use as categorias ao lado.</p>
            </div>
        `;
        return;
    }

    resultsContainer.innerHTML = filtrados.map(art => {
        const url = `artigo.php?s=${encodeURIComponent(window.wikiServidorSlug)}&slug=${encodeURIComponent(art.slug)}`;
        const catBadge = art.categoria_nome ? `
            <span class="badge bg-light text-primary border mb-1" style="font-size: 0.72rem;">
                <i class="${escapeHtml(art.categoria_icone || 'fa-solid fa-folder')} me-1"></i>${escapeHtml(art.categoria_nome)}
            </span>
        ` : '';

        const resumo = art.resumo ? `<p class="text-muted small mb-0 mt-1">${escapeHtml(art.resumo)}</p>` : '';

        return `
            <a href="${url}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                <div class="min-w-0 pe-2">
                    ${catBadge}
                    <h6 class="fw-bold mb-0 text-dark">${escapeHtml(art.titulo)}</h6>
                    ${resumo}
                </div>
                <span class="text-primary small fw-semibold text-nowrap ms-3">Ler guia <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        `;
    }).join('');
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . "/includes/wiki_footer.php"; ?>