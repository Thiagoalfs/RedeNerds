<?php
$paginaAtiva = 'pedidos';
$tituloPagina = 'Pedidos VIP & Vendas';
require_once __DIR__ . "/../includes/admin_header.php";

const POR_PAGINA = 15;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$filtroStatus = trim($_GET['status'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$busca = trim($_GET['busca'] ?? '');

$where = [];
$params = [];

if (!empty($filtroStatus)) {
    $where[] = "status = :status";
    $params[':status'] = $filtroStatus;
}

if (!empty($filtroMetodo)) {
    $where[] = "metodo_pagamento = :metodo";
    $params[':metodo'] = $filtroMetodo;
}

if (!empty($busca)) {
    $where[] = "(nick LIKE :busca OR txid LIKE :busca OR payer_email LIKE :busca OR payer_cpf LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$totalPedidos = 0;
$pedidos = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pedidos_vip $whereSql");
        $stmtCount->execute($params);
        $totalPedidos = (int)$stmtCount->fetchColumn();

        $totalPaginas = max(1, (int)ceil($totalPedidos / POR_PAGINA));

        $stmt = $pdo->prepare("
            SELECT * FROM pedidos_vip 
            $whereSql 
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao listar pedidos: " . $e->getMessage());
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Pedidos VIP & Vendas</h4>
        <p class="text-muted small mb-0"><?php echo $totalPedidos; ?> pedido(s) registrado(s)</p>
    </div>
</div>

<!-- FILTROS E BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por Nick, TXID, E-mail ou CPF..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Status: Todos</option>
                    <option value="pago" <?php echo ($filtroStatus === 'pago') ? 'selected' : ''; ?>>Aprovados</option>
                    <option value="pendente" <?php echo ($filtroStatus === 'pendente') ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="recusado" <?php echo ($filtroStatus === 'recusado') ? 'selected' : ''; ?>>Recusados / Cancelados</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="metodo" class="form-select form-select-sm">
                    <option value="">Método: Todos</option>
                    <option value="pix" <?php echo ($filtroMetodo === 'pix') ? 'selected' : ''; ?>>PIX</option>
                    <option value="cartao" <?php echo ($filtroMetodo === 'cartao') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                <?php if (!empty($busca) || !empty($filtroStatus) || !empty($filtroMetodo)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PEDIDOS -->
<div class="admin-card">
    <div class="card-body p-0">
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
                            <td colspan="9" class="text-center py-4 text-muted">Nenhum pedido encontrado com os filtros selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): 
                            $status = strtolower($p['status'] ?? 'pendente');
                            $metodo = strtolower($p['metodo_pagamento'] ?? 'pix');
                            $parcelas = (int)($p['parcelas'] ?? 1);
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
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($p['servidor_nome'] ?? 'Geral', ENT_QUOTES, 'UTF-8'); ?>
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
                                    <strong class="text-dark">R$ <?php echo number_format((float)($p['valor_pago'] ?? 0), 2, ',', '.'); ?></strong>
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
    </div>
</div>

<!-- PAGINAÇÃO -->
<?php if (isset($totalPaginas) && $totalPaginas > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
        <ul class="pagination pagination-sm">
            <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo max(1, $pagina - 1); ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>&busca=<?php echo urlencode($busca); ?>">‹ Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>&busca=<?php echo urlencode($busca); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo min($totalPaginas, $pagina + 1); ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>&busca=<?php echo urlencode($busca); ?>">Próxima ›</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- MODAL DETALHES DO PEDIDO -->
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
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalDetalhesPedido');
    const modalInstance = new bootstrap.Modal(modalEl);
    const bodyContent = document.getElementById('modalDetalhesBody');

    function renderDetalhesHtml(p) {
        const statusMap = {
            'pago': '<span class="badge-status ativo"><i class="fa-solid fa-check me-1"></i> Aprovado</span>',
            'pendente': '<span class="badge-status pendente"><i class="fa-solid fa-clock me-1"></i> Pendente</span>',
            'recusado': '<span class="badge-status inativo"><i class="fa-solid fa-xmark me-1"></i> Recusado</span>'
        };
        const statusBadge = statusMap[p.status?.toLowerCase()] || `<span class="badge-status inativo">${p.status || 'Desconhecido'}</span>`;
        const metodoBadge = (p.metodo_pagamento === 'cartao') 
            ? `<span class="badge bg-info text-dark"><i class="fa-regular fa-credit-card me-1"></i> Cartão (${p.parcelas || 1}x)</span>`
            : `<span class="badge bg-success"><i class="fa-brands fa-pix me-1"></i> PIX</span>`;

        const valorFormatado = parseFloat(p.valor_pago || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const descontoFormatado = parseFloat(p.cupom_desconto || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                    <span class="fw-semibold">${p.servidor_nome || '—'}</span>
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

            // Tentativa instantânea via data-pedido embutido (zero delay)
            if (btn.dataset.pedido) {
                try {
                    const p = JSON.parse(btn.dataset.pedido);
                    bodyContent.innerHTML = renderDetalhesHtml(p);
                    modalInstance.show();
                    return;
                } catch(e) {}
            }

            // Fallback via API se não estiver em cache no elemento
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
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
