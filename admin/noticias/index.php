<?php
$paginaAtiva = 'noticias';
$tituloPagina = 'Novidades';
require_once __DIR__ . "/../includes/admin_header.php";

if (!defined('POR_PAGINA')) {
    define('POR_PAGINA', 15);
}
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$busca = trim($_GET['busca'] ?? '');
$whereSql = !empty($busca) ? "WHERE titulo LIKE :busca OR autor LIKE :busca OR category LIKE :busca" : "";
$params = !empty($busca) ? [':busca' => "%{$busca}%"] : [];

$totalNoticias = 0;
$totalPaginas = 1;
$noticias = [];
$servidoresMap = [];
$servidoresList = [];
$nicksEquipe = [];
$categorias_envio = ['Anúncios', 'Atualizações'];
$webhooksPorServidor = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon, discord_role_id FROM servidores ORDER BY servername ASC");
        $srvRows = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($srvRows as $s) {
            $servidoresList[] = $s['servername'];
            $servidoresMap[$s['servername']] = $s;
            $webhooksPorServidor[$s['servername']] = [
                'Anúncios' => false,
                'Atualizações' => false,
                'role_id' => !empty($s['discord_role_id']) ? (string)$s['discord_role_id'] : null,
            ];
            if (!empty($s['nome'])) {
                $servidoresMap[$s['nome']] = $s;
            }
        }
        try {
            $stmtWh = $pdo->query("
                SELECT s.servername, w.categoria
                FROM servidores_webhooks w
                INNER JOIN servidores s ON s.id = w.servidor_id
                WHERE w.webhook_url IS NOT NULL AND w.webhook_url <> ''
            ");
            foreach ($stmtWh->fetchAll(PDO::FETCH_ASSOC) as $wh) {
                $srvNome = (string)$wh['servername'];
                $cat = (string)$wh['categoria'];
                if (isset($webhooksPorServidor[$srvNome])) {
                    $webhooksPorServidor[$srvNome][$cat] = true;
                }
            }
        } catch (Throwable $eWh) {
            error_log("Aviso webhooks novidades: " . $eWh->getMessage());
        }
        if (empty($servidoresList)) {
            $servidoresList = ['NerdSky', 'Potato Nerds', 'Nerd Dead'];
        }

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM novidades $whereSql");
        $stmtCount->execute($params);
        $totalNoticias = (int)$stmtCount->fetchColumn();

        $totalPaginas = max(1, (int)ceil($totalNoticias / POR_PAGINA));

        $stmt = $pdo->prepare("
            SELECT id, titulo, conteudo, category, categoria_envio, capa, criado_em, autor, discord_msg_id 
            FROM novidades 
            $whereSql 
            ORDER BY criado_em DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao listar notícias: " . $e->getMessage());
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Novidades</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo pluralizar($totalNoticias, 'novidade', 'novidades'); ?></span> <?php echo $totalNoticias === 1 ? 'cadastrada' : 'cadastradas'; ?></p>
    </div>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarNoticia">
        <i class="fa-solid fa-plus me-1"></i> Nova novidade
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

<!-- BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" id="input-busca-noticias" class="form-control form-control-sm" placeholder="Buscar por título, autor ou servidor..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Buscar</button>
                <?php if (!empty($busca)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpar Busca"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE NOVIDADES -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-noticias">
                <thead>
                    <tr>
                        <th style="width: 140px;">Servidor</th>
                        <th style="width: 70px;">Capa</th>
                        <th>Título</th>
                        <th style="width: 140px;">Categoria</th>
                        <th style="width: 130px;">Autor</th>
                        <th style="width: 140px;">Data</th>
                        <th class="text-end" style="width: 130px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-noticias">
                    <?php if (empty($noticias)): ?>
                        <tr class="empty-row" id="linha-sem-noticias">
                            <td colspan="7" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-regular fa-newspaper"></i></div>
                                    <h6 class="empty-title">Nenhuma novidade encontrada</h6>
                                    <p class="empty-desc">Nenhuma novidade cadastrada ainda ou correspondente à busca.</p>
                                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCriarNoticia">
                                        <i class="fa-solid fa-plus me-1"></i> Publicar Novidade
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($noticias as $n): 
                            $srvKey = $n['category'] ?? '';
                            $srvInfo = $servidoresMap[$srvKey] ?? null;
                            $srvCor = $srvInfo['themecolor'] ?? '#64748b';
                        ?>
                            <tr id="noticia-row-<?php echo (int)$n['id']; ?>"
                                data-id="<?php echo (int)$n['id']; ?>"
                                data-versao-hash="<?php echo htmlspecialchars(gerarHashRegistro($n, [], 'novidades'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-titulo="<?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-category="<?php echo htmlspecialchars($n['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-categoria-envio="<?php echo htmlspecialchars($n['categoria_envio'] ?? 'Anúncios', ENT_QUOTES, 'UTF-8'); ?>"
                                data-autor="<?php echo htmlspecialchars($n['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-capa="<?php echo htmlspecialchars($n['capa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-discord-msg-id="<?php echo htmlspecialchars($n['discord_msg_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-conteudo="<?php echo htmlspecialchars($n['conteudo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-criado-em="<?php echo date('d/m/Y H:i', strtotime($n['criado_em'])); ?>">
                                <td>
                                    <span class="badge js-noticia-servidor" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                        <i class="fa-solid fa-server me-1"></i>
                                        <?php echo htmlspecialchars($srvKey ?: 'Geral', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="js-noticia-capa-box">
                                        <?php if (!empty($n['capa'])): ?>
                                            <img src="<?php echo htmlspecialchars($n['capa'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded border" width="48" height="32" style="object-fit: cover;" alt="Capa" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-dark js-noticia-titulo"><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <td><span class="badge bg-light text-dark border js-noticia-categoria-envio"><?php echo htmlspecialchars($n['categoria_envio'] ?? 'Atualização', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><small class="js-noticia-autor"><?php echo htmlspecialchars($n['autor'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td class="text-muted small js-noticia-data"><?php echo date('d/m/Y H:i', strtotime($n['criado_em'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-noticia" title="Editar Novidade">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/noticias/deletar.php" 
                                                data-id="<?php echo (int)$n['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                data-confirm="Deletar a novidade <?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita." 
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

<!-- PAGINAÇÃO -->
<?php require_once __DIR__ . "/../includes/paginacao.php"; renderPaginacaoAdmin($pagina, $totalPaginas, ['busca' => $busca]); ?>

<!-- MODAL: CRIAR NOVIDADE -->
<div class="modal fade" id="modalCriarNoticia" tabindex="-1" aria-labelledby="modalCriarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content shadow">
            <form id="formCriarNoticia" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarNoticiaLabel">Publicar Nova Novidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="mb-3">
                        <label for="criar-noticia-titulo" class="form-label fw-bold">Título da Novidade <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="criar-noticia-titulo" name="titulo" placeholder="Ex: Inauguração do Novo Servidor" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="criar-noticia-category" class="form-label fw-bold">Servidor Relacionado</label>
                            <select class="form-select" id="criar-noticia-category" name="category">
                                <?php foreach ($servidoresList as $srv): ?>
                                    <option value="<?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="criar-noticia-categoria-envio" class="form-label fw-bold">Canal de Envio</label>
                            <select class="form-select" id="criar-noticia-categoria-envio" name="categoria_envio">
                                <?php foreach ($categorias_envio as $cEnv): ?>
                                    <option value="<?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label for="criar-noticia-conteudo" class="form-label fw-bold mb-0">Conteúdo (Markdown suportado) <span class="text-danger">*</span></label>
                        </div>
                        <?php 
                        $toolbarTarget = 'criar-noticia-conteudo';
                        require __DIR__ . "/../includes/wiki_editor_toolbar.php"; 
                        ?>
                        <textarea class="form-control font-monospace" id="criar-noticia-conteudo" name="conteudo" rows="8" placeholder="Escreva a novidade aqui..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="border rounded p-3">
                            <div class="fw-bold small mb-2">Imagem de Capa <span class="text-danger">*</span></div>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                <div id="criar-noticia-capa-preview" class="rounded d-flex flex-column align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 192px; height: 108px; border: 1px dashed var(--border-color, #475569); background: var(--admin-bg, transparent);">
                                    <i class="fa-regular fa-image text-muted fs-4 mb-1"></i>
                                    <span class="text-muted" style="font-size: 0.72rem;">Sem capa</span>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <input type="file" class="d-none" id="criar-noticia-capa-upload" name="capa_upload" accept="image/*">
                                    <label for="criar-noticia-capa-upload" class="btn btn-sm btn-primary mb-1">
                                        <i class="fa-solid fa-upload me-1"></i> Escolher capa
                                    </label>
                                    <div id="criar-noticia-capa-upload-name" class="text-muted small text-truncate">Nenhum arquivo selecionado</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light border rounded">
                        <h6 class="fw-bold mb-2">Notificação no Discord</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="criar-noticia-webhook" name="enviar_webhook" value="1" checked>
                            <label class="form-check-label fw-semibold" for="criar-noticia-webhook">Enviar anúncio automaticamente para o Discord</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="criar-noticia-everyone" name="marcar_everyone" value="1">
                            <label class="form-check-label text-muted" for="criar-noticia-everyone" id="criar-noticia-everyone-label">Mencionar cargo do servidor na mensagem</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-noticia">
                        <i class="fa-solid fa-check me-1"></i> Publicar Novidade
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR NOVIDADE -->
<div class="modal fade" id="modalEditarNoticia" tabindex="-1" aria-labelledby="modalEditarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content shadow">
            <form id="formEditarNoticia" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-noticia-id" value="">
                <input type="hidden" name="_versao_hash" id="edit-noticia-versao-hash" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarNoticiaLabel">
                        Editar Novidade: <span id="modalEditarNoticiaTituloHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="mb-3">
                        <label for="edit-noticia-titulo" class="form-label fw-bold">Título da Novidade <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-noticia-titulo" name="titulo" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="edit-noticia-category" class="form-label fw-bold">Servidor Relacionado</label>
                            <select class="form-select" id="edit-noticia-category" name="category">
                                <?php foreach ($servidoresList as $srv): ?>
                                    <option value="<?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($srv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-noticia-categoria-envio" class="form-label fw-bold">Canal de Envio</label>
                            <select class="form-select" id="edit-noticia-categoria-envio" name="categoria_envio">
                                <?php foreach ($categorias_envio as $cEnv): ?>
                                    <option value="<?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($cEnv, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label for="edit-noticia-conteudo" class="form-label fw-bold mb-0">Conteúdo (Markdown suportado) <span class="text-danger">*</span></label>
                        </div>
                        <?php 
                        $toolbarTarget = 'edit-noticia-conteudo';
                        require __DIR__ . "/../includes/wiki_editor_toolbar.php"; 
                        ?>
                        <textarea class="form-control font-monospace" id="edit-noticia-conteudo" name="conteudo" rows="8" required></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="border rounded p-3">
                            <div class="fw-bold small mb-2">Imagem de Capa <span class="text-danger">*</span></div>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                <div id="edit-noticia-capa-preview" class="rounded d-flex flex-column align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 192px; height: 108px; border: 1px dashed var(--border-color, #475569); background: var(--admin-bg, transparent);">
                                    <i class="fa-regular fa-image text-muted fs-4 mb-1"></i>
                                    <span class="text-muted" style="font-size: 0.72rem;">Sem capa</span>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <input type="file" class="d-none" id="edit-noticia-capa-upload" name="capa_upload" accept="image/*">
                                    <label for="edit-noticia-capa-upload" class="btn btn-sm btn-primary mb-1">
                                        <i class="fa-solid fa-upload me-1"></i> Trocar capa
                                    </label>
                                    <div id="edit-noticia-capa-upload-name" class="text-muted small text-truncate">Nenhum arquivo selecionado</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light border rounded">
                        <h6 class="fw-bold mb-2">Notificação no Discord</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="edit-noticia-webhook" name="enviar_webhook" value="1">
                            <label class="form-check-label fw-semibold" for="edit-noticia-webhook">Enviar / manter anúncio no Discord</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit-noticia-everyone" name="marcar_everyone" value="1">
                            <label class="form-check-label text-muted" for="edit-noticia-everyone" id="edit-noticia-everyone-label">Mencionar cargo do servidor ao reenviar mensagem</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-noticia">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const servidoresMap = <?php echo json_encode($servidoresMap, JSON_UNESCAPED_UNICODE); ?>;
    const webhooksPorServidor = <?php echo json_encode($webhooksPorServidor, JSON_UNESCAPED_UNICODE); ?>;
    const CATEGORIAS_ENVIO = <?php echo json_encode($categorias_envio, JSON_UNESCAPED_UNICODE); ?>;

    function atualizarSeletorCanalEnvio(selectSrvId, selectCanalId, checkboxWebhookId, checkboxEveryoneId, valorPreferido, marcarPorPadrao = false) {
        const srvEl = document.getElementById(selectSrvId);
        const canalEl = document.getElementById(selectCanalId);
        if (!srvEl || !canalEl) return;

        const srvNome = srvEl.value || '';
        const status = webhooksPorServidor[srvNome] || { 'Anúncios': false, 'Atualizações': false };
        const disponiveis = CATEGORIAS_ENVIO.filter((cat) => Boolean(status[cat]));

        canalEl.innerHTML = '';
        if (disponiveis.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Webhooks não cadastrados';
            opt.disabled = true;
            opt.selected = true;
            canalEl.appendChild(opt);
        } else {
            let primeiroDisponivel = null;
            CATEGORIAS_ENVIO.forEach((cat) => {
                const opt = document.createElement('option');
                if (status[cat]) {
                    opt.value = cat;
                    opt.textContent = cat;
                    if (!primeiroDisponivel) primeiroDisponivel = cat;
                } else {
                    opt.value = '';
                    opt.textContent = `${cat} - Não vinculado`;
                    opt.disabled = true;
                }
                canalEl.appendChild(opt);
            });
            canalEl.value = (valorPreferido && status[valorPreferido]) ? valorPreferido : (primeiroDisponivel || '');
        }

        const cbWh = checkboxWebhookId ? document.getElementById(checkboxWebhookId) : null;
        const cbEv = checkboxEveryoneId ? document.getElementById(checkboxEveryoneId) : null;
        if (cbWh) {
            if (disponiveis.length === 0) {
                cbWh.checked = false;
                cbWh.disabled = true;
            } else {
                cbWh.disabled = false;
                if (marcarPorPadrao) cbWh.checked = true;
            }
        }
        if (cbEv) {
            if (disponiveis.length === 0) {
                cbEv.checked = false;
                cbEv.disabled = true;
            } else {
                cbEv.disabled = false;
            }
            const lblEv = docLabel(checkboxEveryoneId);
            if (lblEv) {
                const isEdit = checkboxEveryoneId === 'edit-noticia-everyone';
                const acaoTxt = isEdit ? 'ao reenviar mensagem' : 'na mensagem';
                if (status.role_id) {
                    lblEv.innerHTML = `Mencionar cargo do servidor ${acaoTxt} <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace ms-1">ID: ${escapeHtml(status.role_id)}</span>`;
                } else {
                    lblEv.textContent = `Mencionar @everyone ${acaoTxt} (sem cargo vinculado)`;
                }
            }
        }
    }

    function docLabel(inputId) {
        return inputId ? document.getElementById(inputId + '-label') : null;
    }

    const criarSrvSelect = document.getElementById('criar-noticia-category');
    if (criarSrvSelect) {
        criarSrvSelect.addEventListener('change', () => {
            atualizarSeletorCanalEnvio('criar-noticia-category', 'criar-noticia-categoria-envio', 'criar-noticia-webhook', 'criar-noticia-everyone', null, true);
        });
        atualizarSeletorCanalEnvio('criar-noticia-category', 'criar-noticia-categoria-envio', 'criar-noticia-webhook', 'criar-noticia-everyone', null, true);
    }
    const editSrvSelect = document.getElementById('edit-noticia-category');
    if (editSrvSelect) {
        editSrvSelect.addEventListener('change', () => {
            atualizarSeletorCanalEnvio('edit-noticia-category', 'edit-noticia-categoria-envio', 'edit-noticia-webhook', 'edit-noticia-everyone');
        });
    }

    const modalCriarEl = document.getElementById('modalCriarNoticia');
    const modalEditarEl = document.getElementById('modalEditarNoticia');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function atualizarContadores() {
        const total = document.querySelectorAll('#lista-noticias tr[data-id]').length;
        const totalEl = document.querySelector('[data-item-counter]');
        if (totalEl) totalEl.textContent = total + ' ' + (total === 1 ? 'novidade' : 'novidades');
    }

    function abrirModalEditarNoticia(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-noticia-id').value = d.id || '';
        document.getElementById('edit-noticia-versao-hash').value = d.versaoHash || '';
        document.getElementById('edit-noticia-titulo').value = d.titulo || '';
        document.getElementById('modalEditarNoticiaTituloHeader').textContent = d.titulo || '';
        document.getElementById('edit-noticia-category').value = d.category || '';
        const editCbWh = document.getElementById('edit-noticia-webhook');
        const editCbEv = document.getElementById('edit-noticia-everyone');
        if (editCbWh) editCbWh.checked = Boolean(d.discordMsgId);
        if (editCbEv) editCbEv.checked = false;
        atualizarSeletorCanalEnvio('edit-noticia-category', 'edit-noticia-categoria-envio', 'edit-noticia-webhook', 'edit-noticia-everyone', d.categoriaEnvio || 'Anúncios');
        document.getElementById('edit-noticia-conteudo').value = d.conteudo || '';
        document.getElementById('edit-noticia-capa-upload').value = '';
        const editCapaName = document.getElementById('edit-noticia-capa-upload-name');
        if (editCapaName) editCapaName.textContent = 'Nenhum arquivo selecionado';

        const editCapaPreview = document.getElementById('edit-noticia-capa-preview');
        if (editCapaPreview) {
            if (d.capa) {
                editCapaPreview.style.borderStyle = 'solid';
                editCapaPreview.innerHTML = `<img src="${escapeHtml(d.capa)}" class="w-100 h-100" style="object-fit: cover;" alt="Capa">`;
            } else {
                editCapaPreview.style.borderStyle = 'dashed';
                editCapaPreview.innerHTML = '<i class="fa-regular fa-image text-muted fs-4 mb-1"></i><span class="text-muted" style="font-size: 0.72rem;">Sem capa</span>';
            }
        }

        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-noticia');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarNoticia(row);
    });

    function criarElementoLinhaNoticia(n) {
        const tr = document.createElement('tr');
        tr.id = `noticia-row-${n.id}`;
        tr.dataset.id = n.id;
        tr.dataset.versaoHash = n._versao_hash || '';
        tr.dataset.titulo = n.titulo || '';
        tr.dataset.category = n.category || '';
        tr.dataset.categoriaEnvio = n.categoria_envio || 'Anúncios';
        tr.dataset.autor = n.autor || '';
        tr.dataset.capa = n.capa || '';
        tr.dataset.conteudo = n.conteudo || '';

        const srvKey = n.category || '';
        const srvInfo = servidoresMap[srvKey] || null;
        const srvCor = srvInfo ? (srvInfo.themecolor || '#64748b') : '#64748b';

        let dataFormatada = '';
        if (n.criado_em) {
            const d = new Date(n.criado_em);
            if (!isNaN(d.getTime())) {
                const dia = String(d.getDate()).padStart(2, '0');
                const mes = String(d.getMonth() + 1).padStart(2, '0');
                const ano = d.getFullYear();
                const hora = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                dataFormatada = `${dia}/${mes}/${ano} ${hora}:${min}`;
            }
        }
        tr.dataset.capa = n.capa || '';
        tr.dataset.discordMsgId = n.discord_msg_id || '';
        tr.dataset.criadoEm = dataFormatada;

        const capaHtml = n.capa 
            ? `<img src="${escapeHtml(n.capa)}" class="rounded border" width="48" height="32" style="object-fit: cover;" alt="Capa" onerror="this.style.display='none'">` 
            : `<span class="text-muted">—</span>`;

        tr.innerHTML = `
            <td>
                <span class="badge js-noticia-servidor" style="background-color: ${escapeHtml(srvCor)}; color: #fff; font-weight: 600;">
                    <i class="fa-solid fa-server me-1"></i>
                    ${escapeHtml(srvKey || 'Geral')}
                </span>
            </td>
            <td>
                <div class="js-noticia-capa-box">${capaHtml}</div>
            </td>
            <td>
                <strong class="text-dark js-noticia-titulo">${escapeHtml(n.titulo)}</strong>
            </td>
            <td><span class="badge bg-light text-dark border js-noticia-categoria-envio">${escapeHtml(n.categoria_envio || 'Atualização')}</span></td>
            <td><small class="js-noticia-autor">${escapeHtml(n.autor || 'Admin')}</small></td>
            <td class="text-muted small js-noticia-data">${escapeHtml(dataFormatada)}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-noticia" title="Editar Novidade">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/noticias/deletar.php" 
                            data-id="${n.id}" 
                            data-name="${escapeHtml(n.titulo)}" 
                            data-confirm="Deletar a novidade ${escapeHtml(n.titulo)}? Essa ação não pode ser desfeita." 
                            title="Deletar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    function setupCapaPreview(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const nameEl = document.getElementById(inputId + '-name');
        if (!input || !preview) return;
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                if (nameEl) nameEl.textContent = this.files[0].name;
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.style.borderStyle = 'solid';
                    preview.innerHTML = `<img src="${ev.target.result}" class="w-100 h-100" style="object-fit: cover;" alt="Capa">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    setupCapaPreview('criar-noticia-capa-upload', 'criar-noticia-capa-preview');
    setupCapaPreview('edit-noticia-capa-upload', 'edit-noticia-capa-preview');

    // SUBMIT: CRIAR NOTÍCIA
    const formCriar = document.getElementById('formCriarNoticia');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const capaInput = document.getElementById('criar-noticia-capa-upload');
            if (!capaInput || !capaInput.files || !capaInput.files.length) {
                AdminToast.error('A imagem de capa é obrigatória.');
                return;
            }
            const btnSubmit = document.getElementById('btn-submit-criar-noticia');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Publicando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/noticias/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                atualizarSeletorCanalEnvio('criar-noticia-category', 'criar-noticia-categoria-envio', 'criar-noticia-webhook', 'criar-noticia-everyone', null, true);
                const criarPreview = document.getElementById('criar-noticia-capa-preview');
                if (criarPreview) {
                    criarPreview.style.borderStyle = 'dashed';
                    criarPreview.innerHTML = '<i class="fa-regular fa-image text-muted fs-4 mb-1"></i><span class="text-muted" style="font-size: 0.72rem;">Sem capa</span>';
                }
                const criarName = document.getElementById('criar-noticia-capa-upload-name');
                if (criarName) criarName.textContent = 'Nenhum arquivo selecionado';

                if (res.noticia) {
                    const tbody = document.getElementById('lista-noticias');
                    const emptyRow = document.getElementById('linha-sem-noticias');
                    if (emptyRow) emptyRow.remove();

                    const novaLinha = criarElementoLinhaNoticia(res.noticia);
                    if (tbody) tbody.prepend(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Novidade publicada com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao publicar novidade.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR NOTÍCIA
    const formEditar = document.getElementById('formEditarNoticia');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const editCapaInput = document.getElementById('edit-noticia-capa-upload');
            const editCapaPreview = document.getElementById('edit-noticia-capa-preview');
            const temCapaAtual = !!(editCapaPreview && editCapaPreview.querySelector('img'));
            const temNovoArquivo = !!(editCapaInput && editCapaInput.files && editCapaInput.files.length);
            if (!temCapaAtual && !temNovoArquivo) {
                AdminToast.error('A imagem de capa é obrigatória.');
                return;
            }
            const btnSubmit = document.getElementById('btn-submit-editar-noticia');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/noticias/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.noticia) {
                    const n = res.noticia;
                    const tr = document.getElementById(`noticia-row-${n.id}`);
                    if (tr) {
                        const novaLinha = criarElementoLinhaNoticia(n);
                        tr.replaceWith(novaLinha);
                        novaLinha.dataset.versaoHash = n._versao_hash || '';
                        novaLinha.classList.add('table-row-highlight');
                        setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Novidade atualizada com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar novidade.');
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
        const row = document.getElementById(`noticia-row-${editId}`);
        if (row) {
            abrirModalEditarNoticia(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
