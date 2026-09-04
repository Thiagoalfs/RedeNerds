<?php
$paginaAtiva = 'wiki_categorias';
$tituloPagina = 'Gerenciar Categorias da Wiki';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../../wiki/wiki_helper.php";

$servidores = $pdo->query("SELECT id, servername, nome FROM servidores ORDER BY servername ASC")->fetchAll(PDO::FETCH_ASSOC);

$filtroServidor = isset($_GET['servidor_id']) ? (int)$_GET['servidor_id'] : ($servidores[0]['id'] ?? 0);
if ($filtroServidor <= 0 && !empty($servidores)) {
    $filtroServidor = (int)$servidores[0]['id'];
}

$servidorSelecionado = null;
foreach ($servidores as $s) {
    if ((int)$s['id'] === $filtroServidor) {
        $servidorSelecionado = $s;
        break;
    }
}

$categorias = [];
if ($filtroServidor > 0) {
    $categorias = getCategoriasServidorWiki($pdo, $filtroServidor);
}

$sucesso = $_GET['sucesso'] ?? '';
$erro = $_GET['erro'] ?? '';
?>

<!-- NAVEGAÇÃO DE SUB-ABAS DA WIKI -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Wiki & Base de Conhecimento</h4>
        <p class="text-muted small mb-0">Gerenciamento de categorias de tópicos por servidor</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/wiki/" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Wiki Pública</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaCategoria">
            <i class="fa-solid fa-plus me-1"></i> Nova Categoria
        </button>
    </div>
</div>

<!-- SUB-ABAS DE NAVEGAÇÃO -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-regular fa-file-lines me-1"></i> Artigos da Wiki
    </a>
    <a href="categorias.php<?php echo $filtroServidor ? '?servidor_id=' . $filtroServidor : ''; ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-folder-tree me-1"></i> Gerenciar Categorias
    </a>
</div>

<!-- ALERTAS DE FEEDBACK -->
<?php if ($sucesso === 'criado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Categoria cadastrada com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php elseif ($sucesso === 'editado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Categoria atualizada com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php elseif ($sucesso === 'deletado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Categoria excluída com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<!-- SELETOR DE SERVIDOR -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="categorias.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-bold mb-1">Selecione o Servidor:</label>
                <select name="servidor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($servidores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo ($filtroServidor === (int)$s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end justify-content-md-end">
                <span class="text-muted small">Mostrando categorias de: <strong><?php echo htmlspecialchars($servidorSelecionado['servername'] ?? 'Nenhum', ENT_QUOTES, 'UTF-8'); ?></strong></span>
            </div>
        </form>
    </div>
</div>

<!-- LISTA DE CATEGORIAS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">Ícone</th>
                        <th>Nome da Categoria</th>
                        <th>Slug</th>
                        <th style="width: 100px;">Ordem</th>
                        <th style="width: 120px;">Qtd. Artigos</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhuma categoria cadastrada para este servidor.
                                <br><small>Clique em "+ Nova Categoria" acima para criar a primeira.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td class="text-center">
                                    <i class="<?php echo htmlspecialchars($cat['icone'] ?: 'fa-solid fa-folder', ENT_QUOTES, 'UTF-8'); ?> text-primary fs-5"></i>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <td>
                                    <code class="text-muted"><?php echo htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'); ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo (int)$cat['ordem']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo (int)$cat['total_artigos']; ?> artigo(s)</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalEditarCategoria(<?php echo (int)$cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['nome']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($cat['icone']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$cat['ordem']; ?>)">
                                            <i class="fa-solid fa-pen-to-square"></i> Editar
                                        </button>
                                        <form method="POST" action="/admin/api/wiki/categoria_deletar.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Excluir" <?php echo ($cat['total_artigos'] > 0) ? 'disabled title="Mova os artigos antes de excluir"' : ''; ?>>
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- MODAL: NOVA CATEGORIA -->
<div class="modal fade" id="modalNovaCategoria" tabindex="-1" aria-labelledby="modalNovaCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/api/wiki/categoria_criar.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="servidor_id" value="<?php echo (int)$filtroServidor; ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalNovaCategoriaLabel">
                        <i class="fa-solid fa-plus-circle text-success me-2"></i> Nova Categoria da Wiki
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Servidor</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?php echo htmlspecialchars($servidorSelecionado['servername'] ?? 'Nenhum', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome da Categoria <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Primeiros Passos, Mineração, Economia" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ícone (Classe FontAwesome)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i id="preview-novo-icone" class="fa-solid fa-folder text-primary"></i></span>
                            <input type="text" id="input-novo-icone" name="icone" class="form-control" value="fa-solid fa-folder" placeholder="Ex: fa-solid fa-dragon" oninput="atualizarPreviewIcone(this.value, 'preview-novo-icone')">
                        </div>
                        <div class="form-text small mt-1">
                            Sugestões: 
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-compass', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none me-2"><i class="fa-solid fa-compass"></i> Compass</a>
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-gears', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none me-2"><i class="fa-solid fa-gears"></i> Gears</a>
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-wand-magic-sparkles', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none me-2"><i class="fa-solid fa-wand-magic-sparkles"></i> Magic</a>
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-shield-halved', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none me-2"><i class="fa-solid fa-shield-halved"></i> Shield</a>
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-dragon', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none me-2"><i class="fa-solid fa-dragon"></i> Dragon</a>
                            <a href="javascript:void(0)" onclick="definirIconeRapido('fa-solid fa-flask', 'input-novo-icone', 'preview-novo-icone')" class="text-decoration-none"><i class="fa-solid fa-flask"></i> Flask</a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ordem de Exibição</label>
                        <input type="number" name="ordem" class="form-control form-control-sm" value="0" min="0">
                        <div class="form-text small">Categorias com menor valor numérico aparecem primeiro na barra lateral.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check me-1"></i> Salvar Categoria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CATEGORIA -->
<div class="modal fade" id="modalEditarCategoria" tabindex="-1" aria-labelledby="modalEditarCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/api/wiki/categoria_editar.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-cat-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarCategoriaLabel">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Categoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome da Categoria <span class="text-danger">*</span></label>
                        <input type="text" id="edit-cat-nome" name="nome" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ícone (Classe FontAwesome)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i id="preview-edit-icone" class="fa-solid fa-folder text-primary"></i></span>
                            <input type="text" id="edit-cat-icone" name="icone" class="form-control" oninput="atualizarPreviewIcone(this.value, 'preview-edit-icone')">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ordem de Exibição</label>
                        <input type="number" id="edit-cat-ordem" name="ordem" class="form-control form-control-sm" min="0">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i> Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function atualizarPreviewIcone(val, previewId) {
    const el = document.getElementById(previewId);
    if (!el) return;
    el.className = val.trim() || 'fa-solid fa-folder';
}

function definirIconeRapido(iconClass, inputId, previewId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = iconClass;
        atualizarPreviewIcone(iconClass, previewId);
    }
}

function abrirModalEditarCategoria(id, nome, icone, ordem) {
    document.getElementById('edit-cat-id').value = id;
    document.getElementById('edit-cat-nome').value = nome;
    document.getElementById('edit-cat-icone').value = icone || 'fa-solid fa-folder';
    document.getElementById('edit-cat-ordem').value = ordem || 0;
    atualizarPreviewIcone(icone, 'preview-edit-icone');

    const modal = new bootstrap.Modal(document.getElementById('modalEditarCategoria'));
    modal.show();
}
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
