<?php
$paginaAtiva = 'webhooks';
$tituloPagina = 'Webhooks';
require_once __DIR__ . "/../includes/admin_header.php";

$servidores = [];
$servidoresAtivos = [];
$servidoresInativos = [];
$webhooksPorServidor = [];
$erroCarregamento = '';
$totalCompletos = 0;
$totalPendentes = 0;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $servidores = $pdo->query("
            SELECT id, servername, nome, icon, themecolor, enabled, discord_role_id
            FROM servidores
            ORDER BY servername ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $rows = $pdo->query("
            SELECT servidor_id, categoria, webhook_url, atualizado_em
            FROM servidores_webhooks
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $sid = (int)$r['servidor_id'];
            $webhooksPorServidor[$sid][$r['categoria']] = [
                'url' => (string)$r['webhook_url'],
                'atualizado_em' => $r['atualizado_em'] ?? null,
            ];
        }

        foreach ($servidores as $srv) {
            $sid = (int)$srv['id'];
            $temAn = !empty($webhooksPorServidor[$sid]['Anúncios']['url']);
            $temAt = !empty($webhooksPorServidor[$sid]['Atualizações']['url']);
            if ($temAn && $temAt) {
                $totalCompletos++;
            } else {
                $totalPendentes++;
            }
            // 'Geral' fica junto aos visíveis nos webhooks para permitir anúncios gerais da rede
            if ((int)$srv['enabled'] === 1 || strcasecmp((string)$srv['servername'], 'Geral') === 0) {
                $servidoresAtivos[] = $srv;
            } else {
                $servidoresInativos[] = $srv;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Erro ao carregar webhooks: " . $e->getMessage());
    $erroCarregamento = "Não foi possível carregar a tabela de webhooks. Verifique se a migration foi executada.";
}

function renderIconeWebhook(?string $icone): string {
    if (!$icone) {
        return '<i class="fa-solid fa-server text-muted"></i>';
    }
    if (function_exists('tipoDoIcone') && tipoDoIcone($icone) === 'img') {
        $src = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="rounded" width="32" height="32" style="object-fit: cover;" alt="">';
    }
    if (preg_match('#^(/|https?://|assets/|uploads/)#i', $icone)) {
        $src = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="rounded" width="32" height="32" style="object-fit: cover;" alt="">';
    }
    return '<i class="' . htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') . '"></i>';
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Webhooks</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($servidores), 'servidor', 'servidores'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" id="webhooks-resumo-completos"><?php echo pluralizar($totalCompletos, 'completo', 'completos'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" id="webhooks-resumo-pendentes"><?php echo pluralizar($totalPendentes, 'pendente', 'pendentes'); ?></span>
        </p>
    </div>
</div>

<?php if ($erroCarregamento !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($erroCarregamento, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php
function renderTabelaWebhooks(array $lista, array $webhooksPorServidor, string $emptyTitulo): void { ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">Ícone</th>
                        <th>Servidor</th>
                        <th style="width: 150px;">Anúncios</th>
                        <th style="width: 150px;">Atualizações</th>
                        <th style="width: 180px;">Status Geral</th>
                        <th class="text-end" style="width: 120px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista)): ?>
                        <tr class="empty-row">
                            <td colspan="6" class="text-center py-4">
                                <div class="admin-empty-state py-3">
                                    <div class="empty-icon"><i class="fa-brands fa-discord"></i></div>
                                    <h6 class="empty-title"><?php echo htmlspecialchars($emptyTitulo, ENT_QUOTES, 'UTF-8'); ?></h6>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lista as $srv):
                            $sid = (int)$srv['id'];
                            $wAnuncios = $webhooksPorServidor[$sid]['Anúncios']['url'] ?? '';
                            $wAtualizacoes = $webhooksPorServidor[$sid]['Atualizações']['url'] ?? '';
                            $temAnuncios = $wAnuncios !== '';
                            $temAtualizacoes = $wAtualizacoes !== '';

                            if ($temAnuncios && $temAtualizacoes) {
                                $resumoBadge = '<span class="badge-status visivel">Ambos cadastrados</span>';
                            } elseif (!$temAnuncios && !$temAtualizacoes) {
                                $resumoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Nenhum cadastrado</span>';
                            } elseif (!$temAnuncios) {
                                $resumoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Falta Anúncios</span>';
                            } else {
                                $resumoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Falta Atualizações</span>';
                            }
                        ?>
                        <tr data-servidor-id="<?php echo $sid; ?>">
                            <td class="text-center"><?php echo renderIconeWebhook($srv['icon'] ?? null); ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <small class="text-muted font-monospace js-col-role"><?php echo !empty($srv['discord_role_id']) ? 'Cargo ID: ' . htmlspecialchars($srv['discord_role_id'], ENT_QUOTES, 'UTF-8') : 'Sem cargo de menção'; ?></small>
                            </td>
                            <td class="js-col-anuncios">
                                <?php if ($temAnuncios): ?>
                                    <span class="badge-status visivel"><i class="fa-solid fa-check me-1"></i>Cadastrado</span>
                                <?php else: ?>
                                    <span class="badge-status oculto">Não vinculado</span>
                                <?php endif; ?>
                            </td>
                            <td class="js-col-atualizacoes">
                                <?php if ($temAtualizacoes): ?>
                                    <span class="badge-status visivel"><i class="fa-solid fa-check me-1"></i>Cadastrado</span>
                                <?php else: ?>
                                    <span class="badge-status oculto">Não vinculado</span>
                                <?php endif; ?>
                            </td>
                            <td class="js-col-resumo"><?php echo $resumoBadge; ?></td>
                            <td class="text-end text-nowrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary js-btn-config-webhook"
                                    title="Configurar Webhooks"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalConfigWebhook"
                                    data-servidor-id="<?php echo $sid; ?>"
                                    data-servidor-nome="<?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-url-anuncios="<?php echo htmlspecialchars($wAnuncios, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-url-atualizacoes="<?php echo htmlspecialchars($wAtualizacoes, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-role-id="<?php echo htmlspecialchars((string)($srv['discord_role_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Configurar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php } ?>

<!-- TABELA 1: SERVIDORES ATIVOS / VISÍVEIS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Servidores Visíveis</h6>
        <span class="text-muted small fw-medium"><?php echo pluralizar(count($servidoresAtivos), 'servidor', 'servidores'); ?></span>
    </div>
    <?php renderTabelaWebhooks($servidoresAtivos, $webhooksPorServidor, 'Nenhum servidor visível cadastrado'); ?>
</div>

<!-- TABELA 2: SERVIDORES OCULTOS / INATIVOS -->
<div class="admin-card">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">Servidores Ocultos</h6>
        <span class="text-muted small fw-medium"><?php echo pluralizar(count($servidoresInativos), 'servidor', 'servidores'); ?></span>
    </div>
    <?php renderTabelaWebhooks($servidoresInativos, $webhooksPorServidor, 'Nenhum servidor oculto cadastrado'); ?>
</div>

<!-- MODAL: CONFIGURAR WEBHOOKS -->
<div class="modal fade" id="modalConfigWebhook" tabindex="-1" aria-labelledby="modalConfigWebhookLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formConfigWebhook">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="servidor_id" id="webhook-servidor-id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalConfigWebhookLabel">Webhooks — <span id="webhook-servidor-nome"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <div class="mb-3">
                        <label for="webhook-url-anuncios" class="form-label fw-bold">Webhook de Anúncios</label>
                        <input type="url" class="form-control form-control-sm" id="webhook-url-anuncios" name="webhook_anuncios" placeholder="https://discord.com/api/webhooks/...">
                        <div class="form-text">Deixe em branco para desvincular o canal de Anúncios deste servidor.</div>
                    </div>
                    <div class="mb-3">
                        <label for="webhook-url-atualizacoes" class="form-label fw-bold">Webhook de Atualizações</label>
                        <input type="url" class="form-control form-control-sm" id="webhook-url-atualizacoes" name="webhook_atualizacoes" placeholder="https://discord.com/api/webhooks/...">
                        <div class="form-text">Deixe em branco para desvincular o canal de Atualizações deste servidor.</div>
                    </div>
                    <div class="mb-1">
                        <label for="webhook-role-id" class="form-label fw-bold">ID do Cargo no Discord (Menção)</label>
                        <input type="text" inputmode="numeric" pattern="\d{15,25}" class="form-control form-control-sm font-monospace" id="webhook-role-id" name="discord_role_id" placeholder="Ex: 1234567890123456789 (opcional)">
                        <div class="form-text">Cargo mencionado ao marcar a opção de menção em Novidades. Se vazio, usa <code>@everyone</code>.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-salvar-webhook">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalConfigWebhook');
    const bsModal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const form = document.getElementById('formConfigWebhook');
    const inputSid = document.getElementById('webhook-servidor-id');
    const spanNome = document.getElementById('webhook-servidor-nome');
    const inputAnuncios = document.getElementById('webhook-url-anuncios');
    const inputAtualizacoes = document.getElementById('webhook-url-atualizacoes');
    const inputRoleId = document.getElementById('webhook-role-id');
    const btnSalvar = document.getElementById('btn-salvar-webhook');

    function atualizarContadoresWebhooks() {
        let completos = 0;
        let pendentes = 0;
        document.querySelectorAll('.admin-card tbody tr[data-servidor-id]').forEach((tr) => {
            const b = tr.querySelector('.js-btn-config-webhook');
            const temAn = Boolean(b && b.dataset.urlAnuncios);
            const temAt = Boolean(b && b.dataset.urlAtualizacoes);
            if (temAn && temAt) completos++;
            else pendentes++;
        });
        const elComp = document.getElementById('webhooks-resumo-completos');
        const elPend = document.getElementById('webhooks-resumo-pendentes');
        if (elComp) elComp.textContent = completos + ' ' + (completos === 1 ? 'completo' : 'completos');
        if (elPend) elPend.textContent = pendentes + ' ' + (pendentes === 1 ? 'pendente' : 'pendentes');
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            if (!btn) return;
            inputSid.value = btn.dataset.servidorId || '';
            spanNome.textContent = btn.dataset.servidorNome || '';
            inputAnuncios.value = btn.dataset.urlAnuncios || '';
            inputAtualizacoes.value = btn.dataset.urlAtualizacoes || '';
            if (inputRoleId) inputRoleId.value = btn.dataset.roleId || '';
        });
    }

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const originalHtml = btnSalvar ? btnSalvar.innerHTML : '';
            if (btnSalvar) {
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }
            try {
                const res = await AdminApi.post('/admin/api/webhooks/salvar.php', new FormData(form));
                AdminToast.success(res.mensagem || 'Webhooks atualizados!');
                const sid = String(res.servidor_id || inputSid.value);
                const row = document.querySelector(`tr[data-servidor-id="${sid}"]`);
                if (row) {
                    const btn = row.querySelector('.js-btn-config-webhook');
                    if (btn) {
                        btn.dataset.urlAnuncios = inputAnuncios.value.trim();
                        btn.dataset.urlAtualizacoes = inputAtualizacoes.value.trim();
                        btn.dataset.roleId = res.discord_role_id || '';
                    }
                    const colRole = row.querySelector('.js-col-role');
                    if (colRole) {
                        colRole.textContent = res.discord_role_id ? ('Cargo ID: ' + res.discord_role_id) : 'Sem cargo de menção';
                    }
                    const colAn = row.querySelector('.js-col-anuncios');
                    const colAt = row.querySelector('.js-col-atualizacoes');
                    const colRes = row.querySelector('.js-col-resumo');
                    if (colAn) {
                        colAn.innerHTML = res.anuncios
                            ? '<span class="badge-status visivel"><i class="fa-solid fa-check me-1"></i>Cadastrado</span>'
                            : '<span class="badge-status oculto">Não vinculado</span>';
                    }
                    if (colAt) {
                        colAt.innerHTML = res.atualizacoes
                            ? '<span class="badge-status visivel"><i class="fa-solid fa-check me-1"></i>Cadastrado</span>'
                            : '<span class="badge-status oculto">Não vinculado</span>';
                    }
                    if (colRes) {
                        if (res.anuncios && res.atualizacoes) {
                            colRes.innerHTML = '<span class="badge-status visivel">Ambos cadastrados</span>';
                        } else if (!res.anuncios && !res.atualizacoes) {
                            colRes.innerHTML = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Nenhum cadastrado</span>';
                        } else if (!res.anuncios) {
                            colRes.innerHTML = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Falta Anúncios</span>';
                        } else {
                            colRes.innerHTML = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Falta Atualizações</span>';
                        }
                    }
                    row.classList.add('table-row-highlight');
                    setTimeout(() => row.classList.remove('table-row-highlight'), 1000);
                }
                atualizarContadoresWebhooks();
                if (bsModal) bsModal.hide();
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao salvar webhooks.');
            } finally {
                if (btnSalvar) {
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = originalHtml;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
