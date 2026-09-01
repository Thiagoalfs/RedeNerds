<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Equipe';
require_once __DIR__ . "/includes/admin_header.php";

try {
    $stmt = $pdo->query("SELECT id, nick, cargo FROM equipe ORDER BY id ASC");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membros = [];
}

$grupos = [];
foreach ($membros as $m) {
    $grupos[$m['cargo']][] = $m;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Equipe</h4>
        <p class="text-muted small mb-0"><?php echo count($membros); ?> membro(s) em <?php echo count($grupos); ?> categoria(s)</p>
    </div>
    <a href="equipe_criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo membro</a>
</div>

<?php if (empty($grupos)): ?>
    <div class="admin-card p-4 text-center text-muted">
        Nenhum membro cadastrado ainda.
    </div>
<?php else: ?>
    <?php foreach ($grupos as $cargo => $lista): ?>
        <div class="admin-card mb-3">
            <div class="admin-card-header">
                <h6 class="fw-bold mb-0">🏷️ <?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?></h6>
                <span class="badge bg-light text-dark border"><?php echo count($lista); ?> membro(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Avatar</th>
                                <th>Nick</th>
                                <th style="width: 180px;">Cargo</th>
                                <th class="text-end" style="width: 130px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista as $m): ?>
                                <tr>
                                    <td>
                                        <img class="rounded border" src="https://mc-heads.net/avatar/<?php echo urlencode($m['nick']); ?>/32" width="32" height="32" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($m['cargo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                            <a href="equipe_editar.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <a href="api/equipe/deletar.php?id=<?php echo (int)$m['id']; ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Remover <?php echo htmlspecialchars(addslashes($m['nick']), ENT_QUOTES, 'UTF-8'); ?> da equipe?');" title="Deletar">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>