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

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM vips WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$vip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vip) {
    header("Location: index.php?erro=" . urlencode("Pacote VIP não encontrado."));
    exit;
}

$servidores = [];
$servidoresMap = [];
try {
    $stmtSrv = $pdo->query("SELECT id, servername, nome FROM servidores ORDER BY servername ASC");
    $servidores = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
    foreach ($servidores as $s) {
        $servidoresMap[$s['id']] = $s;
        $servidoresMap[$s['servername']] = $s;
        if (!empty($s['nome'])) {
            $servidoresMap[$s['nome']] = $s;
        }
    }
} catch (PDOException $e) {
    $servidores = [];
}

$mensagem_erro = "";

$servidor_id_selecionado = (int)($vip['servidor_id'] ?? 0);
if ($servidor_id_selecionado <= 0 && !empty($vip['servidor'])) {
    if (isset($servidoresMap[$vip['servidor']])) {
        $servidor_id_selecionado = (int)$servidoresMap[$vip['servidor']]['id'];
    }
}

$nome = $vip['nome'];
$preco = number_format((float)$vip['preco'], 2, '.', '');
$duracao_dias = (int)($vip['duracao_dias'] ?? 30);
$destaque = !empty($vip['destaque']);
$ativo = !empty($vip['ativo']);

$vantagens = [];
if (!empty($vip['vantagens'])) {
    $json = json_decode($vip['vantagens'], true);
    if (is_array($json)) {
        $vantagens = $json;
    } else {
        $vantagens = array_values(array_filter(array_map('trim', explode("\n", $vip['vantagens']))));
    }
}
if (empty($vantagens)) {
    $vantagens = [""];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $mensagem_erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $servidor_id_selecionado = (int)($_POST['servidor_id'] ?? ($_POST['servidor'] ?? 0));
        $servidorObj             = $servidoresMap[$servidor_id_selecionado] ?? null;
        $nome                    = trim($_POST['nome'] ?? '');
        $precoRaw                = str_replace(',', '.', trim($_POST['preco'] ?? '0'));
        $preco                   = (float)$precoRaw;
        $duracao_dias            = (int)($_POST['duracao_dias'] ?? 30);
        $destaque                = isset($_POST['destaque']) ? 1 : 0;
        $ativo                   = isset($_POST['ativo']) ? 1 : 0;

        $vantagensPost = $_POST['vantagens'] ?? [];
        $vantagens = array_values(array_filter(array_map('trim', $vantagensPost), fn($v) => $v !== ''));

        if (!$servidorObj || $servidor_id_selecionado <= 0) {
            $mensagem_erro = "Selecione um servidor válido para este pacote VIP.";
        } elseif ($nome === '' || strlen($nome) < 2) {
            $mensagem_erro = "Informe um nome válido para o pacote VIP (ex: VIP Ouro).";
        } elseif ($preco <= 0) {
            $mensagem_erro = "O preço do VIP deve ser maior que zero.";
        } elseif (empty($vantagens)) {
            $mensagem_erro = "Adicione ao menos um benefício (bullet point) para o pacote VIP.";
        } else {
            $vantagensJson = json_encode($vantagens, JSON_UNESCAPED_UNICODE);
            $servidorNome = $servidorObj['servername'];

            try {
                $stmt = $pdo->prepare("
                    UPDATE vips 
                    SET servidor_id = :servidor_id, servidor = :servidor, nome = :nome, preco = :preco, 
                        duracao_dias = :duracao_dias, destaque = :destaque, 
                        vantagens = :vantagens, ativo = :ativo
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':servidor_id'  => $servidor_id_selecionado,
                    ':servidor'     => $servidorNome,
                    ':nome'         => $nome,
                    ':preco'        => $preco,
                    ':duracao_dias' => $duracao_dias > 0 ? $duracao_dias : 30,
                    ':destaque'     => $destaque,
                    ':vantagens'    => $vantagensJson,
                    ':ativo'        => $ativo,
                    ':id'           => $id
                ]);

                header("Location: index.php?msg=" . urlencode("Pacote VIP '{$nome}' atualizado com sucesso!"));
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao atualizar pacote VIP: " . $e->getMessage();
            }
        }
    }
}

$paginaAtiva = 'vips';
$tituloPagina = 'Editar Pacote VIP';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-gem text-primary"></i> Editar Pacote VIP: <?php echo htmlspecialchars($vip['nome'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar para Pacotes VIP</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="servidor_id">Servidor *</label>
                            <select class="admin-form-control" id="servidor_id" name="servidor_id" required autofocus>
                                <option value="">Selecione o servidor...</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ($servidor_id_selecionado === (int)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 admin-form-group">
                            <label for="nome">Nome do Pacote VIP *</label>
                            <input type="text" class="admin-form-control" id="nome" name="nome"
                                   value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Ex: VIP Netherite" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 admin-form-group">
                            <label for="preco">Preço (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="preco" name="preco"
                                       value="<?php echo htmlspecialchars((string)$preco, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="duracao_dias">Duração (em dias)</label>
                            <input type="number" min="1" max="3650" class="admin-form-control" id="duracao_dias" name="duracao_dias"
                                   value="<?php echo (int)$duracao_dias; ?>" placeholder="30">
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label>Package ID Gerado</label>
                            <input type="text" class="admin-form-control font-monospace text-muted bg-light"
                                   value="<?php echo htmlspecialchars($vip['packageId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                        </div>
                    </div>

                    <!-- BENEFÍCIOS (BULLET POINTS) -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-primary me-1"></i> Benefícios do VIP (Bullet Points)</h6>
                                <p class="small text-muted mb-0">Cada item abaixo é exibido como um bullet point no card do VIP na loja.</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarBeneficio()">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar Benefício
                            </button>
                        </div>
                        <div id="vantagens-container" class="mt-3">
                            <?php foreach ($vantagens as $v): ?>
                                <div class="input-group mb-2 vantagem-row">
                                    <span class="input-group-text bg-white text-success border-end-0"><i class="fa-solid fa-check"></i></span>
                                    <input type="text" class="form-control border-start-0" name="vantagens[]" 
                                           value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>" 
                                           placeholder="Ex: Tag [NETHERITE] suprema no chat e tablist">
                                    <button type="button" class="btn btn-outline-danger" onclick="removerBeneficio(this)" title="Remover benefício">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch p-3 bg-light border rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="destaque" name="destaque" value="1" <?php echo $destaque ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="destaque">
                                    <i class="fa-solid fa-star text-warning me-1"></i> Destacar na Loja (Mais Popular / Mais Vendido)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch p-3 bg-light border rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="ativo">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i> Pacote VIP Ativo e Disponível para Compra
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function adicionarBeneficio() {
    const container = document.getElementById('vantagens-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 vantagem-row';
    div.innerHTML = `
        <span class="input-group-text bg-white text-success border-end-0"><i class="fa-solid fa-check"></i></span>
        <input type="text" class="form-control border-start-0" name="vantagens[]" placeholder="Ex: Acesso ao Sistema de Cosméticos">
        <button type="button" class="btn btn-outline-danger" onclick="removerBeneficio(this)" title="Remover benefício">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    container.appendChild(div);
    const input = div.querySelector('input');
    if (input) input.focus();
}

function removerBeneficio(btn) {
    const rows = document.querySelectorAll('.vantagem-row');
    if (rows.length > 1) {
        btn.closest('.vantagem-row').remove();
    } else {
        btn.closest('.vantagem-row').querySelector('input').value = '';
    }
}
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>