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

require_once __DIR__ . "/chave_upload.php";

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

$servidor_id_selecionado = 0;
$servidor_param = trim($_GET['servidor'] ?? ($_GET['servidor_id'] ?? ''));
if ($servidor_param !== '') {
    if (is_numeric($servidor_param)) {
        $servidor_id_selecionado = (int)$servidor_param;
    } elseif (isset($servidoresMap[$servidor_param])) {
        $servidor_id_selecionado = (int)$servidoresMap[$servidor_param]['id'];
    }
}

$nome = "";
$packageId = "";
$imagem = "";
$preco = "4.90";
$destaque = false;
$ativo = true;
$vantagens = ["Abertura na Caixa Mítica do Spawn", "Chance de itens Raros e Lendários"];
$cor1 = "#FFD700";
$cor2 = "#FFA500";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $mensagem_erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $servidor_id_selecionado = (int)($_POST['servidor_id'] ?? ($_POST['servidor'] ?? 0));
        $servidorObj             = $servidoresMap[$servidor_id_selecionado] ?? null;
        $nome                    = trim($_POST['nome'] ?? '');
        $packageId               = trim($_POST['packageId'] ?? '');
        $precoRaw                = str_replace(',', '.', trim($_POST['preco'] ?? '0'));
        $preco                   = (float)$precoRaw;
        $destaque                = isset($_POST['destaque']) ? 1 : 0;
        $ativo                   = isset($_POST['ativo']) ? 1 : 0;
        $cor1                    = trim($_POST['cor1'] ?? '#FFD700');
        $cor2                    = trim($_POST['cor2'] ?? '#FFA500');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor1)) $cor1 = '#FFD700';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor2)) $cor2 = '#FFA500';

        [$imagem, $erro_imagem] = processarImagemChave(null);

        $vantagensPost = $_POST['vantagens'] ?? [];
        $vantagens = array_values(array_filter(array_map('trim', $vantagensPost), fn($v) => $v !== ''));

        if ($erro_imagem) {
            $mensagem_erro = $erro_imagem;
        } elseif (!$servidorObj || $servidor_id_selecionado <= 0) {
            $mensagem_erro = "Selecione um servidor válido para este pacote de chaves.";
        } elseif ($nome === '' || strlen($nome) < 2) {
            $mensagem_erro = "Informe um nome válido para o pacote de chaves (ex: Chave Mítica).";
        } elseif ($packageId === '') {
            $mensagem_erro = "Informe o Package ID correspondente no plugin de entrega do Minecraft.";
        } elseif ($preco <= 0) {
            $mensagem_erro = "O preço unitário da chave deve ser maior que zero.";
        } else {
            $vantagensJson = !empty($vantagens) ? json_encode($vantagens, JSON_UNESCAPED_UNICODE) : null;
            $servidorNome = $servidorObj['servername'] ?? 'Servidor';

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO chaves (servidor_id, servidor, nome, imagem, packageId, preco, destaque, vantagens, ativo, cor1, cor2)
                    VALUES (:servidor_id, :servidor, :nome, :imagem, :packageId, :preco, :destaque, :vantagens, :ativo, :cor1, :cor2)
                ");
                $stmt->execute([
                    ':servidor_id' => $servidor_id_selecionado,
                    ':servidor'    => $servidorNome,
                    ':nome'        => $nome,
                    ':imagem'      => !empty($imagem) ? $imagem : null,
                    ':packageId'   => $packageId,
                    ':preco'       => $preco,
                    ':destaque'    => $destaque,
                    ':vantagens'   => $vantagensJson,
                    ':ativo'       => $ativo,
                    ':cor1'        => $cor1,
                    ':cor2'        => $cor2,
                ]);

                header("Location: index.php?msg=" . urlencode("Pacote de Chaves '{$nome}' cadastrado com sucesso!"));
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao cadastrar pacote de chaves: " . $e->getMessage();
            }
        }
    }
}

$paginaAtiva = 'chaves';
$tituloPagina = 'Novo Pacote de Chaves';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-key text-warning"></i> Cadastrar Novo Pacote de Chaves</h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar para Pacotes de Chaves</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 admin-form-group">
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
                        <div class="col-md-5 admin-form-group">
                            <label for="nome">Nome do Pacote de Chaves *</label>
                            <input type="text" class="admin-form-control" id="nome" name="nome"
                                   value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Ex: Chave Mítica" required>
                        </div>
                        <div class="col-md-3 admin-form-group">
                            <label for="packageId">Package ID (Plugin) *</label>
                            <input type="text" class="admin-form-control font-monospace" id="packageId" name="packageId"
                                   value="<?php echo htmlspecialchars($packageId, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Ex: potato_chave_mitica" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="preco">Preço Unitário (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="admin-form-control" id="preco" name="preco"
                                       value="<?php echo htmlspecialchars(number_format((float)$preco, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <small class="text-muted">Preço por 1 unidade da chave. O comprador poderá selecionar a quantidade na loja.</small>
                        </div>
                        <div class="col-md-6 admin-form-group">
                            <label class="mb-1">Degradê do Título</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="cor1" name="cor1" value="<?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>" title="Cor Inicial">
                                <input type="color" class="form-control form-control-color" id="cor2" name="cor2" value="<?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>" title="Cor Final">
                                <span class="badge border" id="preview-gradient-badge" style="background: linear-gradient(135deg, <?php echo $cor1; ?>, <?php echo $cor2; ?>); color: #000; font-weight: 700; padding: 6px 12px;">Prévia</span>
                            </div>
                        </div>
                    </div>

                    <!-- IMAGEM / RENDER 3D DO PACOTE DE CHAVES (key-card-image-box) -->
                    <div class="admin-form-group mb-3">
                        <label class="form-label fw-bold mb-1"><i class="fa-solid fa-image me-1 text-primary"></i> Imagem do Item (Topo do Card)</label>
                        <div class="form-text small mb-2">Envie uma imagem com fundo transparente (PNG, WEBP ou GIF). Ela será exibida no topo do card da chave.</div>
                        
                        <div class="row g-3 align-items-center">
                            <div class="col-md-9">
                                <label class="small fw-semibold mb-1">Upload de Arquivo</label>
                                <input type="file" class="admin-form-control" id="imagem_upload" name="imagem_upload" accept="image/*" onchange="previewImagemChave(this)">
                            </div>
                            <div class="col-md-3 d-flex align-items-center gap-2">
                                <span class="small text-muted">Prévia:</span>
                                <div id="chave-preview-box" class="bg-dark border rounded d-flex align-items-center justify-content-center overflow-hidden" style="width: 50px; height: 50px;">
                                    <img id="chave-preview-img" src="<?php echo !empty($imagem) ? htmlspecialchars($imagem, ENT_QUOTES, 'UTF-8') : '/assets/images/logo.webp'; ?>" style="max-width: 42px; max-height: 42px; object-fit: contain; <?php echo empty($imagem) ? 'opacity: 0.35;' : ''; ?>" alt="Prévia">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RECOMPENSAS / DESCRIÇÃO -->
                    <div class="admin-form-group mb-3">
                        <label class="d-flex align-items-center justify-content-between mb-2">
                            <span>Recompensas / Vantagens da Caixa</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarVantagem()">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar Item
                            </button>
                        </label>
                        <div id="container-vantagens" class="d-flex flex-column gap-2">
                            <?php foreach ($vantagens as $idx => $v): ?>
                                <div class="input-group input-group-sm vantagem-item">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-gift"></i></span>
                                    <input type="text" class="admin-form-control" name="vantagens[]" value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Espada Lendária de Fogo" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removerVantagem(this)" title="Remover"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="destaque" name="destaque" value="1" <?php echo $destaque ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="destaque">
                                    <i class="fa-solid fa-star text-warning me-1"></i> Destacar pacote de chaves na loja
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="ativo">
                                    <i class="fa-solid fa-eye text-success me-1"></i> Pacote visível e ativo para compras
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-1"></i> Salvar Pacote de Chaves</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewImagemChave(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('chave-preview-img');
            if (img) {
                img.src = e.target.result;
                img.style.opacity = '1';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function atualizarPrevia() {
    const c1 = document.getElementById('cor1').value;
    const c2 = document.getElementById('cor2').value;
    const badge = document.getElementById('preview-gradient-badge');
    if (badge) {
        badge.style.background = `linear-gradient(135deg, ${c1}, ${c2})`;
    }
}
document.getElementById('cor1').addEventListener('input', atualizarPrevia);
document.getElementById('cor2').addEventListener('input', atualizarPrevia);

function adicionarVantagem() {
    const container = document.getElementById('container-vantagens');
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm vantagem-item';
    div.innerHTML = `
        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-gift"></i></span>
        <input type="text" class="admin-form-control" name="vantagens[]" placeholder="Ex: Armadura Mítica Completa" required>
        <button type="button" class="btn btn-outline-danger" onclick="removerVantagem(this)" title="Remover"><i class="fa-solid fa-trash"></i></button>
    `;
    container.appendChild(div);
    div.querySelector('input').focus();
}

function removerVantagem(btn) {
    const itens = document.querySelectorAll('.vantagem-item');
    if (itens.length > 1) {
        btn.closest('.vantagem-item').remove();
    } else {
        btn.closest('.vantagem-item').querySelector('input').value = '';
    }
}
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
