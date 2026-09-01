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

$slugServidor = trim($_GET['s'] ?? '');
$slugArtigo = trim($_GET['slug'] ?? '');

$servidorAtual = (isset($pdo) && $pdo instanceof PDO) ? getServidorWikiPorSlug($pdo, $slugServidor) : null;

if (!$servidorAtual || empty($slugArtigo)) {
    header("Location: index.php");
    exit;
}

// Busca o artigo no banco
$stmt = $pdo->prepare("
    SELECT a.*, c.nome AS categoria_nome, c.slug AS categoria_slug 
    FROM wiki_artigos a 
    JOIN wiki_categorias c ON c.id = a.categoria_id 
    WHERE a.servidor_id = :servidor_id AND a.slug = :slug AND a.publicado = 1 
    LIMIT 1
");
$stmt->execute([
    ':servidor_id' => $servidorAtual['id'],
    ':slug'        => $slugArtigo
]);
$artigo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artigo) {
    header("Location: servidor.php?s=" . urlencode($servidorAtual['nome']));
    exit;
}

// Incrementa visualização
try {
    $pdo->prepare("UPDATE wiki_artigos SET visualizacoes = visualizacoes + 1 WHERE id = :id")->execute([':id' => $artigo['id']]);
} catch (Exception $e) {}

// Busca categorias do servidor para a sidebar esquerda
$categorias = getCategoriasServidorWiki($pdo, (int)$servidorAtual['id']);
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

$categoriaAtivaId = (int)$artigo['categoria_id'];
$artigoAtivoId = (int)$artigo['id'];

$tituloPagina = $artigo['titulo'] . " - Wiki " . $servidorAtual['servername'];
require_once __DIR__ . "/includes/wiki_header.php";

$headings = [];
$conteudoHtml = parseMarkdownWiki($artigo['conteudo'], $headings);
?>

<div class="wiki-layout-docs">
    <!-- SIDEBAR ESQUERDA COM A CATEGORIA MÃE EXPANDIDA -->
    <?php require __DIR__ . "/includes/wiki_sidebar.php"; ?>

    <!-- ÁREA PRINCIPAL DO ARTIGO -->
    <main class="wiki-docs-main">
        <div class="row g-4 align-items-start">
            <!-- CONTEÚDO DO ARTIGO -->
            <div class="col-12 col-xl-9">
                <article class="wiki-content-card">
                    <!-- BREADCRUMB -->
                    <div class="wiki-breadcrumb">
                        <a href="index.php"><i class="fa-solid fa-house"></i> Wiki</a>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                        <a href="servidor.php?s=<?php echo urlencode($servidorAtual['nome']); ?>"><?php echo htmlspecialchars($servidorAtual['servername'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                        <a href="servidor.php?s=<?php echo urlencode($servidorAtual['nome']); ?>&cat=<?php echo urlencode($artigo['categoria_slug']); ?>"><?php echo htmlspecialchars($artigo['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>

                    <h1 class="wiki-article-title"><?php echo htmlspecialchars($artigo['titulo'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <div class="wiki-article-meta">
                        <span><i class="fa-solid fa-user me-1"></i> <?php echo htmlspecialchars($artigo['autor'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>•</span>
                        <span><i class="fa-regular fa-clock me-1"></i> <?php echo date('d/m/Y', strtotime($artigo['atualizado_em'])); ?></span>
                        <span>•</span>
                        <span><i class="fa-regular fa-eye me-1"></i> <?php echo (int)$artigo['visualizacoes']; ?> visualizações</span>
                    </div>

                    <div class="wiki-content">
                        <?php echo $conteudoHtml; ?>
                    </div>

                    <div class="border-top pt-3 mt-4 text-start">
                        <a href="servidor.php?s=<?php echo urlencode($servidorAtual['nome']); ?>&cat=<?php echo urlencode($artigo['categoria_slug']); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left me-1"></i> Voltar para <?php echo htmlspecialchars($artigo['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </article>
            </div>

            <!-- SUMÁRIO FLUTUANTE (TOC) À DIREITA -->
            <div class="col-12 col-xl-3 d-none d-xl-block">
                <div class="wiki-toc-box">
                    <div class="wiki-toc-title">Nesta Página</div>
                    <?php if (empty($headings)): ?>
                        <p class="text-muted small mb-0">Início do guia.</p>
                    <?php else: ?>
                        <ul class="wiki-toc-list">
                            <?php foreach ($headings as $h): ?>
                                <li style="<?php echo ($h['level'] === 3) ? 'padding-left: 0.75rem;' : ''; ?>">
                                    <a href="#<?php echo htmlspecialchars($h['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($h['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . "/includes/wiki_footer.php"; ?>