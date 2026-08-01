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
            // Limpa os campos do formulário
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
        body { background-color: #f5f7fa; }
        .navbar-brand { font-weight: 600; }
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

    <div class="container my-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">📝 Criar Nova Notícia</h4>
            </div>
            <div class="card-body">

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
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo"
                               value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label for="autor" class="form-label">Autor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="autor" name="autor"
                               value="<?php echo htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="capa" class="form-label">Capa (URL da imagem)</label>
                        <input type="url" class="form-control" id="capa" name="capa"
                               value="<?php echo htmlspecialchars($capa, ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="500" placeholder="https://exemplo.com/imagem.jpg">
                        <div class="form-text">Cole a URL completa da imagem de capa (opcional).</div>
                    </div>

                    <div class="mb-3">
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

                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="12" required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">💾 Publicar Notícia</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
