<?php
require_once __DIR__ . "/sessao.php";
$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
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
require_once __DIR__ . "/../wiki/wiki_helper.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header("Location: wiki.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM wiki_artigos WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$artigo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artigo) {
    header("Location: wiki.php");
    exit;
}

$servidores = $pdo->query("SELECT id, servername FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_ASSOC);

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
if (!empty($artigo['autor']) && !in_array($artigo['autor'], $nicksEquipe)) {
    $nicksEquipe[] = $artigo['autor'];
}
if (empty($nicksEquipe)) {
    $nicksEquipe = [$_SESSION['usuario_nome'] ?? 'Admin'];
}

$erro = null;
$servidor_id = (int)$artigo['servidor_id'];
$categoria_id = (int)$artigo['categoria_id'];
$titulo = $artigo['titulo'];
$conteudo = $artigo['conteudo'];
$autor = $artigo['autor'];
$publicado = (int)$artigo['publicado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servidor_id = (int)($_POST['servidor_id'] ?? $servidor_id);
    $categoria_id = (int)($_POST['categoria_id'] ?? $categoria_id);
    $titulo = trim($_POST['titulo'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');
    $autor = trim($_POST['autor'] ?? $autor);
    $publicado = isset($_POST['publicado']) ? 1 : 0;

    if ($servidor_id <= 0 || empty($titulo) || empty($conteudo) || empty($autor)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else {
        try {
            $upd = $pdo->prepare("
                UPDATE wiki_artigos 
                SET servidor_id = :servidor_id, categoria_id = :categoria_id, titulo = :titulo, conteudo = :conteudo, autor = :autor, publicado = :publicado
                WHERE id = :id
            ");
            $upd->execute([
                ':servidor_id'  => $servidor_id,
                ':categoria_id' => $categoria_id,
                ':titulo'       => $titulo,
                ':conteudo'     => $conteudo,
                ':autor'        => $autor,
                ':publicado'    => $publicado,
                ':id'           => $id
            ]);

            header("Location: wiki.php");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    }
}

$paginaAtiva = 'wiki';
$tituloPagina = 'Editar Artigo da Wiki';
require_once __DIR__ . "/includes/admin_header.php";
$categoriasDisponiveis = getCategoriasServidorWiki($pdo, $servidor_id);
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-pen text-primary"></i> Editar Artigo: <?php echo htmlspecialchars($artigo['titulo'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="wiki.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
            </div>
            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="wiki_artigo_editar.php?id=<?php echo (int)$id; ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="servidor_id">Servidor *</label>
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
                            <label for="titulo">Título do Artigo *</label>
                            <input type="text" class="admin-form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="autor">Autor (Membro da Equipe) *</label>
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
                        <label for="conteudo">Conteúdo (Markdown) *</label>
                        <textarea class="admin-form-control font-monospace" id="conteudo" name="conteudo" rows="12" required><?php echo htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="publicado" name="publicado" value="1" <?php echo $publicado ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Artigo Publicado</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="wiki.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Salvar Alterações</button>
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