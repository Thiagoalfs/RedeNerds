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
require_once "capa_upload.php";
require_once "webhook_helper.php";

$mensagem_sucesso = "";
$mensagem_erro = "";
$titulo = "";
$conteudo = "";
$autor = "";
$capa = "";
$category = "NerdSky";
$categoria_envio = "Anúncios";

try {
    $servidores = $pdo->query("SELECT servername FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (empty($servidores)) {
        $servidores = ['NerdSky', 'Potato Nerds', 'Nerd Dead'];
    }
} catch (PDOException $e) {
    $servidores = ['NerdSky', 'Potato Nerds', 'Nerd Dead'];
}

$categorias_envio = ['Anúncios', 'Atualizações'];

try {
    $nicksEquipe = $pdo->query("SELECT DISTINCT nick FROM equipe ORDER BY nick ASC")->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    $nicksEquipe = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo          = trim($_POST['titulo'] ?? '');
    $conteudo        = trim($_POST['conteudo'] ?? '');
    $autor           = trim($_POST['autor'] ?? '');
    $category        = trim($_POST['category'] ?? 'NerdSky');
    $categoria_envio = trim($_POST['categoria_envio'] ?? 'Anúncios');
    
    $enviar_webhook  = isset($_POST['enviar_webhook']);
    $marcar_everyone = isset($_POST['marcar_everyone']);

    [$capa, $erro_upload] = processarCapa();

    if ($titulo === '' || $conteudo === '' || $autor === '') {
        $mensagem_erro = "Preencha os campos obrigatórios (título, conteúdo e autor).";
    } elseif (!in_array($category, $servidores, true)) {
        $mensagem_erro = "Servidor inválido.";
    } elseif (!in_array($categoria_envio, $categorias_envio, true)) {
        $mensagem_erro = "Categoria de envio inválida.";
    } elseif ($erro_upload) {
        $mensagem_erro = $erro_upload;
    } else {
        try {
            $mensagemID = null;

            // Se a opção de Webhook foi marcada
            if ($enviar_webhook) {
                $mensagemID = enviarWebhookDiscord($categoria_envio, $titulo, $conteudo, $autor, $capa, $category, $marcar_everyone);
            }

            $stmt = $pdo->prepare("INSERT INTO novidades (titulo, conteudo, autor, capa, category, categoria_envio, mensagemID) VALUES (:titulo, :conteudo, :autor, :capa, :category, :categoria_envio, :mensagemID)");
            $stmt->execute([
                ':titulo'          => $titulo,
                ':conteudo'        => $conteudo,
                ':autor'           => $autor,
                ':capa'            => $capa !== '' ? $capa : null,
                ':category'        => $category,
                ':categoria_envio' => $categoria_envio,
                ':mensagemID'      => $mensagemID,
            ]);

            $mensagem_sucesso = "Notícia criada com sucesso!" . ($enviar_webhook ? ($mensagemID ? " (Enviada ao Discord com sucesso)" : " (Falha ao enviar ao Discord)") : "");
            $titulo = $conteudo = $autor = $capa = "";
            $category = "NerdSky";
            $categoria_envio = "Anúncios";
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao salvar a notícia. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Notícia - Painel Administrativo</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=2" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel</a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 my-3 my-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white p-3 p-md-4">
                <h4 class="mb-0 h5 h-md-4">📝 Criar Nova Notícia</h4>
            </div>
            <div class="card-body p-3 p-md-4">

                <?php if ($mensagem_sucesso): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8'); ?>
                        <a href="dashboard.php" class="alert-link">Voltar para o dashboard</a>
                    </div>
                <?php endif; ?>

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="criar.php" autocomplete="off" enctype="multipart/form-data">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo"
                                value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="150" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="category" class="form-label">Servidor <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($servidores as $srv): ?>
                                    <option value="<?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($category === $srv) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="categoria_envio" class="form-label">Categoria de Envio <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoria_envio" name="categoria_envio" required
                                onchange="atualizarLabelCategoria(this.value)">
                                <?php foreach ($categorias_envio as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($categoria_envio === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card bg-light p-3 mb-3">
                        <h6 class="mb-2">💬 Integração Discord</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="enviar_webhook" name="enviar_webhook" value="1" checked>
                            <label class="form-check-label" for="enviar_webhook">
                                Enviar notícia pelo webhook de <strong id="categoria-envio-label"><?php echo htmlspecialchars($categoria_envio, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="marcar_everyone" name="marcar_everyone" value="1">
                            <label class="form-check-label" for="marcar_everyone">
                                Marcar <strong>@everyone</strong> na mensagem do Discord
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="10" required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-8">
                            <label for="capa" class="form-label">Capa</label>
                            <input type="hidden" id="capa_fonte" name="capa_fonte" value="">
                            <input type="file" required class="form-control mb-2" id="capa" name="capa"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                onchange="usarUploadCapa(this)">
                            <small class="text-muted d-block mt-1">Envie um arquivo OU cole um link — o que for usado por último substitui o outro.</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="autor" class="form-label">Autor <span class="text-danger">*</span></label>
                            <select class="form-select" id="autor" name="autor" required
                                onchange="atualizarPreviewAutor(this.value)">
                                <option value="" disabled <?php echo $autor === '' ? 'selected' : ''; ?>>Selecione...</option>
                                <?php foreach ($nicksEquipe as $nick): ?>
                                    <option value="<?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($autor === $nick) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($nicksEquipe)): ?>
                                <small class="text-danger d-block mt-1">Nenhum membro cadastrado em Equipe ainda.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-8">
                            <img id="capa-preview" class="preview-capa"
                                src="" alt="Preview da capa" style="display: none;"
                                onerror="this.style.display='none'">
                            <div id="capa-placeholder" class="preview-placeholder">
                                🖼️ Preview da capa
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <img id="autor-preview" class="preview-autor"
                                src="<?php echo $autor !== '' ? 'https://mc-heads.net/avatar/' . htmlspecialchars($autor, ENT_QUOTES, 'UTF-8') . '/100' : ''; ?>"
                                alt="Preview do autor"
                                style="display: <?php echo $autor !== '' ? 'block' : 'none'; ?>;"
                                onerror="this.style.display='none'">
                            <div id="autor-placeholder" class="preview-placeholder"
                                style="display: <?php echo $autor !== '' ? 'none' : 'flex'; ?>;">
                                🎮 Preview do autor
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-2">
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Publicar Notícia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function usarUploadCapa(input) {
            const arquivo = input.files && input.files[0];
            if (!arquivo) return;
            document.getElementById('capa_fonte').value = 'upload';
            document.getElementById('capa_url').value = '';
            const img = document.getElementById('capa-preview');
            const placeholder = document.getElementById('capa-placeholder');
            const leitor = new FileReader();
            leitor.onload = (e) => {
                img.src = e.target.result;
                img.style.display = 'block';
                placeholder.style.display = 'none';
            };
            leitor.readAsDataURL(arquivo);
        }

        function usarUrlCapa(input) {
            const url = input.value.trim();
            document.getElementById('capa_fonte').value = 'url';
            const fileInput = document.getElementById('capa');
            if (fileInput.value) fileInput.value = '';
            const img = document.getElementById('capa-preview');
            const placeholder = document.getElementById('capa-placeholder');
            if (url) {
                img.src = url;
                img.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        }

        function atualizarLabelCategoria(categoria) {
            document.getElementById('categoria-envio-label').textContent = categoria;
        }

        function atualizarPreviewAutor(nome) {
            const img = document.getElementById('autor-preview');
            const placeholder = document.getElementById('autor-placeholder');
            if (nome) {
                img.src = 'https://mc-heads.net/avatar/' + nome + '/100';
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