<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Editar Membro';
require_once __DIR__ . "/includes/admin_header.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: equipe.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membro) {
        header("Location: equipe.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar membro.");
}

$cargosPredefinidos = [
    'Direção',
    'Administração',
    'Gerência',
    'Moderação',
    'Suporte',
    'Ajudante',
    'Desenvolvedor',
    'Builder',
    'Designer',
    'Criador de Conteúdo'
];

$erro = null;
$nick = $membro['nick'];
$cargoAtual = $membro['cargo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim($_POST['nick'] ?? '');
    $cargo_select = trim($_POST['cargo_select'] ?? '');
    $cargo_custom = trim($_POST['cargo_custom'] ?? '');
    $cargo = ($cargo_select === '__custom__') ? $cargo_custom : $cargo_select;

    if (empty($nick)) {
        $erro = 'O campo Nick é obrigatório.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{2,16}$/', $nick)) {
        $erro = 'Nick inválido.';
    } elseif (empty($cargo)) {
        $erro = 'Informe um cargo.';
    } else {
        try {
            $upd = $pdo->prepare("UPDATE equipe SET nick = :nick, cargo = :cargo WHERE id = :id");
            $upd->execute([':nick' => $nick, ':cargo' => $cargo, ':id' => $id]);
            header("Location: equipe.php");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    }
}

$isPredefinido = in_array($cargoAtual, $cargosPredefinidos);
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-user-pen text-primary"></i> Editar Membro</h5>
                <a href="equipe.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="equipe_editar.php?id=<?php echo (int)$id; ?>">
                    <div class="admin-form-group">
                        <label for="nick">Nick do Jogador</label>
                        <input type="text" class="admin-form-control" id="nick" name="nick" value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="cargo_select">Cargo / Grupo</label>
                        <select class="admin-form-control mb-2" id="cargo_select" name="cargo_select">
                            <option value="">Selecione...</option>
                            <?php foreach ($cargosPredefinidos as $c): ?>
                                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($isPredefinido && $cargoAtual === $c) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom__" <?php echo (!$isPredefinido) ? 'selected' : ''; ?>>+ Outro cargo...</option>
                        </select>
                        <input type="text" class="admin-form-control mt-2" id="cargo_custom" name="cargo_custom" 
                               value="<?php echo (!$isPredefinido) ? htmlspecialchars($cargoAtual, ENT_QUOTES, 'UTF-8') : ''; ?>" 
                               style="<?php echo (!$isPredefinido) ? 'display:block;' : 'display:none;'; ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="equipe.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('cargo_select').addEventListener('change', function() {
    const custom = document.getElementById('cargo_custom');
    if (this.value === '__custom__') {
        custom.style.display = 'block';
        custom.focus();
    } else {
        custom.style.display = 'none';
        custom.value = '';
    }
});
</script>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>