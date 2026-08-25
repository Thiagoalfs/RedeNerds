<?php
require_once "sessao.php";
require_once "../../config.php";
require_once "icon_upload.php";

$mensagem_sucesso = "";
$mensagem_erro = "";

$servername  = "";
$descricao   = "";
$features    = [""];
$modpackurl  = "";
$ip          = "";
$themecolor  = "#B971DA";
$enabled     = true;
$icon_fa     = "";
$icon_url    = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = trim($_POST['servername'] ?? '');
    $descricao  = trim($_POST['descricao'] ?? '');
    $modpackurl = trim($_POST['modpackurl'] ?? '');
    $ip         = trim($_POST['ip'] ?? '');
    $themecolor = trim($_POST['themecolor'] ?? '#B971DA');
    $enabled    = isset($_POST['enabled']);
    $icon_fa    = trim($_POST['icon_fa'] ?? '');
    $icon_url   = trim($_POST['icon_url'] ?? '');

    $featuresPost = $_POST['features'] ?? [];
    $features = array_values(array_filter(array_map('trim', $featuresPost), fn($f) => $f !== ''));

    [$icon, $erro_icone] = processarIcone(null);

    if ($servername === '' || $descricao === '' || $modpackurl === '' || $ip === '') {
        $mensagem_erro = "Preencha todos os campos obrigatórios.";
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $themecolor)) {
        $mensagem_erro = "A cor do tema deve estar no formato hexadecimal, ex: #B971DA.";
    } elseif (empty($features)) {
        $mensagem_erro = "Adicione ao menos uma feature.";
    } elseif ($erro_icone) {
        $mensagem_erro = $erro_icone;
    } elseif (empty($icon)) {
        $mensagem_erro = "Defina um ícone: classe FontAwesome, link de imagem ou upload de arquivo.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO servidores (servername, icon, descricao, features, modpackurl, ip, themecolor, enabled) VALUES (:servername, :icon, :descricao, :features, :modpackurl, :ip, :themecolor, :enabled)");
            $stmt->execute([
                ':servername' => $servername,
                ':icon'       => $icon,
                ':descricao'  => $descricao,
                ':features'   => json_encode($features, JSON_UNESCAPED_UNICODE),
                ':modpackurl' => $modpackurl,
                ':ip'         => $ip,
                ':themecolor' => $themecolor,
                ':enabled'    => $enabled ? 1 : 0,
            ]);

            $mensagem_sucesso = "Servidor criado com sucesso!";
            $servername = $descricao = $modpackurl = $ip = $icon_fa = $icon_url = "";
            $features = [""];
            $themecolor = "#B971DA";
            $enabled = true;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao salvar o servidor. Verifique se já não existe um servidor com esse nome.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Servidor - Painel Administrativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { max-width: 80%; margin: 0 auto; }
        @media (max-width: 768px) { .card { max-width: 100%; } }
        .preview-icone {
            width: 90px; height: 90px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            background: #f0f2f5; border: 1px solid #e0e0e0; font-size: 2.2rem;
            margin: 0 auto;
        }
        .preview-icone img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
        .feature-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .cor-preview-box { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .cor-preview-swatch { width: 32px; height: 32px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.15); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel</a>
            <div class="d-flex">
                <a href="servidores.php" class="btn btn-outline-light btn-sm">← Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 my-3 my-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white p-3 p-md-4">
                <h4 class="mb-0 h5 h-md-4">➕ Novo Servidor</h4>
            </div>
            <div class="card-body p-3 p-md-4">

                <?php if ($mensagem_sucesso): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8'); ?>
                        <a href="servidores.php" class="alert-link">Voltar para servidores</a>
                    </div>
                <?php endif; ?>

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="servidor_criar.php" autocomplete="off" enctype="multipart/form-data">
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="servername" class="form-label">Nome do servidor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="servername" name="servername"
                                value="<?php echo htmlspecialchars($servername, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="100" required>
                            <small class="text-muted">O slug da URL e o título da página (<code>&lt;Nome&gt; - Rede Nerds</code>) são gerados automaticamente pelo banco.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="ip" class="form-label">IP do servidor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ip" name="ip"
                                value="<?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="jogar.exemplo.com.br:25565" maxlength="100" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="modpackurl" class="form-label">Link do modpack/download <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modpackurl" name="modpackurl"
                                value="<?php echo htmlspecialchars($modpackurl, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="255" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="5" required><?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Features <span class="text-danger">*</span></label>
                        <div id="features-container">
                            <?php foreach ($features as $f): ?>
                                <div class="feature-row">
                                    <input type="text" class="form-control" name="features[]"
                                        value="<?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?>"
                                        maxlength="255" placeholder="Ex: Sistema de clãs e cooperação">
                                    <button type="button" class="btn btn-outline-danger" onclick="removerFeature(this)">✕</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="adicionarFeature()">➕ Adicionar feature</button>
                    </div>

                    <div class="card bg-light p-3 mb-3">
                        <h6 class="mb-2">🎨 Ícone</h6>
                        <small class="text-muted d-block mb-2">Preencha apenas UM dos três campos abaixo.</small>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="icon_fa" class="form-label">Classe FontAwesome</label>
                                <input type="text" class="form-control" id="icon_fa" name="icon_fa"
                                    value="<?php echo htmlspecialchars($icon_fa, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="fa-solid fa-skull" oninput="atualizarPreviewIcone()">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="icon_url" class="form-label">Link de imagem</label>
                                <input type="text" class="form-control" id="icon_url" name="icon_url"
                                    value="<?php echo htmlspecialchars($icon_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="https://..." oninput="atualizarPreviewIcone()">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="icon_upload" class="form-label">Upload de imagem</label>
                                <input type="file" class="form-control" id="icon_upload" name="icon_upload"
                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                    onchange="usarUploadIcone(this)">
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="preview-icone" id="icone-preview-box">
                                <i class="fa-solid fa-image text-muted"></i>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="themecolor" class="form-label">Cor do tema <span class="text-danger">*</span></label>
                            <input type="color" class="form-control form-control-color" id="themecolor" name="themecolor"
                                value="<?php echo htmlspecialchars($themecolor, ENT_QUOTES, 'UTF-8'); ?>"
                                title="Escolha a cor" oninput="atualizarPreviewCor()">
                            <div class="cor-preview-box">
                                <div class="cor-preview-swatch" id="cor-swatch"></div>
                                <small class="text-muted">Sombra e fundo de hover são gerados automaticamente pelo banco a partir dessa cor.</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enabled">
                                    Exibir este servidor no site
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-2">
                        <a href="servidores.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Criar Servidor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function adicionarFeature() {
            const container = document.getElementById('features-container');
            const row = document.createElement('div');
            row.className = 'feature-row';
            row.innerHTML = `
                <input type="text" class="form-control" name="features[]" maxlength="255" placeholder="Ex: Eventos especiais">
                <button type="button" class="btn btn-outline-danger" onclick="removerFeature(this)">✕</button>
            `;
            container.appendChild(row);
        }

        function removerFeature(botao) {
            const container = document.getElementById('features-container');
            if (container.children.length > 1) {
                botao.closest('.feature-row').remove();
            } else {
                botao.closest('.feature-row').querySelector('input').value = '';
            }
        }

        function usarUploadIcone(input) {
            const arquivo = input.files && input.files[0];
            if (!arquivo) return;
            document.getElementById('icon_fa').value = '';
            document.getElementById('icon_url').value = '';
            const box = document.getElementById('icone-preview-box');
            const leitor = new FileReader();
            leitor.onload = (e) => {
                box.innerHTML = `<img src="${e.target.result}" alt="preview">`;
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
                box.innerHTML = `<img src="${url}" alt="preview" onerror="this.parentNode.innerHTML='<i class=\\'fa-solid fa-triangle-exclamation text-danger\\'></i>'">`;
            } else {
                box.innerHTML = `<i class="fa-solid fa-image text-muted"></i>`;
            }
        }

        function atualizarPreviewCor() {
            const cor = document.getElementById('themecolor').value;
            document.getElementById('cor-swatch').style.background = cor;
        }

        atualizarPreviewCor();
        atualizarPreviewIcone();
    </script>
</body>
</html>
