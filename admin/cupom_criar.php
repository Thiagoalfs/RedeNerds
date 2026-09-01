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

$erro = null;
$codigo = '';
$porcentagem = 10.0;
$expira_em = date('Y-m-d\TH:i', strtotime('+30 days'));
$ativo = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
    $expira_em = trim($_POST['expira_em'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    // Remove caracteres especiais do código
    $codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);

    if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 50) {
        $erro = "O código do cupom deve ter entre 2 e 50 caracteres (apenas letras, números, hífens ou underlines).";
    } elseif ($porcentagem <= 0 || $porcentagem > 100) {
        $erro = "A porcentagem de desconto deve ser entre 0.1% e 100%.";
    } elseif (empty($expira_em)) {
        $erro = "Informe a data e hora de expiração do cupom.";
    } else {
        $expiraSql = date('Y-m-d H:i:s', strtotime($expira_em));

        try {
            // Verifica se já existe
            $check = $pdo->prepare("SELECT id FROM cupons WHERE codigo = :codigo LIMIT 1");
            $check->execute([':codigo' => $codigo]);
            if ($check->fetch()) {
                $erro = "Já existe um cupom cadastrado com o código '{$codigo}'.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO cupons (codigo, porcentagem_desconto, expira_em, ativo, usos_total, criado_em)
                    VALUES (:codigo, :porcentagem, :expira_em, :ativo, 0, NOW())
                ");
                $stmt->execute([
                    ':codigo' => $codigo,
                    ':porcentagem' => $porcentagem,
                    ':expira_em' => $expiraSql,
                    ':ativo' => $ativo
                ]);

                header("Location: cupons.php?msg=" . urlencode("Cupom '{$codigo}' criado com sucesso!"));
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cupom - Painel Administrativo</title>
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
                <h5 class="mb-0 fw-bold">➕ Criar Novo Cupom de Desconto</h5>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="cupom_criar.php">
                <div class="mb-3">
                    <label for="codigo" class="form-label fw-semibold">Código do Cupom (Ex: NERD10, PROMO20)</label>
                    <input type="text" class="form-control text-uppercase fw-bold" id="codigo" name="codigo" 
                           value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="EX: NERD10" maxlength="50" required autofocus>
                    <div class="form-text">Será convertido automaticamente em letras maiúsculas.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="porcentagem" class="form-label fw-semibold">Porcentagem de Desconto (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.5" min="0.5" max="100" class="form-control" id="porcentagem" name="porcentagem" 
                                   value="<?php echo htmlspecialchars($porcentagem, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <span class="input-group-text">% OFF</span>
                        </div>
                        <div class="form-text">Ex: 10 para 10% de desconto.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="expira_em" class="form-label fw-semibold">Expira em (Data e Hora)</label>
                        <input type="datetime-local" class="form-control" id="expira_em" name="expira_em" 
                               value="<?php echo htmlspecialchars($expira_em, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <div class="form-text">O cupom desativa automaticamente após esta data.</div>
                    </div>
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="ativo">Cupom Ativo para uso imediato</label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="cupons.php" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success fw-bold px-4">Salvar Cupom</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>