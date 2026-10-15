<?php
$paginaAtiva = 'servidores';
$tituloPagina = 'Servidores';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/icon_upload.php";

try {
    $stmt = $pdo->query("SELECT id, servername, nome, title, icon, ip, themecolor, enabled FROM servidores ORDER BY servername ASC");
    $servidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $servidoresVisiveis = [];
    $servidoresOcultos = [];
    foreach ($servidores as $s) {
        if (!empty($s['enabled'])) {
            $servidoresVisiveis[] = $s;
        } else {
            $servidoresOcultos[] = $s;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar servidores: " . $e->getMessage());
    $servidores = [];
    $servidoresVisiveis = [];
    $servidoresOcultos = [];
}

function renderIconeTable(?string $icone): string {
    if (!$icone) return '<i class="fa-solid fa-server"></i>';
    if (tipoDoIcone($icone) === 'img') {
        $src = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="rounded" width="32" height="32" style="object-fit: cover;" alt="">';
    }
    return '<i class="' . htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') . '"></i>';
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Servidores</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($servidores), 'servidor', 'servidores'); ?></span> <?php echo count($servidores) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($servidoresVisiveis), 'visível', 'visíveis'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($servidoresOcultos), 'oculto', 'ocultos'); ?></span>
        </p>
    </div>
    <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo servidor</a>
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

<!-- TABELA 1: SERVIDORES VISÍVEIS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Servidores Visíveis
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($servidoresVisiveis), 'servidor', 'servidores'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-servidores-ativos">
                <thead>
                    <tr>
                        <th style="width: 60px;">Ícone</th>
                        <th>Servidor</th>
                        <th style="width: 140px;">Slug</th>
                        <th style="width: 220px;">IP de Conexão</th>
                        <th style="width: 120px;">Tema</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servidoresVisiveis)): ?>
                        <tr class="empty-row">
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum servidor visível no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servidoresVisiveis as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 36px; height: 36px; color: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; font-size: 1.1rem;">
                                        <?php echo renderIconeTable($s['icon']); ?>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><small class="font-monospace text-muted"><?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span style="display:inline-block; width:16px; height:16px; border-radius:4px; background:<?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; border:1px solid rgba(0,0,0,0.15);"></span>
                                        <small class="font-monospace"><?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status visivel">Visível</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/servidores/toggle.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                title="Ocultar servidor">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/servidores/deletar.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o servidor <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
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

<!-- TABELA 2: SERVIDORES OCULTOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Servidores Ocultos
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($servidoresOcultos), 'servidor', 'servidores'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-servidores-inativos">
                <thead>
                    <tr>
                        <th style="width: 60px;">Ícone</th>
                        <th>Servidor</th>
                        <th style="width: 140px;">Slug</th>
                        <th style="width: 220px;">IP de Conexão</th>
                        <th style="width: 120px;">Tema</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servidoresOcultos)): ?>
                        <tr class="empty-row">
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum servidor oculto no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servidoresOcultos as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 36px; height: 36px; color: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; font-size: 1.1rem;">
                                        <?php echo renderIconeTable($s['icon']); ?>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><small class="font-monospace text-muted"><?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span style="display:inline-block; width:16px; height:16px; border-radius:4px; background:<?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; border:1px solid rgba(0,0,0,0.15);"></span>
                                        <small class="font-monospace"><?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status oculto">Oculto</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/servidores/toggle.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                title="Exibir servidor">
                                            <i class="fa-solid fa-eye-slash"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/servidores/deletar.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o servidor <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
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