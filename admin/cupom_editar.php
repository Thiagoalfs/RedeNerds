<?php
$paginaAtiva = 'cupons';
$tituloPagina = 'Editar Cupom';
require_once __DIR__ . "/includes/admin_header.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: cupons.php?erro=Cupom não encontrado.");
    exit;
}

$erro = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $cupom = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cupom) {
        header("Location: cupons.php?erro=Cupom não encontrado.");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar cupom.");
}

$codigo = $cupom['codigo'];
$porcentagem = (float)$cupom['porcentagem_desconto'];
$expira_em = date('Y-m-d\TH:i', strtotime($cupom['expira_em']));
$ativo = (int)$cupom['ativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
    $expira_em = trim($_POST['expira_em'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);

    if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 50) {
        $erro = "O código do cupom deve ter entre 2 e 50 caracteres.";
    } elseif ($porcentagem <= 0 || $porcentagem > 100) {
        $erro = "A porcentagem de desconto deve ser entre 0.1% e 100%.";
    } elseif (empty($expira_em)) {
        $erro = "Informe a data e hora de expiração do cupom.";
    } else {
        $expiraSql = date('Y-m-d H:i:s', strtotime($expira_em));

        try {
            $check = $pdo->prepare("SELECT id FROM cupons WHERE codigo = :codigo AND id != :id LIMIT 1");
            $check->execute([':codigo' => $codigo, ':id' => $id]);
            if ($check->fetch()) {
                $erro = "Já existe outro cupom cadastrado com o código '{$codigo}'.";
            } else {
                $upd = $pdo->prepare("
                    UPDATE cupons 
                    SET codigo = :codigo, porcentagem_desconto = :porcentagem, expira_em = :expira_em, ativo = :ativo
                    WHERE id = :id
                ");
                $upd->execute([
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
            $erro = "Erro ao atualizar cupom: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-pen text-primary"></i> Editar Cupom: <?php echo htmlspecialchars($cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="cupons.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="cupom_editar.php?id=<?php echo (int)$id; ?>">
                    <div class="admin-form-group">
                        <label for="codigo">Código do Cupom</label>
                        <input type="text" class="admin-form-control text-uppercase fw-bold" id="codigo" name="codigo" 
                               value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="porcentagem">Porcentagem de Desconto (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0.5" max="100" class="form-control" id="porcentagem" name="porcentagem" 
                                       value="<?php echo htmlspecialchars($porcentagem, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <span class="input-group-text">% OFF</span>
                            </div>
                        </div>

                        <div class="col-md-6 admin-form-group">
                            <label for="expira_em">Expira em (Data e Hora)</label>
                            <input type="datetime-local" class="admin-form-control" id="expira_em" name="expira_em" 
                                   value="<?php echo htmlspecialchars($expira_em, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="ativo">Cupom Ativo</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="cupons.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>