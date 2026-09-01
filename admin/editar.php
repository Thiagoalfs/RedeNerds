<?php
$paginaAtiva = 'noticias';
$tituloPagina = 'Editar Novidade';

require_once __DIR__ . "/includes/admin_header.php";
require_once "capa_upload.php";
require_once "webhook_helper.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header("Location: noticias.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$noticia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$noticia) {
    header("Location: noticias.php");
    exit;
}

try {
    $servidores = $pdo->query("SELECT servername FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (empty($servidores)) $servidores = ['NerdSky', 'Potato Nerds', 'Nerd Dead'];
} catch (PDOException $e) {
    $servidores = ['NerdSky', 'Potato Nerds', 'Nerd Dead'];
}

$categorias_envio = ['Anúncios', 'Atualizações'];
$mensagem_erro = "";

$titulo          = $noticia['titulo'];
$conteudo        = $noticia['conteudo'];
$autor           = $noticia['autor'];
$category        = $noticia['category'];
$categoria_envio = $noticia['categoria_envio'];
$capaAtual       = $noticia['capa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo          = trim($_POST['titulo'] ?? '');
    $conteudo        = trim($_POST['conteudo'] ?? '');
    $autor           = trim($_POST['autor'] ?? '');
    $category        = trim($_POST['category'] ?? 'NerdSky');
    $categoria_envio = trim($_POST['categoria_envio'] ?? 'Anúncios');

    [$capa, $erro_upload] = processarCapa($capaAtual);

    if ($erro_upload) {
        $mensagem_erro = $erro_upload;
    } elseif (empty($titulo) || empty($conteudo) || empty($autor)) {
        $mensagem_erro = "Preencha todos os campos obrigatórios.";
    } else {
        try {
            $upd = $pdo->prepare("
                UPDATE novidades 
                SET titulo = :titulo, conteudo = :conteudo, autor = :autor, capa = :capa, category = :category, categoria_envio = :categoria_envio
                WHERE id = :id
            ");
            $upd->execute([
                ':titulo'          => $titulo,
                ':conteudo'        => $conteudo,
                ':autor'           => $autor,
                ':capa'            => $capa,
                ':category'        => $category,
                ':categoria_envio' => $categoria_envio,
                ':id'              => $id
            ]);

            header("Location: noticias.php");
            exit;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao atualizar novidade: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-pen text-primary"></i> Editar Novidade</h5>
                <a href="noticias.php" class="btn btn-outline-secondary btn-sm">← Voltar para Novidades</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

                    <div class="admin-form-group">
                        <label for="titulo">Título da Novidade *</label>
                        <input type="text" class="admin-form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 admin-form-group">
                            <label for="category">Servidor Relacionado</label>
                            <select class="admin-form-control" id="category" name="category">
                                <?php foreach ($servidores as $srv): ?>
                                    <option value="<?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($category === $srv) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="categoria_envio">Canal de Envio</label>
                            <select class="admin-form-control" id="categoria_envio" name="categoria_envio">
                                <?php foreach ($categorias_envio as $cEnv): ?>
                                    <option value="<?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($categoria_envio === $cEnv) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="autor">Autor *</label>
                            <input type="text" class="admin-form-control" id="autor" name="autor" value="<?php echo htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="admin-form-group mb-3">
                        <label for="conteudo">Conteúdo *</label>
                        <textarea class="admin-form-control font-monospace" id="conteudo" name="conteudo" rows="8" required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- CAPA -->
                    <div class="admin-card p-3 mb-4 bg-light border">
                        <h6 class="fw-bold mb-2">Imagem de Capa</h6>
                        <?php if (!empty($capaAtual)): ?>
                            <div class="mb-2">
                                <span class="small text-muted d-block mb-1">Capa Atual:</span>
                                <img src="<?php echo htmlspecialchars($capaAtual, ENT_QUOTES, 'UTF-8'); ?>" class="rounded border" width="120" height="70" style="object-fit:cover;" alt="Capa">
                            </div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-semibold">Substituir por Arquivo</label>
                                <input type="file" class="admin-form-control" name="capa_upload" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-semibold">Ou Nova URL Externa</label>
                                <input type="url" class="admin-form-control" name="capa_url" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="noticias.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>