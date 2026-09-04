<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Adicionar Membro';
require_once __DIR__ . "/../includes/admin_header.php";

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
$nick = '';
$cargo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim($_POST['nick'] ?? '');
    $cargo_select = trim($_POST['cargo_select'] ?? '');
    $cargo_custom = trim($_POST['cargo_custom'] ?? '');
    $cargo = ($cargo_select === '__custom__') ? $cargo_custom : $cargo_select;

    if (empty($nick)) {
        $erro = 'O campo Nick é obrigatório.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{2,16}$/', $nick)) {
        $erro = 'Nick inválido. Use entre 2 e 16 caracteres alfanuméricos.';
    } elseif (empty($cargo)) {
        $erro = 'Informe ou selecione um cargo.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO equipe (nick, cargo) VALUES (:nick, :cargo)");
            $stmt->execute([':nick' => $nick, ':cargo' => $cargo]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-user-plus text-success"></i> Adicionar Membro da Equipe</h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="criar.php">
                    <div class="admin-form-group">
                        <label for="nick">Nick do Jogador (Minecraft)</label>
                        <input type="text" class="admin-form-control" id="nick" name="nick" value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Steve" required autofocus>
                    </div>

                    <div class="admin-form-group">
                        <label for="cargo_select">Cargo / Grupo</label>
                        <select class="admin-form-control mb-2" id="cargo_select" name="cargo_select">
                            <option value="">Selecione um cargo...</option>
                            <?php foreach ($cargosPredefinidos as $c): ?>
                                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                            <option value="__custom__">+ Outro cargo personalizado...</option>
                        </select>
                        <input type="text" class="admin-form-control mt-2" id="cargo_custom" name="cargo_custom" placeholder="Digite o novo cargo..." style="display:none;">
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4">Salvar Membro</button>
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
