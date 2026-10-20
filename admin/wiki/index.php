<?php
$paginaAtiva = 'wiki';
$tituloPagina = 'Wiki & Tutoriais';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../../wiki/wiki_helper.php";

$wikiHabilitada = isWikiHabilitada($pdo);
$filtroServidor = isset($_GET['servidor_id']) ? (int)$_GET['servidor_id'] : 0;

$servidores = [];
try {
    $stmtSrv = $pdo->query("SELECT id, servername, themecolor FROM servidores ORDER BY servername ASC");
    $servidores = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar servidores (wiki): " . $e->getMessage());
    $servidores = [];
}

$nicksEquipe = [];
try {
    $cargosPermitidos = [
        'Fundadores', 'Fundador',
        'Diretores', 'Diretor',
        'Coordenadores', 'Coordenador',
        'Administradores', 'Administrador',
        'Desenvolvedores', 'Desenvolvedor',
        'Developers', 'Developer',
        'Devs', 'Dev'
    ];
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
              OR LOWER(cargo) LIKE '%desenvolv%'
              OR LOWER(cargo) LIKE '%developer%'
              OR LOWER(cargo) LIKE '%dev%'
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

$where = [];
$params = [];

if ($filtroServidor > 0) {
    $where[] = "c.servidor_id = :servidor_id";
    $params[':servidor_id'] = $filtroServidor;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$artigos = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.titulo, a.slug, a.publicado, a.visualizacoes, a.autor, a.criado_em, a.conteudo, a.categoria_id,
               s.id as servidor_id, s.servername, s.nome as servidor_slug, s.themecolor, c.nome as categoria_nome
        FROM wiki_artigos a
        JOIN wiki_categorias c ON c.id = a.categoria_id
        JOIN servidores s ON s.id = c.servidor_id
        $whereSql
        ORDER BY a.id DESC
    ");
    $stmt->execute($params);
    $artigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar artigos wiki: " . $e->getMessage());
}

$primeiroServidorId = !empty($servidores) ? (int)$servidores[0]['id'] : 0;
$categoriasIniciais = $primeiroServidorId > 0 ? getCategoriasServidorWiki($pdo, $primeiroServidorId) : [];
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Wiki & Base de Conhecimento</h4>
        <p class="text-muted small mb-0"><span id="contador-artigos-wiki" data-item-counter><?php echo pluralizar(count($artigos), 'artigo', 'artigos'); ?></span> <?php echo count($artigos) === 1 ? 'cadastrado' : 'cadastrados'; ?></p>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <!-- TOGGLE GERAL DA WIKI (PÚBLICA / NAVBAR) -->
        <button type="button" id="btn-toggle-wiki-geral"
                class="btn btn-sm <?php echo $wikiHabilitada ? 'btn-outline-success' : 'btn-outline-danger'; ?>"
                data-wiki-ativa="<?php echo $wikiHabilitada ? '1' : '0'; ?>"
                title="<?php echo $wikiHabilitada ? 'Clique para ocultar a Wiki no site' : 'Clique para ativar a visualização pública da Wiki'; ?>"
                onclick="alternarWikiGeral(this)">
            <i class="fa-solid <?php echo $wikiHabilitada ? 'fa-globe' : 'fa-eye-slash'; ?> me-1"></i>
            Wiki: <?php echo $wikiHabilitada ? 'Ativa' : 'Oculta'; ?>
        </button>

        <a href="/wiki/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Wiki</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarArtigo">
            <i class="fa-solid fa-plus me-1"></i> Novo Artigo
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- SUB-ABAS DE NAVEGAÇÃO -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-primary btn-sm">
        <i class="fa-regular fa-file-lines me-1"></i> Artigos da Wiki
    </a>
    <a href="categorias.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-folder-tree me-1"></i> Gerenciar Categorias
    </a>
</div>

<!-- FILTROS E BUSCA EM TEMPO REAL -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" id="form-filtro-wiki" class="row g-2 align-items-center" onsubmit="event.preventDefault();">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="filtro-wiki-busca" class="form-control" placeholder="Buscar por título, autor...">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <select name="servidor_id" id="filtro-wiki-servidor" class="form-select form-select-sm">
                    <option value="">Filtrar por Servidor: Todos</option>
                    <?php foreach ($servidores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo ($filtroServidor === (int)$s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtroServidor > 0): ?>
                <div class="col-12 col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm w-100">Limpar Filtro</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABELA DE ARTIGOS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th>Título do Artigo</th>
                        <th style="width: 150px;">Servidor</th>
                        <th style="width: 180px;">Categoria</th>
                        <th style="width: 100px;">Visualizações</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 140px;">Data</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-artigos-wiki">
                    <tr id="linha-wiki-filtro-vazio" class="d-none">
                        <td colspan="7" class="text-center py-4">
                            <div class="admin-empty-state py-3">
                                <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                                <h6 class="empty-title">Nenhum artigo encontrado</h6>
                                <p class="empty-desc">Nenhum artigo corresponde à pesquisa ou filtro selecionado.</p>
                            </div>
                        </td>
                    </tr>
                    <?php if (empty($artigos)): ?>
                        <tr id="linha-artigos-vazia">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
                                    <h6 class="empty-title">Nenhum artigo cadastrado</h6>
                                    <p class="empty-desc">Nenhum artigo cadastrado ainda para este servidor.</p>
                                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCriarArtigo">
                                        <i class="fa-solid fa-plus me-1"></i> Criar Primeiro Artigo
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($artigos as $art): ?>
                            <tr id="artigo-row-<?php echo (int)$art['id']; ?>"
                                data-id="<?php echo (int)$art['id']; ?>"
                                data-titulo="<?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-slug="<?php echo htmlspecialchars($art['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-servidor-id="<?php echo (int)$art['servidor_id']; ?>"
                                data-categoria-id="<?php echo (int)$art['categoria_id']; ?>"
                                data-autor="<?php echo htmlspecialchars($art['autor'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-publicado="<?php echo (int)$art['publicado']; ?>"
                                data-conteudo="<?php echo htmlspecialchars($art['conteudo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-visualizacoes="<?php echo (int)$art['visualizacoes']; ?>"
                                data-criado-em="<?php echo date('d/m/Y H:i', strtotime($art['criado_em'])); ?>"
                                data-servername="<?php echo htmlspecialchars($art['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-servidor-slug="<?php echo htmlspecialchars($art['servidor_slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-themecolor="<?php echo htmlspecialchars($art['themecolor'] ?? '#64748b', ENT_QUOTES, 'UTF-8'); ?>"
                                data-categoria-nome="<?php echo htmlspecialchars($art['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <strong class="text-dark js-artigo-titulo"><?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="text-muted d-block font-monospace js-artigo-link" style="font-size: 0.7rem;">/wiki/artigo.php?s=<?php echo urlencode($art['servidor_slug']); ?>&slug=<?php echo urlencode($art['slug']); ?></small>
                                </td>
                                <td>
                                    <span class="badge js-artigo-servidor" style="background-color: <?php echo htmlspecialchars($art['themecolor'] ?? '#64748b', ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <span class="js-artigo-servername"><?php echo htmlspecialchars($art['servername'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-dark border js-artigo-categoria"><?php echo htmlspecialchars($art['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="badge bg-light text-dark border font-monospace js-artigo-views"><?php echo (int)$art['visualizacoes']; ?></span></td>
                                <td>
                                    <span class="badge-status <?php echo $art['publicado'] ? 'ativo' : 'inativo'; ?> js-artigo-status" data-status-badge="1" data-status-publicado="<?php echo $art['publicado'] ? '1' : '0'; ?>">
                                        <?php echo $art['publicado'] ? 'Publicado' : 'Oculto'; ?>
                                    </span>
                                </td>
                                <td class="text-muted small js-artigo-data"><?php echo date('d/m/Y H:i', strtotime($art['criado_em'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-artigo" title="Editar Artigo">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/wiki/toggle.php" 
                                                data-id="<?php echo (int)$art['id']; ?>" 
                                                title="<?php echo $art['publicado'] ? 'Ocultar' : 'Publicar'; ?>">
                                            <i class="fa-solid <?php echo $art['publicado'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/wiki/deletar.php" 
                                                data-id="<?php echo (int)$art['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                data-confirm="Deletar o artigo <?php echo htmlspecialchars($art['titulo'], ENT_QUOTES, 'UTF-8'); ?> da wiki?" 
                                                title="Deletar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CRIAR ARTIGO -->
<div class="modal fade" id="modalCriarArtigo" tabindex="-1" aria-labelledby="modalCriarArtigoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content shadow">
            <form id="formCriarArtigo">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarArtigoLabel">Novo Artigo da Wiki</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="criar-servidor_id" class="form-label fw-bold">Servidor de Destino <span class="text-danger">*</span></label>
                            <select class="form-select" id="criar-servidor_id" name="servidor_id" required onchange="carregarCategorias(this.value, 'criar-categoria_id')">
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ($primeiroServidorId === (int)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label for="criar-categoria_id" class="form-label fw-bold mb-0">Categoria <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="abrirModalNovaCategoria('criar')">
                                    Nova Categoria
                                </button>
                            </div>
                            <select class="form-select" id="criar-categoria_id" name="categoria_id" required>
                                <?php foreach ($categoriasIniciais as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>">
                                        <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label for="criar-titulo" class="form-label fw-bold">Título do Artigo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criar-titulo" name="titulo" placeholder="Ex: Como criar e proteger seu terreno" required>
                        </div>
                        <div class="col-md-4">
                            <label for="criar-autor" class="form-label fw-bold">Autor <span class="text-danger">*</span></label>
                            <select class="form-select" id="criar-autor" name="autor" required>
                                <?php foreach ($nicksEquipe as $nk): ?>
                                    <option value="<?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label for="criar-conteudo" class="form-label fw-bold mb-0">Conteúdo do Artigo (Markdown) <span class="text-danger">*</span></label>
                            <span class="badge bg-light text-dark border">Markdown suportado</span>
                        </div>
                        <?php 
                        $toolbarTarget = 'criar-conteudo';
                        require __DIR__ . "/../includes/wiki_editor_toolbar.php"; 
                        ?>
                        <textarea class="form-control font-monospace" id="criar-conteudo" name="conteudo" rows="12" placeholder="# Introdução&#10;&#10;Descreva o guia detalhadamente aqui..." required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="criar-publicado" name="publicado" value="1" checked>
                        <label class="form-check-label fw-semibold" for="criar-publicado">Publicar imediatamente na Wiki pública</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-artigo">
                        <i class="fa-solid fa-check me-1"></i> Criar Artigo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR ARTIGO -->
<div class="modal fade" id="modalEditarArtigo" tabindex="-1" aria-labelledby="modalEditarArtigoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content shadow">
            <form id="formEditarArtigo">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-artigo-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarArtigoLabel">
                        Editar Artigo: <span id="modalEditarArtigoTituloHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="edit-servidor_id" class="form-label fw-bold">Servidor de Destino <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit-servidor_id" name="servidor_id" required onchange="carregarCategorias(this.value, 'edit-categoria_id')">
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>">
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label for="edit-categoria_id" class="form-label fw-bold mb-0">Categoria <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="abrirModalNovaCategoria('edit')">
                                    Nova Categoria
                                </button>
                            </div>
                            <select class="form-select" id="edit-categoria_id" name="categoria_id" required>
                                <!-- Preenchido dinamicamente via JS -->
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label for="edit-titulo" class="form-label fw-bold">Título do Artigo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-titulo" name="titulo" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-autor" class="form-label fw-bold">Autor <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit-autor" name="autor" required>
                                <?php foreach ($nicksEquipe as $nk): ?>
                                    <option value="<?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($nk, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label for="edit-conteudo" class="form-label fw-bold mb-0">Conteúdo do Artigo (Markdown) <span class="text-danger">*</span></label>
                            <span class="badge bg-light text-dark border">Markdown suportado</span>
                        </div>
                        <?php 
                        $toolbarTarget = 'edit-conteudo';
                        require __DIR__ . "/../includes/wiki_editor_toolbar.php"; 
                        ?>
                        <textarea class="form-control font-monospace" id="edit-conteudo" name="conteudo" rows="12" required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit-publicado" name="publicado" value="1">
                        <label class="form-check-label fw-semibold" for="edit-publicado">Publicar imediatamente na Wiki pública</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-artigo">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL RÁPIDO PARA CRIAR NOVA CATEGORIA -->
<div class="modal fade" id="modalNovaCategoriaRapida" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow">
            <form id="form-nova-cat-rapida" onsubmit="criarCategoriaRapida(event)">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Nova Categoria</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="feedback-cat-rapida" class="alert alert-danger small d-none py-1 px-2 mb-2"></div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Nome da Categoria</label>
                        <input type="text" id="cat-rapida-nome" class="form-control form-control-sm" placeholder="Ex: Clãs & Guerras" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Ícone (FontAwesome)</label>
                        <input type="text" id="cat-rapida-icone" class="form-control form-control-sm" value="fa-solid fa-folder">
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-bold mb-1">Ordem</label>
                        <input type="number" id="cat-rapida-ordem" class="form-control form-control-sm" value="0" min="0">
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
let origemModalCategoria = 'criar';

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Toggle geral da Wiki
function alternarWikiGeral(btn) {
    if (!window.AdminApi || !window.AdminToast) {
        if (window.AdminToast) { window.AdminToast.error('Recursos assíncronos indisponíveis. Recarregue a página.'); }
        return;
    }

    const estavaAtiva = btn.dataset.wikiAtiva === '1';
    const novoValor = estavaAtiva ? '0' : '1';
    const label = estavaAtiva ? 'Wiki ocultada no site.' : 'Wiki ativada no site.';
    const htmlOriginal = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';

    window.AdminApi.post('/admin/api/wiki/toggle_geral.php', { wiki_habilitada: novoValor })
        .then(res => {
            const ativa = res.wiki_habilitada !== undefined ? Boolean(res.wiki_habilitada) : !estavaAtiva;
            btn.dataset.wikiAtiva = ativa ? '1' : '0';
            btn.className = 'btn btn-sm ' + (ativa ? 'btn-outline-success' : 'btn-outline-danger');
            btn.title = ativa ? 'Clique para ocultar a Wiki no site' : 'Clique para ativar a visualização pública da Wiki';
            btn.innerHTML = '<i class="fa-solid ' + (ativa ? 'fa-globe' : 'fa-eye-slash') + ' me-1"></i> Wiki: ' + (ativa ? 'Ativa' : 'Oculta');
            window.AdminToast.success(res.mensagem || label);
        })
        .catch(err => {
            btn.innerHTML = htmlOriginal;
            window.AdminToast.error(err && err.message ? err.message : 'Erro ao alterar status da Wiki.');
        })
        .finally(() => {
            btn.disabled = false;
        });
}

// Carregar categorias dinamicamente por servidor
async function carregarCategorias(servidorId, targetSelectId, categoriaIdPreSelecionada = null) {
    const select = document.getElementById(targetSelectId);
    if (!select) return;

    select.innerHTML = '<option value="">Carregando categorias...</option>';
    try {
        const res = await fetch(`/admin/api/wiki/categorias.php?servidor_id=${servidorId}`);
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

function abrirModalNovaCategoria(origem) {
    origemModalCategoria = origem;
    const modalEl = document.getElementById('modalNovaCategoriaRapida');
    if (modalEl) {
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }
}

async function criarCategoriaRapida(e) {
    e.preventDefault();
    const servidorSelectId = origemModalCategoria === 'edit' ? 'edit-servidor_id' : 'criar-servidor_id';
    const catSelectId = origemModalCategoria === 'edit' ? 'edit-categoria_id' : 'criar-categoria_id';
    const servidorId = document.getElementById(servidorSelectId)?.value || 0;

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
        const res = await fetch('/admin/api/wiki/categoria_criar.php?ajax=1', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success && data.categoria) {
            await carregarCategorias(servidorId, catSelectId, data.categoria.id);
            const modalEl = document.getElementById('modalNovaCategoriaRapida');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            document.getElementById('form-nova-cat-rapida').reset();
            if (window.AdminToast) {
                window.AdminToast.success(`Categoria '${nome}' criada com sucesso!`);
            }
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

document.addEventListener('DOMContentLoaded', function() {
    const modalCriarEl = document.getElementById('modalCriarArtigo');
    const modalEditarEl = document.getElementById('modalEditarArtigo');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    const listaArtigos = document.getElementById('lista-artigos-wiki');
    const contadorArtigos = document.getElementById('contador-artigos-wiki');

    function atualizarContadorArtigos() {
        if (!contadorArtigos || !listaArtigos) return;
        const total = listaArtigos.querySelectorAll('tr[data-id]').length;
        contadorArtigos.textContent = total + ' ' + (total === 1 ? 'artigo' : 'artigos');
    }

    function renderLinhaArtigo(art) {
        const tr = document.createElement('tr');
        tr.id = `artigo-row-${art.id}`;
        tr.dataset.id = art.id;
        tr.dataset.titulo = art.titulo || '';
        tr.dataset.slug = art.slug || '';
        tr.dataset.servidorId = art.servidor_id || '';
        tr.dataset.categoriaId = art.categoria_id || '';
        tr.dataset.autor = art.autor || '';
        tr.dataset.publicado = art.publicado ? '1' : '0';
        tr.dataset.conteudo = art.conteudo || '';
        tr.dataset.visualizacoes = art.visualizacoes || '0';
        tr.dataset.criadoEm = art.criado_em || '';
        tr.dataset.servername = art.servername || '';
        tr.dataset.servidorSlug = art.servidor_slug || '';
        tr.dataset.themecolor = art.themecolor || '#64748b';
        tr.dataset.categoriaNome = art.categoria_nome || '';

        const dataFormatada = art.criado_em ? (new Date(art.criado_em.replace(' ', 'T')).toLocaleDateString('pt-BR') + ' ' + new Date(art.criado_em.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })) : 'Agora';

        tr.innerHTML = `
            <td>
                <strong class="text-dark js-artigo-titulo">${escapeHtml(art.titulo)}</strong>
                <small class="text-muted d-block font-monospace js-artigo-link" style="font-size: 0.7rem;">/wiki/artigo.php?s=${encodeURIComponent(art.servidor_slug || '')}&slug=${encodeURIComponent(art.slug || '')}</small>
            </td>
            <td>
                <span class="badge js-artigo-servidor" style="background-color: ${escapeHtml(art.themecolor || '#64748b')}; color: #fff; font-weight: 600;">
                    <i class="fa-solid fa-server me-1"></i>
                    <span class="js-artigo-servername">${escapeHtml(art.servername || '')}</span>
                </span>
            </td>
            <td><span class="badge bg-light text-dark border js-artigo-categoria">${escapeHtml(art.categoria_nome || '')}</span></td>
            <td><span class="badge bg-light text-dark border font-monospace js-artigo-views">${parseInt(art.visualizacoes || 0, 10)}</span></td>
            <td>
                <span class="badge-status ${art.publicado ? 'ativo' : 'inativo'} js-artigo-status" data-status-badge="1" data-status-publicado="${art.publicado ? '1' : '0'}">
                    ${art.publicado ? 'Publicado' : 'Oculto'}
                </span>
            </td>
            <td class="text-muted small js-artigo-data">${dataFormatada}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-artigo" title="Editar Artigo">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-action="async-toggle" 
                            data-url="/admin/api/wiki/toggle.php" 
                            data-id="${art.id}" 
                            title="${art.publicado ? 'Ocultar' : 'Publicar'}">
                        <i class="fa-solid ${art.publicado ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/wiki/deletar.php" 
                            data-id="${art.id}" 
                            data-name="${escapeHtml(art.titulo)}" 
                            data-confirm="Deletar o artigo ${escapeHtml(art.titulo)} da wiki?" 
                            title="Deletar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // Modal Editar: abrir e preencher
    function abrirModalEditarArtigo(dataset) {
        document.getElementById('edit-artigo-id').value = dataset.id || '';
        document.getElementById('modalEditarArtigoTituloHeader').textContent = dataset.titulo || '';
        document.getElementById('edit-titulo').value = dataset.titulo || '';
        document.getElementById('edit-conteudo').value = dataset.conteudo || '';
        document.getElementById('edit-publicado').checked = (dataset.publicado === '1');

        // Selecionar autor se existir na lista
        const autorSelect = document.getElementById('edit-autor');
        if (autorSelect) {
            let found = false;
            for (let opt of autorSelect.options) {
                if (opt.value === dataset.autor) {
                    opt.selected = true;
                    found = true;
                    break;
                }
            }
            if (!found && dataset.autor) {
                const newOpt = document.createElement('option');
                newOpt.value = dataset.autor;
                newOpt.textContent = dataset.autor;
                newOpt.selected = true;
                autorSelect.appendChild(newOpt);
            }
        }

        // Selecionar servidor e carregar categorias correspondentes
        const srvSelect = document.getElementById('edit-servidor_id');
        if (srvSelect) {
            srvSelect.value = dataset.servidorId || '';
        }
        carregarCategorias(dataset.servidorId, 'edit-categoria_id', dataset.categoriaId);

        if (bsModalEditar) bsModalEditar.show();
    }

    // Event delegation para botão de editar
    document.addEventListener('click', function(e) {
        const btnEdit = e.target.closest('.btn-abrir-editar-artigo');
        if (btnEdit) {
            const tr = btnEdit.closest('tr');
            if (tr && tr.dataset) {
                abrirModalEditarArtigo(tr.dataset);
            }
        }
    });

    // Submissão do Modal Criar Artigo
    const formCriar = document.getElementById('formCriarArtigo');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-criar-artigo');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Criando...';

            const formData = new FormData(formCriar);

            try {
                const res = await fetch('/admin/api/wiki/criar.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success && data.artigo) {
                    if (window.AdminToast) {
                        window.AdminToast.success(data.mensagem || 'Artigo criado com sucesso!');
                    }

                    // Remove placeholder de vazio se existir
                    const linhaVazia = document.getElementById('linha-artigos-vazia');
                    if (linhaVazia) linhaVazia.remove();

                    // Insere nova linha no topo da tabela
                    const novaLinha = renderLinhaArtigo(data.artigo);
                    if (listaArtigos) {
                        listaArtigos.prepend(novaLinha);
                    }

                    atualizarContadorArtigos();
                    formCriar.reset();
                    if (bsModalCriar) bsModalCriar.hide();
                } else {
                    if (window.AdminToast) { window.AdminToast.error(data.erro || 'Erro ao criar artigo.'); }
                }
            } catch (err) {
                if (window.AdminToast) { window.AdminToast.error('Erro de conexão ao criar artigo.'); }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

    // Submissão do Modal Editar Artigo
    const formEditar = document.getElementById('formEditarArtigo');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-editar-artigo');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';

            const formData = new FormData(formEditar);
            const id = document.getElementById('edit-artigo-id').value;

            try {
                const res = await fetch('/admin/api/wiki/editar.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success && data.artigo) {
                    if (window.AdminToast) {
                        window.AdminToast.success(data.mensagem || 'Artigo atualizado com sucesso!');
                    }

                    // Atualiza a linha existente na tabela
                    const rowAntiga = document.getElementById(`artigo-row-${id}`);
                    if (rowAntiga) {
                        const rowNova = renderLinhaArtigo(data.artigo);
                        rowAntiga.replaceWith(rowNova);
                    }

                    if (bsModalEditar) bsModalEditar.hide();
                } else {
                    if (window.AdminToast) { window.AdminToast.error(data.erro || 'Erro ao atualizar artigo.'); }
                }
            } catch (err) {
                if (window.AdminToast) { window.AdminToast.error('Erro de conexão ao atualizar artigo.'); }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

        // Filtro instantâneo em tempo real (busca textual + servidor)
    const buscaInput = document.getElementById('filtro-wiki-busca');
    const servidorSelect = document.getElementById('filtro-wiki-servidor');
    const emptyFilterRow = document.getElementById('linha-wiki-filtro-vazio');

    function aplicarFiltroArtigos() {
        const termo = buscaInput ? buscaInput.value.trim().toLowerCase() : '';
        const servidorId = servidorSelect ? servidorSelect.value : '';
        const rows = document.querySelectorAll('#lista-artigos-wiki tr[data-id]');
        let visiveis = 0;

        rows.forEach(r => {
            const titulo = (r.dataset.titulo || '').toLowerCase();
            const autor = (r.dataset.autor || '').toLowerCase();
            const cat = (r.dataset.categoriaNome || '').toLowerCase();
            const srvId = r.dataset.servidorId || '';

            const matchTermo = !termo || titulo.includes(termo) || autor.includes(termo) || cat.includes(termo);
            const matchServidor = !servidorId || srvId === servidorId;

            if (matchTermo && matchServidor) {
                r.style.display = '';
                visiveis++;
            } else {
                r.style.display = 'none';
            }
        });

        if (emptyFilterRow) {
            if (visiveis === 0 && rows.length > 0) {
                emptyFilterRow.classList.remove('d-none');
            } else {
                emptyFilterRow.classList.add('d-none');
            }
        }
    }

    if (buscaInput) buscaInput.addEventListener('input', aplicarFiltroArtigos);
    if (servidorSelect) servidorSelect.addEventListener('change', aplicarFiltroArtigos);

    // Deep link para abrir modal diretamente via URL (?action=novo ou ?edit=ID)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'novo') {
        if (bsModalCriar) bsModalCriar.show();
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('edit')) {
        const editId = urlParams.get('edit');
        const targetRow = document.getElementById(`artigo-row-${editId}`);
        if (targetRow && targetRow.dataset) {
            abrirModalEditarArtigo(targetRow.dataset);
        }
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
