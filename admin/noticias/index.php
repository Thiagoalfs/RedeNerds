<?php
$paginaAtiva = 'noticias';
$tituloPagina = 'Novidades';
require_once __DIR__ . "/../includes/admin_header.php";

const POR_PAGINA = 10;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$busca = trim($_GET['busca'] ?? '');
$whereSql = !empty($busca) ? "WHERE titulo LIKE :busca OR autor LIKE :busca OR category LIKE :busca" : "";
$params = !empty($busca) ? [':busca' => "%{$busca}%"] : [];

$totalNoticias = 0;
$noticias = [];
$servidoresMap = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon FROM servidores ORDER BY servername ASC");
        $servidoresList = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($servidoresList as $s) {
            $servidoresMap[$s['servername']] = $s;
            if (!empty($s['nome'])) {
                $servidoresMap[$s['nome']] = $s;
            }
        }

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM novidades $whereSql");
        $stmtCount->execute($params);
        $totalNoticias = (int)$stmtCount->fetchColumn();

        $totalPaginas = max(1, (int)ceil($totalNoticias / POR_PAGINA));

        $stmt = $pdo->prepare("
            SELECT id, titulo, category, categoria_envio, capa, criado_em, autor 
            FROM novidades 
            $whereSql 
            ORDER BY criado_em DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao listar notícias: " . $e->getMessage());
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Novidades</h4>
        <p class="text-muted small mb-0"><?php echo $totalNoticias; ?> novidade(s) cadastrada(s)</p>
    </div>
    <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Nova novidade</a>
</div>

<!-- BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por título, autor ou servidor..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Buscar</button>
                <?php if (!empty($busca)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpar Busca"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE NOVIDADES -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">Capa</th>
                        <th>Título</th>
                        <th style="width: 130px;">Servidor</th>
                        <th style="width: 140px;">Categoria</th>
                        <th style="width: 130px;">Autor</th>
                        <th style="width: 140px;">Data</th>
                        <th class="text-end" style="width: 130px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($noticias)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhuma novidade encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($noticias as $n): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($n['capa'])): ?>
                                        <img src="<?php echo htmlspecialchars($n['capa'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded border" width="48" height="32" style="object-fit: cover;" alt="Capa" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                        $srvKey = $n['category'] ?? '';
                                        $srvInfo = $servidoresMap[$srvKey] ?? null;
                                        $srvCor = $srvInfo['themecolor'] ?? '#64748b';
                                    ?>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvKey ?: 'Geral', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($n['categoria_envio'] ?? 'Atualização', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><small><?php echo htmlspecialchars($n['autor'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($n['criado_em'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$n['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <form method="POST" action="/admin/api/noticias/deletar.php" class="d-inline" onsubmit="return confirm('Deletar esta novidade? Essa ação não pode ser desfeita.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
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

<!-- PAGINAÇÃO -->
<?php if (isset($totalPaginas) && $totalPaginas > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
        <ul class="pagination pagination-sm">
            <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo max(1, $pagina - 1); ?>&busca=<?php echo urlencode($busca); ?>">‹ Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo min($totalPaginas, $pagina + 1); ?>&busca=<?php echo urlencode($busca); ?>">Próxima ›</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
