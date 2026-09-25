<?php
require_once __DIR__ . "/../sessao.php";

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membro) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar membro.");
}

$cargosBanco = [];
try {
    $stmtCargos = $pdo->query("SELECT nome FROM equipe_cargos ORDER BY ordem ASC, id ASC");
    $cargosBanco = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $cargosBanco = [];
}

if (empty($cargosBanco)) {
    try {
        $stmtCargos = $pdo->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != '' ORDER BY cargo ASC");
        $cargosBanco = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $cargosBanco = [];
    }
}

$erro = null;
$nick = $membro['nick'];
$cargo = $membro['cargo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $nick = trim($_POST['nick'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');

        if (empty($nick)) {
            $erro = 'O campo Nick é obrigatório.';
        } elseif (!preg_match('/^[A-Za-z0-9_]{2,16}$/', $nick)) {
            $erro = 'Nick inválido. Use entre 2 e 16 caracteres alfanuméricos.';
        } elseif (empty($cargo)) {
            $erro = 'Selecione um cargo.';
        } else {
            try {
                $upd = $pdo->prepare("UPDATE equipe SET nick = :nick, cargo = :cargo WHERE id = :id");
                $upd->execute([':nick' => $nick, ':cargo' => $cargo, ':id' => $id]);

                require_once __DIR__ . "/../../api/cache_helper.php";
                invalidarCache('equipe');

                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                $erro = "Erro ao atualizar: " . $e->getMessage();
            }
        }
    }
}

$paginaAtiva = 'equipe';
$tituloPagina = 'Editar Membro';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-user-pen text-primary"></i> Editar Membro</h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="editar.php?id=<?php echo (int)$id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="admin-form-group">
                        <label for="nick">Nick do Jogador (Minecraft)</label>
                        <input type="text" class="admin-form-control" id="nick" name="nick" value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Steve" required autofocus>
                    </div>

                    <div class="admin-form-group">
                        <label for="cargo">Cargo / Grupo</label>
                        <select class="admin-form-control" id="cargo" name="cargo" required>
                            <option value="">Selecione um cargo...</option>
                            <?php foreach ($cargosBanco as $c): ?>
                                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cargo === $c) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($cargo && !in_array($cargo, $cargosBanco)): ?>
                                <option value="<?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                    <?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?> (Não cadastrado na hierarquia)
                                </option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text small mt-1">Para criar ou reorganizar cargos, acesse o painel de <a href="manage.php">Gerenciar Cargos</a>.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
