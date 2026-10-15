<?php
$paginaAtiva = 'vips';
$tituloPagina = 'Pacotes VIP';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/servidores_admin.php";

$filtroServidor = trim($_GET['servidor'] ?? '');

$servidoresData = carregarServidoresAdmin($pdo);
$servidores = $servidoresData['lista'];
$servidoresMap = $servidoresData['mapa'];

$where = [];
$params = [];

if ($filtroServidor !== '') {
    if (is_numeric($filtroServidor)) {
        $where[] = "(v.servidor_id = :servidor_id OR v.servidor = :servidor_nome)";
        $params[':servidor_id'] = (int)$filtroServidor;
        $srvObj = $servidoresMap[$filtroServidor] ?? null;
        $params[':servidor_nome'] = $srvObj ? $srvObj['servername'] : $filtroServidor;
    } else {
        $where[] = "v.servidor = :servidor_nome";
        $params[':servidor_nome'] = $filtroServidor;
    }
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$vips = [];
try {
    $stmt = $pdo->prepare("SELECT v.* FROM vips v $whereSql ORDER BY v.id DESC");
    $stmt->execute($params);
    $vipsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vipsRaw as $v) {
        $srvObj = null;
        if (!empty($v['servidor_id']) && isset($servidoresMap[$v['servidor_id']])) {
            $srvObj = $servidoresMap[$v['servidor_id']];
        } elseif (!empty($v['servidor'])) {
            $srvObj = $servidoresMap[$v['servidor']] ?? null;
            if (!$srvObj) {
                foreach ($servidores as $s) {
                    if (strcasecmp($s['servername'], $v['servidor']) === 0 || (!empty($s['nome']) && strcasecmp($s['nome'], $v['servidor']) === 0)) {
                        $srvObj = $s;
                        break;
                    }
                }
            }
        }

        $v['servidor_nome_oficial'] = $srvObj['servername'] ?? ($v['servidor'] ?? 'Servidor');
        $v['servidor_cor'] = $srvObj['themecolor'] ?? '#B971DA';
        $v['servidor_icone'] = $srvObj['icon'] ?? null;
        $v['servidor_enabled'] = isset($srvObj['enabled']) ? (int)$srvObj['enabled'] : 1;

        $vips[] = $v;
    }

    usort($vips, function($a, $b) {
        $cmpSrv = strcasecmp($a['servidor_nome_oficial'], $b['servidor_nome_oficial']);
        if ($cmpSrv !== 0) return $cmpSrv;
        return ((float)($a['preco'] ?? 0)) <=> ((float)($b['preco'] ?? 0));
    });

    $vipsAtivos = [];
    $vipsInativos = [];
    foreach ($vips as $v) {
        $srvEnabled = (int)($v['servidor_enabled'] ?? 1);
        $itemAtivo = !empty($v['ativo']);

        if ($itemAtivo && $srvEnabled === 1) {
            $vipsAtivos[] = $v;
        } else {
            if ($srvEnabled === 0 && $itemAtivo) {
                $v['motivo_inativo'] = 'servidor_oculto';
            } elseif ($srvEnabled === 0 && !$itemAtivo) {
                $v['motivo_inativo'] = 'ambos_inativos';
            } else {
                $v['motivo_inativo'] = 'item_inativo';
            }
            $vipsInativos[] = $v;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de VIPs: " . $e->getMessage());
    $vips = [];
    $vipsAtivos = [];
    $vipsInativos = [];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Pacotes VIP da Loja</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($vips), 'pacote', 'pacotes'); ?></span> VIP <?php echo count($vips) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($vipsAtivos), 'ativo', 'ativos'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($vipsInativos), 'inativo', 'inativos'); ?></span>
        </p>
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

<!-- TABELA 1: VIPS ATIVOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Pacotes VIP Ativos
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($vipsAtivos), 'pacote', 'pacotes'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-vips-ativos">
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
                    <?php if (empty($vipsAtivos)): ?>
                        <tr class="empty-row">
                            <td colspan="7" class="text-center py-4 text-muted">
                                Nenhum pacote VIP ativo no momento<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vipsAtivos as $v): 
                            $srvNome = $v['servidor_nome_oficial'] ?: ($servidoresMap[$v['servidor_id']]['servername'] ?? ($v['servidor'] ?: 'Servidor'));
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
                                    <span class="badge-status ativo">Ativo</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$v['id']; ?>" class="btn btn-sm btn-primary" title="Editar VIP">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/vips/toggle.php" 
                                                data-id="<?php echo (int)$v['id']; ?>"
                                                title="Desativar da loja">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/vips/deletar.php" 
                                                data-id="<?php echo (int)$v['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja excluir o pacote <?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?> do servidor <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar VIP">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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

<!-- TABELA 2: VIPS INATIVOS OU COM SERVIDOR DESATIVADO -->
<div class="admin-card">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Pacotes VIP Inativos / Servidor Desativado
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($vipsInativos), 'pacote', 'pacotes'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-vips-inativos">
                <thead>
                    <tr>
                        <th style="width: 160px;">Servidor</th>
                        <th>Pacote VIP</th>
                        <th style="width: 160px;">Package ID</th>
                        <th style="width: 140px;">Preço</th>
                        <th style="width: 120px;">Duração</th>
                        <th style="width: 180px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vipsInativos)): ?>
                        <tr class="empty-row">
                            <td colspan="7" class="text-center py-4 text-muted">
                                Nenhum pacote VIP inativo<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vipsInativos as $v): 
                            $srvNome = $v['servidor_nome_oficial'] ?: ($servidoresMap[$v['servidor_id']]['servername'] ?? ($v['servidor'] ?: 'Servidor'));
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
                                    <?php if ($v['motivo_inativo'] === 'servidor_oculto'): ?>
                                        <span class="badge-status pendente" title="O pacote está ativo, mas o servidor está desativado no painel">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto
                                        </span>
                                    <?php elseif ($v['motivo_inativo'] === 'ambos_inativos'): ?>
                                        <span class="badge-status inativo">Inativo</span>
                                        <span class="badge-status pendente ms-1" title="Servidor também está desativado">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$v['id']; ?>" class="btn btn-sm btn-primary" title="Editar VIP">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/vips/toggle.php" 
                                                data-id="<?php echo (int)$v['id']; ?>"
                                                title="<?php echo !empty($v['ativo']) ? 'Desativar da loja' : 'Ativar na loja'; ?>">
                                            <i class="fa-solid <?php echo !empty($v['ativo']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/vips/deletar.php" 
                                                data-id="<?php echo (int)$v['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja excluir o pacote <?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?> do servidor <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar VIP">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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