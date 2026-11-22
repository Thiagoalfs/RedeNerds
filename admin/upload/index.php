<?php
$paginaAtiva = 'upload';
$tituloPagina = 'Upload de Imagens';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/upload_helper.php";

$filtroPasta = uploadNormalizarPasta((string)($_GET['pasta'] ?? '')) ?? '';
$busca = trim((string)($_GET['busca'] ?? ''));
$porPagina = 10;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$pastasAssets = uploadListarPastasAssets();
$todasImagens = uploadListarImagensSite($pdo ?? null, $filtroPasta, $busca);
$totalImagens = count($todasImagens);
$totalPaginas = max(1, (int)ceil($totalImagens / $porPagina));
$pagina = min($pagina, $totalPaginas);
$imagens = array_slice($todasImagens, ($pagina - 1) * $porPagina, $porPagina);
$baseUrl = uploadBaseSiteUrl();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Upload de Imagens</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar($totalImagens, 'imagem', 'imagens'); ?></span>
            <span class="mx-1">•</span>
            <span><?php echo pluralizar(count($pastasAssets), 'pasta', 'pastas'); ?> em <code>assets/</code></span>
        </p>
    </div>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalEnviarUpload">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Enviar imagem
    </button>
</div>

<!-- FILTRO -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <select name="pasta" class="form-select form-select-sm">
                    <option value="">Todas as pastas (assets/)</option>
                    <?php foreach ($pastasAssets as $p): ?>
                        <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filtroPasta === $p) ? 'selected' : ''; ?>>
                            assets/<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por nome, caminho ou autor..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                <?php if ($filtroPasta !== '' || $busca !== ''): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpar filtros"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE IMAGENS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-uploads">
                <thead>
                    <tr>
                        <th style="width: 72px;">Preview</th>
                        <th>Nome</th>
                        <th style="width: 150px;">Pasta</th>
                        <th style="width: 140px;">Enviado por</th>
                        <th style="width: 145px;">Data</th>
                        <th class="text-end" style="width: 170px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-uploads">
                    <?php if (empty($imagens)): ?>
                        <tr class="empty-row" id="linha-sem-uploads">
                            <td colspan="6" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-regular fa-images"></i></div>
                                    <h6 class="empty-title">Nenhuma imagem encontrada</h6>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($imagens as $img):
                            $urlCompleta = $baseUrl . $img['caminho_publico'];
                            $dataFmt = $img['criado_em'] ? date('d/m/Y H:i', strtotime($img['criado_em'])) : '—';
                        ?>
                        <tr id="<?php echo $img['id'] ? 'upload-row-' . (int)$img['id'] : ''; ?>">
                            <td>
                                <button type="button"
                                        class="btn p-0 border-0 bg-transparent js-abrir-preview-upload"
                                        data-src="<?php echo htmlspecialchars($img['caminho_publico'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-nome="<?php echo htmlspecialchars($img['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-url="<?php echo htmlspecialchars($urlCompleta, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Ampliar imagem">
                                    <img src="<?php echo htmlspecialchars($img['caminho_publico'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded border" width="52" height="36" style="object-fit: cover; cursor: zoom-in;" alt="" loading="lazy">
                                </button>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 280px;" title="<?php echo htmlspecialchars($img['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($img['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <small class="text-muted font-monospace d-block text-truncate" style="max-width: 320px;">
                                    <?php echo htmlspecialchars($img['caminho_publico'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo htmlspecialchars(uploadFormatarTamanho((int)$img['tamanho_bytes']), ENT_QUOTES, 'UTF-8'); ?>)
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">assets/<?php echo htmlspecialchars($img['pasta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td><small><?php echo htmlspecialchars($img['autor_nome'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($dataFmt, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                    <button type="button"
                                            class="btn btn-sm btn-primary js-copiar-link-upload"
                                            data-url="<?php echo htmlspecialchars($urlCompleta, ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Copiar link completo">
                                        <i class="fa-regular fa-copy me-1"></i>Copiar
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-danger"
                                            data-action="async-delete"
                                            data-url="/admin/api/upload/deletar.php"
                                            data-id="<?php echo (int)($img['id'] ?? 0); ?>"
                                            data-caminho="<?php echo htmlspecialchars($img['caminho_publico'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-name="<?php echo htmlspecialchars($img['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-confirm="Deletar a imagem <?php echo htmlspecialchars($img['nome'], ENT_QUOTES, 'UTF-8'); ?>? O arquivo físico será removido."
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

<?php require_once __DIR__ . "/../includes/paginacao.php"; renderPaginacaoAdmin($pagina, $totalPaginas, ['pasta' => $filtroPasta, 'busca' => $busca]); ?>

<!-- MODAL: PREVIEW DE IMAGEM -->
<div class="modal fade" id="modalPreviewImagemUpload" tabindex="-1" aria-labelledby="modalPreviewImagemUploadTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 94vw;">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-truncate" id="modalPreviewImagemUploadTitulo">Preview da Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <img id="preview-upload-img" src="" alt="" class="rounded border" style="max-width: 100%; max-height: 78vh; object-fit: contain;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary btn-sm js-copiar-link-upload" id="btn-copiar-preview-upload" data-url="">
                    <i class="fa-regular fa-copy me-1"></i>Copiar Link
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ENVIAR IMAGEM -->
<div class="modal fade" id="modalEnviarUpload" tabindex="-1" aria-labelledby="modalEnviarUploadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formEnviarUpload" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEnviarUploadLabel">Enviar Nova Imagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="upload-pasta-select" class="form-label fw-bold">Pasta de Destino (em <code>assets/</code>) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="upload-pasta-select" name="pasta" required>
                                <?php foreach ($pastasAssets as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($p === 'images') ? 'selected' : ''; ?>>
                                        assets/<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__nova__">+ Criar nova pasta...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="upload-nome-custom" class="form-label fw-bold">Nome da Imagem (opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="upload-nome-custom" name="nome_custom" placeholder="Ex: banner-evento-pascoa" maxlength="160">
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="upload-nova-pasta-wrap">
                        <label for="upload-nova-pasta" class="form-label fw-bold">Nome da Nova Pasta <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">assets/</span>
                            <input type="text" class="form-control form-control-sm font-monospace" id="upload-nova-pasta" name="nova_pasta" placeholder="Ex: banners ou eventos/2026">
                        </div>
                        <div class="form-text">Use apenas letras, números, hífen, underline ou <code>/</code> para subpastas.</div>
                    </div>

                    <div class="border rounded p-3">
                        <div class="fw-bold small mb-2">Arquivo de Imagem (JPG, PNG, WEBP ou GIF — máx. 10MB) <span class="text-danger">*</span></div>
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                            <div id="upload-imagem-preview" class="rounded d-flex flex-column align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 192px; height: 108px; border: 1px dashed var(--border-color, #475569); background: var(--admin-bg, transparent);">
                                <i class="fa-regular fa-image text-muted fs-4 mb-1"></i>
                                <span class="text-muted" style="font-size: 0.72rem;">Sem imagem</span>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <input type="file" class="d-none" id="upload-imagem-input" name="imagem" accept="image/*" required>
                                <label for="upload-imagem-input" class="btn btn-sm btn-primary mb-1">
                                    <i class="fa-solid fa-upload me-1"></i> Escolher imagem
                                </label>
                                <div id="upload-imagem-nome" class="text-muted small text-truncate">Nenhum arquivo selecionado</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-upload">
                        <i class="fa-solid fa-check me-1"></i> Enviar e Gerar Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pastaSelect = document.getElementById('upload-pasta-select');
    const novaPastaWrap = document.getElementById('upload-nova-pasta-wrap');
    const novaPastaInput = document.getElementById('upload-nova-pasta');
    const fileInput = document.getElementById('upload-imagem-input');
    const previewBox = document.getElementById('upload-imagem-preview');
    const fileNameEl = document.getElementById('upload-imagem-nome');
    const formUpload = document.getElementById('formEnviarUpload');
    const modalEl = document.getElementById('modalEnviarUpload');
    const bsModal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const btnSubmit = document.getElementById('btn-submit-upload');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    async function copiarUrl(url) {
        if (!url) return;
        try {
            await navigator.clipboard.writeText(url);
            AdminToast.success('Link copiado: ' + url);
        } catch (e) {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
            AdminToast.success('Link copiado!');
        }
    }

    const modalPreviewEl = document.getElementById('modalPreviewImagemUpload');
    const bsModalPreview = modalPreviewEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalPreviewEl) : null;
    const previewImgFull = document.getElementById('preview-upload-img');
    const previewTituloFull = document.getElementById('modalPreviewImagemUploadTitulo');
    const previewCopiarBtn = document.getElementById('btn-copiar-preview-upload');

    document.addEventListener('click', (e) => {
        const previewTrigger = e.target.closest('.js-abrir-preview-upload');
        if (previewTrigger && bsModalPreview && previewImgFull) {
            previewImgFull.src = previewTrigger.dataset.src || '';
            previewImgFull.alt = previewTrigger.dataset.nome || '';
            if (previewTituloFull) previewTituloFull.textContent = previewTrigger.dataset.nome || 'Preview da Imagem';
            if (previewCopiarBtn) previewCopiarBtn.dataset.url = previewTrigger.dataset.url || '';
            bsModalPreview.show();
            return;
        }
        const btn = e.target.closest('.js-copiar-link-upload');
        if (btn) {
            copiarUrl(btn.dataset.url || '');
        }
    });

    if (pastaSelect && novaPastaWrap && novaPastaInput) {
        pastaSelect.addEventListener('change', () => {
            const isNova = pastaSelect.value === '__nova__';
            novaPastaWrap.classList.toggle('d-none', !isNova);
            novaPastaInput.required = isNova;
            if (!isNova) novaPastaInput.value = '';
        });
    }

    if (fileInput && previewBox) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                if (fileNameEl) fileNameEl.textContent = this.files[0].name;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    previewBox.style.borderStyle = 'solid';
                    previewBox.innerHTML = `<img src="${ev.target.result}" class="w-100 h-100" style="object-fit: cover;" alt="Preview">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (formUpload) {
        formUpload.addEventListener('submit', async function(e) {
            e.preventDefault();
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Enviando...';
            }
            try {
                const res = await AdminApi.post('/admin/api/upload/enviar.php', new FormData(formUpload));
                if (bsModal) bsModal.hide();
                formUpload.reset();
                if (novaPastaWrap) novaPastaWrap.classList.add('d-none');
                if (novaPastaInput) novaPastaInput.required = false;
                if (previewBox) {
                    previewBox.style.borderStyle = 'dashed';
                    previewBox.innerHTML = '<i class="fa-regular fa-image text-muted fs-4 mb-1"></i><span class="text-muted" style="font-size: 0.72rem;">Sem imagem</span>';
                }
                if (fileNameEl) fileNameEl.textContent = 'Nenhum arquivo selecionado';

                if (res.item) {
                    const it = res.item;
                    const tbody = document.getElementById('lista-uploads');
                    const emptyRow = document.getElementById('linha-sem-uploads');
                    if (emptyRow) emptyRow.remove();

                    if (pastaSelect && !Array.from(pastaSelect.options).some((o) => o.value === it.pasta)) {
                        const opt = document.createElement('option');
                        opt.value = it.pasta;
                        opt.textContent = 'assets/' + it.pasta;
                        pastaSelect.insertBefore(opt, pastaSelect.querySelector('option[value="__nova__"]'));
                    }

                    const tr = document.createElement('tr');
                    tr.id = 'upload-row-' + it.id;
                    tr.className = 'table-row-highlight';
                    tr.innerHTML = `
                        <td>
                            <button type="button"
                                    class="btn p-0 border-0 bg-transparent js-abrir-preview-upload"
                                    data-src="${escapeHtml(it.caminho_publico)}"
                                    data-nome="${escapeHtml(it.nome)}"
                                    data-url="${escapeHtml(it.url_completa)}"
                                    title="Ampliar imagem">
                                <img src="${escapeHtml(it.caminho_publico)}" class="rounded border" width="52" height="36" style="object-fit: cover; cursor: zoom-in;" alt="">
                            </button>
                        </td>
                        <td>
                            <div class="fw-semibold text-truncate" style="max-width: 280px;">${escapeHtml(it.nome)}</div>
                            <small class="text-muted font-monospace d-block text-truncate" style="max-width: 320px;">${escapeHtml(it.caminho_publico)} (${escapeHtml(it.tamanho_formatado)})</small>
                        </td>
                        <td><span class="badge bg-light text-dark border font-monospace">assets/${escapeHtml(it.pasta)}</span></td>
                        <td><small>${escapeHtml(it.autor_nome)}</small></td>
                        <td class="text-muted small">Agora</td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-primary js-copiar-link-upload" data-url="${escapeHtml(it.url_completa)}" title="Copiar link completo">
                                    <i class="fa-regular fa-copy me-1"></i>Copiar
                                </button>
                                <button type="button" class="btn btn-sm btn-danger"
                                        data-action="async-delete"
                                        data-url="/admin/api/upload/deletar.php"
                                        data-id="${it.id}"
                                        data-name="${escapeHtml(it.nome)}"
                                        data-confirm="Deletar a imagem ${escapeHtml(it.nome)}? O arquivo físico será removido."
                                        title="Deletar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    if (tbody) tbody.prepend(tr);
                    setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
                }

                if (res.url_completa) {
                    await copiarUrl(res.url_completa);
                } else {
                    AdminToast.success(res.mensagem || 'Imagem enviada!');
                }
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao enviar imagem.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
