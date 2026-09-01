<?php
$paginaAtiva = 'wiki';
$tituloPagina = 'Wiki & Tutoriais';
require_once __DIR__ . "/includes/admin_header.php";

$filtroServidor = isset($_GET['servidor_id']) ? (int)$_GET['servidor_id'] : 0;
$servidores = $pdo->query("SELECT id, servername FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($filtroServidor > 0) {
    $where[] = "a.servidor_id = :servidor_id";
    $params[':servidor_id'] = $filtroServidor;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$artigos = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.titulo, a.slug, a.publicado, a.visualizacoes, a.autor, a.criado_em,
               s.servername, s.nome as servidor_slug, c.nome as categoria_nome
        FROM wiki_artigos a
        JOIN servidores s ON s.id = a.servidor_id
        JOIN wiki_categorias c ON c.id = a.categoria_id
        $whereSql
        ORDER BY a.id DESC
    ");
    $stmt->execute($params);
    $artigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Wiki & Base de Conhecimento</h4>
        <p class="text-muted small mb-0"><?php echo count($artigos); ?> artigo(s) cadastrado(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/wiki/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Wiki</a>
        <a href="wiki_artigo_criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Artigo</a>
    </div>
</div>

<!-- SUB-ABAS DE NAVEGAÇÃO -->
<div class="d-flex gap-2 mb-3">
    <a href="wiki.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-primary btn-sm">
        <i class="fa-regular fa-file-lines me-1"></i> Artigos da Wiki
    </a>
    <a href="wiki_categorias.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-folder-tree me-1"></i> Gerenciar Categorias
    </a>
</div>

<!-- FILTROS -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="wiki.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <select name="servidor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Filtrar por Servidor: Todos</option>
                    <?php foreach ($servidores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo ($filtroServidor === (int)$s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtroServidor > 0): ?>
                <div class="col-12 col-md-2">
                    <a href="wiki.php" class="btn btn-outline-secondary btn-sm w-100">Limpar Filtro</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABELA DE ARTIGOS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th>Título do Artigo</th>
                        <th style="width: 150px;">Servidor</th>
                        <th style="width: 180px;">Categoria</th>
                        <th style="width: 100px;">Visualizações</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 140px;">Data</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($artigos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum artigo cadastrado ainda. Clique em "+ Novo Artigo" para começar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($artigos as $art): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="text-muted d-block font-monospace" style="font-size: 0.7rem;">/wiki/artigo.php?s=<?php echo urlencode($art['servidor_slug']); ?>&slug=<?php echo urlencode($art['slug']); ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($art['servername'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($art['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo (int)$art['visualizacoes']; ?></span></td>
                                <td>
                                    <?php if ($art['publicado']): ?>
                                        <span class="badge-status ativo">Publicado</span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Oculto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($art['criado_em'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="wiki_artigo_editar.php?id=<?php echo (int)$art['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <form method="POST" action="api/wiki/toggle.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$art['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $art['publicado'] ? 'Ocultar' : 'Publicar'; ?>">
                                                <i class="fa-solid <?php echo $art['publicado'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="api/wiki/deletar.php" class="d-inline" onsubmit="return confirm('Deletar este artigo da wiki?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$art['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Deletar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>