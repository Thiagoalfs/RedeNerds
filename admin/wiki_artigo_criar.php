<?php
$paginaAtiva = 'wiki';
$tituloPagina = 'Criar Artigo da Wiki';
require_once __DIR__ . "/includes/admin_header.php";
require_once __DIR__ . "/../wiki/wiki_helper.php";

$servidores = $pdo->query("SELECT id, servername FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_ASSOC);

$erro = null;
$servidor_id = (int)($_POST['servidor_id'] ?? ($servidores[0]['id'] ?? 0));
$categoria_id = (int)($_POST['categoria_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');
$autor = trim($_POST['autor'] ?? $nome_usuario);
$publicado = isset($_POST['publicado']) ? 1 : 0;

if ($servidor_id > 0) {
    garantirCategoriasPadraoWiki($pdo, $servidor_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($servidor_id <= 0 || empty($titulo) || empty($conteudo)) {
        $erro = "Preencha o servidor, título e conteúdo do artigo.";
    } else {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $titulo)));
        $slug = trim($slug, '-');

        // Se slug ficar vazio
        if (empty($slug)) $slug = 'artigo-' . time();

        try {
            // Garante unicidade do slug
            $stmtCheck = $pdo->prepare("SELECT id FROM wiki_artigos WHERE slug = :slug LIMIT 1");
            $stmtCheck->execute([':slug' => $slug]);
            if ($stmtCheck->fetch()) {
                $slug .= '-' . time();
            }

            $stmt = $pdo->prepare("
                INSERT INTO wiki_artigos (servidor_id, categoria_id, titulo, slug, conteudo, autor, publicado, criado_em)
                VALUES (:servidor_id, :categoria_id, :titulo, :slug, :conteudo, :autor, :publicado, NOW())
            ");
            $stmt->execute([
                ':servidor_id'  => $servidor_id,
                ':categoria_id' => $categoria_id,
                ':titulo'       => $titulo,
                ':slug'         => $slug,
                ':conteudo'     => $conteudo,
                ':autor'        => $autor,
                ':publicado'    => $publicado
            ]);

            header("Location: wiki.php");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao salvar artigo: " . $e->getMessage();
        }
    }
}

$categoriasDisponiveis = getCategoriasServidorWiki($pdo, $servidor_id);
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-plus text-success"></i> Criar Artigo para a Wiki</h5>
                <a href="wiki.php" class="btn btn-outline-secondary btn-sm">← Voltar para a Wiki</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="wiki_artigo_criar.php">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="servidor_id">Servidor de Destino *</label>
                            <select class="admin-form-control" id="servidor_id" name="servidor_id" required onchange="carregarCategorias(this.value)">
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ($servidor_id === (int)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 admin-form-group">
                            <label for="categoria_id">Categoria *</label>
                            <select class="admin-form-control" id="categoria_id" name="categoria_id" required>
                                <?php foreach ($categoriasDisponiveis as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo ($categoria_id === (int)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8 admin-form-group">
                            <label for="titulo">Título do Artigo / Guia *</label>
                            <input type="text" class="admin-form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Como proteger seu terreno inicial" required autofocus>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="autor">Autor *</label>
                            <input type="text" class="admin-form-control" id="autor" name="autor" value="<?php echo htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="admin-form-group mb-3">
                        <label for="conteudo">Conteúdo do Artigo (Markdown suportado) *</label>
                        <textarea class="admin-form-control font-monospace" id="conteudo" name="conteudo" rows="12" placeholder="Escreva o artigo em Markdown. Ex:
## 1. Primeiros Passos
Utilize o comando `/claim` para proteger sua área.

> [!DICA]
> Pressione M para abrir o mapa visual de proteção." required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="publicado" name="publicado" value="1" <?php echo $publicado ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicar artigo imediatamente na Wiki</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="wiki.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4">Salvar Artigo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function carregarCategorias(servidorId) {
    const select = document.getElementById('categoria_id');
    select.innerHTML = '<option value="">Carregando categorias...</option>';
    try {
        const res = await fetch(`api/wiki/categorias.php?servidor_id=${servidorId}`);
        const data = await res.json();
        if (data.categorias && data.categorias.length > 0) {
            select.innerHTML = '';
            data.categorias.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nome;
                select.appendChild(opt);
            });
        }
    } catch (e) {}
}
</script>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>