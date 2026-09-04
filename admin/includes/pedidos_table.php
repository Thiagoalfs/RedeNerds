<?php
/**
 * admin/includes/pedidos_table.php
 * Componente reutilizável da tabela de pedidos VIP com modal de detalhes instantâneo.
 *
 * Variáveis esperadas:
 * - array $pedidos: lista de pedidos a serem exibidos
 * - array $servidoresMap: mapa de servidores (por nome/id)
 * - string $emptyMessage (opcional): mensagem caso a lista esteja vazia
 * - bool $mostrarPaginacao (opcional): se deve exibir a paginação abaixo da tabela
 * - int $totalPaginas, $pagina, string $filtroStatus, $filtroMetodo, $busca (para paginação)
 */

$pedidos = $pedidos ?? [];
$servidoresMap = $servidoresMap ?? [];
$emptyMessage = $emptyMessage ?? 'Nenhum pedido encontrado.';
$mostrarPaginacao = $mostrarPaginacao ?? false;
?>

<div class="table-responsive">
    <table class="table table-admin align-middle mb-0">
        <thead>
            <tr>
                <th style="width: 220px;">Jogador</th>
                <th>Pacote VIP</th>
                <th>Servidor</th>
                <th>Método</th>
                <th>Valor Pago</th>
                <th>Cupom</th>
                <th>Status</th>
                <th>Data</th>
                <th class="text-end" style="width: 80px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pedidos)): ?>
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted"><?php echo htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($pedidos as $p): 
                    $status = strtolower($p['status'] ?? 'pendente');
                    $metodo = strtolower($p['metodo_pagamento'] ?? 'pix');
                    $parcelas = (int)($p['parcelas'] ?? 1);
                    $srvKey = $p['servidor'] ?? $p['servidor_nome'] ?? '';
                    $srvInfo = $servidoresMap[$srvKey] ?? ($servidoresMap[$p['servidor_id'] ?? 0] ?? null);
                    $srvCor = $srvInfo['themecolor'] ?? '#64748b';
                ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="https://mc-heads.net/avatar/<?php echo urlencode($p['nick']); ?>/32" 
                                     class="rounded border" width="32" height="32" alt="Skin"
                                     onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                                <div>
                                    <strong class="text-dark d-block"><?php echo htmlspecialchars($p['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="text-muted font-monospace" style="font-size: 0.72rem;">
                                        <?php echo htmlspecialchars(mb_strimwidth($p['txid'] ?? '', 0, 18, '...'), ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">
                                <i class="fa-solid fa-crown text-warning me-1"></i>
                                <?php echo htmlspecialchars($p['vip_nome'] ?? 'VIP', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($srvCor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff; font-weight: 600;">
                                <i class="fa-solid fa-server me-1"></i>
                                <?php echo htmlspecialchars($srvKey ?: 'Geral', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($metodo === 'cartao'): ?>
                                <span class="badge bg-info text-dark">
                                    <i class="fa-regular fa-credit-card me-1"></i> Cartão (<?php echo $parcelas; ?>x)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    <i class="fa-brands fa-pix me-1"></i> PIX
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-dark">R$ <?php echo number_format((float)($p['valor'] ?? $p['valor_pago'] ?? 0), 2, ',', '.'); ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($p['cupom_codigo'])): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-tag text-primary me-1"></i>
                                    <?php echo htmlspecialchars($p['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status === 'pago'): ?>
                                <span class="badge-status ativo"><i class="fa-solid fa-check me-1"></i> Aprovado</span>
                            <?php elseif ($status === 'pendente'): ?>
                                <span class="badge-status pendente"><i class="fa-solid fa-clock me-1"></i> Pendente</span>
                            <?php else: ?>
                                <span class="badge-status inativo"><i class="fa-solid fa-xmark me-1"></i> <?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo date('d/m/Y H:i', strtotime($p['criado_em'])); ?>
                        </td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary btn-ver-detalhes" 
                                    data-id="<?php echo (int)$p['id']; ?>"
                                    data-pedido="<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Detalhes do Pedido">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGINAÇÃO (QUANDO HABILITADA) -->
<?php if ($mostrarPaginacao && isset($totalPaginas) && $totalPaginas > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
        <ul class="pagination pagination-sm">
            <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo max(1, $pagina - 1); ?>&status=<?php echo urlencode($filtroStatus ?? ''); ?>&metodo=<?php echo urlencode($filtroMetodo ?? ''); ?>&busca=<?php echo urlencode($busca ?? ''); ?>">‹ Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filtroStatus ?? ''); ?>&metodo=<?php echo urlencode($filtroMetodo ?? ''); ?>&busca=<?php echo urlencode($busca ?? ''); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo min($totalPaginas, $pagina + 1); ?>&status=<?php echo urlencode($filtroStatus ?? ''); ?>&metodo=<?php echo urlencode($filtroMetodo ?? ''); ?>&busca=<?php echo urlencode($busca ?? ''); ?>">Próxima ›</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- MODAL DETALHES DO PEDIDO -->
<?php if (!defined('MODAL_DETALHES_PEDIDO_RENDERED')): define('MODAL_DETALHES_PEDIDO_RENDERED', true); ?>
<div class="modal fade" id="modalDetalhesPedido" tabindex="-1" aria-labelledby="modalDetalhesPedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalDetalhesPedidoLabel"><i class="fa-solid fa-receipt text-primary me-2"></i> Detalhes do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="modalDetalhesBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initDetalhesPedidos() {
        const modalEl = document.getElementById('modalDetalhesPedido');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        const bodyContent = document.getElementById('modalDetalhesBody');

        function renderDetalhesHtml(p) {
            const statusMap = {
                'pago': '<span class="badge-status ativo"><i class="fa-solid fa-check me-1"></i> Aprovado</span>',
                'pendente': '<span class="badge-status pendente"><i class="fa-solid fa-clock me-1"></i> Pendente</span>',
                'recusado': '<span class="badge-status inativo"><i class="fa-solid fa-xmark me-1"></i> Recusado</span>',
                'cancelado': '<span class="badge-status inativo"><i class="fa-solid fa-xmark me-1"></i> Cancelado</span>'
            };
            const statusBadge = statusMap[p.status?.toLowerCase()] || `<span class="badge-status inativo">${p.status || 'Desconhecido'}</span>`;
            const metodoBadge = (p.metodo_pagamento === 'cartao') 
                ? `<span class="badge bg-info text-dark"><i class="fa-regular fa-credit-card me-1"></i> Cartão (${p.parcelas || 1}x)</span>`
                : `<span class="badge bg-success"><i class="fa-brands fa-pix me-1"></i> PIX</span>`;

            const valorFormatado = parseFloat(p.valor || p.valor_pago || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const descontoFormatado = parseFloat(p.desconto_aplicado || p.cupom_desconto || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            return `
                <div class="p-3 bg-light border-bottom d-flex align-items-center gap-3">
                    <img src="https://mc-heads.net/avatar/${encodeURIComponent(p.nick)}/48" class="rounded border" width="48" height="48" alt="Skin" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/48'">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">${p.nick}</h5>
                        <small class="text-muted font-monospace">ID do Pedido: #${p.id}</small>
                    </div>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Status:</span>
                        <span>${statusBadge}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">TXID / Transação:</span>
                        <span class="font-monospace text-break" style="font-size: 0.78rem;">${p.txid || '—'}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Servidor:</span>
                        <span class="fw-semibold">${p.servidor || p.servidor_nome || '—'}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Pacote VIP:</span>
                        <span class="badge bg-light text-dark border font-monospace fs-6"><i class="fa-solid fa-crown text-warning me-1"></i>${p.vip_nome || '—'}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Método de Pagamento:</span>
                        <span>${metodoBadge}</span>
                    </li>
                    ${p.payer_email ? `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">E-mail Pagador:</span>
                        <span>${p.payer_email}</span>
                    </li>` : ''}
                    ${p.payer_cpf ? `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">CPF Pagador:</span>
                        <span class="font-monospace">${p.payer_cpf}</span>
                    </li>` : ''}
                    ${p.card_first_six_digits ? `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Cartão:</span>
                        <span>${p.card_first_six_digits}••••••${p.card_last_four_digits}</span>
                    </li>` : ''}
                    ${p.cupom_codigo ? `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Cupom Aplicado:</span>
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-tag me-1"></i>${p.cupom_codigo} (-R$ ${descontoFormatado})</span>
                    </li>` : ''}
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Valor Pago:</span>
                        <strong class="text-success fs-6">R$ ${valorFormatado}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Criado em:</span>
                        <span>${p.criado_em || '—'}</span>
                    </li>
                    ${p.pago_em ? `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Pago em:</span>
                        <span>${p.pago_em}</span>
                    </li>` : ''}
                </ul>
            `;
        }

        document.querySelectorAll('.btn-ver-detalhes').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;

                if (btn.dataset.pedido) {
                    try {
                        const p = JSON.parse(btn.dataset.pedido);
                        bodyContent.innerHTML = renderDetalhesHtml(p);
                        modalInstance.show();
                        return;
                    } catch(e) {}
                }

                bodyContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
                modalInstance.show();

                try {
                    const res = await fetch(`/admin/api/pedidos/detalhes.php?id=${id}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const text = await res.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Falha ao processar resposta do servidor.');
                    }
                    if (!res.ok || !data.pedido) {
                        throw new Error(data.erro || 'Falha ao buscar dados do pedido.');
                    }
                    bodyContent.innerHTML = renderDetalhesHtml(data.pedido);
                } catch (err) {
                    bodyContent.innerHTML = `<div class="alert alert-danger mb-0">${err.message}</div>`;
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDetalhesPedidos);
    } else {
        initDetalhesPedidos();
    }
})();
</script>
<?php endif; ?>
