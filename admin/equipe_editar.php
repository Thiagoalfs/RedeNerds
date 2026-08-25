<?php
require_once "sessao.php";
$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if (!$configPath) {
    die("Erro: Arquivo config.php não encontrado.");
}
require_once $configPath;

$mensagem_erro = "";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: equipe.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nick, cargo FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        header("Location: equipe.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: equipe.php");
    exit;
}

try {
    $cargosExistentes = $pdo->query("SELECT DISTINCT cargo FROM equipe ORDER BY cargo ASC")->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    $cargosExistentes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick        = trim($_POST['nick'] ?? '');
    $cargoSelect = trim($_POST['cargo'] ?? '');
    $cargo       = $cargoSelect === '__novo__' ? trim($_POST['cargo_novo'] ?? '') : $cargoSelect;

    if ($nick === '' || $cargo === '') {
        $mensagem_erro = "Preencha o nick e o cargo.";
        $membro['nick'] = $nick;
        $membro['cargo'] = $cargo;
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE equipe SET nick = :nick, cargo = :cargo WHERE id = :id");
            $stmt->execute([
                ':nick'  => $nick,
                ':cargo' => $cargo,
                ':id'    => $id,
            ]);
            header("Location: equipe.php");
            exit;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao atualizar. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pessoa - Equipe</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=2" rel="stylesheet">
    <style>
        .card { max-width: 80%; margin: 0 auto; }
        .preview-autor {
            width: auto;
            max-height: 140px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            display: block;
            margin: 0 auto;
        }
        .preview-placeholder {
            width: 100%;
            height: 140px;
            background: #f0f2f5;
            border: 2px dashed #ced4da;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel</a>
            <div class="d-flex">
                <a href="equipe.php" class="btn btn-outline-light btn-sm">← Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 my-3 my-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white p-3 p-md-4">
                <h4 class="mb-0 h5 h-md-4">✏️ Editar Pessoa #<?php echo (int)$membro['id']; ?></h4>
            </div>
            <div class="card-body p-3 p-md-4">

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="equipe_editar.php?id=<?php echo (int)$membro['id']; ?>" autocomplete="off">
                    <input type="hidden" name="id" value="<?php echo (int)$membro['id']; ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nick" class="form-label">Nick <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nick" name="nick"
                                value="<?php echo htmlspecialchars($membro['nick'], ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="50" required
                                oninput="atualizarPreview(this.value)">
                        </div>
                        <div class="col-md-6">
                            <label for="cargo" class="form-label">Cargo / Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="cargo" name="cargo" required onchange="alternarCargoNovo(this.value)">
                                <?php foreach ($cargosExistentes as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($membro['cargo'] === $c) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__novo__" <?php echo (!in_array($membro['cargo'], $cargosExistentes, true)) ? 'selected' : ''; ?>>➕ Novo cargo...</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="cargo_novo" name="cargo_novo"
                                placeholder="Nome do novo cargo"
                                maxlength="100"
                                value="<?php echo (!in_array($membro['cargo'], $cargosExistentes, true) ? htmlspecialchars($membro['cargo'], ENT_QUOTES, 'UTF-8') : ''); ?>"
                                style="display: <?php echo (!in_array($membro['cargo'], $cargosExistentes, true)) ? 'block' : 'none'; ?>;">
                        </div>
                    </div>

                    <small class="text-muted d-block mb-3">A ordem de exibição é automática: alfabética pelo nick dentro de cada categoria.</small>

                    <div class="mb-4">
                        <img id="autor-preview" class="preview-autor"
                            src="<?php echo !empty($membro['nick']) ? 'https://mc-heads.net/avatar/' . htmlspecialchars($membro['nick'], ENT_QUOTES, 'UTF-8') . '/100' : ''; ?>"
                            alt="Preview"
                            style="display: <?php echo !empty($membro['nick']) ? 'block' : 'none'; ?>;"
                            onerror="this.style.display='none'">
                        <div id="autor-placeholder" class="preview-placeholder"
                            style="display: <?php echo !empty($membro['nick']) ? 'none' : 'flex'; ?>;">
                            🎮 Preview da skin
                        </div>
                    </div>

                    <div class="form-actions mt-2">
                        <a href="equipe.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function alternarCargoNovo(valor) {
            const campoNovo = document.getElementById('cargo_novo');
            if (valor === '__novo__') {
                campoNovo.style.display = 'block';
                campoNovo.required = true;
                campoNovo.focus();
            } else {
                campoNovo.style.display = 'none';
                campoNovo.required = false;
                campoNovo.value = '';
            }
        }

        function atualizarPreview(nick) {
            const img = document.getElementById('autor-preview');
            const placeholder = document.getElementById('autor-placeholder');
            if (nick) {
                img.src = 'https://mc-heads.net/avatar/' + nick + '/100';
                img.style.display = 'block';
                img.onerror = () => { img.style.display = 'none'; placeholder.style.display = 'flex'; };
                placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        }
    </script>
</body>
</html>
