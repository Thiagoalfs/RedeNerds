<?php
$paginaAtiva = 'vips';
$tituloPagina = 'Pacotes VIP';
require_once __DIR__ . "/../includes/admin_header.php";

$filtroServidor = trim($_GET['servidor'] ?? '');

$servidores = [];
$servidoresMap = [];
try {
    $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon FROM servidores ORDER BY servername ASC");
    $servidores = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
    foreach ($servidores as $s) {
        $servidoresMap[$s['id']] = $s;
        $servidoresMap[$s['servername']] = $s;
        if (!empty($s['nome'])) {
            $servidoresMap[$s['nome']] = $s;
        }
    }
} catch (PDOException $e) {
    $servidores = [];
}

$where = [];
$params = [];

if ($filtroServidor !== '') {
    if (is_numeric($filtroServidor)) {
        $where[] = "(v.servidor_id = :servidor_id OR v.servidor = :servidor_nome)";
        $params[':servidor_id'] = (int)$filtroServidor;
        $srvObj = $servidoresMap[$filtroServidor] ?? null;
        $params[':servidor_nome'] = $srvObj ? $srvObj['servername'] : $filtroServidor;
    } else {
        $where[] = "(v.servidor = :servidor_nome OR v.servidor = :servidor_slug)";
        $params[':servidor_nome'] = $filtroServidor;
        $params[':servidor_slug'] = $filtroServidor;
    }
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$vips = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.*, s.servername as servidor_nome_oficial, s.themecolor as servidor_cor, s.icon as servidor_icone
        FROM vips v
        LEFT JOIN servidores s ON (s.id = v.servidor_id OR s.servername = v.servidor OR s.nome = v.servidor)
        $whereSql
        ORDER BY COALESCE(s.servername, v.servidor) ASC, v.preco ASC
    ");
    $stmt->execute($params);
    $vips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vips = [];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Pacotes VIP da Loja</h4>
        <p class="text-muted small mb-0"><?php echo count($vips); ?> pacote(s) VIP cadastrado(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/loja/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Loja</a>
        <a href="criar.php<?php echo $filtroServidor ? '?servidor=' . urlencode($filtroServidor) : ''; ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Pacote VIP</a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- FILTROS -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <select name="servidor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Filtrar por Servidor: Todos os Servidores</option>
                    <?php foreach ($servidores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$filtroServidor === (string)$s['id'] || $filtroServidor === $s['servername'] || $filtroServidor === $s['nome']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtroServidor !== ''): ?>
                <div class="col-12 col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm w-100">Limpar Filtro</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABELA DE VIPS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 160px;">Servidor</th>
                        <th>Pacote VIP</th>
                        <th style="width: 160px;">Package ID</th>
                        <th style="width: 140px;">Preço</th>
                        <th style="width: 120px;">Duração</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vips)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Nenhum pacote VIP encontrado<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>. Clique em "+ Novo Pacote VIP" para cadastrar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vips as $v): 
                            $srvNome = $v['servidor_nome_oficial'] ?: ($v['servidor'] ?: 'Servidor');
                            $srvCor = $v['servidor_cor'] ?: ($servidoresMap[$v['servidor_id'] ?? $v['servidor']]['themecolor'] ?? '#B971DA');
                        ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <div class="d-flex align-items-center gap-1">
                                            <strong class="text-dark"><?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($v['destaque'])): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque / Mais Popular na loja">
                                                    <i class="fa-solid fa-star me-1"></i>Destaque
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($v['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace">
                                        R$ <?php echo number_format((float)$v['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <?php echo (int)($v['duracao_dias'] ?? 30); ?> dias
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($v['ativo'])): ?>
                                        <span class="badge-status ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$v['id']; ?>" class="btn btn-sm btn-primary" title="Editar VIP">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="/admin/api/vips/toggle.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo !empty($v['ativo']) ? 'Desativar da loja' : 'Ativar na loja'; ?>">
                                                <i class="fa-solid <?php echo !empty($v['ativo']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/admin/api/vips/deletar.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir o pacote <?php echo htmlspecialchars(addslashes($v['nome']), ENT_QUOTES, 'UTF-8'); ?> do servidor <?php echo htmlspecialchars(addslashes($v['servidor']), ENT_QUOTES, 'UTF-8'); ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Deletar VIP">
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>