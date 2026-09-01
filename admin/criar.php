<?php
$paginaAtiva = 'noticias';
$tituloPagina = 'Nova Novidade';

require_once __DIR__ . "/includes/admin_header.php";
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
    $cargosPermitidos = ['Fundadores', 'Fundador', 'Diretores', 'Diretor', 'Coordenadores', 'Coordenador', 'Administradores', 'Administrador'];
    $placeholders = implode(',', array_fill(0, count($cargosPermitidos), '?'));
    $stmtEquipe = $pdo->prepare("
        SELECT DISTINCT nick 
        FROM equipe 
        WHERE nick IS NOT NULL AND nick != '' 
          AND (
              cargo IN ($placeholders) 
              OR LOWER(cargo) LIKE '%fundad%' 
              OR LOWER(cargo) LIKE '%direto%' 
              OR LOWER(cargo) LIKE '%coordena%' 
              OR LOWER(cargo) LIKE '%admin%'
          )
        ORDER BY nick ASC
    ");
    $stmtEquipe->execute($cargosPermitidos);
    $nicksEquipe = $stmtEquipe->fetchAll(PDO::FETCH_COLUMN, 0);
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

    if ($erro_upload) {
        $mensagem_erro = $erro_upload;
    } elseif (empty($titulo) || empty($conteudo) || empty($autor)) {
        $mensagem_erro = "Preencha todos os campos obrigatórios.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO novidades (titulo, conteudo, autor, capa, category, categoria_envio) 
                VALUES (:titulo, :conteudo, :autor, :capa, :category, :categoria_envio)
            ");
            $stmt->execute([
                ':titulo'          => $titulo,
                ':conteudo'        => $conteudo,
                ':autor'           => $autor,
                ':capa'            => $capa,
                ':category'        => $category,
                ':categoria_envio' => $categoria_envio
            ]);

            $noticia_id = (int)$pdo->lastInsertId();

            if ($enviar_webhook) {
                enviarWebhookDiscord($titulo, $conteudo, $autor, $capa, $category, $categoria_envio, $noticia_id, $marcar_everyone);
            }

            header("Location: noticias.php");
            exit;
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao salvar novidade: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-newspaper text-success"></i> Publicar Nova Novidade</h5>
                <a href="noticias.php" class="btn btn-outline-secondary btn-sm">← Voltar para Novidades</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="admin-form-group">
                        <label for="titulo">Título da Novidade *</label>
                        <input type="text" class="admin-form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Inauguração do Novo Servidor" required autofocus>
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
                            <label for="autor">Autor da Postagem *</label>
                            <select class="admin-form-control" id="autor" name="autor" required>
                                <?php foreach ($nicksEquipe as $nk): ?>
                                    <option value="<?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($autor === $nk) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="admin-form-group mb-3">
                        <label for="conteudo">Conteúdo (Markdown suportado) *</label>
                        <textarea class="admin-form-control font-monospace" id="conteudo" name="conteudo" rows="8" placeholder="Escreva a novidade aqui..." required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- CAPA -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <h6 class="fw-bold mb-2">Imagem de Capa (Opcional)</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-semibold">Upload de Arquivo</label>
                                <input type="file" class="admin-form-control" name="capa_upload" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-semibold">Ou URL Externa</label>
                                <input type="url" class="admin-form-control" name="capa_url" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <!-- DISCORD -->
                    <div class="admin-card p-3 mb-4 bg-light border">
                        <h6 class="fw-bold mb-2"><i class="fa-brands fa-discord text-primary me-1"></i> Notificação no Discord</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="enviar_webhook" name="enviar_webhook" value="1" checked>
                            <label class="form-check-label fw-semibold" for="enviar_webhook">Enviar anúncio automaticamente para o Discord</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="marcar_everyone" name="marcar_everyone" value="1">
                            <label class="form-check-label text-muted" for="marcar_everyone">Marcar @everyone na mensagem</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="noticias.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4">Publicar Novidade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>