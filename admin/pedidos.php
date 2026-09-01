<?php
$paginaAtiva = 'pedidos';
$tituloPagina = 'Pedidos VIP & Vendas';
require_once __DIR__ . "/includes/admin_header.php";

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
        <form method="GET" action="pedidos.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por Nick, TXID, E-mail ou CPF..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Status: Todos</option>
                    <option value="pago" <?php echo ($filtroStatus === 'pago') ? 'selected' : ''; ?>>🟢 Aprovados</option>
                    <option value="pendente" <?php echo ($filtroStatus === 'pendente') ? 'selected' : ''; ?>>🟡 Pendentes</option>
                    <option value="recusado" <?php echo ($filtroStatus === 'recusado') ? 'selected' : ''; ?>>🔴 Recusados / Cancelados</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="metodo" class="form-select form-select-sm">
                    <option value="">Método: Todos</option>
                    <option value="pix" <?php echo ($filtroMetodo === 'pix') ? 'selected' : ''; ?>>⚡ PIX</option>
                    <option value="cartao" <?php echo ($filtroMetodo === 'cartao') ? 'selected' : ''; ?>>💳 Cartão de Crédito</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                <?php if (!empty($busca) || !empty($filtroStatus) || !empty($filtroMetodo)): ?>
                    <a href="pedidos.php" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros"><i class="fa-solid fa-xmark"></i></a>
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
                                            <strong class="text-dark"><?php echo htmlspecialchars($p['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="badge bg-light text-muted border font-monospace d-block" style="font-size: 0.68rem;"><?php echo htmlspecialchars($p['txid'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><strong class="text-primary"><?php echo htmlspecialchars($p['vip_nome'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['servidor'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <?php if ($metodo === 'cartao'): ?>
                                        <span class="badge bg-info text-dark">💳 Cartão <?php echo ($parcelas > 1) ? "({$parcelas}x)" : ""; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">⚡ PIX</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark">R$ <?php echo number_format((float)$p['valor'], 2, ',', '.'); ?></strong>
                                    <?php if (isset($p['valor_original']) && (float)$p['valor_original'] > (float)$p['valor']): ?>
                                        <small class="text-muted d-block text-decoration-line-through">R$ <?php echo number_format((float)$p['valor_original'], 2, ',', '.'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['cupom_codigo'])): ?>
                                        <span class="badge bg-light text-dark border">🏷️ <?php echo htmlspecialchars($p['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'pago'): ?>
                                        <span class="badge-status pago">🟢 Aprovado</span>
                                    <?php elseif ($status === 'pendente'): ?>
                                        <span class="badge-status pendente">🟡 Pendente</span>
                                    <?php else: ?>
                                        <span class="badge-status recusado">🔴 Recusado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($p['criado_em'])); ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-ver-detalhes" 
                                            data-id="<?php echo (int)$p['id']; ?>"
                                            data-pedido='<?php echo htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
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
                <a class="page-link" href="?page=<?php echo max(1, $pagina - 1); ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>">‹ Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo min($totalPaginas, $pagina + 1); ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo urlencode($filtroStatus); ?>&metodo=<?php echo urlencode($filtroMetodo); ?>">Próxima ›</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- MODAL DE DETALHES DO PEDIDO -->
<div class="modal fade" id="modal-detalhes-pedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="detalhe-title">Detalhes da Transação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="detalhes-conteudo">
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
    const modalEl = document.getElementById('modal-detalhes-pedido');
    const modalInstance = new bootstrap.Modal(modalEl);
    const bodyContent = document.getElementById('detalhes-conteudo');

    function renderDetalhesHtml(p) {
        const statusBadge = (p.status === 'pago') 
            ? '<span class="badge bg-success">🟢 Aprovado</span>' 
            : (p.status === 'pendente' ? '<span class="badge bg-warning text-dark">🟡 Pendente</span>' : '<span class="badge bg-danger">🔴 Recusado / Cancelado</span>');

        const metodoBadge = (p.metodo_pagamento === 'cartao')
            ? `💳 Cartão de Crédito ${(p.parcelas && p.parcelas > 1) ? `(${p.parcelas}x)` : ''}`
            : '⚡ PIX';

        const valorFormatado = Number(p.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const descontoFormatado = Number(p.desconto_aplicado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const valorOrigFormatado = Number(p.valor_original || p.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        return `
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3 border">
                <img src="https://mc-heads.net/avatar/${encodeURIComponent(p.nick)}/48" class="rounded border" width="48" height="48" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/48'">
                <div>
                    <h6 class="fw-bold mb-0">${p.nick}</h6>
                    <small class="text-muted">${p.servidor || 'Servidor'} • <span class="text-primary fw-semibold">${p.vip_nome || 'VIP'}</span></small>
                </div>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">Status:</span>
                    <div>${statusBadge}</div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">Identificador (TXID):</span>
                    <strong class="font-monospace text-dark">${p.txid || '—'}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">Mercado Pago ID:</span>
                    <span class="font-monospace">${p.mp_payment_id || '—'}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">Método:</span>
                    <strong>${metodoBadge}</strong>
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
                    <span class="badge bg-light text-dark border">🏷️ ${p.cupom_codigo} (-R$ ${descontoFormatado})</span>
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
                const res = await fetch(`api/pedidos/detalhes.php?id=${id}`, {
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

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>