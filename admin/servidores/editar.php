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

require_once __DIR__ . "/icon_upload.php";
require_once __DIR__ . "/bg_upload.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM servidores WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$servidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$servidor) {
    header("Location: index.php");
    exit;
}

$mensagem_erro = "";
$servername  = $servidor['servername'];
$descricao   = $servidor['descricao'];
$features    = json_decode($servidor['features'] ?? '[]', true) ?: [""];
$modpackurl  = $servidor['modpackurl'];
$ip          = $servidor['ip'];
$themecolor  = $servidor['themecolor'];
$enabled     = (bool)$servidor['enabled'];
$iconAtual   = $servidor['icon'] ?? null;
$bgAtual     = $servidor['bg_image'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $mensagem_erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $servername = trim($_POST['servername'] ?? '');
        $descricao  = trim($_POST['descricao'] ?? '');
        $modpackurl = trim($_POST['modpackurl'] ?? '');
        $ip         = trim($_POST['ip'] ?? '');
        $themecolor = trim($_POST['themecolor'] ?? '#B971DA');
        $enabled    = isset($_POST['enabled']);

        $slugCalculado = preg_replace('/[^a-z0-9]/', '', strtolower($servername));

        $featuresPost = $_POST['features'] ?? [];
        $features = array_values(array_filter(array_map('trim', $featuresPost), fn($f) => $f !== ''));

        [$icon, $erro_icone] = processarIcone($iconAtual);
        if (empty($icon)) {
            $icon = $iconAtual ?: '/assets/images/logo.webp';
        }

        [$bg_image, $erro_bg] = processarBgServidor($servidor['nome'] ?? $slugCalculado, $bgAtual);

        if ($servername === '' || $descricao === '' || $modpackurl === '' || $ip === '') {
            $mensagem_erro = "Preencha todos os campos obrigatórios.";
        } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $themecolor)) {
            $mensagem_erro = "A cor do tema deve estar no formato hexadecimal.";
        } elseif (empty($features)) {
            $mensagem_erro = "Adicione ao menos uma feature.";
        } elseif ($erro_icone) {
            $mensagem_erro = $erro_icone;
        } elseif ($erro_bg) {
            $mensagem_erro = $erro_bg;
        } else {
            $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE);

            try {
                $stmt = $pdo->prepare("
                    UPDATE servidores 
                    SET servername = :servername, icon = :icon, bg_image = :bg_image, 
                        descricao = :descricao, features = :features, modpackurl = :modpackurl, 
                        ip = :ip, themecolor = :themecolor, enabled = :enabled
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':servername' => $servername,
                    ':icon'       => $icon,
                    ':bg_image'   => $bg_image,
                    ':descricao'  => $descricao,
                    ':features'   => $featuresJson,
                    ':modpackurl' => $modpackurl,
                    ':ip'         => $ip,
                    ':themecolor' => $themecolor,
                    ':enabled'    => $enabled ? 1 : 0,
                    ':id'         => $id
                ]);

                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao atualizar servidor: " . $e->getMessage();
            }
        }
    }
}

$paginaAtiva = 'servidores';
$tituloPagina = 'Editar Servidor';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-server text-primary"></i> Editar Servidor: <?php echo htmlspecialchars($servidor['servername'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar para Servidores</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="servername">Nome do Servidor *</label>
                            <input type="text" class="admin-form-control" id="servername" name="servername"
                                   value="<?php echo htmlspecialchars($servername, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6 admin-form-group">
                            <label for="ip">IP de Conexão *</label>
                            <input type="text" class="admin-form-control" id="ip" name="ip"
                                   value="<?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="admin-form-group mb-3">
                        <label for="descricao">Descrição *</label>
                        <textarea class="admin-form-control" id="descricao" name="descricao" rows="3" required><?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8 admin-form-group">
                            <label for="modpackurl">URL do Modpack / Launcher *</label>
                            <input type="url" class="admin-form-control" id="modpackurl" name="modpackurl"
                                   value="<?php echo htmlspecialchars($modpackurl, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="themecolor">Cor do Tema *</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="themecolor_picker"
                                       value="<?php echo htmlspecialchars($themecolor, ENT_QUOTES, 'UTF-8'); ?>"
                                       oninput="document.getElementById('themecolor').value = this.value; atualizarPreviewCor();">
                                <input type="text" class="admin-form-control font-monospace" id="themecolor" name="themecolor"
                                       value="<?php echo htmlspecialchars($themecolor, ENT_QUOTES, 'UTF-8'); ?>"
                                       maxlength="7" oninput="document.getElementById('themecolor_picker').value = this.value; atualizarPreviewCor();">
                            </div>
                        </div>
                    </div>

                    <!-- ÍCONE -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <h6 class="fw-bold mb-2"><i class="fa-solid fa-icons me-1 text-primary"></i> Ícone do Servidor</h6>
                        <p class="small text-muted mb-3">Envie uma imagem para substituir o ícone atual (PNG, JPG, WEBP ou GIF). Deixe em branco para manter o ícone atual.</p>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-9">
                                <label class="small fw-semibold">Upload de Novo Ícone</label>
                                <input type="file" class="admin-form-control" id="icon_upload" name="icon_upload" accept="image/*" onchange="previewIcone(this)">
                            </div>
                            <div class="col-md-3 d-flex align-items-center gap-2">
                                <span class="small text-muted">Atual / Preview:</span>
                                <div id="icone-preview-box" class="bg-white border rounded d-flex align-items-center justify-content-center overflow-hidden" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <?php if (!empty($iconAtual)): ?>
                                        <img src="<?php echo htmlspecialchars($iconAtual, ENT_QUOTES, 'UTF-8'); ?>" class="rounded" width="48" height="48" style="object-fit:cover;" alt="Ícone">
                                    <?php else: ?>
                                        <i class="fa-solid fa-server text-muted"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BACKGROUND / WALLPAPER DO SERVIDOR -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <h6 class="fw-bold mb-2"><i class="fa-solid fa-image me-1 text-primary"></i> Background do Servidor (Wallpaper)</h6>
                        <p class="small text-muted mb-3">Imagem de fundo exibida no site para este servidor (como no servidor Nerd Dead). Recomendado: 1920x1080 em JPG, PNG ou WEBP.</p>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-8">
                                <label class="small fw-semibold">Upload de Nova Imagem de Fundo</label>
                                <input type="file" class="admin-form-control" id="bg_upload" name="bg_upload" accept="image/*" onchange="previewBg(this)">
                                <?php if (!empty($bgAtual)): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remover_bg" name="remover_bg" value="1">
                                        <label class="form-check-label small text-danger fw-semibold" for="remover_bg">
                                            Remover imagem de fundo atual
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <div id="bg-preview-box" class="bg-dark border rounded overflow-hidden d-flex align-items-center justify-content-center text-muted small position-relative" style="height: 75px; width: 100%;">
                                    <?php if (!empty($bgAtual)): ?>
                                        <img src="<?php echo htmlspecialchars($bgAtual, ENT_QUOTES, 'UTF-8'); ?>" alt="Fundo Atual" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <span class="text-white-50 small">Nenhum fundo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FEATURES -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0">Destaques / Features</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarFeature()">+ Adicionar Feature</button>
                        </div>
                        <div id="features-container">
                            <?php foreach ($features as $feat): ?>
                                <div class="input-group mb-2 feature-row">
                                    <input type="text" class="form-control" name="features[]" value="<?php echo htmlspecialchars($feat, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Economia balanceada">
                                    <button type="button" class="btn btn-outline-danger" onclick="removerFeature(this)"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="enabled">Servidor Ativo e Visível no Site</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function adicionarFeature() {
    const container = document.getElementById('features-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 feature-row';
    div.innerHTML = '<input type="text" class="form-control" name="features[]" placeholder="Ex: Novo recurso"><button type="button" class="btn btn-outline-danger" onclick="removerFeature(this)"><i class="fa-solid fa-xmark"></i></button>';
    container.appendChild(div);
}

function removerFeature(btn) {
    const rows = document.querySelectorAll('.feature-row');
    if (rows.length > 1) {
        btn.closest('.feature-row').remove();
    }
}

function atualizarPreviewCor() {
    const cor = document.getElementById('themecolor').value;
    const box = document.getElementById('icone-preview-box');
    if (box) box.style.color = cor;
}

function previewIcone(input) {
    const arquivo = input.files && input.files[0];
    const box = document.getElementById('icone-preview-box');
    if (!arquivo) return;
    const leitor = new FileReader();
    leitor.onload = (e) => {
        box.innerHTML = `<img src="${e.target.result}" alt="preview" style="width:100%;height:100%;object-fit:cover;" class="rounded">`;
    };
    leitor.readAsDataURL(arquivo);
}

function previewBg(input) {
    const arquivo = input.files && input.files[0];
    const box = document.getElementById('bg-preview-box');
    if (!arquivo) return;
    const leitor = new FileReader();
    leitor.onload = (e) => {
        box.innerHTML = `<img src="${e.target.result}" alt="preview" style="width:100%;height:100%;object-fit:cover;">`;
    };
    leitor.readAsDataURL(arquivo);
}

atualizarPreviewCor();
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>