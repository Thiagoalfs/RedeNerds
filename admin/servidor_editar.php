<?php
$paginaAtiva = 'servidores';
$tituloPagina = 'Editar Servidor';

require_once __DIR__ . "/includes/admin_header.php";
require_once "icon_upload.php";
require_once "bg_upload.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: servidores.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM servidores WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$servidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$servidor) {
    header("Location: servidores.php");
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
$iconAtual   = $servidor['icon'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if ($servername === '' || $descricao === '' || $modpackurl === '' || $ip === '') {
        $mensagem_erro = "Preencha todos os campos obrigatórios.";
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $themecolor)) {
        $mensagem_erro = "A cor do tema deve estar no formato hexadecimal.";
    } elseif (empty($features)) {
        $mensagem_erro = "Adicione ao menos uma feature.";
    } elseif ($erro_icone) {
        $mensagem_erro = $erro_icone;
    } else {
        $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("
                UPDATE servidores 
                SET servername = :servername, nome = :nome, title = :title, icon = :icon, 
                    descricao = :descricao, features = :features, modpackurl = :modpackurl, 
                    ip = :ip, themecolor = :themecolor, enabled = :enabled
                WHERE id = :id
            ");
            $stmt->execute([
                ':servername' => $servername,
                ':nome'       => $slugCalculado,
                ':title'      => $servername,
                ':icon'       => $icon,
                ':descricao'  => $descricao,
                ':features'   => $featuresJson,
                ':modpackurl' => $modpackurl,
                ':ip'         => $ip,
                ':themecolor' => $themecolor,
                ':enabled'    => $enabled ? 1 : 0,
                ':id'         => $id
            ]);

            header("Location: servidores.php");
            exit;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao atualizar servidor: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-server text-primary"></i> Editar Servidor: <?php echo htmlspecialchars($servidor['servername'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="servidores.php" class="btn btn-outline-secondary btn-sm">← Voltar para Servidores</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
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
                        <h6 class="fw-bold mb-3">Ícone do Servidor</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="small fw-semibold">Classe FontAwesome (Opcional)</label>
                                <input type="text" class="admin-form-control" id="icon_fa" name="icon_fa"
                                       placeholder="fa-solid fa-server" oninput="atualizarPreviewIcone()">
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-semibold">Upload de Novo Ícone</label>
                                <input type="file" class="admin-form-control" id="icon_upload" name="icon_upload" accept="image/*" onchange="usarUploadIcone(this)">
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-semibold">URL de Imagem Externa</label>
                                <input type="url" class="admin-form-control" id="icon_url" name="icon_url"
                                       placeholder="https://..." oninput="atualizarPreviewIcone()">
                            </div>
                        </div>
                        <div class="mt-3 d-flex align-items-center gap-2">
                            <span class="small text-muted">Ícone Atual / Preview:</span>
                            <div id="icone-preview-box" class="bg-white border rounded d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.25rem;">
                                <?php if (!empty($iconAtual)): ?>
                                    <?php if (tipoDoIcone($iconAtual) === 'img'): ?>
                                        <img src="<?php echo htmlspecialchars($iconAtual, ENT_QUOTES, 'UTF-8'); ?>" class="rounded" width="36" height="36" style="object-fit:cover;" alt="">
                                    <?php else: ?>
                                        <i class="<?php echo htmlspecialchars($iconAtual, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="fa-solid fa-server"></i>
                                <?php endif; ?>
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
                        <a href="servidores.php" class="btn btn-outline-secondary">Cancelar</a>
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

function usarUploadIcone(input) {
    const arquivo = input.files && input.files[0];
    if (!arquivo) return;
    document.getElementById('icon_fa').value = '';
    document.getElementById('icon_url').value = '';
    const box = document.getElementById('icone-preview-box');
    const leitor = new FileReader();
    leitor.onload = (e) => {
        box.innerHTML = `<img src="${e.target.result}" alt="preview" style="width:100%;height:100%;object-fit:cover;" class="rounded">`;
    };
    leitor.readAsDataURL(arquivo);
}

function atualizarPreviewIcone() {
    const fa = document.getElementById('icon_fa').value.trim();
    const url = document.getElementById('icon_url').value.trim();
    const box = document.getElementById('icone-preview-box');
    document.getElementById('icon_upload').value = '';

    if (fa) {
        box.innerHTML = `<i class="${fa}"></i>`;
    } else if (url) {
        box.innerHTML = `<img src="${url}" alt="preview" style="width:100%;height:100%;object-fit:cover;" class="rounded" onerror="this.parentNode.innerHTML='<i class=\\'fa-solid fa-triangle-exclamation text-danger\\'></i>'">`;
    }
}

atualizarPreviewCor();
</script>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>