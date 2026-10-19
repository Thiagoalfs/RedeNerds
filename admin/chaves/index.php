<?php
$paginaAtiva = 'chaves';
$tituloPagina = 'Pacotes';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/servidores_admin.php";

// Auto-criação da tabela e colunas se não existirem
if (empty($_SESSION['chaves_schema_ok'])) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `chaves` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `servidor_id` INT NOT NULL DEFAULT 0,
          `servidor` VARCHAR(100) NOT NULL DEFAULT '',
          `nome` VARCHAR(100) NOT NULL,
          `imagem` VARCHAR(255) NULL DEFAULT NULL,
          `packageId` VARCHAR(100) NOT NULL,
          `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `destaque` TINYINT(1) NOT NULL DEFAULT 0,
          `ativo` TINYINT(1) NOT NULL DEFAULT 1,
          `cor1` VARCHAR(20) NOT NULL DEFAULT '#FFD700',
          `cor2` VARCHAR(20) NOT NULL DEFAULT '#FFA500',
          `cor_tipo` VARCHAR(20) NOT NULL DEFAULT 'colorwheel',
          `cor_string` VARCHAR(255) NOT NULL DEFAULT '',
          `vantagens` TEXT NULL,
          `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_srv (`servidor_id`, `ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $cols = $pdo->query("SHOW COLUMNS FROM `chaves`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('cor_tipo', $cols, true)) {
            $pdo->exec("ALTER TABLE `chaves` ADD COLUMN `cor_tipo` VARCHAR(20) NOT NULL DEFAULT 'colorwheel' AFTER `cor2`");
        }
        if (!in_array('cor_string', $cols, true)) {
            $pdo->exec("ALTER TABLE `chaves` ADD COLUMN `cor_string` VARCHAR(255) NOT NULL DEFAULT '' AFTER `cor_tipo`");
        }

        require_once __DIR__ . "/../../api/loja/config_loja.php";
        garantirSchemaTabelaPedidos($pdo);
        $_SESSION['chaves_schema_ok'] = true;
    } catch (Exception $e) {
        error_log("Erro ao preparar schema de chaves: " . $e->getMessage());
    }
}

$filtroServidor = trim($_GET['servidor'] ?? '');

$servidoresData = carregarServidoresAdmin($pdo);
$servidores = $servidoresData['lista'];
$servidoresMap = $servidoresData['mapa'];

$where = [];
$params = [];

if ($filtroServidor !== '') {
    if (is_numeric($filtroServidor)) {
        $where[] = "(c.servidor_id = :servidor_id OR c.servidor = :servidor_nome)";
        $params[':servidor_id'] = (int)$filtroServidor;
        $srvObj = $servidoresMap[$filtroServidor] ?? null;
        $params[':servidor_nome'] = $srvObj ? $srvObj['servername'] : $filtroServidor;
    } else {
        $where[] = "c.servidor = :servidor_nome";
        $params[':servidor_nome'] = $filtroServidor;
    }
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$chaves = [];
try {
    $stmt = $pdo->prepare("SELECT c.* FROM chaves c $whereSql ORDER BY c.id DESC");
    $stmt->execute($params);
    $chavesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($chavesRaw as $c) {
        $srvObj = null;
        if (!empty($c['servidor_id']) && isset($servidoresMap[$c['servidor_id']])) {
            $srvObj = $servidoresMap[$c['servidor_id']];
        } elseif (!empty($c['servidor'])) {
            $srvObj = $servidoresMap[$c['servidor']] ?? null;
            if (!$srvObj) {
                foreach ($servidores as $s) {
                    if (strcasecmp($s['servername'], $c['servidor']) === 0 || (!empty($s['nome']) && strcasecmp($s['nome'], $c['servidor']) === 0)) {
                        $srvObj = $s;
                        break;
                    }
                }
            }
        }

        $c['servidor_nome_oficial'] = $srvObj['servername'] ?? ($c['servidor'] ?? 'Servidor');
        $c['servidor_cor'] = $srvObj['themecolor'] ?? '#F59E0B';
        $c['servidor_icone'] = $srvObj['icon'] ?? null;
        $c['servidor_enabled'] = isset($srvObj['enabled']) ? (int)$srvObj['enabled'] : 1;

        $chaves[] = $c;
    }

    usort($chaves, function($a, $b) {
        $cmpSrv = strcasecmp($a['servidor_nome_oficial'], $b['servidor_nome_oficial']);
        if ($cmpSrv !== 0) return $cmpSrv;
        return ((float)($a['preco'] ?? 0)) <=> ((float)($b['preco'] ?? 0));
    });

    $chavesAtivas = [];
    $chavesInativas = [];
    foreach ($chaves as $c) {
        $srvEnabled = (int)($c['servidor_enabled'] ?? 1);
        $itemAtivo = !empty($c['ativo']);

        if ($itemAtivo && $srvEnabled === 1) {
            $chavesAtivas[] = $c;
        } else {
            if ($srvEnabled === 0 && $itemAtivo) {
                $c['motivo_inativo'] = 'servidor_oculto';
            } elseif ($srvEnabled === 0 && !$itemAtivo) {
                $c['motivo_inativo'] = 'ambos_inativos';
            } else {
                $c['motivo_inativo'] = 'item_inativo';
            }
            $chavesInativas[] = $c;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de Chaves: " . $e->getMessage());
    $chaves = [];
    $chavesAtivas = [];
    $chavesInativas = [];
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
        <h4 class="fw-bold mb-1">Pacotes da Loja</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($chaves), 'pacote', 'pacotes'); ?></span> <?php echo count($chaves) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($chavesAtivas), 'ativo', 'ativos'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($chavesInativas), 'inativo', 'inativos'); ?></span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/loja/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Loja</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarChave">
            <i class="fa-solid fa-plus me-1"></i> Novo Pacote
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

<!-- TABELA 1: PACOTES ATIVOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Pacotes Ativos</h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($chavesAtivas), 'pacote', 'pacotes'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-chaves-ativas">
                <thead>
                    <tr>
                        <th style="width: 160px;">Servidor</th>
                        <th>Pacote</th>
                        <th style="width: 160px;">Package ID</th>
                        <th style="width: 140px;">Preço Unitário</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-chaves-ativas">
                    <?php if (empty($chavesAtivas)): ?>
                        <tr class="empty-row" id="linha-sem-chaves-ativas">
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhum pacote ativo no momento<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chavesAtivas as $c): 
                            $srvNome = $c['servidor_nome_oficial'] ?: ($servidoresMap[$c['servidor_id']]['servername'] ?? ($c['servidor'] ?: 'Servidor'));
                            $srvCor = $c['servidor_cor'] ?: ($servidoresMap[$c['servidor_id'] ?? $c['servidor']]['themecolor'] ?? '#F59E0B');
                        ?>
                            <tr id="chave-row-<?php echo (int)$c['id']; ?>"
                                data-id="<?php echo (int)$c['id']; ?>"
                                data-servidor-id="<?php echo (int)$c['servidor_id']; ?>"
                                data-nome="<?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-package-id="<?php echo htmlspecialchars($c['packageId'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-preco="<?php echo htmlspecialchars(number_format((float)$c['preco'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-destaque="<?php echo !empty($c['destaque']) ? '1' : '0'; ?>"
                                data-ativo="<?php echo !empty($c['ativo']) ? '1' : '0'; ?>"
                                data-cor1="<?php echo htmlspecialchars($c['cor1'] ?? '#FFD700', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor2="<?php echo htmlspecialchars($c['cor2'] ?? '#FFA500', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-tipo="<?php echo htmlspecialchars($c['cor_tipo'] ?? 'colorwheel', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-string="<?php echo htmlspecialchars($c['cor_string'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imagem="<?php echo htmlspecialchars($c['imagem'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-vantagens="<?php echo htmlspecialchars($c['vantagens'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <span class="badge js-chave-servidor" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="js-chave-preview-container">
                                            <?php if (!empty($c['imagem'])): ?>
                                                <img src="<?php echo htmlspecialchars($c['imagem'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">
                                            <?php else: ?>
                                                <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-key" style="font-size: 0.85rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-1">
                                                <strong class="text-dark js-chave-nome"><?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span class="js-chave-destaque-badge">
                                                    <?php if (!empty($c['destaque'])): ?>
                                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque na loja">
                                                            <i class="fa-solid fa-star me-1"></i>Destaque
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace js-chave-package" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($c['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace js-chave-preco">
                                        R$ <?php echo number_format((float)$c['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge-status ativo">Ativo</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-chave" title="Editar Pacote">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/chaves/toggle.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                title="Desativar da loja">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/chaves/deletar.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja excluir o pacote <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?> do servidor <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar Pacote">
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

<!-- TABELA 2: PACOTES INATIVOS OU COM SERVIDOR DESATIVADO -->
<div class="admin-card">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Pacotes Inativos / Servidor Desativado</h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($chavesInativas), 'pacote', 'pacotes'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-chaves-inativas">
                <thead>
                    <tr>
                        <th style="width: 160px;">Servidor</th>
                        <th>Pacote</th>
                        <th style="width: 160px;">Package ID</th>
                        <th style="width: 140px;">Preço Unitário</th>
                        <th style="width: 180px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-chaves-inativas">
                    <?php if (empty($chavesInativas)): ?>
                        <tr class="empty-row" id="linha-sem-chaves-inativas">
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhum pacote inativo<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chavesInativas as $c): 
                            $srvNome = $c['servidor_nome_oficial'] ?: ($servidoresMap[$c['servidor_id']]['servername'] ?? ($c['servidor'] ?: 'Servidor'));
                            $srvCor = $c['servidor_cor'] ?: ($servidoresMap[$c['servidor_id'] ?? $c['servidor']]['themecolor'] ?? '#F59E0B');
                        ?>
                            <tr id="chave-row-<?php echo (int)$c['id']; ?>"
                                data-id="<?php echo (int)$c['id']; ?>"
                                data-servidor-id="<?php echo (int)$c['servidor_id']; ?>"
                                data-nome="<?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-package-id="<?php echo htmlspecialchars($c['packageId'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-preco="<?php echo htmlspecialchars(number_format((float)$c['preco'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-destaque="<?php echo !empty($c['destaque']) ? '1' : '0'; ?>"
                                data-ativo="<?php echo !empty($c['ativo']) ? '1' : '0'; ?>"
                                data-cor1="<?php echo htmlspecialchars($c['cor1'] ?? '#FFD700', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor2="<?php echo htmlspecialchars($c['cor2'] ?? '#FFA500', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-tipo="<?php echo htmlspecialchars($c['cor_tipo'] ?? 'colorwheel', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cor-string="<?php echo htmlspecialchars($c['cor_string'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imagem="<?php echo htmlspecialchars($c['imagem'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-vantagens="<?php echo htmlspecialchars($c['vantagens'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <span class="badge js-chave-servidor" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="js-chave-preview-container">
                                            <?php if (!empty($c['imagem'])): ?>
                                                <img src="<?php echo htmlspecialchars($c['imagem'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">
                                            <?php else: ?>
                                                <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-key" style="font-size: 0.85rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-1">
                                                <strong class="text-dark js-chave-nome"><?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span class="js-chave-destaque-badge">
                                                    <?php if (!empty($c['destaque'])): ?>
                                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque na loja">
                                                            <i class="fa-solid fa-star me-1"></i>Destaque
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace js-chave-package" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($c['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace js-chave-preco">
                                        R$ <?php echo number_format((float)$c['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($c['motivo_inativo'] === 'servidor_oculto'): ?>
                                        <span class="badge-status pendente" title="O pacote está ativo, mas o servidor está desativado no painel">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto
                                        </span>
                                    <?php elseif ($c['motivo_inativo'] === 'ambos_inativos'): ?>
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
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-chave" title="Editar Pacote">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/chaves/toggle.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                title="<?php echo !empty($c['ativo']) ? 'Desativar da loja' : 'Ativar na loja'; ?>">
                                            <i class="fa-solid <?php echo !empty($c['ativo']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/chaves/deletar.php" 
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja excluir o pacote <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?> do servidor <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar Pacote">
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

<!-- MODAL: CRIAR PACOTE -->
<div class="modal fade" id="modalCriarChave" tabindex="-1" aria-labelledby="modalCriarChaveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formCriarChave" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarChaveLabel">
                        Cadastrar Novo Pacote
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="criar-servidor-id" class="form-label fw-bold">Servidor <span class="text-danger">*</span></label>
                            <select class="form-select" id="criar-servidor-id" name="servidor_id" required>
                                <option value="">Selecione o servidor...</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$filtroServidor === (string)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="criar-nome" class="form-label fw-bold">Nome do Pacote <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criar-nome" name="nome" placeholder="Ex: Chave Mítica" required>
                        </div>
                        <div class="col-md-3">
                            <label for="criar-packageId" class="form-label fw-bold">Package ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="criar-packageId" name="packageId" placeholder="Ex: potato_chave_mitica" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3 align-items-start">
                        <div class="col-md-5">
                            <label for="criar-preco" class="form-label fw-bold">Preço Unitário (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="criar-preco" name="preco" value="4.90" required>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label for="criar-imagem-upload" class="form-label fw-bold">Imagem do Item (Topo do Card)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="criar-imagem-upload" name="imagem_upload" accept="image/*">
                                <div class="bg-dark border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 42px; height: 38px;">
                                    <img id="criar-preview-img" src="/assets/images/logo.webp" style="max-width: 32px; max-height: 32px; object-fit: contain; opacity: 0.35;" alt="Prévia">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SISTEMA DE COR DO TÍTULO / TAG (DEGRADÊ OU STRING MINECRAFT) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Cor do Título</label>
                        <div class="d-flex align-items-center gap-2">
                            <!-- 1. PREVIEW NA ESQUERDA -->
                            <div class="rounded border text-center font-monospace d-flex align-items-center justify-content-center px-3"
                                 style="background: #0f172a; border-color: #334155 !important; height: 46px; min-width: 150px; max-width: 200px; flex-shrink: 0;"
                                 title="Prévia em tempo real">
                                <div id="criar-chave-tag-preview-content" class="fw-bold" style="font-size: 1.05rem; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                            </div>

                            <!-- 2. INPUTFIELD NO MEIO -->
                            <div class="flex-grow-1 position-relative" style="min-width: 0;">
                                <!-- Painel Degradê -->
                                <div id="criar-chave-panel-colorwheel" class="w-100">
                                    <div class="scratch-gradient-wrapper position-relative">
                                        <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background: #0f172a; border-color: #334155; height: 46px; box-sizing: border-box;">
                                            <div class="scratch-swatch-box" id="criar-chave-swatch-cor1-btn" onclick="abrirSeletorCorChave('criar-chave', 'cor1', event)" title="Cor inicial (clique para escolher)">
                                                <span class="scratch-swatch" id="criar-chave-preview-swatch-cor1" style="background-color: #FFD700;"></span>
                                            </div>
                                            <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradienteChave('criar-chave', event)" title="Inverter cores">
                                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                            </button>
                                            <div class="scratch-swatch-box" id="criar-chave-swatch-cor2-btn" onclick="abrirSeletorCorChave('criar-chave', 'cor2', event)" title="Cor final (clique para escolher)">
                                                <span class="scratch-swatch" id="criar-chave-preview-swatch-cor2" style="background-color: #FFA500;"></span>
                                            </div>
                                            <div class="flex-grow-1 px-1">
                                                <div id="criar-chave-scratch-mini-gradient-bar" class="rounded border border-secondary" style="height: 18px; width: 100%; background: linear-gradient(90deg, #FFD700, #FFA500);"></div>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 text-muted d-flex align-items-center justify-content-center" title="Resetar cores para padrão (#FFD700 / #FFA500)" onclick="resetarCoresPadraoChave('criar-chave')" style="width: 28px; height: 28px; flex-shrink: 0;">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" id="criar-chave-cor1" name="cor1" value="#FFD700">
                                        <input type="hidden" id="criar-chave-cor2" name="cor2" value="#FFA500">

                                        <!-- Floating Popover Picker -->
                                        <div id="criar-chave-scratch-popover" class="scratch-popover shadow-lg" style="display: none;" onclick="event.stopPropagation()">
                                            <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                                                <span class="small fw-bold text-dark" id="criar-chave-scratch-popover-title">Selecionar Cor</span>
                                                <button type="button" class="btn-close btn-sm" onclick="fecharSeletorCorChave('criar-chave')"></button>
                                            </div>
                                            <div class="mb-2">
                                                <input type="color" id="criar-chave-scratch-native-picker" class="form-control form-control-color w-100 mb-2" style="height: 42px; cursor: pointer;" oninput="onNativeColorChangeChave('criar-chave', this.value)">
                                                <div class="d-flex flex-wrap gap-1 mb-2">
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFD700;" title="Dourado" onclick="setPresetColorChave('criar-chave', '#FFD700')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFA500;" title="Laranja" onclick="setPresetColorChave('criar-chave', '#FFA500')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#ffffff;" title="Branco" onclick="setPresetColorChave('criar-chave', '#ffffff')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#9945FF;" title="Roxo" onclick="setPresetColorChave('criar-chave', '#9945FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#14F195;" title="Verde Esmeralda" onclick="setPresetColorChave('criar-chave', '#14F195')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#00C0FF;" title="Azul Diamante" onclick="setPresetColorChave('criar-chave', '#00C0FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF3366;" title="Vermelho Ruby" onclick="setPresetColorChave('criar-chave', '#FF3366')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#000000;" title="Preto" onclick="setPresetColorChave('criar-chave', '#000000')"></button>
                                                </div>
                                            </div>
                                            <div class="pt-2 border-top">
                                                <label class="small text-muted mb-1 d-block fw-semibold" style="font-size: 0.72rem;">Código HEX:</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text font-monospace">#</span>
                                                    <input type="text" id="criar-chave-scratch-hex-input" class="form-control font-monospace" maxlength="7" placeholder="FFD700" oninput="onHexInputChangeChave('criar-chave', this.value)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Painel String (Minecraft) -->
                                <div id="criar-chave-panel-string" class="w-100" style="display: none;">
                                    <div class="input-group" style="height: 46px;">
                                        <span class="input-group-text font-monospace" style="background: #0f172a; border-color: #334155; color: #94a3b8; font-size: 0.85rem;">
                                            <i class="fa-solid fa-code"></i>
                                        </span>
                                        <input type="text" class="form-control font-monospace h-100" id="criar-chave-cor_string" name="cor_string" 
                                               placeholder="#HEX&amp;lLetra&#38;#HEX&amp;l..."
                                               autocomplete="off" style="background: #0f172a; border-color: #334155; color: #f8fafc; font-size: 0.82rem;" oninput="atualizarPreviewTagChave('criar-chave')">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Limpar string" onclick="limparCorStringChave('criar-chave')" style="border-color: #334155;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. SWITCH NA DIREITA -->
                            <div class="flex-shrink-0">
                                <div class="tag-minimal-switch">
                                    <button type="button" class="tag-switch-btn active" id="criar-chave-btn-mode-colorwheel" onclick="definirModoCorChave('criar-chave', 'colorwheel')">
                                        Degradê
                                    </button>
                                    <button type="button" class="tag-switch-btn" id="criar-chave-btn-mode-string" onclick="definirModoCorChave('criar-chave', 'string')">
                                        String
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="criar-chave-cor_tipo" name="cor_tipo" value="colorwheel">
                        <div id="criar-chave-string-helper" style="display: none;" class="mt-1 d-flex align-items-center justify-content-between">
                            <span class="text-muted small" style="font-size: 0.72rem;">Suporta formatos hexadecimais (&amp;#RRGGBB, #RRGGBB) e códigos clássicos (&amp;0-&amp;f, &amp;l).</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.72rem;" onclick="preencherExemploStringChave('criar-chave')">Preencher exemplo</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Recompensas / Vantagens da Caixa</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-adicionar-vantagem-criar">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar Item
                            </button>
                        </label>
                        <div id="container-vantagens-criar" class="d-flex flex-column gap-2">
                            <div class="input-group input-group-sm vantagem-item">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-gift"></i></span>
                                <input type="text" class="form-control" name="vantagens[]" value="Abertura na Caixa Mítica do Spawn" placeholder="Ex: Espada Lendária" required>
                                <button type="button" class="btn btn-outline-danger btn-remover-vantagem"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 pt-2 border-top">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="criar-destaque" name="destaque" value="1">
                                <label class="form-check-label fw-bold" for="criar-destaque">Destacar pacote na loja</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="criar-ativo" name="ativo" value="1" checked>
                                <label class="form-check-label fw-bold" for="criar-ativo">Pacote ativo para compras</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-chave">
                        <i class="fa-solid fa-check me-1"></i> Cadastrar Pacote
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR PACOTE -->
<div class="modal fade" id="modalEditarChave" tabindex="-1" aria-labelledby="modalEditarChaveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formEditarChave" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarChaveLabel">
                        Editar Pacote: <span id="modalEditarChaveNomeHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="edit-servidor-id" class="form-label fw-bold">Servidor <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit-servidor-id" name="servidor_id" required>
                                <option value="">Selecione o servidor...</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>">
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="edit-nome" class="form-label fw-bold">Nome do Pacote <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-nome" name="nome" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit-packageId" class="form-label fw-bold">Package ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="edit-packageId" name="packageId" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3 align-items-start">
                        <div class="col-md-5">
                            <label for="edit-preco" class="form-label fw-bold">Preço Unitário (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="edit-preco" name="preco" required>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label for="edit-imagem-upload" class="form-label fw-bold">Imagem do Item (Topo do Card)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="edit-imagem-upload" name="imagem_upload" accept="image/*">
                                <div class="bg-dark border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 42px; height: 38px;">
                                    <img id="edit-preview-img" src="/assets/images/logo.webp" style="max-width: 32px; max-height: 32px; object-fit: contain;" alt="Prévia">
                                </div>
                            </div>
                            <div class="form-check mt-1" id="edit-remover-imagem-box" style="display: none;">
                                <input class="form-check-input" type="checkbox" id="edit-remover-imagem" name="remover_imagem" value="1">
                                <label class="form-check-label small text-danger fw-semibold" for="edit-remover-imagem">
                                    Remover imagem personalizada
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SISTEMA DE COR DO TÍTULO / TAG (DEGRADÊ OU STRING MINECRAFT) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-2">Cor do Título</label>
                        <div class="d-flex align-items-center gap-2">
                            <!-- 1. PREVIEW NA ESQUERDA -->
                            <div class="rounded border text-center font-monospace d-flex align-items-center justify-content-center px-3"
                                 style="background: #0f172a; border-color: #334155 !important; height: 46px; min-width: 150px; max-width: 200px; flex-shrink: 0;"
                                 title="Prévia em tempo real">
                                <div id="edit-chave-tag-preview-content" class="fw-bold" style="font-size: 1.05rem; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                            </div>

                            <!-- 2. INPUTFIELD NO MEIO -->
                            <div class="flex-grow-1 position-relative" style="min-width: 0;">
                                <!-- Painel Degradê -->
                                <div id="edit-chave-panel-colorwheel" class="w-100">
                                    <div class="scratch-gradient-wrapper position-relative">
                                        <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background: #0f172a; border-color: #334155; height: 46px; box-sizing: border-box;">
                                            <div class="scratch-swatch-box" id="edit-chave-swatch-cor1-btn" onclick="abrirSeletorCorChave('edit-chave', 'cor1', event)" title="Cor inicial (clique para escolher)">
                                                <span class="scratch-swatch" id="edit-chave-preview-swatch-cor1" style="background-color: #FFD700;"></span>
                                            </div>
                                            <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradienteChave('edit-chave', event)" title="Inverter cores">
                                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                            </button>
                                            <div class="scratch-swatch-box" id="edit-chave-swatch-cor2-btn" onclick="abrirSeletorCorChave('edit-chave', 'cor2', event)" title="Cor final (clique para escolher)">
                                                <span class="scratch-swatch" id="edit-chave-preview-swatch-cor2" style="background-color: #FFA500;"></span>
                                            </div>
                                            <div class="flex-grow-1 px-1">
                                                <div id="edit-chave-scratch-mini-gradient-bar" class="rounded border border-secondary" style="height: 18px; width: 100%; background: linear-gradient(90deg, #FFD700, #FFA500);"></div>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 text-muted d-flex align-items-center justify-content-center" title="Resetar cores para padrão (#FFD700 / #FFA500)" onclick="resetarCoresPadraoChave('edit-chave')" style="width: 28px; height: 28px; flex-shrink: 0;">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" id="edit-chave-cor1" name="cor1" value="#FFD700">
                                        <input type="hidden" id="edit-chave-cor2" name="cor2" value="#FFA500">

                                        <!-- Floating Popover Picker -->
                                        <div id="edit-chave-scratch-popover" class="scratch-popover shadow-lg" style="display: none;" onclick="event.stopPropagation()">
                                            <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                                                <span class="small fw-bold text-dark" id="edit-chave-scratch-popover-title">Selecionar Cor</span>
                                                <button type="button" class="btn-close btn-sm" onclick="fecharSeletorCorChave('edit-chave')"></button>
                                            </div>
                                            <div class="mb-2">
                                                <input type="color" id="edit-chave-scratch-native-picker" class="form-control form-control-color w-100 mb-2" style="height: 42px; cursor: pointer;" oninput="onNativeColorChangeChave('edit-chave', this.value)">
                                                <div class="d-flex flex-wrap gap-1 mb-2">
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFD700;" title="Dourado" onclick="setPresetColorChave('edit-chave', '#FFD700')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FFA500;" title="Laranja" onclick="setPresetColorChave('edit-chave', '#FFA500')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#ffffff;" title="Branco" onclick="setPresetColorChave('edit-chave', '#ffffff')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#9945FF;" title="Roxo" onclick="setPresetColorChave('edit-chave', '#9945FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#14F195;" title="Verde Esmeralda" onclick="setPresetColorChave('edit-chave', '#14F195')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#00C0FF;" title="Azul Diamante" onclick="setPresetColorChave('edit-chave', '#00C0FF')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#FF3366;" title="Vermelho Ruby" onclick="setPresetColorChave('edit-chave', '#FF3366')"></button>
                                                    <button type="button" class="scratch-preset-btn" style="background:#000000;" title="Preto" onclick="setPresetColorChave('edit-chave', '#000000')"></button>
                                                </div>
                                            </div>
                                            <div class="pt-2 border-top">
                                                <label class="small text-muted mb-1 d-block fw-semibold" style="font-size: 0.72rem;">Código HEX:</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text font-monospace">#</span>
                                                    <input type="text" id="edit-chave-scratch-hex-input" class="form-control font-monospace" maxlength="7" placeholder="FFD700" oninput="onHexInputChangeChave('edit-chave', this.value)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Painel String (Minecraft) -->
                                <div id="edit-chave-panel-string" class="w-100" style="display: none;">
                                    <div class="input-group" style="height: 46px;">
                                        <span class="input-group-text font-monospace" style="background: #0f172a; border-color: #334155; color: #94a3b8; font-size: 0.85rem;">
                                            <i class="fa-solid fa-code"></i>
                                        </span>
                                        <input type="text" class="form-control font-monospace h-100" id="edit-chave-cor_string" name="cor_string" 
                                               placeholder="#HEX&amp;lLetra&#38;#HEX&amp;l..."
                                               autocomplete="off" style="background: #0f172a; border-color: #334155; color: #f8fafc; font-size: 0.82rem;" oninput="atualizarPreviewTagChave('edit-chave')">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Limpar string" onclick="limparCorStringChave('edit-chave')" style="border-color: #334155;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. SWITCH NA DIREITA -->
                            <div class="flex-shrink-0">
                                <div class="tag-minimal-switch">
                                    <button type="button" class="tag-switch-btn active" id="edit-chave-btn-mode-colorwheel" onclick="definirModoCorChave('edit-chave', 'colorwheel')">
                                        Degradê
                                    </button>
                                    <button type="button" class="tag-switch-btn" id="edit-chave-btn-mode-string" onclick="definirModoCorChave('edit-chave', 'string')">
                                        String
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="edit-chave-cor_tipo" name="cor_tipo" value="colorwheel">
                        <div id="edit-chave-string-helper" style="display: none;" class="mt-1 d-flex align-items-center justify-content-between">
                            <span class="text-muted small" style="font-size: 0.72rem;">Suporta formatos hexadecimais (&amp;#RRGGBB, #RRGGBB) e códigos clássicos (&amp;0-&amp;f, &amp;l).</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.72rem;" onclick="preencherExemploStringChave('edit-chave')">Preencher exemplo</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Recompensas / Vantagens da Caixa</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-adicionar-vantagem-edit">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar Item
                            </button>
                        </label>
                        <div id="container-vantagens-edit" class="d-flex flex-column gap-2"></div>
                    </div>

                    <div class="row g-3 pt-2 border-top">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-destaque" name="destaque" value="1">
                                <label class="form-check-label fw-bold" for="edit-destaque">Destacar pacote na loja</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-ativo" name="ativo" value="1">
                                <label class="form-check-label fw-bold" for="edit-ativo">Pacote ativo para compras</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-chave">
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
    'criar-chave': 'cor1',
    'edit-chave': 'cor1'
};

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Parser para tags e títulos no formato Minecraft (hex e formatações)
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

function atualizarPreviewTagChave(prefix) {
    const previewEl = document.getElementById(`${prefix}-tag-preview-content`);
    const modo = document.getElementById(`${prefix}-cor_tipo`)?.value || 'colorwheel';
    const nomeInput = document.getElementById(`${prefix}-nome`) || document.getElementById(prefix === 'criar-chave' ? 'criar-nome' : 'edit-nome');
    const nomeChave = (nomeInput?.value || 'PACOTE').trim() || 'PACOTE';

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
                previewHtml = parsed;
            }
        }
    } else {
        const c1 = document.getElementById(`${prefix}-cor1`)?.value || '#FFD700';
        const c2 = document.getElementById(`${prefix}-cor2`)?.value || '#FFA500';
        const hasCustomGrad = c1 && c2 && (c1.toLowerCase() !== c2.toLowerCase()) && (c1 !== '#ffffff' || c2 !== '#ffffff');

        const titleStyle = hasCustomGrad
            ? `background: linear-gradient(135deg, ${c1}, ${c2}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800;`
            : `color: ${c1}; font-weight: 800;`;

        previewHtml = `<span style="${titleStyle}">${escapeHtml(nomeChave)}</span>`;
    }

    if (previewEl) previewEl.innerHTML = previewHtml;
}

function definirModoCorChave(prefix, modo) {
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
        fecharSeletorCorChave(prefix);
    } else {
        if (panelColorwheel) panelColorwheel.style.display = 'block';
        if (panelString) panelString.style.display = 'none';
        if (btnColorwheel) btnColorwheel.classList.add('active');
        if (btnString) btnString.classList.remove('active');
        if (stringHelper) stringHelper.style.display = 'none';
    }

    atualizarPreviewTagChave(prefix);
}

function atualizarMiniGradienteChave(prefix) {
    const c1 = document.getElementById(`${prefix}-cor1`)?.value || '#FFD700';
    const c2 = document.getElementById(`${prefix}-cor2`)?.value || '#FFA500';
    const bar = document.getElementById(`${prefix}-scratch-mini-gradient-bar`);
    if (bar) {
        bar.style.background = `linear-gradient(90deg, ${c1}, ${c2})`;
    }
}

function abrirSeletorCorChave(prefix, slot, event) {
    if (event) event.stopPropagation();
    activeColorSlots[prefix] = slot;

    document.getElementById(`${prefix}-swatch-cor1-btn`)?.classList.toggle('active', slot === 'cor1');
    document.getElementById(`${prefix}-swatch-cor2-btn`)?.classList.toggle('active', slot === 'cor2');

    const inputVal = document.getElementById(`${prefix}-${slot}`)?.value || (slot === 'cor1' ? '#FFD700' : '#FFA500');
    const title = document.getElementById(`${prefix}-scratch-popover-title`);
    if (title) title.textContent = slot === 'cor1' ? 'Cor Inicial (Gradiente)' : 'Cor Final (Gradiente)';

    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = inputVal;

    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = inputVal.replace('#', '').toUpperCase();

    const popover = document.getElementById(`${prefix}-scratch-popover`);
    if (popover) popover.style.display = 'block';
}

function fecharSeletorCorChave(prefix) {
    const popover = document.getElementById(`${prefix}-scratch-popover`);
    if (popover) popover.style.display = 'none';
    document.getElementById(`${prefix}-swatch-cor1-btn`)?.classList.remove('active');
    document.getElementById(`${prefix}-swatch-cor2-btn`)?.classList.remove('active');
}

function aplicarCorChave(prefix, hex) {
    if (!hex.startsWith('#')) hex = '#' + hex;
    const slot = activeColorSlots[prefix] || 'cor1';
    const input = document.getElementById(`${prefix}-${slot}`);
    if (input) {
        input.value = hex;
    }

    const swatch = document.getElementById(`${prefix}-preview-swatch-${slot}`);
    if (swatch) swatch.style.backgroundColor = hex;

    atualizarMiniGradienteChave(prefix);
    atualizarPreviewTagChave(prefix);
}

function onNativeColorChangeChave(prefix, hex) {
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCorChave(prefix, hex);
}

function onHexInputChangeChave(prefix, val) {
    const clean = val.trim().replace('#', '');
    if (/^[0-9A-Fa-f]{6}$/.test(clean)) {
        const hex = '#' + clean.toUpperCase();
        const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
        if (nativePicker) nativePicker.value = hex;
        aplicarCorChave(prefix, hex);
    }
}

function setPresetColorChave(prefix, hex) {
    const nativePicker = document.getElementById(`${prefix}-scratch-native-picker`);
    if (nativePicker) nativePicker.value = hex;
    const hexInput = document.getElementById(`${prefix}-scratch-hex-input`);
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCorChave(prefix, hex);
}

function trocarCoresGradienteChave(prefix, e) {
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

    atualizarMiniGradienteChave(prefix);
    atualizarPreviewTagChave(prefix);
}

function resetarCoresPadraoChave(prefix) {
    const def1 = '#FFD700';
    const def2 = '#FFA500';
    const c1Input = document.getElementById(`${prefix}-cor1`);
    const c2Input = document.getElementById(`${prefix}-cor2`);
    if (c1Input) c1Input.value = def1;
    if (c2Input) c2Input.value = def2;

    const sw1 = document.getElementById(`${prefix}-preview-swatch-cor1`);
    const sw2 = document.getElementById(`${prefix}-preview-swatch-cor2`);
    if (sw1) sw1.style.backgroundColor = def1;
    if (sw2) sw2.style.backgroundColor = def2;

    fecharSeletorCorChave(prefix);
    atualizarMiniGradienteChave(prefix);
    atualizarPreviewTagChave(prefix);
}

function limparCorStringChave(prefix) {
    const input = document.getElementById(`${prefix}-cor_string`);
    if (input) {
        input.value = '';
        input.focus();
    }
    atualizarPreviewTagChave(prefix);
}

function preencherExemploStringChave(prefix) {
    const input = document.getElementById(`${prefix}-cor_string`);
    if (input) {
        input.value = '&#FFD700&lChave &#FFA500&lMítica';
    }
    atualizarPreviewTagChave(prefix);
}

document.addEventListener('DOMContentLoaded', function() {
    const servidoresMap = <?php echo json_encode($servidoresMap, JSON_UNESCAPED_UNICODE); ?>;

    const modalCriarEl = document.getElementById('modalCriarChave');
    const modalEditarEl = document.getElementById('modalEditarChave');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    // Inicialização do preview de criação
    atualizarMiniGradienteChave('criar-chave');
    atualizarPreviewTagChave('criar-chave');

    // Atualiza preview em tempo real ao alterar o nome
    document.getElementById('criar-nome')?.addEventListener('input', () => atualizarPreviewTagChave('criar-chave'));
    document.getElementById('edit-nome')?.addEventListener('input', () => atualizarPreviewTagChave('edit-chave'));

    // Fechar popover ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.scratch-gradient-wrapper')) {
            fecharSeletorCorChave('criar-chave');
            fecharSeletorCorChave('edit-chave');
        }
    });

    // Image previews
    function setupImagePreview(inputId, imgId) {
        const input = document.getElementById(inputId);
        const img = document.getElementById(imgId);
        if (input && img) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                        img.style.opacity = '1';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
    setupImagePreview('criar-imagem-upload', 'criar-preview-img');
    setupImagePreview('edit-imagem-upload', 'edit-preview-img');

    const removerImgChk = document.getElementById('edit-remover-imagem');
    if (removerImgChk) {
        removerImgChk.addEventListener('change', function() {
            const img = document.getElementById('edit-preview-img');
            if (img) img.style.opacity = this.checked ? '0.2' : '1';
        });
    }

    // Vantagens dynamic list
    function addVantagemRow(containerId, valor = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm vantagem-item';
        div.innerHTML = `
            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-gift"></i></span>
            <input type="text" class="form-control" name="vantagens[]" value="${escapeHtml(valor)}" placeholder="Ex: Espada Lendária de Fogo" required>
            <button type="button" class="btn btn-outline-danger btn-remover-vantagem"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(div);
        div.querySelector('.btn-remover-vantagem').addEventListener('click', function() {
            const items = container.querySelectorAll('.vantagem-item');
            if (items.length > 1) {
                div.remove();
            } else {
                div.querySelector('input').value = '';
            }
        });
    }

    document.querySelectorAll('.btn-remover-vantagem').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.d-flex');
            const items = container.querySelectorAll('.vantagem-item');
            if (items.length > 1) {
                this.closest('.vantagem-item').remove();
            } else {
                this.closest('.vantagem-item').querySelector('input').value = '';
            }
        });
    });

    const btnAddCriar = document.getElementById('btn-adicionar-vantagem-criar');
    if (btnAddCriar) {
        btnAddCriar.addEventListener('click', () => addVantagemRow('container-vantagens-criar'));
    }
    const btnAddEdit = document.getElementById('btn-adicionar-vantagem-edit');
    if (btnAddEdit) {
        btnAddEdit.addEventListener('click', () => addVantagemRow('container-vantagens-edit'));
    }

    function atualizarContadores() {
        const ativas = document.querySelectorAll('#lista-chaves-ativas tr[data-id]').length;
        const inativas = document.querySelectorAll('#lista-chaves-inativas tr[data-id]').length;
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

    function abrirModalEditarChave(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-id').value = d.id || '';
        document.getElementById('edit-nome').value = d.nome || '';
        document.getElementById('modalEditarChaveNomeHeader').textContent = d.nome || '';
        document.getElementById('edit-servidor-id').value = d.servidorId || '';
        document.getElementById('edit-packageId').value = d.packageId || '';
        document.getElementById('edit-preco').value = d.preco || '';
        
        // Configuração de cores e modo
        const cor1 = d.cor1 || '#FFD700';
        const cor2 = d.cor2 || '#FFA500';
        const corTipo = d.corTipo || 'colorwheel';
        const corString = d.corString || '';

        document.getElementById('edit-chave-cor1').value = cor1;
        document.getElementById('edit-chave-cor2').value = cor2;
        const sw1 = document.getElementById('edit-chave-preview-swatch-cor1');
        const sw2 = document.getElementById('edit-chave-preview-swatch-cor2');
        if (sw1) sw1.style.backgroundColor = cor1;
        if (sw2) sw2.style.backgroundColor = cor2;
        document.getElementById('edit-chave-cor_string').value = corString;

        definirModoCorChave('edit-chave', corTipo);
        atualizarMiniGradienteChave('edit-chave');
        atualizarPreviewTagChave('edit-chave');

        document.getElementById('edit-destaque').checked = (d.destaque === '1');
        document.getElementById('edit-ativo').checked = (d.ativo === '1');

        const imgEl = document.getElementById('edit-preview-img');
        const removerBox = document.getElementById('edit-remover-imagem-box');
        const removerChk = document.getElementById('edit-remover-imagem');
        if (removerChk) removerChk.checked = false;

        if (d.imagem) {
            if (imgEl) {
                imgEl.src = d.imagem;
                imgEl.style.opacity = '1';
            }
            if (removerBox) removerBox.style.display = 'block';
        } else {
            if (imgEl) {
                imgEl.src = '/assets/images/logo.webp';
                imgEl.style.opacity = '0.35';
            }
            if (removerBox) removerBox.style.display = 'none';
        }

        // Vantagens
        const containerV = document.getElementById('container-vantagens-edit');
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
                listaV = ['Abertura na Caixa Mítica'];
            }
            listaV.forEach(v => addVantagemRow('container-vantagens-edit', v));
        }

        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-chave');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarChave(row);
    });

    function criarElementoLinhaChave(c) {
        const tr = document.createElement('tr');
        tr.id = `chave-row-${c.id}`;
        tr.dataset.id = c.id;
        tr.dataset.servidorId = c.servidor_id || 0;
        tr.dataset.nome = c.nome || '';
        tr.dataset.packageId = c.packageId || '';
        tr.dataset.preco = Number(c.preco || 0).toFixed(2);
        tr.dataset.destaque = c.destaque ? '1' : '0';
        tr.dataset.ativo = c.ativo ? '1' : '0';
        tr.dataset.cor1 = c.cor1 || '#FFD700';
        tr.dataset.cor2 = c.cor2 || '#FFA500';
        tr.dataset.corTipo = c.cor_tipo || 'colorwheel';
        tr.dataset.corString = c.cor_string || '';
        tr.dataset.imagem = c.imagem || '';
        tr.dataset.vantagens = c.vantagens || '[]';

        const sId = parseInt(c.servidor_id) || 0;
        const srv = (sId > 0 && servidoresMap[sId]) ? servidoresMap[sId] : null;
        const srvNome = srv ? (srv.servername || srv.nome) : (c.servidor || 'Servidor');
        const srvCor = srv ? (srv.themecolor || '#F59E0B') : '#F59E0B';
        const srvEnabled = srv ? (parseInt(srv.enabled) !== 0) : true;

        const isAtivo = Boolean(Number(c.ativo));
        const statusBadge = (isAtivo && srvEnabled)
            ? `<span class="badge-status ativo">Ativo</span>`
            : (!srvEnabled && isAtivo 
                ? `<span class="badge-status pendente"><i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto</span>`
                : `<span class="badge-status inativo">Inativo</span>`);

        const precoFormatado = Number(c.preco || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const imgThumb = c.imagem 
            ? `<img src="${escapeHtml(c.imagem)}" alt="" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">`
            : `<div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded" style="width: 28px; height: 28px;"><i class="fa-solid fa-key" style="font-size: 0.85rem;"></i></div>`;

        const destaqueBadge = c.destaque 
            ? `<span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque na loja"><i class="fa-solid fa-star me-1"></i>Destaque</span>`
            : '';

        tr.innerHTML = `
            <td>
                <span class="badge js-chave-servidor" style="background-color: ${escapeHtml(srvCor)}; color: #fff; font-weight: 600;">
                    <i class="fa-solid fa-server me-1"></i>${escapeHtml(srvNome)}
                </span>
            </td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="js-chave-preview-container">${imgThumb}</div>
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-center gap-1">
                            <strong class="text-dark js-chave-nome">${escapeHtml(c.nome)}</strong>
                            <span class="js-chave-destaque-badge">${destaqueBadge}</span>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge bg-light text-dark border font-monospace js-chave-package" style="font-size: 0.78rem;">
                    ${escapeHtml(c.packageId)}
                </span>
            </td>
            <td>
                <strong class="text-success fw-bold font-monospace js-chave-preco">
                    R$ ${precoFormatado}
                </strong>
            </td>
            <td>${statusBadge}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-chave" title="Editar Pacote">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-action="async-toggle" 
                            data-url="/admin/api/chaves/toggle.php" 
                            data-id="${c.id}"
                            title="${isAtivo ? 'Desativar da loja' : 'Ativar na loja'}">
                        <i class="fa-solid ${isAtivo ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/chaves/deletar.php" 
                            data-id="${c.id}"
                            data-name="${escapeHtml(c.nome)}"
                            data-confirm="Tem certeza que deseja excluir o pacote ${escapeHtml(c.nome)} do servidor ${escapeHtml(srvNome)}?"
                            title="Deletar Pacote">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // SUBMIT: CRIAR CHAVE
    const formCriar = document.getElementById('formCriarChave');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar-chave');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cadastrando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/chaves/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                resetarCoresPadraoChave('criar-chave');
                definirModoCorChave('criar-chave', 'colorwheel');
                atualizarPreviewTagChave('criar-chave');
                document.getElementById('criar-preview-img').src = '/assets/images/logo.webp';
                document.getElementById('criar-preview-img').style.opacity = '0.35';

                if (res.chave) {
                    const isAtivo = Boolean(Number(res.chave.ativo));
                    const tbody = isAtivo ? document.getElementById('lista-chaves-ativas') : document.getElementById('lista-chaves-inativas');
                    const emptyRow = isAtivo ? document.getElementById('linha-sem-chaves-ativas') : document.getElementById('linha-sem-chaves-inativas');
                    if (emptyRow) emptyRow.remove();

                    const novaLinha = criarElementoLinhaChave(res.chave);
                    if (tbody) tbody.prepend(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Pacote cadastrado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao cadastrar pacote.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR CHAVE
    const formEditar = document.getElementById('formEditarChave');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar-chave');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/chaves/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.chave) {
                    const c = res.chave;
                    let tr = document.getElementById(`chave-row-${c.id}`);
                    const isAtivo = Boolean(Number(c.ativo));
                    const destinoTbody = isAtivo ? document.getElementById('lista-chaves-ativas') : document.getElementById('lista-chaves-inativas');

                    if (tr) {
                        const novaLinha = criarElementoLinhaChave(c);
                        tr.replaceWith(novaLinha);
                        tr = novaLinha;

                        if (tr.parentElement !== destinoTbody && destinoTbody) {
                            const emptyRow = isAtivo ? document.getElementById('linha-sem-chaves-ativas') : document.getElementById('linha-sem-chaves-inativas');
                            if (emptyRow) emptyRow.remove();
                            destinoTbody.prepend(tr);
                        }

                        tr.classList.add('table-row-highlight');
                        setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Pacote atualizado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar pacote.');
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
        const row = document.getElementById(`chave-row-${editId}`);
        if (row) {
            abrirModalEditarChave(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
