<?php
$paginaAtiva = 'cupons';
$tituloPagina = 'Cupons de Desconto';
require_once __DIR__ . "/includes/admin_header.php";

try {
    $stmt = $pdo->query("SELECT * FROM cupons ORDER BY criado_em DESC");
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cupons = [];
}

function statusDoCupom(array $cupom): array {
    $now = time();
    $expiraEm = strtotime($cupom['expira_em']);
    $isExpirado = ($expiraEm && $expiraEm < $now);

    if ($isExpirado) {
        return ['label' => 'Expirado', 'badge' => 'badge-status expirado', 'ativo' => false, 'expirado' => true];
    }
    if (!$cupom['ativo']) {
        return ['label' => 'Inativo', 'badge' => 'badge-status inativo', 'ativo' => false, 'expirado' => false];
    }
    return ['label' => 'Ativo', 'badge' => 'badge-status ativo', 'ativo' => true, 'expirado' => false];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Cupons de Desconto</h4>
        <p class="text-muted small mb-0"><?php echo count($cupons); ?> cupom(ns) cadastrado(s)</p>
    </div>
    <a href="cupom_criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo cupom</a>
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

<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 180px;">Código do Cupom</th>
                        <th style="width: 140px;">Desconto</th>
                        <th style="width: 200px;">Validade</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 100px;">Usos</th>
                        <th style="width: 150px;">Criado em</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cupons)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum cupom cadastrado ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cupons as $c): 
                            $st = statusDoCupom($c);
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
                                    <i class="fa-regular fa-clock text-muted me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($c['expira_em'])); ?>
                                    <?php if ($st['expirado']): ?>
                                        <span class="badge bg-danger ms-1" style="font-size: 0.68rem;">Expirado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $st['badge']; ?>"><?php echo $st['label']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?php echo (int)$c['usos_total']; ?> compra(s)</span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($c['criado_em'])); ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="cupom_editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <form method="POST" action="api/cupons/toggle.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $c['ativo'] ? 'Desativar cupom' : 'Ativar cupom'; ?>">
                                                <i class="fa-solid <?php echo $c['ativo'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="api/cupons/deletar.php" class="d-inline" onsubmit="return confirm('Deletar o cupom <?php echo htmlspecialchars(addslashes($c['codigo']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
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