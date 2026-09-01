<?php
$paginaAtiva = 'servidores';
$tituloPagina = 'Servidores';
require_once __DIR__ . "/includes/admin_header.php";
require_once __DIR__ . "/icon_upload.php";

try {
    $stmt = $pdo->query("SELECT id, servername, nome, title, icon, ip, themecolor, enabled FROM servidores ORDER BY servername ASC");
    $servidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $servidores = [];
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
        <p class="text-muted small mb-0"><?php echo count($servidores); ?> servidor(es) cadastrado(s)</p>
    </div>
    <a href="servidor_criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo servidor</a>
</div>

<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
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
                    <?php if (empty($servidores)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum servidor cadastrado ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servidores as $s): ?>
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
                                    <?php if ($s['enabled']): ?>
                                        <span class="badge-status visivel">🟢 Visível</span>
                                    <?php else: ?>
                                        <span class="badge-status oculto">⚪ Oculto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="servidor_editar.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <a href="api/servidores/toggle.php?id=<?php echo (int)$s['id']; ?>"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="<?php echo $s['enabled'] ? 'Ocultar servidor' : 'Exibir servidor'; ?>">
                                            <i class="fa-solid <?php echo $s['enabled'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </a>
                                        <a href="api/servidores/deletar.php?id=<?php echo (int)$s['id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Deletar o servidor <?php echo htmlspecialchars(addslashes($s['servername']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');" title="Deletar">🗑️</a>
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