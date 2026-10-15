<?php
$paginaAtiva = 'cupons';
$tituloPagina = 'Cupons';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/servidores_admin.php";

$servidoresData = carregarServidoresAdmin($pdo);
$servidores = $servidoresData['lista'];
$servidoresMap = $servidoresData['mapa'];

// Garante a coluna servidor_id apenas uma vez por sessão
// (DDL completo em migrations/2026_10_14_vips_cupons_equipe.sql)
if (empty($_SESSION['cupons_schema_ok'])) {
    try {
        $colunasCupons = $pdo->query("SHOW COLUMNS FROM cupons")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('servidor_id', $colunasCupons, true)) {
            $pdo->exec("ALTER TABLE cupons ADD COLUMN servidor_id INT NULL DEFAULT 0");
        }
        $_SESSION['cupons_schema_ok'] = true;
    } catch (Exception $e) {
        error_log("Erro ao preparar schema de cupons: " . $e->getMessage());
    }
}

$cupons = [];
$cuponsAtivos = [];
$cuponsInativos = [];

function statusDoCupom(array $cupom, array $servidoresMap): array {
    $now = time();
    $expiraEm = strtotime($cupom['expira_em']);
    $isExpirado = ($expiraEm && $expiraEm < $now);
    $sId = (int)($cupom['servidor_id'] ?? 0);
    $srvObj = ($sId > 0 && isset($servidoresMap[$sId])) ? $servidoresMap[$sId] : null;
    $srvEnabled = $srvObj ? (int)($srvObj['enabled'] ?? 1) : 1;

    if ($isExpirado) {
        return [
            'label' => 'Expirado',
            'badge' => 'badge-status expirado',
            'ativo_real' => false,
            'motivo' => 'expirado'
        ];
    }

    if (!$cupom['ativo'] && $sId > 0 && $srvEnabled === 0) {
        return [
            'label' => 'Inativo',
            'badge' => 'badge-status inativo',
            'ativo_real' => false,
            'motivo' => 'ambos_inativos',
            'extra_label' => 'Servidor Oculto'
        ];
    }

    if (!$cupom['ativo']) {
        return [
            'label' => 'Inativo',
            'badge' => 'badge-status inativo',
            'ativo_real' => false,
            'motivo' => 'inativo'
        ];
    }

    if ($sId > 0 && $srvEnabled === 0) {
        return [
            'label' => 'Servidor Oculto',
            'badge' => 'badge-status pendente',
            'ativo_real' => false,
            'motivo' => 'servidor_oculto'
        ];
    }

    return [
        'label' => 'Ativo',
        'badge' => 'badge-status ativo',
        'ativo_real' => true,
        'motivo' => 'ativo'
    ];
}

try {
    $stmt = $pdo->query("SELECT * FROM cupons ORDER BY criado_em DESC");
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cupons as $c) {
        $st = statusDoCupom($c, $servidoresMap);
        $c['_status_info'] = $st;

        if ($st['ativo_real']) {
            $cuponsAtivos[] = $c;
        } else {
            $cuponsInativos[] = $c;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de cupons: " . $e->getMessage());
    $cupons = [];
    $cuponsAtivos = [];
    $cuponsInativos = [];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Cupons de Desconto</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($cupons), 'cupom', 'cupons'); ?></span> <?php echo count($cupons) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($cuponsAtivos), 'ativo', 'ativos'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($cuponsInativos), 'inativo', 'inativos'); ?></span>
        </p>
    </div>
    <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo cupom</a>
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

<!-- TABELA 1: CUPONS ATIVOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Cupons Ativos
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($cuponsAtivos), 'cupom', 'cupons'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-cupons-ativos">
                <thead>
                    <tr>
                        <th style="width: 170px;">Código do Cupom</th>
                        <th style="width: 130px;">Desconto</th>
                        <th style="width: 180px;">Servidor</th>
                        <th style="width: 180px;">Validade</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 90px;">Usos</th>
                        <th style="width: 140px;">Criado em</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cuponsAtivos)): ?>
                        <tr class="empty-row">
                            <td colspan="8" class="text-center py-4 text-muted">Nenhum cupom ativo no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuponsAtivos as $c): 
                            $st = $c['_status_info'] ?? statusDoCupom($c, $servidoresMap);
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-6 px-2 py-1">
                                        <i class="fa-solid fa-tag text-primary me-1"></i>
                                        <?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold"><?php echo number_format((float)$c['porcentagem_desconto'], 1, ',', '.'); ?>% OFF</strong>
                                </td>
                                <td>
                                    <?php 
                                    $sId = (int)($c['servidor_id'] ?? 0);
                                    if ($sId > 0 && isset($servidoresMap[$sId])): 
                                        $srv = $servidoresMap[$sId];
                                        $corSrv = !empty($srv['themecolor']) ? $srv['themecolor'] : '#6366f1';
                                    ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($corSrv, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                            <i class="fa-solid fa-server me-1"></i><?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-body border">
                                            <i class="fa-solid fa-globe me-1 text-primary"></i> Todos os Servidores
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fa-regular fa-clock text-muted me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($c['expira_em'])); ?>
                                </td>
                                <td>
                                    <span class="<?php echo $st['badge']; ?>"><?php echo $st['label']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?php echo pluralizar((int)$c['usos_total'], 'compra', 'compras'); ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($c['criado_em'])); ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/cupons/toggle.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                title="Desativar cupom">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/cupons/deletar.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o cupom <?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
                                                title="Deletar">
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

<!-- TABELA 2: CUPONS INATIVOS / EXPIRADOS / SERVIDOR DESATIVADO -->
<div class="admin-card">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Cupons Inativos / Expirados / Servidor Desativado
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($cuponsInativos), 'cupom', 'cupons'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-cupons-inativos">
                <thead>
                    <tr>
                        <th style="width: 170px;">Código do Cupom</th>
                        <th style="width: 130px;">Desconto</th>
                        <th style="width: 180px;">Servidor</th>
                        <th style="width: 180px;">Validade</th>
                        <th style="width: 160px;">Status</th>
                        <th style="width: 90px;">Usos</th>
                        <th style="width: 140px;">Criado em</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cuponsInativos)): ?>
                        <tr class="empty-row">
                            <td colspan="8" class="text-center py-4 text-muted">Nenhum cupom inativo.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuponsInativos as $c): 
                            $st = $c['_status_info'] ?? statusDoCupom($c, $servidoresMap);
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-6 px-2 py-1">
                                        <i class="fa-solid fa-tag text-primary me-1"></i>
                                        <?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold"><?php echo number_format((float)$c['porcentagem_desconto'], 1, ',', '.'); ?>% OFF</strong>
                                </td>
                                <td>
                                    <?php 
                                    $sId = (int)($c['servidor_id'] ?? 0);
                                    if ($sId > 0 && isset($servidoresMap[$sId])): 
                                        $srv = $servidoresMap[$sId];
                                        $corSrv = !empty($srv['themecolor']) ? $srv['themecolor'] : '#6366f1';
                                    ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($corSrv, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                            <i class="fa-solid fa-server me-1"></i><?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-body border">
                                            <i class="fa-solid fa-globe me-1 text-primary"></i> Todos os Servidores
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fa-regular fa-clock text-muted me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($c['expira_em'])); ?>
                                </td>
                                <td>
                                    <span class="<?php echo $st['badge']; ?>">
                                        <?php if ($st['motivo'] === 'servidor_oculto'): ?>
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                        <?php endif; ?>
                                        <?php echo $st['label']; ?>
                                    </span>
                                    <?php if (!empty($st['extra_label'])): ?>
                                        <span class="badge-status pendente ms-1" title="Servidor vinculado também está desativado">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo $st['extra_label']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?php echo pluralizar((int)$c['usos_total'], 'compra', 'compras'); ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($c['criado_em'])); ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/cupons/toggle.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                title="<?php echo $c['ativo'] ? 'Desativar cupom' : 'Ativar cupom'; ?>">
                                            <i class="fa-solid <?php echo $c['ativo'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/cupons/deletar.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o cupom <?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
                                                title="Deletar">
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