<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Equipe';
require_once __DIR__ . "/../includes/admin_header.php";

try {
    $stmt = $pdo->query("SELECT id, nick, cargo FROM equipe ORDER BY id ASC");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membros = [];
}

// Busca a ordem definida na tabela equipe_cargos
$cargosOrdem = [];
try {
    $stmtCargos = $pdo->query("SELECT nome FROM equipe_cargos ORDER BY ordem ASC, id ASC");
    $cargosOrdem = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $cargosOrdem = [];
}

$gruposBrutos = [];
foreach ($membros as $m) {
    $gruposBrutos[$m['cargo']][] = $m;
}

// Ordena os grupos conforme a hierarquia de equipe_cargos
$grupos = [];
foreach ($cargosOrdem as $cNome) {
    if (isset($gruposBrutos[$cNome])) {
        $grupos[$cNome] = $gruposBrutos[$cNome];
        unset($gruposBrutos[$cNome]);
    }
}
// Adiciona eventuais cargos restantes que não estejam cadastrados na tabela
foreach ($gruposBrutos as $cNome => $lista) {
    $grupos[$cNome] = $lista;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-users text-primary me-2"></i> Equipe</h4>
        <p class="text-muted small mb-0"><?php echo count($membros); ?> membro(s) em <?php echo count($grupos); ?> categoria(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="manage.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-layer-group me-1"></i> Gerenciar Cargos</a>
        <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Membro</a>
    </div>
</div>

<!-- NAVEGAÇÃO DE SUB-ABAS -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-users me-1"></i> Membros da Equipe
    </a>
    <a href="manage.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-layer-group me-1"></i> Hierarquia de Cargos
    </a>
</div>

<?php if (empty($grupos)): ?>
    <div class="admin-card p-4 text-center text-muted">
        Nenhum membro cadastrado ainda.
    </div>
<?php else: ?>
    <?php foreach ($grupos as $cargo => $lista): ?>
        <div class="admin-card mb-3">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle shadow-sm" style="display:inline-block; width: 14px; height: 14px; background-color: <?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?></h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border"><?php echo count($lista); ?> membro(s)</span>
                    <a href="criar.php?cargo=<?php echo urlencode($cargo); ?>" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.78rem;" title="Adicionar membro neste cargo">
                        <i class="fa-solid fa-plus"></i> Adicionar
                    </a>
                </div>
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
                                            <a href="editar.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <form method="POST" action="/admin/api/equipe/deletar.php" class="d-inline" onsubmit="return confirm('Remover <?php echo htmlspecialchars(addslashes($m['nick']), ENT_QUOTES, 'UTF-8'); ?> da equipe?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Deletar">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
