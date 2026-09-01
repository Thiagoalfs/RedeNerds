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
if (empty($nicksEquipe)) {
    $nicksEquipe = [$_SESSION['usuario_nome'] ?? 'Admin'];
}

$erro = null;
$servidor_id = (int)($_POST['servidor_id'] ?? ($servidores[0]['id'] ?? 0));
$categoria_id = (int)($_POST['categoria_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');
$autor = trim($_POST['autor'] ?? ($nicksEquipe[0] ?? ($_SESSION['usuario_nome'] ?? 'Admin')));
$publicado = isset($_POST['publicado']) ? 1 : 0;

if ($servidor_id > 0) {
    garantirCategoriasPadraoWiki($pdo, $servidor_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($servidor_id <= 0 || empty($titulo) || empty($conteudo) || empty($autor)) {
        $erro = "Preencha o servidor, título, autor e conteúdo do artigo.";
    } else {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $titulo)));
        $slug = trim($slug, '-');
        if (empty($slug)) $slug = 'artigo-' . time();

        try {
            // Garante que a categoria pertença estritamente a este servidor
            $stmtCatCheck = $pdo->prepare("SELECT id FROM wiki_categorias WHERE id = :cat_id AND servidor_id = :servidor_id LIMIT 1");
            $stmtCatCheck->execute([':cat_id' => $categoria_id, ':servidor_id' => $servidor_id]);
            if (!$stmtCatCheck->fetch()) {
                $stmtFirstCat = $pdo->prepare("SELECT id FROM wiki_categorias WHERE servidor_id = :servidor_id ORDER BY ordem ASC, id ASC LIMIT 1");
                $stmtFirstCat->execute([':servidor_id' => $servidor_id]);
                $categoria_id = (int)$stmtFirstCat->fetchColumn();
            }

            if ($categoria_id <= 0) {
                throw new Exception("Nenhuma categoria encontrada para o servidor selecionado.");
            }

            // Garante unicidade do slug do artigo
            $stmtCheck = $pdo->prepare("SELECT id FROM wiki_artigos WHERE slug = :slug AND servidor_id = :servidor_id LIMIT 1");
            $stmtCheck->execute([':slug' => $slug, ':servidor_id' => $servidor_id]);
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

$paginaAtiva = 'wiki';
$tituloPagina = 'Criar Artigo da Wiki';
require_once __DIR__ . "/includes/admin_header.php";
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label for="categoria_id" class="mb-0">Categoria *</label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalNovaCategoriaRapida">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Nova Categoria
                                </button>
                            </div>
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

<!-- MODAL RÁPIDO: NOVA CATEGORIA -->
<div class="modal fade" id="modalNovaCategoriaRapida" tabindex="-1" aria-labelledby="modalNovaCategoriaRapidaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-nova-cat-rapida" onsubmit="criarCategoriaRapida(event)">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalNovaCategoriaRapidaLabel">
                        <i class="fa-solid fa-folder-plus text-primary me-2"></i> Criar Nova Categoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="feedback-cat-rapida" class="alert alert-danger d-none small"></div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome da Categoria <span class="text-danger">*</span></label>
                        <input type="text" id="cat-rapida-nome" class="form-control form-control-sm" placeholder="Ex: Chefões & Dungeons" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ícone (FontAwesome)</label>
                        <input type="text" id="cat-rapida-icone" class="form-control form-control-sm" value="fa-solid fa-folder">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ordem</label>
                        <input type="number" id="cat-rapida-ordem" class="form-control form-control-sm" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btn-submit-cat-rapida" class="btn btn-success btn-sm">Criar e Selecionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function carregarCategorias(servidorId, categoriaIdPreSelecionada = null) {
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
                if (categoriaIdPreSelecionada && parseInt(categoriaIdPreSelecionada, 10) === parseInt(c.id, 10)) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        } else {
            select.innerHTML = '<option value="">Nenhuma categoria cadastrada</option>';
        }
    } catch (e) {
        select.innerHTML = '<option value="">Erro ao carregar categorias</option>';
    }
}

async function criarCategoriaRapida(e) {
    e.preventDefault();
    const servidorId = document.getElementById('servidor_id').value;
    const nome = document.getElementById('cat-rapida-nome').value.trim();
    const icone = document.getElementById('cat-rapida-icone').value.trim() || 'fa-solid fa-folder';
    const ordem = document.getElementById('cat-rapida-ordem').value || 0;
    const feedback = document.getElementById('feedback-cat-rapida');
    const btn = document.getElementById('btn-submit-cat-rapida');

    if (!nome) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Criando...';
    feedback.classList.add('d-none');

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $csrfToken; ?>');
    formData.append('servidor_id', servidorId);
    formData.append('nome', nome);
    formData.append('icone', icone);
    formData.append('ordem', ordem);

    try {
        const res = await fetch('api/wiki/categoria_criar.php?ajax=1', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success && data.categoria) {
            await carregarCategorias(servidorId, data.categoria.id);
            const modalEl = document.getElementById('modalNovaCategoriaRapida');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            document.getElementById('form-nova-cat-rapida').reset();
        } else {
            feedback.textContent = data.erro || 'Erro ao criar categoria.';
            feedback.classList.remove('d-none');
        }
    } catch (err) {
        feedback.textContent = 'Erro de comunicação ao criar categoria.';
        feedback.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Criar e Selecionar';
    }
}
</script>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>