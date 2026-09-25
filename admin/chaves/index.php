<?php
$paginaAtiva = 'chaves';
$tituloPagina = 'Pacotes de Chaves';
require_once __DIR__ . "/../includes/admin_header.php";

// Auto-criação da tabela e colunas se não existirem
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
      `vantagens` TEXT NULL,
      `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_srv (`servidor_id`, `ativo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    require_once __DIR__ . "/../../api/loja/config_loja.php";
    garantirSchemaTabelaPedidos($pdo);
} catch (Exception $e) {}

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

        $chaves[] = $c;
    }

    usort($chaves, function($a, $b) {
        $cmpSrv = strcasecmp($a['servidor_nome_oficial'], $b['servidor_nome_oficial']);
        if ($cmpSrv !== 0) return $cmpSrv;
        return ((float)($a['preco'] ?? 0)) <=> ((float)($b['preco'] ?? 0));
    });
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de Chaves: " . $e->getMessage());
    $chaves = [];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Pacotes de Chaves da Loja</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo count($chaves); ?> pacote(s)</span> de Chaves cadastrado(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/loja/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Loja</a>
        <a href="criar.php<?php echo $filtroServidor ? '?servidor=' . urlencode($filtroServidor) : ''; ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Pacote de Chaves</a>
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

<!-- TABELA DE CHAVES -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 160px;">Servidor</th>
                        <th>Pacote de Chaves</th>
                        <th style="width: 160px;">Package ID</th>
                        <th style="width: 140px;">Preço Unitário</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chaves)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhum pacote de chaves encontrado<?php echo $filtroServidor ? ' para o servidor selecionado' : ''; ?>. Clique em "+ Novo Pacote de Chaves" para cadastrar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chaves as $c): 
                            $srvNome = $c['servidor_nome_oficial'] ?: ($servidoresMap[$c['servidor_id']]['servername'] ?? ($c['servidor'] ?: 'Servidor'));
                            $srvCor = $c['servidor_cor'] ?: ($servidoresMap[$c['servidor_id'] ?? $c['servidor']]['themecolor'] ?? '#F59E0B');
                        ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvNome, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($c['imagem'])): ?>
                                            <img src="<?php echo htmlspecialchars($c['imagem'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">
                                        <?php else: ?>
                                            <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded" style="width: 28px; height: 28px;">
                                                <i class="fa-solid fa-key" style="font-size: 0.85rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-1">
                                                <strong class="text-dark"><?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if (!empty($c['destaque'])): ?>
                                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="Destaque na loja">
                                                        <i class="fa-solid fa-star me-1"></i>Destaque
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">
                                        <?php echo htmlspecialchars($c['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success fw-bold font-monospace">
                                        R$ <?php echo number_format((float)$c['preco'], 2, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if (!empty($c['ativo'])): ?>
                                        <span class="badge-status ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary" title="Editar Chave">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
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
                                                title="Deletar Chave">
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
