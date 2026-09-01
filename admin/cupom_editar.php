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

$id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    header("Location: cupons.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cupom = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cupom) {
    header("Location: cupons.php?erro=" . urlencode("Cupom não encontrado."));
    exit;
}

$erro = null;
$codigo = $cupom['codigo'];
$porcentagem = (float)$cupom['porcentagem_desconto'];
$expira_em = date('Y-m-d\TH:i', strtotime($cupom['expira_em']));
$ativo = (int)$cupom['ativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
    $expira_em_post = trim($_POST['expira_em'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);

    if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 50) {
        $erro = "O código do cupom deve ter entre 2 e 50 caracteres.";
    } elseif ($porcentagem <= 0 || $porcentagem > 100) {
        $erro = "A porcentagem de desconto deve ser entre 0.1% e 100%.";
    } elseif (empty($expira_em_post)) {
        $erro = "Informe a data e hora de expiração do cupom.";
    } else {
        $expiraSql = date('Y-m-d H:i:s', strtotime($expira_em_post));

        try {
            // Verifica duplicidade com outro ID
            $check = $pdo->prepare("SELECT id FROM cupons WHERE codigo = :codigo AND id != :id LIMIT 1");
            $check->execute([':codigo' => $codigo, ':id' => $id]);
            if ($check->fetch()) {
                $erro = "Já existe outro cupom cadastrado com o código '{$codigo}'.";
            } else {
                $up = $pdo->prepare("
                    UPDATE cupons 
                    SET codigo = :codigo, porcentagem_desconto = :porcentagem, expira_em = :expira_em, ativo = :ativo 
                    WHERE id = :id
                ");
                $up->execute([
                    ':codigo' => $codigo,
                    ':porcentagem' => $porcentagem,
                    ':expira_em' => $expiraSql,
                    ':ativo' => $ativo,
                    ':id' => $id
                ]);

                header("Location: cupons.php?msg=" . urlencode("Cupom '{$codigo}' atualizado com sucesso!"));
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cupom - Painel Administrativo</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=1" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-size: 15px; }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 24px;
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel</a>
            <div class="d-flex align-items-center gap-2">
                <a href="cupons.php" class="btn btn-outline-light btn-sm">← Voltar para Cupons</a>
            </div>
        </div>
    </nav>

    <div class="container px-3 px-md-4 mt-4 mb-5">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                <h5 class="mb-0 fw-bold">✏️ Editar Cupom: <span class="text-primary"><?php echo htmlspecialchars($cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?></span></h5>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="cupom_editar.php?id=<?php echo $id; ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="mb-3">
                    <label for="codigo" class="form-label fw-semibold">Código do Cupom</label>
                    <input type="text" class="form-control text-uppercase fw-bold" id="codigo" name="codigo" 
                           value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" 
                           maxlength="50" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="porcentagem" class="form-label fw-semibold">Porcentagem de Desconto (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.5" min="0.5" max="100" class="form-control" id="porcentagem" name="porcentagem" 
                                   value="<?php echo htmlspecialchars($porcentagem, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <span class="input-group-text">% OFF</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="expira_em" class="form-label fw-semibold">Expira em (Data e Hora)</label>
                        <input type="datetime-local" class="form-control" id="expira_em" name="expira_em" 
                               value="<?php echo htmlspecialchars($expira_em, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="ativo">Cupom Ativo para uso</label>
                </div>

                <div class="p-3 bg-light rounded border mb-4 text-muted small">
                    <div><strong>📊 Total de Usos Realizados:</strong> <?php echo (int)$cupom['usos_total']; ?> compra(s)</div>
                    <div><strong>📅 Data de Cadastro:</strong> <?php echo date('d/m/Y H:i', strtotime($cupom['criado_em'])); ?></div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="cupons.php" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>