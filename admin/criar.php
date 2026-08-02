<?php
require_once "sessao.php";
require_once "../../config.php";

$mensagem_sucesso = "";
$mensagem_erro = "";
$titulo = "";
$conteudo = "";
$autor = "";
$capa = "";
$category = "NerdSky";

$categorias = ['NerdSky', 'Potato Nerd', 'NerdDead'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo    = trim($_POST['titulo'] ?? '');
    $conteudo  = trim($_POST['conteudo'] ?? '');
    $autor     = trim($_POST['autor'] ?? '');
    $capa      = trim($_POST['capa'] ?? '');
    $category  = trim($_POST['category'] ?? 'NerdSky');

    if ($titulo === '' || $conteudo === '' || $autor === '') {
        $mensagem_erro = "Preencha os campos obrigatórios (título, conteúdo e autor).";
    } elseif (!in_array($category, $categorias, true)) {
        $mensagem_erro = "Categoria inválida.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO novidades (titulo, conteudo, autor, capa, category) VALUES (:titulo, :conteudo, :autor, :capa, :category)");
            $stmt->execute([
                ':titulo'    => $titulo,
                ':conteudo'  => $conteudo,
                ':autor'     => $autor,
                ':capa'      => $capa !== '' ? $capa : null,
                ':category'  => $category,
            ]);
            $mensagem_sucesso = "Notícia criada com sucesso!";
            $titulo = $conteudo = $autor = $capa = "";
            $category = "NerdSky";
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

                <form method="POST" action="criar.php" autocomplete="off">
                    <!-- Linha 1: título + categoria -->
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo"
                                value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="150" required>
                        </div>
                        <div class="col-4">
                            <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Linha 2: conteúdo -->
                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="10" required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- Linha 3: link da capa + autor -->
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label for="capa" class="form-label">Capa (URL da imagem)</label>
                            <input type="text" class="form-control" id="capa" name="capa"
                                value="<?php echo htmlspecialchars(!empty($noticia['capa']) ? $noticia['capa'] : '../assets/images/fundo.png', ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="500"
                                oninput="atualizarPreviewCapa(this.value)">
                        </div>
                        <div class="col-4">
                            <label for="autor" class="form-label">Autor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="autor" name="autor"
                                value="<?php echo htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="32" required
                                oninput="atualizarPreviewAutor(this.value)">
                        </div>
                    </div>

                    <!-- Linha 4: preview da capa + preview do autor -->
                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <img id="capa-preview" class="preview-capa"
                                src="<?php echo htmlspecialchars($capa, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Preview da capa"
                                style="display: <?php echo $capa !== '' ? 'block' : 'none'; ?>;"
                                onerror="this.style.display='none'">
                            <div id="capa-placeholder" class="preview-placeholder"
                                style="display: <?php echo $capa !== '' ? 'none' : 'flex'; ?>;">
                                🖼️ Preview da capa
                            </div>
                        </div>
                        <div class="col-4">
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
        function atualizarPreviewCapa(url) {
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