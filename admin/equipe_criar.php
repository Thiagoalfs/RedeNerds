<?php
require_once "sessao.php";
require_once "../../config.php";

$mensagem_erro = "";
$nick = "";
$cargo = "";

// Sugestões de cargo já usados, pra facilitar (datalist)
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
    } elseif (mb_strlen($nick) > 50) {
        $mensagem_erro = "O nick deve ter no máximo 50 caracteres.";
    } elseif (mb_strlen($cargo) > 100) {
        $mensagem_erro = "O cargo deve ter no máximo 100 caracteres.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO equipe (nick, cargo) VALUES (:nick, :cargo)");
            $stmt->execute([
                ':nick'  => $nick,
                ':cargo' => $cargo,
            ]);
            header("Location: equipe.php");
            exit;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao salvar. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Pessoa - Equipe</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css" rel="stylesheet">
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
                <h4 class="mb-0 h5 h-md-4">➕ Nova Pessoa na Equipe</h4>
            </div>
            <div class="card-body p-3 p-md-4">

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="equipe_criar.php" autocomplete="off">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nick" class="form-label">Nick <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nick" name="nick"
                                value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="50" required
                                oninput="atualizarPreview(this.value)">
                        </div>
                        <div class="col-md-6">
                            <label for="cargo" class="form-label">Cargo / Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="cargo" name="cargo" required onchange="alternarCargoNovo(this.value)">
                                <option value="" disabled <?php echo $cargo === '' ? 'selected' : ''; ?>>Selecione um cargo...</option>
                                <?php foreach ($cargosExistentes as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($cargo === $c) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__novo__" <?php echo (!in_array($cargo, $cargosExistentes, true) && $cargo !== '') ? 'selected' : ''; ?>>➕ Novo cargo...</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="cargo_novo" name="cargo_novo"
                                placeholder="Nome do novo cargo"
                                maxlength="100"
                                value="<?php echo (!in_array($cargo, $cargosExistentes, true) ? htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8') : ''); ?>"
                                style="display: <?php echo (!in_array($cargo, $cargosExistentes, true) && $cargo !== '') ? 'block' : 'none'; ?>;">
                            <small class="text-muted">Escolha um cargo existente para agrupar na mesma categoria, ou crie um novo.</small>
                        </div>
                    </div>

                    <small class="text-muted d-block mb-3">A ordem de exibição é automática: alfabética pelo nick dentro de cada categoria.</small>

                    <div class="mb-4">
                        <img id="autor-preview" class="preview-autor" src="" alt="Preview" style="display:none;" onerror="this.style.display='none'">
                        <div id="autor-placeholder" class="preview-placeholder">🎮 Preview da skin</div>
                    </div>

                    <div class="form-actions mt-2">
                        <a href="equipe.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Adicionar</button>
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
        <?php if ($nick !== ''): ?>
        atualizarPreview(<?php echo json_encode($nick); ?>);
        <?php endif; ?>
    </script>
</body>
</html>
