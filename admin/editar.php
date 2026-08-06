<?php
require_once "sessao.php";
require_once "../../config.php";
require_once "capa_upload.php";

$categorias = ['NerdSky', 'Potato Nerd', 'NerdDead'];

$mensagem_sucesso = "";
$mensagem_erro = "";

// Pega o ID pela URL (GET) ou pelo form (POST)
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Carrega os dados atuais da notícia
try {
    $stmt = $pdo->prepare("SELECT id, titulo, conteudo, autor, capa, category FROM novidades WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$noticia) {
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $mensagem_erro = "Erro ao carregar a notícia.";
    $noticia = [
        'id' => $id,
        'titulo' => '',
        'conteudo' => '',
        'autor' => '',
        'capa' => '',
        'category' => 'NerdSky',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo    = trim($_POST['titulo'] ?? '');
    $conteudo  = trim($_POST['conteudo'] ?? '');
    $autor     = trim($_POST['autor'] ?? '');
    $category  = trim($_POST['category'] ?? 'NerdSky');

    [$capa, $erro_upload] = processarCapa($noticia['capa'] ?? null);

    if ($titulo === '' || $conteudo === '' || $autor === '') {
        $mensagem_erro = "Preencha os campos obrigatórios (título, conteúdo e autor).";
        // Mantém os valores enviados no formulário para o usuário não perder o que digitou
        $noticia['titulo']   = $titulo;
        $noticia['conteudo'] = $conteudo;
        $noticia['autor']    = $autor;
        $noticia['capa']     = $capa;
        $noticia['category'] = $category;
    } elseif (!in_array($category, $categorias, true)) {
        $mensagem_erro = "Categoria inválida.";
    } elseif ($erro_upload) {
        $mensagem_erro = $erro_upload;
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE novidades SET titulo = :titulo, conteudo = :conteudo, autor = :autor, capa = :capa, category = :category WHERE id = :id");
            $stmt->execute([
                ':titulo'   => $titulo,
                ':conteudo' => $conteudo,
                ':autor'    => $autor,
                ':capa'     => $capa !== '' ? $capa : null,
                ':category' => $category,
                ':id'       => $id,
            ]);
            $mensagem_sucesso = "Notícia atualizada com sucesso!";

            // Atualiza os dados no array para refletir no formulário
            $noticia['titulo']   = $titulo;
            $noticia['conteudo'] = $conteudo;
            $noticia['autor']    = $autor;
            $noticia['capa']     = $capa;
            $noticia['category'] = $category;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao atualizar a notícia. Tente novamente.";
        }
    }
}
// A capa atual só é pré-preenchida no campo de link se for uma URL externa
// (uploads próprios ficam em ../assets/novidades/ e não fazem sentido como "link" editável)
$capaAtualEhLink = !empty($noticia['capa']) && preg_match('#^https?://#i', $noticia['capa']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Notícia - Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            max-width: 80%;
            margin: 0 auto;
        }
        .preview-capa {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .preview-autor {
            width: auto;
            max-height: 180px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            display: block;
            margin: 0 auto;
        }

        .preview-placeholder {
            width: 100%;
            height: 180px;
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel de Notícias</a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Voltar</a>
            </div>
        </div>
    </nav>


    <div class="container-fluid px-3 px-md-4 my-3 my-md-4">
        <div class="card shadow-sm">


            <div class="card-header bg-white p-3 p-md-4">
                <h4 class="mb-0 h5 h-md-4">✏️ Editar Notícia #<?php echo (int)$noticia['id']; ?></h4>
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

                <form method="POST" action="editar.php?id=<?php echo (int)$noticia['id']; ?>" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo (int)$noticia['id']; ?>">

                    <!-- Linha 1: título + categoria -->
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo"
                                value="<?php echo htmlspecialchars($noticia['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="150" required>
                        </div>
                        <div class="col-4">
                            <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo (($noticia['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Linha 2: conteúdo -->
                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="10" required><?php echo htmlspecialchars($noticia['conteudo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- Linha 3: link da capa + autor -->
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label for="capa" class="form-label">Capa</label>
                            <input type="hidden" id="capa_fonte" name="capa_fonte" value="">
                            <input type="file" class="form-control mb-2" id="capa" name="capa"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                onchange="usarUploadCapa(this)">
                            <input type="text" class="form-control" id="capa_url" name="capa_url"
                                maxlength="500" placeholder="ou cole o link de uma imagem (https://...)"
                                value="<?php echo htmlspecialchars($capaAtualEhLink ? $noticia['capa'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                oninput="usarUrlCapa(this)">
                            <small class="text-muted d-block mt-1">Deixe os dois em branco para manter a capa atual. Envie um arquivo OU cole um link — o que for usado por último substitui o outro.</small>
                        </div>
                        <div class="col-4">
                            <label for="autor" class="form-label">Autor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="autor" name="autor"
                                value="<?php echo htmlspecialchars($noticia['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="32" required
                                oninput="atualizarPreviewAutor(this.value)">
                        </div>
                    </div>

                    <!-- Linha 4: preview da capa + preview do autor -->
                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <img id="capa-preview" class="preview-capa"
                                src="<?php echo htmlspecialchars($noticia['capa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Preview da capa"
                                style="display: <?php echo !empty($noticia['capa']) ? 'block' : 'none'; ?>;"
                                onerror="this.style.display='none'">
                            <div id="capa-placeholder" class="preview-placeholder"
                                style="display: <?php echo !empty($noticia['capa']) ? 'none' : 'flex'; ?>;">
                                🖼️ Preview da capa
                            </div>
                        </div>
                        <div class="col-4">
                            <img id="autor-preview" class="preview-autor"
                                src="<?php echo !empty($noticia['autor']) ? 'https://mc-heads.net/avatar/' . htmlspecialchars($noticia['autor'], ENT_QUOTES, 'UTF-8') . '/100' : ''; ?>"
                                alt="Preview do autor"
                                style="display: <?php echo !empty($noticia['autor']) ? 'block' : 'none'; ?>;"
                                onerror="this.style.display='none'">
                            <div id="autor-placeholder" class="preview-placeholder"
                                style="display: <?php echo !empty($noticia['autor']) ? 'none' : 'flex'; ?>;">
                                🎮 Preview do autor
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-2">
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Editar Notícia</button>
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

            // Upload passa a ser a fonte ativa: limpa o campo de link
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

            // Link passa a ser a fonte ativa: limpa o campo de arquivo
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
