<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Adicionar Membro';
require_once __DIR__ . "/../includes/admin_header.php";

$cargosBanco = [];
try {
    $stmtCargos = $pdo->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != '' ORDER BY cargo ASC");
    $cargosBanco = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $cargosBanco = [];
}

$ordemHierarquia = [
    'Fundadores',
    'Fundador',
    'Co-Fundador',
    'Diretores',
    'Diretor',
    'Coordenadores',
    'Coordenador',
    'Administradores',
    'Administrador',
    'Gerentes',
    'Gerente',
    'Moderadores',
    'Moderador',
    'Suporte',
    'Ajudantes',
    'Ajudante',
    'Desenvolvedores',
    'Desenvolvedor',
    'Designers',
    'Designer',
    'Builders',
    'Builder',
    'Criadores de Conteúdo',
    'Criador de Conteúdo'
];

if (!empty($cargosBanco)) {
    usort($cargosBanco, function ($a, $b) use ($ordemHierarquia) {
        $posA = array_search($a, $ordemHierarquia, true);
        $posB = array_search($b, $ordemHierarquia, true);
        $posA = ($posA === false) ? PHP_INT_MAX : $posA;
        $posB = ($posB === false) ? PHP_INT_MAX : $posB;
        if ($posA === $posB) {
            return strcasecmp($a, $b);
        }
        return $posA <=> $posB;
    });
} else {
    $cargosBanco = [
        'Fundadores',
        'Co-Fundador',
        'Diretores',
        'Coordenadores',
        'Administradores',
        'Moderadores',
        'Desenvolvedores',
        'Designers'
    ];
}

$erro = null;
$nick = '';
$cargo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="admin-form-group">
                        <label for="nick">Nick do Jogador (Minecraft)</label>
                        <input type="text" class="admin-form-control" id="nick" name="nick" value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Steve" required autofocus>
                    </div>

                    <div class="admin-form-group">
                        <label for="cargo_select">Cargo / Grupo</label>
                        <select class="admin-form-control mb-2" id="cargo_select" name="cargo_select">
                            <option value="">Selecione um cargo...</option>
                            <?php foreach ($cargosBanco as $c): ?>
                                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cargo === $c) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom__" <?php echo ($cargo && !in_array($cargo, $cargosBanco)) ? 'selected' : ''; ?>>+ Outro cargo personalizado...</option>
                        </select>
                        <input type="text" class="admin-form-control mt-2" id="cargo_custom" name="cargo_custom" 
                               value="<?php echo ($cargo && !in_array($cargo, $cargosBanco)) ? htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8') : ''; ?>"
                               placeholder="Digite o novo cargo..." 
                               style="<?php echo ($cargo && !in_array($cargo, $cargosBanco)) ? 'display:block;' : 'display:none;'; ?>">
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
