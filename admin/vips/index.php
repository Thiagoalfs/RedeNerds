<?php
$paginaAtiva = 'vips';
$tituloPagina = 'Pacotes VIP';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/servidores_admin.php";

if (empty($_SESSION['vips_schema_multiservidor_ok'])) {
    try {
        $pdo->exec("ALTER TABLE vips MODIFY COLUMN servidor_id INT NULL DEFAULT NULL");
        $_SESSION['vips_schema_multiservidor_ok'] = true;
    } catch (Exception $e) {}
}

$filtroServidor = trim($_GET['servidor'] ?? '');

$servidoresData = carregarServidoresAdmin($pdo);
$servidores = $servidoresData['lista'];
$servidoresMap = $servidoresData['mapa'];

$where = [];
$params = [];

if ($filtroServidor !== '') {
    if ($filtroServidor === 'multi' || $filtroServidor === '0') {
        $where[] = "(v.servidor_id = 0 OR v.servidor_id IS NULL OR v.servidor = 'todos' OR v.servidor = 'global' OR v.servidor = 'rede')";
    } elseif (is_numeric($filtroServidor)) {
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

<style>
.scratch-swatch-box {
    width: 32px;
    height: 32px;
    padding: 2px;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    transition: all 0.15s ease;
}
.scratch-swatch-box:hover {
    border-color: #cbd5e1;
}
.scratch-swatch-box.active {
    border-color: #8c52ff;
    box-shadow: 0 0 0 2px rgba(140, 82, 255, 0.35);
}
.scratch-swatch {
    width: 100%;
    height: 100%;
    border-radius: 4px;
    display: block;
    border: 1px solid rgba(0,0,0,0.15);
}
.scratch-swap-btn {
    background: #334155;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    width: 28px;
    height: 28px;
    min-width: 28px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.78rem;
    padding: 0;
    transition: transform 0.15s ease, background-color 0.15s ease;
}
.scratch-swap-btn:hover {
    background: #475569;
    color: #ffffff;
    transform: scale(1.08);
}
.scratch-swap-btn i {
    color: #ffffff !important;
    line-height: 1;
}
.scratch-popover {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 1070;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    width: 230px;
}
.scratch-preset-btn {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.2);
    cursor: pointer;
    padding: 0;
    transition: transform 0.1s ease;
}
.scratch-preset-btn:hover {
    transform: scale(1.2);
}
.tag-minimal-switch {
    display: inline-flex;
    background: #0f172a;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid #1e293b;
    gap: 4px;
    height: 46px;
    box-sizing: border-box;
    align-items: stretch;
}
.tag-switch-btn {
    border: none;
    background: transparent;
    color: #94a3b8;
    padding: 6px 16px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
}
.tag-switch-btn:hover {
    color: #f8fafc;
    background: rgba(255, 255, 255, 0.05);
}
.tag-switch-btn.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
</style>

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
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarVip">
            <i class="fa-solid fa-plus me-1"></i> Novo Pacote VIP
        </button>
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
                    <option value="multi" <?php echo ($filtroServidor === 'multi' || $filtroServidor === '0') ? 'selected' : ''; ?>>🌐 Apenas Pacotes Multiservidor (Rede)</option>
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
        <h6 class="admin-card-title mb-0">Pacotes VIP Ativos</h6>
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
                <tbody id="lista-vips-ativos">
                    <?php if (empty($vipsAtivos)): ?>
                        <tr class="empty-row" id="linha-sem-vips-ativos">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-solid fa-gem"></i></div>
                                    <h6 class="empty-title">Nenhum pacote VIP ativo</h6>
                                    <p class="empty-desc">Cadastre um novo plano VIP para começar a vender na loja.</p>
                                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCriarVip">
                                        <i class="fa-solid fa-plus me-1"></i> Cadastrar Pacote VIP
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vipsAtivos as $v): 
                            $isMulti = empty($v['servidor_id']) || (int)$v['servidor_id'] === 0 || in_array(strtolower(trim($v['servidor'] ?? '')), ['todos', 'global', 'rede']);
                            $srvNome = $isMulti ? 'Todos os Servidores' : ($v['servidor_nome_oficial'] ?: ($servidoresMap[$v['servidor_id']]['servername'] ?? ($v['servidor'] ?: 'Servidor')));
                            $srvCor = $isMulti ? '#0284c7' : ($v['servidor_cor'] ?: ($servidoresMap[$v['servidor_id'] ?? $v['servidor']]['themecolor'] ?? '#B971DA'));
                            $cor1 = !empty($v['cor1']) ? $v['cor1'] : '#ffffff';
                            $cor2 = !empty($v['cor2']) ? $v['cor2'] : '#ffffff';
                        ?>
                            <tr id="vip-row-<?php echo (int)$v['id']; ?>"
                                data-id="<?php echo (int)$v['id']; ?>"
                                data-servidor-id="<?php echo (int)$v['servidor_id']; ?>"
                                data-nome="<?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-package-id="<?php echo htmlspecialchars($v['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-preco="<?php echo htmlspecialchars(number_format((float)$v['preco'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-duracao-dias="<?php echo (int)($v['duracao_dias'] ?? 30); ?>"
                                data-destaque="<?php echo !empty($v['destaque']) ? '1' : '0'; ?>"
                                data-ativo="<?php echo !empty($v['ativo']) ? '1' : '0'; ?>"
                                data-cor1="<?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor2="<?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-tipo="<?php echo htmlspecialchars($v['cor_tipo'] ?? 'colorwheel', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-string="<?php echo htmlspecialchars($v['cor_string'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-vantagens="<?php echo htmlspecialchars($v['vantagens'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <span class="badge js-vip-servidor" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid <?php echo $isMulti ? 'fa-globe' : 'fa-server'; ?> me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <div class="d-flex align-items-center gap-1">
                                            <strong class="text-dark js-vip-nome"><?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="js-vip-destaque-badge">
                                                <?php if (!empty($v['destaque'])): ?>
                                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque / Mais Popular na loja">
                                                        <i class="fa-solid fa-star me-1"></i>Destaque
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace js-vip-package" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($v['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace js-vip-preco">
                                        R$ <?php echo number_format((float)$v['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="text-muted small js-vip-duracao">
                                        <?php 
                                        $d = isset($v['duracao_dias']) ? (int)$v['duracao_dias'] : 30;
                                        if ($d === 0) {
                                            echo '<span class="badge bg-success-subtle text-success border border-success-subtle">Permanente</span>';
                                        } else {
                                            echo $d . ' dias';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status ativo">Ativo</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-vip" title="Editar VIP">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
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
        <h6 class="admin-card-title mb-0">Pacotes VIP Inativos / Servidor Desativado</h6>
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
                <tbody id="lista-vips-inativos">
                    <?php if (empty($vipsInativos)): ?>
                        <tr class="empty-row" id="linha-sem-vips-inativos">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
                                    <h6 class="empty-title">Nenhum pacote VIP inativo</h6>
                                    <p class="empty-desc">Todos os pacotes VIP cadastrados estão ativos na loja.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vipsInativos as $v): 
                            $isMulti = empty($v['servidor_id']) || (int)$v['servidor_id'] === 0 || in_array(strtolower(trim($v['servidor'] ?? '')), ['todos', 'global', 'rede']);
                            $srvNome = $isMulti ? 'Todos os Servidores' : ($v['servidor_nome_oficial'] ?: ($servidoresMap[$v['servidor_id']]['servername'] ?? ($v['servidor'] ?: 'Servidor')));
                            $srvCor = $isMulti ? '#0284c7' : ($v['servidor_cor'] ?: ($servidoresMap[$v['servidor_id'] ?? $v['servidor']]['themecolor'] ?? '#B971DA'));
                            $cor1 = !empty($v['cor1']) ? $v['cor1'] : '#ffffff';
                            $cor2 = !empty($v['cor2']) ? $v['cor2'] : '#ffffff';
                        ?>
                            <tr id="vip-row-<?php echo (int)$v['id']; ?>"
                                data-id="<?php echo (int)$v['id']; ?>"
                                data-servidor-id="<?php echo (int)$v['servidor_id']; ?>"
                                data-nome="<?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-package-id="<?php echo htmlspecialchars($v['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-preco="<?php echo htmlspecialchars(number_format((float)$v['preco'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-duracao-dias="<?php echo (int)($v['duracao_dias'] ?? 30); ?>"
                                data-destaque="<?php echo !empty($v['destaque']) ? '1' : '0'; ?>"
                                data-ativo="<?php echo !empty($v['ativo']) ? '1' : '0'; ?>"
                                data-cor1="<?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor2="<?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-tipo="<?php echo htmlspecialchars($v['cor_tipo'] ?? 'colorwheel', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-string="<?php echo htmlspecialchars($v['cor_string'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-vantagens="<?php echo htmlspecialchars($v['vantagens'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <span class="badge js-vip-servidor" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid <?php echo $isMulti ? 'fa-globe' : 'fa-server'; ?> me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <div class="d-flex align-items-center gap-1">
                                            <strong class="text-dark js-vip-nome"><?php echo htmlspecialchars($v['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="js-vip-destaque-badge">
                                                <?php if (!empty($v['destaque'])): ?>
                                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque / Mais Popular na loja">
                                                        <i class="fa-solid fa-star me-1"></i>Destaque
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace js-vip-package" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($v['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace js-vip-preco">
                                        R$ <?php echo number_format((float)$v['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="text-muted small js-vip-duracao">
                                        <?php 
                                        $d = isset($v['duracao_dias']) ? (int)$v['duracao_dias'] : 30;
                                        if ($d === 0) {
                                            echo '<span class="badge bg-success-subtle text-success border border-success-subtle">Permanente</span>';
                                        } else {
                                            echo $d . ' dias';
                                        }
                                        ?>
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
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-vip" title="Editar VIP">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
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

<!-- MODAL: CRIAR PACOTE VIP -->
<div class="modal fade" id="modalCriarVip" tabindex="-1" aria-labelledby="modalCriarVipLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content shadow">
            <form id="formCriarVip">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarVipLabel">Cadastrar Novo Pacote VIP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label for="criar-vip-servidor" class="form-label fw-bold">Servidor <span class="text-danger">*</span></label>
                            <select class="form-select" id="criar-vip-servidor" name="servidor_id" required>
                                <option value="">Selecione o servidor...</option>
                                <option value="0" <?php echo ($filtroServidor === 'multi' || (string)$filtroServidor === '0') ? 'selected' : ''; ?>>🌐 Todos os Servidores (Multiservidor)</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$filtroServidor === (string)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="criar-vip-nome" class="form-label fw-bold">Nome do Pacote VIP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criar-vip-nome" name="nome" placeholder="Ex: VIP Netherite" required oninput="atualizarPreviewTagVip('criar-vip')">
                        </div>
                        <div class="col-md-3">
                            <label for="criar-vip-packageId" class="form-label fw-bold">Package ID</label>
                            <input type="text" class="form-control font-monospace" id="criar-vip-packageId" name="packageId" placeholder="Ex: vip_netherite">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="criar-vip-preco" class="form-label fw-bold">Preço (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="criar-vip-preco" name="preco" value="20.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="criar-vip-duracao" class="form-label fw-bold">Duração (dias) <span class="text-muted fw-normal small">(0 = Permanente)</span></label>
                            <input type="number" min="0" max="3650" class="form-control" id="criar-vip-duracao" name="duracao_dias" value="30">
                        </div>
                    </div>

                    <!-- SISTEMA DE COR DA TAG (DEGRADÊ OU STRING MINECRAFT) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Cor da Tag</label>
                        <div class="d-flex align-items-center gap-2">
                            <!-- 1. PREVIEW NA ESQUERDA -->
                            <div class="rounded border text-center font-monospace d-flex align-items-center justify-content-center px-3"
                                 style="background: #0f172a; border-color: #334155 !important; height: 46px; min-width: 150px; max-width: 200px; flex-shrink: 0;"
                                 title="Prévia em tempo real">
                                <div id="criar-vip-tag-preview-content" class="fw-bold" style="font-size: 1.05rem; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                            </div>

                            <!-- 2. INPUTFIELD NO MEIO -->
                            <div class="flex-grow-1 position-relative" style="min-width: 0;">
                                <!-- Painel Degradê -->
                                <div id="criar-vip-panel-colorwheel" class="w-100">
                                    <div class="scratch-gradient-wrapper position-relative">
                                        <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background: #0f172a; border-color: #334155; height: 46px; box-sizing: border-box;">
                                            <div class="scratch-swatch-box" id="criar-vip-swatch-cor1-btn" onclick="abrirSeletorCorVip('criar-vip', 'cor1', event)" title="Cor inicial (clique para escolher)">
                                                <span class="scratch-swatch" id="criar-vip-preview-swatch-cor1" style="background-color: #ffffff;"></span>
                                            </div>
                                            <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradienteVip('criar-vip', event)" title="Inverter cores">
                                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                            </button>
                                            <div class="scratch-swatch-box" id="criar-vip-swatch-cor2-btn" onclick="abrirSeletorCorVip('criar-vip', 'cor2', event)" title="Cor final (clique para escolher)">
                                                <span class="scratch-swatch" id="criar-vip-preview-swatch-cor2" style="background-color: #ffffff;"></span>
                                            </div>
                                            <div class="flex-grow-1 px-1">
                                                <div id="criar-vip-scratch-mini-gradient-bar" class="rounded border border-secondary" style="height: 18px; width: 100%; background: linear-gradient(90deg, #ffffff, #ffffff);"></div>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 text-muted d-flex align-items-center justify-content-center" title="Resetar cores para padrão (#ffffff)" onclick="resetarCoresPadraoVip('criar-vip')" style="width: 28px; height: 28px; flex-shrink: 0;">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" id="criar-vip-cor1" name="cor1" value="#ffffff">
                                        <input type="hidden" id="criar-vip-cor2" name="cor2" value="#ffffff">

                                        <!-- Floating Popover Picker -->
                                        <div id="criar-vip-scratch-popover" class="scratch-popover shadow-lg" style="display: none;" onclick="event.stopPropagation()">
                                            <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                                                <span class="small fw-bold text-dark" id="criar-vip-scratch-popover-title">Selecionar Cor</span>
                                                <button type="button" class="btn-close btn-sm" onclick="fecharSeletorCorVip('criar-vip')"></button>
                                            </div>
                                            <div class="mb-2">
                                                <input type="color" id="criar-vip-scratch-native-picker" class="form-control form-control-color w-100 mb-2" style="height: 42px; cursor: pointer;" oninput="onNativeColorChangeVip('criar-vip', this.value)">
                                                <div class="d-flex flex-wrap gap-1 mb-2">
                                                    <button type="button" class="scratch-preset-btn" style="background:#ffffff;" title="Branco / Padrão" onclick="setPresetColorVip('criar-vip', '#ffffff')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#9945FF;" title="Roxo" onclick="setPresetColorVip('criar-vip', '#9945FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#14F195;" title="Verde Esmeralda" onclick="setPresetColorVip('criar-vip', '#14F195')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF9900;" title="Laranja" onclick="setPresetColorVip('criar-vip', '#FF9900')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFD700;" title="Dourado" onclick="setPresetColorVip('criar-vip', '#FFD700')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#00C0FF;" title="Azul Diamante" onclick="setPresetColorVip('criar-vip', '#00C0FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF3366;" title="Vermelho Ruby" onclick="setPresetColorVip('criar-vip', '#FF3366')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#000000;" title="Preto" onclick="setPresetColorVip('criar-vip', '#000000')"></button>
                                                </div>
                                            </div>
                                            <div class="pt-2 border-top">
                                                <label class="small text-muted mb-1 d-block fw-semibold" style="font-size: 0.72rem;">Código HEX:</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text font-monospace">#</span>
                                                    <input type="text" id="criar-vip-scratch-hex-input" class="form-control font-monospace" maxlength="7" placeholder="FFFFFF" oninput="onHexInputChangeVip('criar-vip', this.value)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Painel String (Minecraft) -->
                                <div id="criar-vip-panel-string" class="w-100" style="display: none;">
                                    <div class="input-group" style="height: 46px;">
                                        <span class="input-group-text font-monospace" style="background: #0f172a; border-color: #334155; color: #94a3b8; font-size: 0.85rem;">
                                            <i class="fa-solid fa-code"></i>
                                        </span>
                                        <input type="text" class="form-control font-monospace h-100" id="criar-vip-cor_string" name="cor_string" 
                                               placeholder="#HEX&amp;lLetra&#38;#HEX&amp;l..."
                                               autocomplete="off" style="background: #0f172a; border-color: #334155; color: #f8fafc; font-size: 0.82rem;" oninput="atualizarPreviewTagVip('criar-vip')">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Limpar string" onclick="limparCorStringVip('criar-vip')" style="border-color: #334155;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. SWITCH NA DIREITA -->
                            <div class="flex-shrink-0">
                                <div class="tag-minimal-switch">
                                    <button type="button" class="tag-switch-btn active" id="criar-vip-btn-mode-colorwheel" onclick="definirModoCorVip('criar-vip', 'colorwheel')">
                                        Degradê
                                    </button>
                                    <button type="button" class="tag-switch-btn" id="criar-vip-btn-mode-string" onclick="definirModoCorVip('criar-vip', 'string')">
                                        String
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="criar-vip-cor_tipo" name="cor_tipo" value="colorwheel">
                        <div id="criar-vip-string-helper" style="display: none;" class="mt-1 d-flex align-items-center justify-content-between">
                            <span class="text-muted small" style="font-size: 0.72rem;">Suporta formatos hexadecimais (&amp;#RRGGBB, #RRGGBB) e códigos clássicos (&amp;0-&amp;f, &amp;l).</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.72rem;" onclick="preencherExemploStringVip('criar-vip')">Preencher exemplo</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Benefícios do VIP</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-vantagem-criar-vip">
                                Adicionar Benefício
                            </button>
                        </label>
                        <div id="container-vantagens-criar-vip" class="d-flex flex-column gap-2">
                            <div class="input-group input-group-sm vantagem-vip-row">
                                <span class="input-group-text bg-light text-success"><i class="fa-solid fa-check"></i></span>
                                <input type="text" class="form-control" name="vantagens[]" value="TAG [tag] na tablist" placeholder="Ex: Tag especial e comandos exclusivos" required>
                                <button type="button" class="btn btn-outline-danger btn-remove-vantagem-vip"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 pt-2 border-top">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="criar-vip-destaque" name="destaque" value="1">
                                <label class="form-check-label fw-bold" for="criar-vip-destaque">Destacar na loja</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="criar-vip-ativo" name="ativo" value="1" checked>
                                <label class="form-check-label fw-bold" for="criar-vip-ativo">Pacote VIP ativo para compras</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-vip">
                        <i class="fa-solid fa-check me-1"></i> Cadastrar VIP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR PACOTE VIP -->
<div class="modal fade" id="modalEditarVip" tabindex="-1" aria-labelledby="modalEditarVipLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content shadow">
            <form id="formEditarVip">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-vip-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarVipLabel">
                        Editar Pacote VIP: <span id="modalEditarVipNomeHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label for="edit-vip-servidor" class="form-label fw-bold">Servidor <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit-vip-servidor" name="servidor_id" required>
                                <option value="0">🌐 Todos os Servidores (Multiservidor)</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>">
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="edit-vip-nome" class="form-label fw-bold">Nome do Pacote VIP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-vip-nome" name="nome" required oninput="atualizarPreviewTagVip('edit-vip')">
                        </div>
                        <div class="col-md-3">
                            <label for="edit-vip-packageId" class="form-label fw-bold">Package ID</label>
                            <input type="text" class="form-control font-monospace" id="edit-vip-packageId" name="packageId">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="edit-vip-preco" class="form-label fw-bold">Preço (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="edit-vip-preco" name="preco" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-vip-duracao" class="form-label fw-bold">Duração (dias) <span class="text-muted fw-normal small">(0 = Permanente)</span></label>
                            <input type="number" min="0" max="3650" class="form-control" id="edit-vip-duracao" name="duracao_dias">
                        </div>
                    </div>

                    <!-- SISTEMA DE COR DA TAG (DEGRADÊ OU STRING MINECRAFT) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Cor da Tag</label>
                        <div class="d-flex align-items-center gap-2">
                            <!-- 1. PREVIEW NA ESQUERDA -->
                            <div class="rounded border text-center font-monospace d-flex align-items-center justify-content-center px-3"
                                 style="background: #0f172a; border-color: #334155 !important; height: 46px; min-width: 150px; max-width: 200px; flex-shrink: 0;"
                                 title="Prévia em tempo real">
                                <div id="edit-vip-tag-preview-content" class="fw-bold" style="font-size: 1.05rem; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                            </div>

                            <!-- 2. INPUTFIELD NO MEIO -->
                            <div class="flex-grow-1 position-relative" style="min-width: 0;">
                                <!-- Painel Degradê -->
                                <div id="edit-vip-panel-colorwheel" class="w-100">
                                    <div class="scratch-gradient-wrapper position-relative">
                                        <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background: #0f172a; border-color: #334155; height: 46px; box-sizing: border-box;">
                                            <div class="scratch-swatch-box" id="edit-vip-swatch-cor1-btn" onclick="abrirSeletorCorVip('edit-vip', 'cor1', event)" title="Cor inicial (clique para escolher)">
                                                <span class="scratch-swatch" id="edit-vip-preview-swatch-cor1" style="background-color: #ffffff;"></span>
                                            </div>
                                            <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradienteVip('edit-vip', event)" title="Inverter cores">
                                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                            </button>
                                            <div class="scratch-swatch-box" id="edit-vip-swatch-cor2-btn" onclick="abrirSeletorCorVip('edit-vip', 'cor2', event)" title="Cor final (clique para escolher)">
                                                <span class="scratch-swatch" id="edit-vip-preview-swatch-cor2" style="background-color: #ffffff;"></span>
                                            </div>
                                            <div class="flex-grow-1 px-1">
                                                <div id="edit-vip-scratch-mini-gradient-bar" class="rounded border border-secondary" style="height: 18px; width: 100%; background: linear-gradient(90deg, #ffffff, #ffffff);"></div>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 text-muted d-flex align-items-center justify-content-center" title="Resetar cores para padrão (#ffffff)" onclick="resetarCoresPadraoVip('edit-vip')" style="width: 28px; height: 28px; flex-shrink: 0;">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" id="edit-vip-cor1" name="cor1" value="#ffffff">
                                        <input type="hidden" id="edit-vip-cor2" name="cor2" value="#ffffff">

                                        <!-- Floating Popover Picker -->
                                        <div id="edit-vip-scratch-popover" class="scratch-popover shadow-lg" style="display: none;" onclick="event.stopPropagation()">
                                            <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                                                <span class="small fw-bold text-dark" id="edit-vip-scratch-popover-title">Selecionar Cor</span>
                                                <button type="button" class="btn-close btn-sm" onclick="fecharSeletorCorVip('edit-vip')"></button>
                                            </div>
                                            <div class="mb-2">
                                                <input type="color" id="edit-vip-scratch-native-picker" class="form-control form-control-color w-100 mb-2" style="height: 42px; cursor: pointer;" oninput="onNativeColorChangeVip('edit-vip', this.value)">
                                                <div class="d-flex flex-wrap gap-1 mb-2">
                                                    <button type="button" class="scratch-preset-btn" style="background:#ffffff;" title="Branco / Padrão" onclick="setPresetColorVip('edit-vip', '#ffffff')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#9945FF;" title="Roxo" onclick="setPresetColorVip('edit-vip', '#9945FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#14F195;" title="Verde Esmeralda" onclick="setPresetColorVip('edit-vip', '#14F195')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF9900;" title="Laranja" onclick="setPresetColorVip('edit-vip', '#FF9900')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFD700;" title="Dourado" onclick="setPresetColorVip('edit-vip', '#FFD700')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#00C0FF;" title="Azul Diamante" onclick="setPresetColorVip('edit-vip', '#00C0FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF3366;" title="Vermelho Ruby" onclick="setPresetColorVip('edit-vip', '#FF3366')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#000000;" title="Preto" onclick="setPresetColorVip('edit-vip', '#000000')"></button>
                                                </div>
                                            </div>
                                            <div class="pt-2 border-top">
                                                <label class="small text-muted mb-1 d-block fw-semibold" style="font-size: 0.72rem;">Código HEX:</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text font-monospace">#</span>
                                                    <input type="text" id="edit-vip-scratch-hex-input" class="form-control font-monospace" maxlength="7" placeholder="FFFFFF" oninput="onHexInputChangeVip('edit-vip', this.value)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Painel String (Minecraft) -->
                                <div id="edit-vip-panel-string" class="w-100" style="display: none;">
                                    <div class="input-group" style="height: 46px;">
                                        <span class="input-group-text font-monospace" style="background: #0f172a; border-color: #334155; color: #94a3b8; font-size: 0.85rem;">
                                            <i class="fa-solid fa-code"></i>
                                        </span>
                                        <input type="text" class="form-control font-monospace h-100" id="edit-vip-cor_string" name="cor_string" 
                                               placeholder="#HEX&amp;lLetra&#38;#HEX&amp;l..."
                                               autocomplete="off" style="background: #0f172a; border-color: #334155; color: #f8fafc; font-size: 0.82rem;" oninput="atualizarPreviewTagVip('edit-vip')">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Limpar string" onclick="limparCorStringVip('edit-vip')" style="border-color: #334155;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. SWITCH NA DIREITA -->
                            <div class="flex-shrink-0">
                                <div class="tag-minimal-switch">
                                    <button type="button" class="tag-switch-btn active" id="edit-vip-btn-mode-colorwheel" onclick="definirModoCorVip('edit-vip', 'colorwheel')">
                                        Degradê
                                    </button>
                                    <button type="button" class="tag-switch-btn" id="edit-vip-btn-mode-string" onclick="definirModoCorVip('edit-vip', 'string')">
                                        String
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="edit-vip-cor_tipo" name="cor_tipo" value="colorwheel">
                        <div id="edit-vip-string-helper" style="display: none;" class="mt-1 d-flex align-items-center justify-content-between">
                            <span class="text-muted small" style="font-size: 0.72rem;">Suporta formatos hexadecimais (&amp;#RRGGBB, #RRGGBB) e códigos clássicos (&amp;0-&amp;f, &amp;l).</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.72rem;" onclick="preencherExemploStringVip('edit-vip')">Preencher exemplo</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Benefícios do VIP</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-vantagem-edit-vip">
                                Adicionar Benefício
                            </button>
                        </label>
                        <div id="container-vantagens-edit-vip" class="d-flex flex-column gap-2"></div>
                    </div>

                    <div class="row g-3 pt-2 border-top">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-vip-destaque" name="destaque" value="1">
                                <label class="form-check-label fw-bold" for="edit-vip-destaque">Destacar na loja</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-vip-ativo" name="ativo" value="1">
                                <label class="form-check-label fw-bold" for="edit-vip-ativo">Pacote VIP ativo para compras</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-vip">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Estado de seleção de cor ativo por prefixo
const activeColorSlots = {
    'criar-vip': 'cor1',
    'edit-vip': 'cor1'
};

// Parser para tags no formato Minecraft (hex e formatações)
function parseMinecraftString(raw) {
    if (!raw || typeof raw !== 'string') return '';
    const legacyColors = {
        '0': '#000000', '1': '#0000AA', '2': '#00AA00', '3': '#00AAAA',
        '4': '#AA0000', '5': '#AA00AA', '6': '#FFAA00', '7': '#AAAAAA',
        '8': '#555555', '9': '#5555FF', 'a': '#55FF55', 'b': '#55FFFF',
        'c': '#FF5555', 'd': '#FF55FF', 'e': '#FFFF55', 'f': '#FFFFFF'
    };
    let currentColor = '';
    let isBold = false, isItalic = false, isUnderline = false, isStrikethrough = false;
    let i = 0;
    let result = '';

    while (i < raw.length) {
        // &#RRGGBB
        if (raw.substr(i, 2) === '&#' && i + 8 <= raw.length && /^[0-9A-Fa-f]{6}$/.test(raw.substr(i + 2, 6))) {
            currentColor = '#' + raw.substr(i + 2, 6);
            isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
            i += 8;
            continue;
        }
        // #RRGGBB
        if (raw[i] === '#' && i + 7 <= raw.length && /^[0-9A-Fa-f]{6}$/.test(raw.substr(i + 1, 6))) {
            currentColor = '#' + raw.substr(i + 1, 6);
            isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
            i += 7;
            continue;
        }
        // &x&r&r&g&g&b&b (Bungee Hex)
        if ((raw[i] === '&' || raw[i] === '§') && (raw[i+1] === 'x' || raw[i+1] === 'X')) {
            let bungeeHex = '';
            let k = i + 2;
            for (let step = 0; step < 6; step++) {
                if (k + 1 < raw.length && (raw[k] === '&' || raw[k] === '§') && /^[0-9A-Fa-f]$/.test(raw[k+1])) {
                    bungeeHex += raw[k+1];
                    k += 2;
                } else {
                    break;
                }
            }
            if (bungeeHex.length === 6) {
                currentColor = '#' + bungeeHex;
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i = k;
                continue;
            }
        }
        // Formatações e cores padrão Minecraft (&0-&f, &l, &o, &n, &m, &r)
        if ((raw[i] === '&' || raw[i] === '§') && i + 1 < raw.length) {
            const code = raw[i + 1].toLowerCase();
            if (legacyColors[code] !== undefined) {
                currentColor = legacyColors[code];
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i += 2;
                continue;
            } else if (code === 'l') {
                isBold = true;
                i += 2;
                continue;
            } else if (code === 'o') {
                isItalic = true;
                i += 2;
                continue;
            } else if (code === 'n') {
                isUnderline = true;
                i += 2;
                continue;
            } else if (code === 'm') {
                isStrikethrough = true;
                i += 2;
                continue;
            } else if (code === 'r') {
                currentColor = '';
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i += 2;
                continue;
            }
        }

        const char = raw[i];
        let style = [];
        if (currentColor) style.push('color:' + currentColor);
        if (isBold) style.push('font-weight:800');
        if (isItalic) style.push('font-style:italic');
        if (isUnderline && isStrikethrough) {
            style.push('text-decoration:underline line-through');
        } else if (isUnderline) {
            style.push('text-decoration:underline');
        } else if (isStrikethrough) {
            style.push('text-decoration:line-through');
        }

        const safeChar = char === '&' ? '&amp;' : (char === '<' ? '&lt;' : (char === '>' ? '&gt;' : char));
        if (style.length > 0) {
            result += `<span style="${style.join(';')}">${safeChar}</span>`;
        } else {
            result += safeChar;
        }
        i++;
    }
    return result;
}

function atualizarPreviewTagVip(prefix) {
    const previewEl = document.getElementById(`${prefix}-tag-preview-content`);
    const modeBadge = document.getElementById(`${prefix}-tag-mode-badge`);
    const modo = document.getElementById(`${prefix}-cor_tipo`)?.value || 'colorwheel';

    if (modeBadge) {
        modeBadge.textContent = modo === 'string' ? 'String' : 'Degradê';
        modeBadge.className = modo === 'string' ? 'badge bg-info text-dark text-uppercase position-absolute top-0 end-0 m-2' : 'badge bg-secondary text-uppercase position-absolute top-0 end-0 m-2';
    }

    let previewHtml = '';

    if (modo === 'string') {
        const str = (document.getElementById(`${prefix}-cor_string`)?.value || '').trim();
        if (!str) {
            previewHtml = '<span class="text-muted" style="font-weight: 400; font-size: 0.8rem; font-style: italic;">Sem cor definida</span>';
        } else {
            const parsed = parseMinecraftString(str);
            if (!parsed) {
                previewHtml = '<span class="text-warning" style="font-size: 0.78rem;">Formato inválido</span>';
            } else {
                const hasBrackets = str.includes('[') || str.includes(']');
                previewHtml = hasBrackets ? parsed : `<span style="color: #94a3b8; font-weight: normal;">[</span>${parsed}<span style="color: #94a3b8; font-weight: normal;">]</span>`;
            }
        }
    } else {
        const c1 = document.getElementById(`${prefix}-cor1`)?.value || '#ffffff';
        const c2 = document.getElementById(`${prefix}-cor2`)?.value || '#ffffff';
        const nomeVip = (document.getElementById(`${prefix}-nome`)?.value || 'VIP').replace(/^vip\s*/i, '').trim() || 'TAG';
        const tagName = nomeVip.toUpperCase();
        const hasCustomGrad = c1 && c2 && (c1.toLowerCase() !== c2.toLowerCase()) && (c1 !== '#ffffff' || c2 !== '#ffffff');

        const tagStyle = hasCustomGrad
            ? `background: linear-gradient(135deg, ${c1}, ${c2}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800;`
            : `color: ${c1}; font-weight: 800;`;

        previewHtml = `<span style="color: #94a3b8; font-weight: normal;">[</span><span style="${tagStyle}">${tagName}</span><span style="color: #94a3b8; font-weight: normal;">]</span>`;
    }

    if (previewEl) previewEl.innerHTML = previewHtml;
}

function definirModoCorVip(prefix, modo) {
    const inputModo = document.getElementById(`${prefix}-cor_tipo`);
    const panelColorwheel = document.getElementById(`${prefix}-panel-colorwheel`);
    const panelString = document.getElementById(`${prefix}-panel-string`);
    const btnColorwheel = document.getElementById(`${prefix}-btn-mode-colorwheel`);
    const btnString = document.getElementById(`${prefix}-btn-mode-string`);
    const stringHelper = document.getElementById(`${prefix}-string-helper`);

    if (inputModo) inputModo.value = modo;

    if (modo === 'string') {
        if (panelColorwheel) panelColorwheel.style.display = 'none';
        if (panelString) panelString.style.display = 'block';
        if (btnColorwheel) btnColorwheel.classList.remove('active');
        if (btnString) btnString.classList.add('active');
        if (stringHelper) stringHelper.style.display = 'flex';
        fecharSeletorCorVip(prefix);
    } else {
        if (panelColorwheel) panelColorwheel.style.display = 'block';
        if (panelString) panelString.style.display = 'none';
        if (btnColorwheel) btnColorwheel.classList.add('active');
        if (btnString) btnString.classList.remove('active');
        if (stringHelper) stringHelper.style.display = 'none';
    }

    atualizarPreviewTagVip(prefix);
}

function atualizarMiniGradienteVip(prefix) {
    const c1 = document.getElementById(`${prefix}-cor1`)?.value || '#ffffff';
    const c2 = document.getElementById(`${prefix}-cor2`)?.value || '#ffffff';
    const bar = document.getElementById(`${prefix}-scratch-mini-gradient-bar`);
    if (bar) {
        bar.style.background = `linear-gradient(90deg, ${c1}, ${c2})`;
    }
}

function abrirSeletorCorVip(prefix, slot, event) {
    if (event) event.stopPropagation();
    activeColorSlots[prefix] = slot;

    document.getElementById(`${prefix}-swatch-cor1-btn`)?.classList.toggle('active', slot === 'cor1');
    document.getElementById(`${prefix}-swatch-cor2-btn`)?.classList.toggle('active', slot === 'cor2');

    const inputVal = document.getElementById(`${prefix}-${slot}`)?.value || '#ffffff';
    const title = document.getElementById(`${prefix}-scratch-popover-title`);
    if (title) title.textContent = slot === 'cor1' ? 'Cor Inicial (Gradiente)' : 'Cor Final (Gradiente)';

    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = inputVal;

    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = inputVal.replace('#', '').toUpperCase();

    const popover = document.getElementById(`${prefix}-scratch-popover`);
    if (popover) popover.style.display = 'block';
}

function fecharSeletorCorVip(prefix) {
    const popover = document.getElementById(`${prefix}-scratch-popover`);
    if (popover) popover.style.display = 'none';
    document.getElementById(`${prefix}-swatch-cor1-btn`)?.classList.remove('active');
    document.getElementById(`${prefix}-swatch-cor2-btn`)?.classList.remove('active');
}

function aplicarCorVip(prefix, hex) {
    if (!hex.startsWith('#')) hex = '#' + hex;
    const slot = activeColorSlots[prefix] || 'cor1';
    const input = document.getElementById(`${prefix}-${slot}`);
    if (input) {
        input.value = hex;
    }

    const swatch = document.getElementById(`${prefix}-preview-swatch-${slot}`);
    if (swatch) swatch.style.backgroundColor = hex;

    atualizarMiniGradienteVip(prefix);
    atualizarPreviewTagVip(prefix);
}

function onNativeColorChangeVip(prefix, hex) {
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCorVip(prefix, hex);
}

function onHexInputChangeVip(prefix, val) {
    const clean = val.trim().replace('#', '');
    if (/^[0-9A-Fa-f]{6}$/.test(clean)) {
        const hex = '#' + clean.toUpperCase();
        const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
        if (nativePicker) nativePicker.value = hex;
        aplicarCorVip(prefix, hex);
    }
}

function setPresetColorVip(prefix, hex) {
    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = hex;
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCorVip(prefix, hex);
}

function trocarCoresGradienteVip(prefix, e) {
    if (e) e.stopPropagation();
    const c1Input = document.getElementById(`${prefix}-cor1`);
    const c2Input = document.getElementById(`${prefix}-cor2`);
    if (!c1Input || !c2Input) return;

    const temp = c1Input.value;
    c1Input.value = c2Input.value;
    c2Input.value = temp;

    const sw1 = document.getElementById(`${prefix}-preview-swatch-cor1`);
    const sw2 = document.getElementById(`${prefix}-preview-swatch-cor2`);
    if (sw1) sw1.style.backgroundColor = c1Input.value;
    if (sw2) sw2.style.backgroundColor = c2Input.value;

    const slot = activeColorSlots[prefix] || 'cor1';
    const currentVal = document.getElementById(`${prefix}-${slot}`)?.value || '#ffffff';
    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = currentVal;
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = currentVal.replace('#', '').toUpperCase();

    atualizarMiniGradienteVip(prefix);
    atualizarPreviewTagVip(prefix);
}

function resetarCoresPadraoVip(prefix) {
    const c1Input = document.getElementById(`${prefix}-cor1`);
    const c2Input = document.getElementById(`${prefix}-cor2`);
    if (c1Input) c1Input.value = '#ffffff';
    if (c2Input) c2Input.value = '#ffffff';

    const sw1 = document.getElementById(`${prefix}-preview-swatch-cor1`);
    const sw2 = document.getElementById(`${prefix}-preview-swatch-cor2`);
    if (sw1) sw1.style.backgroundColor = '#ffffff';
    if (sw2) sw2.style.backgroundColor = '#ffffff';

    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = '#ffffff';
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = 'FFFFFF';

    atualizarMiniGradienteVip(prefix);
    atualizarPreviewTagVip(prefix);
}

function limparCorStringVip(prefix) {
    const input = document.getElementById(`${prefix}-cor_string`);
    if (input) {
        input.value = '';
        atualizarPreviewTagVip(prefix);
        input.focus();
    }
}

function preencherExemploStringVip(prefix) {
    const input = document.getElementById(`${prefix}-cor_string`);
    if (input) {
        input.value = '#FFE082&lC&#FFD76B&lO&#FFCE54&lM&#FFC53D&lA&#FFBC26&lN&#FFB30F&lD&#FFAA00&lA&#FFA000&lN&#FF9700&lT&#FF8E00&lE&#FF8E00';
        atualizarPreviewTagVip(prefix);
    }
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.scratch-gradient-wrapper')) {
        fecharSeletorCorVip('criar-vip');
        fecharSeletorCorVip('edit-vip');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const servidoresMap = <?php echo json_encode($servidoresMap, JSON_UNESCAPED_UNICODE); ?>;

    const modalCriarEl = document.getElementById('modalCriarVip');
    const modalEditarEl = document.getElementById('modalEditarVip');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Inicializa preview do modal de criação
    atualizarMiniGradienteVip('criar-vip');
    atualizarPreviewTagVip('criar-vip');

    function addVantagemVipRow(containerId, valor = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm vantagem-vip-row';
        div.innerHTML = `
            <span class="input-group-text bg-light text-success"><i class="fa-solid fa-check"></i></span>
            <input type="text" class="form-control" name="vantagens[]" value="${escapeHtml(valor)}" placeholder="Ex: TAG [tag] na tablist" required>
            <button type="button" class="btn btn-outline-danger btn-remove-vantagem-vip"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(div);
        div.querySelector('.btn-remove-vantagem-vip').addEventListener('click', function() {
            const items = container.querySelectorAll('.vantagem-vip-row');
            if (items.length > 1) {
                div.remove();
            } else {
                div.querySelector('input').value = '';
            }
        });
    }

    document.querySelectorAll('.btn-remove-vantagem-vip').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.d-flex');
            const items = container.querySelectorAll('.vantagem-vip-row');
            if (items.length > 1) {
                this.closest('.vantagem-vip-row').remove();
            } else {
                this.closest('.vantagem-vip-row').querySelector('input').value = '';
            }
        });
    });

    const btnAddCriar = document.getElementById('btn-add-vantagem-criar-vip');
    if (btnAddCriar) {
        btnAddCriar.addEventListener('click', () => addVantagemVipRow('container-vantagens-criar-vip'));
    }
    const btnAddEdit = document.getElementById('btn-add-vantagem-edit-vip');
    if (btnAddEdit) {
        btnAddEdit.addEventListener('click', () => addVantagemVipRow('container-vantagens-edit-vip'));
    }

    function atualizarContadores() {
        const ativas = document.querySelectorAll('#lista-vips-ativos tr[data-id]').length;
        const inativas = document.querySelectorAll('#lista-vips-inativos tr[data-id]').length;
        const total = ativas + inativas;

        const totalEl = document.querySelector('[data-item-counter]');
        if (totalEl) totalEl.textContent = total + ' ' + (total === 1 ? 'pacote' : 'pacotes');

        const totalAtivosSummary = document.querySelector('[data-item-counter-ativos-summary]');
        if (totalAtivosSummary) totalAtivosSummary.textContent = ativas + ' ' + (ativas === 1 ? 'ativo' : 'ativos');

        const totalInativosSummary = document.querySelector('[data-item-counter-inativos-summary]');
        if (totalInativosSummary) totalInativosSummary.textContent = inativas + ' ' + (inativas === 1 ? 'inativo' : 'inativos');

        const totalAtivosHeader = document.querySelector('[data-item-counter-ativos]');
        if (totalAtivosHeader) totalAtivosHeader.textContent = ativas + ' ' + (ativas === 1 ? 'pacote' : 'pacotes');

        const totalInativosHeader = document.querySelector('[data-item-counter-inativos]');
        if (totalInativosHeader) totalInativosHeader.textContent = inativas + ' ' + (inativas === 1 ? 'pacote' : 'pacotes');
    }

    function abrirModalEditarVip(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-vip-id').value = d.id || '';
        document.getElementById('edit-vip-nome').value = d.nome || '';
        document.getElementById('modalEditarVipNomeHeader').textContent = d.nome || '';
        document.getElementById('edit-vip-servidor').value = (d.servidorId !== undefined && d.servidorId !== '') ? d.servidorId : '0';
        document.getElementById('edit-vip-packageId').value = d.packageId || '';
        document.getElementById('edit-vip-preco').value = d.preco || '';
        document.getElementById('edit-vip-duracao').value = (d.duracaoDias !== undefined && d.duracaoDias !== '') ? d.duracaoDias : 30;
        document.getElementById('edit-vip-destaque').checked = (d.destaque === '1');
        document.getElementById('edit-vip-ativo').checked = (d.ativo === '1');

        // Configuração de cores e modo
        const cor1 = d.cor1 || '#ffffff';
        const cor2 = d.cor2 || '#ffffff';
        const corTipo = d.corTipo || 'colorwheel';
        const corString = d.corString || '';

        document.getElementById('edit-vip-cor1').value = cor1;
        document.getElementById('edit-vip-cor2').value = cor2;
        document.getElementById('edit-vip-preview-swatch-cor1').style.backgroundColor = cor1;
        document.getElementById('edit-vip-preview-swatch-cor2').style.backgroundColor = cor2;
        document.getElementById('edit-vip-cor_string').value = corString;

        definirModoCorVip('edit-vip', corTipo);
        atualizarMiniGradienteVip('edit-vip');
        atualizarPreviewTagVip('edit-vip');

        // Vantagens
        const containerV = document.getElementById('container-vantagens-edit-vip');
        if (containerV) {
            containerV.innerHTML = '';
            let listaV = [];
            try {
                listaV = JSON.parse(d.vantagens || '[]');
                if (!Array.isArray(listaV)) listaV = [];
            } catch (e) {
                listaV = [];
            }
            if (listaV.length === 0) {
                listaV = ['TAG [tag] na tablist'];
            }
            listaV.forEach(v => addVantagemVipRow('container-vantagens-edit-vip', v));
        }

        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-vip');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarVip(row);
    });

    function criarElementoLinhaVip(v) {
        const tr = document.createElement('tr');
        tr.id = `vip-row-${v.id}`;
        tr.dataset.id = v.id;
        tr.dataset.servidorId = v.servidor_id || 0;
        tr.dataset.nome = v.nome || '';
        tr.dataset.packageId = v.packageId || '';
        tr.dataset.preco = Number(v.preco || 0).toFixed(2);
        tr.dataset.duracaoDias = (v.duracao_dias !== undefined && v.duracao_dias !== null) ? parseInt(v.duracao_dias) : 30;
        tr.dataset.destaque = v.destaque ? '1' : '0';
        tr.dataset.ativo = v.ativo ? '1' : '0';
        tr.dataset.cor1 = v.cor1 || '#ffffff';
        tr.dataset.cor2 = v.cor2 || '#ffffff';
        tr.dataset.corTipo = v.cor_tipo || 'colorwheel';
        tr.dataset.corString = v.cor_string || '';
        tr.dataset.vantagens = v.vantagens || '[]';

        const sId = parseInt(v.servidor_id) || 0;
        const isMulti = (sId === 0);
        const srv = (!isMulti && sId > 0 && servidoresMap[sId]) ? servidoresMap[sId] : null;
        const srvNome = isMulti ? 'Todos os Servidores' : (srv ? (srv.servername || srv.nome) : (v.servidor || 'Servidor'));
        const srvCor = isMulti ? '#0284c7' : (srv ? (srv.themecolor || '#B971DA') : '#B971DA');
        const srvEnabled = srv ? (parseInt(srv.enabled) !== 0) : true;

        const isAtivo = Boolean(Number(v.ativo));
        const statusBadge = (isAtivo && srvEnabled)
            ? `<span class="badge-status ativo">Ativo</span>`
            : (!srvEnabled && isAtivo 
                ? `<span class="badge-status pendente"><i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto</span>`
                : `<span class="badge-status inativo">Inativo</span>`);

        const precoFormatado = Number(v.preco || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const destaqueBadge = v.destaque 
            ? `<span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque / Mais Popular na loja"><i class="fa-solid fa-star me-1"></i>Destaque</span>`
            : '';

        tr.innerHTML = `
            <td>
                <span class="badge js-vip-servidor" style="background-color: ${escapeHtml(srvCor)}; color: #fff; font-weight: 600;">
                    <i class="fa-solid fa-server me-1"></i>${escapeHtml(srvNome)}
                </span>
            </td>
            <td>
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-center gap-1">
                        <strong class="text-dark js-vip-nome">${escapeHtml(v.nome)}</strong>
                        <span class="js-vip-destaque-badge">${destaqueBadge}</span>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge bg-light text-dark border font-monospace js-vip-package" style="font-size: 0.78rem;">
                    ${escapeHtml(v.packageId || '')}
                </span>
            </td>
            <td>
                <strong class="text-success fw-bold font-monospace js-vip-preco">
                    R$ ${precoFormatado}
                </strong>
            </td>
            <td>
                <span class="text-muted small js-vip-duracao">
                    ${((v.duracao_dias !== undefined && v.duracao_dias !== null && parseInt(v.duracao_dias) === 0) || v.duracao_dias === 0 || v.duracao_dias === '0') 
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Permanente</span>' 
                        : `${parseInt(v.duracao_dias || 30)} dias`}
                </span>
            </td>
            <td>${statusBadge}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-vip" title="Editar VIP">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-action="async-toggle" 
                            data-url="/admin/api/vips/toggle.php" 
                            data-id="${v.id}"
                            title="${isAtivo ? 'Desativar da loja' : 'Ativar na loja'}">
                        <i class="fa-solid ${isAtivo ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/vips/deletar.php" 
                            data-id="${v.id}"
                            data-name="${escapeHtml(v.nome)}"
                            data-confirm="Tem certeza que deseja excluir o pacote ${escapeHtml(v.nome)} do servidor ${escapeHtml(srvNome)}?"
                            title="Deletar VIP">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // SUBMIT: CRIAR VIP
    const formCriar = document.getElementById('formCriarVip');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar-vip');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cadastrando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/vips/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                resetarCoresPadraoVip('criar-vip');
                definirModoCorVip('criar-vip', 'colorwheel');

                if (res.vip) {
                    const isAtivo = Boolean(Number(res.vip.ativo));
                    const tbody = isAtivo ? document.getElementById('lista-vips-ativos') : document.getElementById('lista-vips-inativos');
                    const emptyRow = isAtivo ? document.getElementById('linha-sem-vips-ativos') : document.getElementById('linha-sem-vips-inativos');
                    if (emptyRow) emptyRow.remove();

                    const novaLinha = criarElementoLinhaVip(res.vip);
                    if (tbody) tbody.prepend(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Pacote VIP cadastrado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao cadastrar VIP.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR VIP
    const formEditar = document.getElementById('formEditarVip');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar-vip');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/vips/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.vip) {
                    const v = res.vip;
                    let tr = document.getElementById(`vip-row-${v.id}`);
                    const isAtivo = Boolean(Number(v.ativo));
                    const destinoTbody = isAtivo ? document.getElementById('lista-vips-ativos') : document.getElementById('lista-vips-inativos');

                    if (tr) {
                        const novaLinha = criarElementoLinhaVip(v);
                        tr.replaceWith(novaLinha);
                        tr = novaLinha;

                        if (tr.parentElement !== destinoTbody && destinoTbody) {
                            const emptyRow = isAtivo ? document.getElementById('linha-sem-vips-ativos') : document.getElementById('linha-sem-vips-inativos');
                            if (emptyRow) emptyRow.remove();
                            destinoTbody.prepend(tr);
                        }

                        tr.classList.add('table-row-highlight');
                        setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Pacote VIP atualizado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar VIP.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // Tratamento de deep link ?action=novo e ?edit=ID
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'novo' && bsModalCriar) {
        bsModalCriar.show();
    } else if (urlParams.has('edit')) {
        const editId = urlParams.get('edit');
        const row = document.getElementById(`vip-row-${editId}`);
        if (row) {
            abrirModalEditarVip(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>