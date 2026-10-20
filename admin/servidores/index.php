<?php
$paginaAtiva = 'servidores';
$tituloPagina = 'Servidores';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/icon_upload.php";

try {
    $stmt = $pdo->query("SELECT id, servername, nome, title, icon, bg_image, descricao, features, modpackurl, ip, themecolor, enabled FROM servidores ORDER BY servername ASC");
    $servidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $servidoresVisiveis = [];
    $servidoresOcultos = [];
    foreach ($servidores as $s) {
        if (!empty($s['enabled'])) {
            $servidoresVisiveis[] = $s;
        } else {
            $servidoresOcultos[] = $s;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar servidores: " . $e->getMessage());
    $servidores = [];
    $servidoresVisiveis = [];
    $servidoresOcultos = [];
}

function renderIconeTable(?string $icone): string {
    if (!$icone) return '<i class="fa-solid fa-server"></i>';
    if (tipoDoIcone($icone) === 'img') {
        $src = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="rounded" width="32" height="32" style="object-fit: cover;" alt="">';
    }
    return '<i class="' . htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') . '"></i>';
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Servidores</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($servidores), 'servidor', 'servidores'); ?></span> <?php echo count($servidores) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($servidoresVisiveis), 'visível', 'visíveis'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($servidoresOcultos), 'oculto', 'ocultos'); ?></span>
        </p>
    </div>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarServidor">
        <i class="fa-solid fa-plus me-1"></i> Novo servidor
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- TABELA 1: SERVIDORES VISÍVEIS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Servidores Visíveis</h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($servidoresVisiveis), 'servidor', 'servidores'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-servidores-ativos">
                <thead>
                    <tr>
                        <th style="width: 60px;">Ícone</th>
                        <th>Servidor</th>
                        <th style="width: 140px;">Slug</th>
                        <th style="width: 220px;">IP de Conexão</th>
                        <th style="width: 120px;">Tema</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-servidores-ativos">
                    <?php if (empty($servidoresVisiveis)): ?>
                        <tr class="empty-row" id="linha-sem-servidores-ativos">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-solid fa-server"></i></div>
                                    <h6 class="empty-title">Nenhum servidor ativo</h6>
                                    <p class="empty-desc">Cadastre um novo servidor para ser exibido no site e na loja.</p>
                                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCriarServidor">
                                        <i class="fa-solid fa-plus me-1"></i> Cadastrar Servidor
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servidoresVisiveis as $s): ?>
                            <tr id="servidor-row-<?php echo (int)$s['id']; ?>"
                                data-id="<?php echo (int)$s['id']; ?>"
                                data-servername="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-nome="<?php echo htmlspecialchars($s['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ip="<?php echo htmlspecialchars($s['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-descricao="<?php echo htmlspecialchars($s['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-modpackurl="<?php echo htmlspecialchars($s['modpackurl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-themecolor="<?php echo htmlspecialchars($s['themecolor'] ?? '#B971DA', ENT_QUOTES, 'UTF-8'); ?>"
                                data-icon="<?php echo htmlspecialchars($s['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-bg-image="<?php echo htmlspecialchars($s['bg_image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-features="<?php echo htmlspecialchars($s['features'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>"
                                data-enabled="<?php echo !empty($s['enabled']) ? '1' : '0'; ?>">
                                <td>
                                    <div class="d-flex align-items-center justify-content-center bg-light rounded js-srv-icone-box" style="width: 36px; height: 36px; color: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; font-size: 1.1rem;">
                                        <?php echo renderIconeTable($s['icon']); ?>
                                    </div>
                                </td>
                                <td><strong class="js-srv-servername"><?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><code class="js-srv-slug"><?php echo htmlspecialchars($s['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><small class="font-monospace text-muted js-srv-ip"><?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 js-srv-tema-box">
                                        <span class="js-srv-color-dot" style="display:inline-block; width:16px; height:16px; border-radius:4px; background:<?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; border:1px solid rgba(0,0,0,0.15);"></span>
                                        <small class="font-monospace js-srv-color-text"><?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status visivel">Visível</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-servidor" title="Editar Servidor">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/servidores/toggle.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                title="Ocultar servidor">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/servidores/deletar.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o servidor <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
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

<!-- TABELA 2: SERVIDORES OCULTOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Servidores Ocultos</h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($servidoresOcultos), 'servidor', 'servidores'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-servidores-inativos">
                <thead>
                    <tr>
                        <th style="width: 60px;">Ícone</th>
                        <th>Servidor</th>
                        <th style="width: 140px;">Slug</th>
                        <th style="width: 220px;">IP de Conexão</th>
                        <th style="width: 120px;">Tema</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-servidores-inativos">
                    <?php if (empty($servidoresOcultos)): ?>
                        <tr class="empty-row" id="linha-sem-servidores-inativos">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
                                    <h6 class="empty-title">Nenhum servidor oculto</h6>
                                    <p class="empty-desc">Todos os servidores cadastrados estão públicos e visíveis.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servidoresOcultos as $s): ?>
                            <tr id="servidor-row-<?php echo (int)$s['id']; ?>"
                                data-id="<?php echo (int)$s['id']; ?>"
                                data-servername="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-nome="<?php echo htmlspecialchars($s['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ip="<?php echo htmlspecialchars($s['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-descricao="<?php echo htmlspecialchars($s['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-modpackurl="<?php echo htmlspecialchars($s['modpackurl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-themecolor="<?php echo htmlspecialchars($s['themecolor'] ?? '#B971DA', ENT_QUOTES, 'UTF-8'); ?>"
                                data-icon="<?php echo htmlspecialchars($s['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-bg-image="<?php echo htmlspecialchars($s['bg_image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-features="<?php echo htmlspecialchars($s['features'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>"
                                data-enabled="<?php echo !empty($s['enabled']) ? '1' : '0'; ?>">
                                <td>
                                    <div class="d-flex align-items-center justify-content-center bg-light rounded js-srv-icone-box" style="width: 36px; height: 36px; color: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; font-size: 1.1rem;">
                                        <?php echo renderIconeTable($s['icon']); ?>
                                    </div>
                                </td>
                                <td><strong class="js-srv-servername"><?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><code class="js-srv-slug"><?php echo htmlspecialchars($s['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><small class="font-monospace text-muted js-srv-ip"><?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 js-srv-tema-box">
                                        <span class="js-srv-color-dot" style="display:inline-block; width:16px; height:16px; border-radius:4px; background:<?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>; border:1px solid rgba(0,0,0,0.15);"></span>
                                        <small class="font-monospace js-srv-color-text"><?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status pendente">Oculto</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-servidor" title="Editar Servidor">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/servidores/toggle.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                title="Tornar servidor visível">
                                            <i class="fa-solid fa-eye-slash"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/servidores/deletar.php" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Deletar o servidor <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita."
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

<!-- MODAL: CRIAR SERVIDOR -->
<div class="modal fade" id="modalCriarServidor" tabindex="-1" aria-labelledby="modalCriarServidorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content shadow">
            <form id="formCriarServidor" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarServidorLabel">Cadastrar Novo Servidor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="criar-srv-servername" class="form-label fw-bold">Nome do Servidor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criar-srv-servername" name="servername" placeholder="Ex: Potato Nerds" required>
                        </div>
                        <div class="col-md-6">
                            <label for="criar-srv-ip" class="form-label fw-bold">IP de Conexão <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="criar-srv-ip" name="ip" placeholder="Ex: jogar.redenerds.com.br" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="criar-srv-descricao" class="form-label fw-bold">Descrição <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="criar-srv-descricao" name="descricao" rows="2" placeholder="Descrição curta do servidor..." required></textarea>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label for="criar-srv-modpackurl" class="form-label fw-bold">URL do Modpack / Launcher <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="criar-srv-modpackurl" name="modpackurl" placeholder="https://..." required>
                        </div>
                        <div class="col-md-4">
                            <label for="criar-srv-themecolor" class="form-label fw-bold">Cor do Tema <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="criar-srv-themecolor-picker" value="#B971DA">
                                <input type="text" class="form-control font-monospace" id="criar-srv-themecolor" name="themecolor" value="#B971DA" maxlength="7">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ícone do Servidor</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="criar-srv-icon" name="icon_upload" accept="image/*">
                                <div id="criar-srv-icon-preview" class="bg-light border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 42px; height: 42px;">
                                    <i class="fa-solid fa-server text-muted"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Background do Servidor (Wallpaper)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="criar-srv-bg" name="bg_upload" accept="image/*">
                                <div id="criar-srv-bg-preview" class="bg-dark border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 70px; height: 42px;">
                                    <span class="text-white-50" style="font-size: 0.65rem;">Sem fundo</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Destaques / Features</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-feature-criar-srv">
                                Adicionar Feature
                            </button>
                        </label>
                        <div id="container-features-criar-srv" class="d-flex flex-column gap-2">
                            <div class="input-group input-group-sm feature-row">
                                <input type="text" class="form-control" name="features[]" value="Economia balanceada" placeholder="Ex: Novo recurso" required>
                                <button type="button" class="btn btn-outline-danger btn-remove-feature"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="criar-srv-enabled" name="enabled" value="1" checked>
                            <label class="form-check-label fw-bold" for="criar-srv-enabled">Servidor Ativo e Visível no Site</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-servidor">
                        <i class="fa-solid fa-check me-1"></i> Cadastrar Servidor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR SERVIDOR -->
<div class="modal fade" id="modalEditarServidor" tabindex="-1" aria-labelledby="modalEditarServidorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content shadow">
            <form id="formEditarServidor" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-srv-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarServidorLabel">
                        Editar Servidor: <span id="modalEditarServidorNomeHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="edit-srv-servername" class="form-label fw-bold">Nome do Servidor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-srv-servername" name="servername" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-srv-ip" class="form-label fw-bold">IP de Conexão <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-srv-ip" name="ip" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="edit-srv-descricao" class="form-label fw-bold">Descrição <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit-srv-descricao" name="descricao" rows="2" required></textarea>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label for="edit-srv-modpackurl" class="form-label fw-bold">URL do Modpack / Launcher <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="edit-srv-modpackurl" name="modpackurl" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-srv-themecolor" class="form-label fw-bold">Cor do Tema <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="edit-srv-themecolor-picker" value="#B971DA">
                                <input type="text" class="form-control font-monospace" id="edit-srv-themecolor" name="themecolor" value="#B971DA" maxlength="7">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ícone do Servidor</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="edit-srv-icon" name="icon_upload" accept="image/*">
                                <div id="edit-srv-icon-preview" class="bg-light border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 42px; height: 42px;">
                                    <i class="fa-solid fa-server text-muted"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Background do Servidor (Wallpaper)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" class="form-control" id="edit-srv-bg" name="bg_upload" accept="image/*">
                                <div id="edit-srv-bg-preview" class="bg-dark border rounded d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 70px; height: 42px;">
                                    <span class="text-white-50" style="font-size: 0.65rem;">Sem fundo</span>
                                </div>
                            </div>
                            <div class="form-check mt-1" id="edit-srv-remover-bg-box" style="display: none;">
                                <input class="form-check-input" type="checkbox" id="edit-srv-remover-bg" name="remover_bg" value="1">
                                <label class="form-check-label small text-danger" for="edit-srv-remover-bg">Remover background atual</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
                            <span>Destaques / Features</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-feature-edit-srv">
                                Adicionar Feature
                            </button>
                        </label>
                        <div id="container-features-edit-srv" class="d-flex flex-column gap-2"></div>
                    </div>

                    <div class="pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit-srv-enabled" name="enabled" value="1">
                            <label class="form-check-label fw-bold" for="edit-srv-enabled">Servidor Ativo e Visível no Site</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-servidor">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalCriarEl = document.getElementById('modalCriarServidor');
    const modalEditarEl = document.getElementById('modalEditarServidor');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Color sync
    function syncColor(pickerId, textId) {
        const picker = document.getElementById(pickerId);
        const text = document.getElementById(textId);
        if (picker && text) {
            picker.addEventListener('input', () => text.value = picker.value);
            text.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
                    picker.value = text.value;
                }
            });
        }
    }
    syncColor('criar-srv-themecolor-picker', 'criar-srv-themecolor');
    syncColor('edit-srv-themecolor-picker', 'edit-srv-themecolor');

    // Previews
    function setupFilePreview(inputId, boxId, isBg = false) {
        const input = document.getElementById(inputId);
        const box = document.getElementById(boxId);
        if (input && box) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        box.innerHTML = `<img src="${e.target.result}" alt="preview" style="width:100%;height:100%;object-fit:${isBg ? 'cover' : 'contain'};">`;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
    setupFilePreview('criar-srv-icon', 'criar-srv-icon-preview', false);
    setupFilePreview('criar-srv-bg', 'criar-srv-bg-preview', true);
    setupFilePreview('edit-srv-icon', 'edit-srv-icon-preview', false);
    setupFilePreview('edit-srv-bg', 'edit-srv-bg-preview', true);

    // Dynamic Features
    function addFeatureRow(containerId, valor = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm feature-row';
        div.innerHTML = `
            <input type="text" class="form-control" name="features[]" value="${escapeHtml(valor)}" placeholder="Ex: Novo recurso" required>
            <button type="button" class="btn btn-outline-danger btn-remove-feature"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(div);
        div.querySelector('.btn-remove-feature').addEventListener('click', function() {
            const items = container.querySelectorAll('.feature-row');
            if (items.length > 1) {
                div.remove();
            } else {
                div.querySelector('input').value = '';
            }
        });
    }

    document.querySelectorAll('.btn-remove-feature').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.d-flex');
            const items = container.querySelectorAll('.feature-row');
            if (items.length > 1) {
                this.closest('.feature-row').remove();
            } else {
                this.closest('.feature-row').querySelector('input').value = '';
            }
        });
    });

    const btnAddCriar = document.getElementById('btn-add-feature-criar-srv');
    if (btnAddCriar) {
        btnAddCriar.addEventListener('click', () => addFeatureRow('container-features-criar-srv'));
    }
    const btnAddEdit = document.getElementById('btn-add-feature-edit-srv');
    if (btnAddEdit) {
        btnAddEdit.addEventListener('click', () => addFeatureRow('container-features-edit-srv'));
    }

    function atualizarContadores() {
        const ativas = document.querySelectorAll('#lista-servidores-ativos tr[data-id]').length;
        const inativas = document.querySelectorAll('#lista-servidores-inativos tr[data-id]').length;
        const total = ativas + inativas;

        const totalEl = document.querySelector('[data-item-counter]');
        if (totalEl) totalEl.textContent = total + ' ' + (total === 1 ? 'servidor' : 'servidores');

        const totalAtivosSummary = document.querySelector('[data-item-counter-ativos-summary]');
        if (totalAtivosSummary) totalAtivosSummary.textContent = ativas + ' ' + (ativas === 1 ? 'visível' : 'visíveis');

        const totalInativosSummary = document.querySelector('[data-item-counter-inativos-summary]');
        if (totalInativosSummary) totalInativosSummary.textContent = inativas + ' ' + (inativas === 1 ? 'oculto' : 'ocultos');

        const totalAtivosHeader = document.querySelector('[data-item-counter-ativos]');
        if (totalAtivosHeader) totalAtivosHeader.textContent = ativas + ' ' + (ativas === 1 ? 'servidor' : 'servidores');

        const totalInativosHeader = document.querySelector('[data-item-counter-inativos]');
        if (totalInativosHeader) totalInativosHeader.textContent = inativas + ' ' + (inativas === 1 ? 'servidor' : 'servidores');
    }

    function abrirModalEditarServidor(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-srv-id').value = d.id || '';
        document.getElementById('edit-srv-servername').value = d.servername || '';
        document.getElementById('modalEditarServidorNomeHeader').textContent = d.servername || '';
        document.getElementById('edit-srv-ip').value = d.ip || '';
        document.getElementById('edit-srv-descricao').value = d.descricao || '';
        document.getElementById('edit-srv-modpackurl').value = d.modpackurl || '';
        document.getElementById('edit-srv-themecolor').value = d.themecolor || '#B971DA';
        document.getElementById('edit-srv-themecolor-picker').value = d.themecolor || '#B971DA';
        document.getElementById('edit-srv-enabled').checked = (d.enabled === '1');

        // Ícone preview
        const iconBox = document.getElementById('edit-srv-icon-preview');
        if (iconBox) {
            if (d.icon && (d.icon.startsWith('/') || d.icon.startsWith('http'))) {
                iconBox.innerHTML = `<img src="${escapeHtml(d.icon)}" alt="icone" style="width:100%;height:100%;object-fit:cover;">`;
            } else if (d.icon && d.icon.includes('fa-')) {
                iconBox.innerHTML = `<i class="${escapeHtml(d.icon)}" style="color:${escapeHtml(d.themecolor || '#B971DA')}"></i>`;
            } else {
                iconBox.innerHTML = `<i class="fa-solid fa-server text-muted"></i>`;
            }
        }

        // Bg preview
        const bgBox = document.getElementById('edit-srv-bg-preview');
        const removerBgBox = document.getElementById('edit-srv-remover-bg-box');
        const removerBgChk = document.getElementById('edit-srv-remover-bg');
        if (removerBgChk) removerBgChk.checked = false;

        if (bgBox) {
            if (d.bgImage) {
                bgBox.innerHTML = `<img src="${escapeHtml(d.bgImage)}" alt="bg" style="width:100%;height:100%;object-fit:cover;">`;
                if (removerBgBox) removerBgBox.style.display = 'block';
            } else {
                bgBox.innerHTML = `<span class="text-white-50" style="font-size: 0.65rem;">Sem fundo</span>`;
                if (removerBgBox) removerBgBox.style.display = 'none';
            }
        }

        // Features
        const containerF = document.getElementById('container-features-edit-srv');
        if (containerF) {
            containerF.innerHTML = '';
            let listaF = [];
            try {
                listaF = JSON.parse(d.features || '[]');
                if (!Array.isArray(listaF)) listaF = [];
            } catch (e) {
                listaF = [];
            }
            if (listaF.length === 0) {
                listaF = ['Economia balanceada'];
            }
            listaF.forEach(f => addFeatureRow('container-features-edit-srv', f));
        }

        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-servidor');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarServidor(row);
    });

    function criarElementoLinhaServidor(s) {
        const tr = document.createElement('tr');
        tr.id = `servidor-row-${s.id}`;
        tr.dataset.id = s.id;
        tr.dataset.servername = s.servername || '';
        tr.dataset.nome = s.nome || '';
        tr.dataset.ip = s.ip || '';
        tr.dataset.descricao = s.descricao || '';
        tr.dataset.modpackurl = s.modpackurl || '';
        tr.dataset.themecolor = s.themecolor || '#B971DA';
        tr.dataset.icon = s.icon || '';
        tr.dataset.bgImage = s.bg_image || '';
        tr.dataset.features = s.features || '[]';
        tr.dataset.enabled = s.enabled ? '1' : '0';

        const isVisivel = Boolean(Number(s.enabled));
        const statusBadge = isVisivel 
            ? `<span class="badge-status visivel">Visível</span>` 
            : `<span class="badge-status pendente">Oculto</span>`;

        let iconeHtml = '<i class="fa-solid fa-server"></i>';
        if (s.icon && (s.icon.startsWith('/') || s.icon.startsWith('http'))) {
            iconeHtml = `<img src="${escapeHtml(s.icon)}" class="rounded" width="32" height="32" style="object-fit: cover;" alt="">`;
        } else if (s.icon && s.icon.includes('fa-')) {
            iconeHtml = `<i class="${escapeHtml(s.icon)}"></i>`;
        }

        tr.innerHTML = `
            <td>
                <div class="d-flex align-items-center justify-content-center bg-light rounded js-srv-icone-box" style="width: 36px; height: 36px; color: ${escapeHtml(s.themecolor || '#B971DA')}; font-size: 1.1rem;">
                    ${iconeHtml}
                </div>
            </td>
            <td><strong class="js-srv-servername">${escapeHtml(s.servername)}</strong></td>
            <td><code class="js-srv-slug">${escapeHtml(s.nome || '')}</code></td>
            <td><small class="font-monospace text-muted js-srv-ip">${escapeHtml(s.ip)}</small></td>
            <td>
                <div class="d-flex align-items-center gap-1 js-srv-tema-box">
                    <span class="js-srv-color-dot" style="display:inline-block; width:16px; height:16px; border-radius:4px; background:${escapeHtml(s.themecolor || '#B971DA')}; border:1px solid rgba(0,0,0,0.15);"></span>
                    <small class="font-monospace js-srv-color-text">${escapeHtml(s.themecolor || '#B971DA')}</small>
                </div>
            </td>
            <td>${statusBadge}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-servidor" title="Editar Servidor">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-action="async-toggle" 
                            data-url="/admin/api/servidores/toggle.php" 
                            data-id="${s.id}"
                            title="${isVisivel ? 'Ocultar servidor' : 'Tornar servidor visível'}">
                        <i class="fa-solid ${isVisivel ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/servidores/deletar.php" 
                            data-id="${s.id}"
                            data-name="${escapeHtml(s.servername)}"
                            data-confirm="Deletar o servidor ${escapeHtml(s.servername)}? Essa ação não pode ser desfeita."
                            title="Deletar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // SUBMIT: CRIAR SERVIDOR
    const formCriar = document.getElementById('formCriarServidor');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar-servidor');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cadastrando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/servidores/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                document.getElementById('criar-srv-icon-preview').innerHTML = '<i class="fa-solid fa-server text-muted"></i>';
                document.getElementById('criar-srv-bg-preview').innerHTML = '<span class="text-white-50" style="font-size: 0.65rem;">Sem fundo</span>';

                if (res.servidor) {
                    const isVisivel = Boolean(Number(res.servidor.enabled));
                    const tbody = isVisivel ? document.getElementById('lista-servidores-ativos') : document.getElementById('lista-servidores-inativos');
                    const emptyRow = isVisivel ? document.getElementById('linha-sem-servidores-ativos') : document.getElementById('linha-sem-servidores-inativos');
                    if (emptyRow) emptyRow.remove();

                    const novaLinha = criarElementoLinhaServidor(res.servidor);
                    if (tbody) tbody.prepend(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Servidor cadastrado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao cadastrar servidor.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR SERVIDOR
    const formEditar = document.getElementById('formEditarServidor');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar-servidor');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/servidores/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.servidor) {
                    const s = res.servidor;
                    let tr = document.getElementById(`servidor-row-${s.id}`);
                    const isVisivel = Boolean(Number(s.enabled));
                    const destinoTbody = isVisivel ? document.getElementById('lista-servidores-ativos') : document.getElementById('lista-servidores-inativos');

                    if (tr) {
                        const novaLinha = criarElementoLinhaServidor(s);
                        tr.replaceWith(novaLinha);
                        tr = novaLinha;

                        if (tr.parentElement !== destinoTbody && destinoTbody) {
                            const emptyRow = isVisivel ? document.getElementById('linha-sem-servidores-ativos') : document.getElementById('linha-sem-servidores-inativos');
                            if (emptyRow) emptyRow.remove();
                            destinoTbody.prepend(tr);
                        }

                        tr.classList.add('table-row-highlight');
                        setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Servidor atualizado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar servidor.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // Tratamento de deep link ?action=novo e ?edit=ID
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'novo' && bsModalCriar) {
        bsModalCriar.show();
    } else if (urlParams.has('edit')) {
        const editId = urlParams.get('edit');
        const row = document.getElementById(`servidor-row-${editId}`);
        if (row) {
            abrirModalEditarServidor(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>