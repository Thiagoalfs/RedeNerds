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

// Busca artigos por categoria
$artigosPorCategoria = [];
try {
    $stmtArtigos = $pdo->prepare("
        SELECT id, categoria_id, titulo, slug, resumo, visualizacoes, criado_em 
        FROM wiki_artigos 
        WHERE servidor_id = :servidor_id AND publicado = 1 
        ORDER BY ordem ASC, id ASC
    ");
    $stmtArtigos->execute([':servidor_id' => $servidorAtual['id']]);
    $artigosDb = $stmtArtigos->fetchAll(PDO::FETCH_ASSOC);
    foreach ($artigosDb as $art) {
        $artigosPorCategoria[$art['categoria_id']][] = $art;
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
    <!-- SIDEBAR ESQUERDA (CATEGORIAS ACCORDION) -->
    <?php require __DIR__ . "/includes/wiki_sidebar.php"; ?>

    <!-- ÁREA PRINCIPAL À DIREITA -->
    <main class="wiki-docs-main">
        <div class="wiki-content-card">
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
    </main>
</div>

<?php require_once __DIR__ . "/includes/wiki_footer.php"; ?>