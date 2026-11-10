<?php
$paginaAtiva = 'dashboard';
$tituloPagina = 'Dashboard';
require_once __DIR__ . "/includes/admin_header.php";
require_once __DIR__ . "/includes/servidores_admin.php";

// 1. Consulta Métricas & KPIs
$totalFaturamento = 0.00;
$faturamentoMes = 0.00;
$faturamentoMesAnterior = 0.00;
$totalPedidosPagos = 0;
$pedidosMes = 0;
$pedidosMesAnterior = 0;
$totalPedidosPendentes = 0;
$totalCuponsAtivos = 0;
$totalUsosCupons = 0;
$totalServidores = 0;
$totalNoticias = 0;
$ticketMedioMes = 0.00;
$ultimosPedidos = [];
$servidoresMap = [];
$vendas7dias = [];
$metodosPagamento = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $servidoresMap = carregarServidoresAdmin($pdo)['mapa'];

        // Métricas consolidadas de faturamento e vendas com comparação com o mês anterior
        $metricasPedidos = $pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) AS total_faturamento,
                COALESCE(SUM(CASE WHEN status = 'pago' AND criado_em >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01 00:00:00') THEN valor ELSE 0 END), 0) AS faturamento_mes,
                COALESCE(SUM(CASE WHEN status = 'pago' AND criado_em >= DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), '%Y-%m-01 00:00:00') AND criado_em < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01 00:00:00') THEN valor ELSE 0 END), 0) AS faturamento_mes_anterior,
                COUNT(CASE WHEN status = 'pago' THEN 1 END) AS pedidos_pagos,
                COUNT(CASE WHEN status = 'pago' AND criado_em >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01 00:00:00') THEN 1 END) AS pedidos_mes,
                COUNT(CASE WHEN status = 'pago' AND criado_em >= DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), '%Y-%m-01 00:00:00') AND criado_em < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01 00:00:00') THEN 1 END) AS pedidos_mes_anterior,
                COUNT(CASE WHEN status = 'pendente' THEN 1 END) AS pedidos_pendentes
            FROM pedidos
        ")->fetch(PDO::FETCH_ASSOC);

        $totalFaturamento = (float)($metricasPedidos['total_faturamento'] ?? 0);
        $faturamentoMes = (float)($metricasPedidos['faturamento_mes'] ?? 0);
        $faturamentoMesAnterior = (float)($metricasPedidos['faturamento_mes_anterior'] ?? 0);
        $totalPedidosPagos = (int)($metricasPedidos['pedidos_pagos'] ?? 0);
        $pedidosMes = (int)($metricasPedidos['pedidos_mes'] ?? 0);
        $pedidosMesAnterior = (int)($metricasPedidos['pedidos_mes_anterior'] ?? 0);
        $totalPedidosPendentes = (int)($metricasPedidos['pedidos_pendentes'] ?? 0);

        if ($pedidosMes > 0) {
            $ticketMedioMes = $faturamentoMes / $pedidosMes;
        }

        // Vendas dos últimos 7 dias para visualização analítica
        $stmt7dias = $pdo->query("
            SELECT 
                DATE(criado_em) AS dia,
                COUNT(*) AS qtd,
                COALESCE(SUM(valor), 0) AS total
            FROM pedidos
            WHERE status = 'pago' AND criado_em >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
            GROUP BY DATE(criado_em)
            ORDER BY dia ASC
        ");
        $vendas7dias = $stmt7dias->fetchAll(PDO::FETCH_ASSOC);

        // Métodos de pagamento (PIX vs Cartão)
        $stmtMetodos = $pdo->query("
            SELECT 
                metodo_pagamento,
                COUNT(*) AS total_pedidos,
                COALESCE(SUM(valor), 0) AS total_valor
            FROM pedidos
            WHERE status = 'pago'
            GROUP BY metodo_pagamento
        ");
        $metodosPagamento = $stmtMetodos->fetchAll(PDO::FETCH_ASSOC);

        // Cupons
        $totalCuponsAtivos = (int)$pdo->query("SELECT COUNT(*) FROM cupons WHERE ativo = 1 AND expira_em >= NOW()")->fetchColumn();
        $totalUsosCupons = (int)$pdo->query("SELECT COALESCE(SUM(usos_total), 0) FROM cupons")->fetchColumn();

        // Servidores & Novidades
        $totalServidores = (int)$pdo->query("SELECT COUNT(*) FROM servidores WHERE enabled = 1")->fetchColumn();
        $totalNoticias = (int)$pdo->query("SELECT COUNT(*) FROM novidades")->fetchColumn();

        // Últimos 8 Pedidos
        $stmtUltimos = $pdo->query("SELECT * FROM pedidos ORDER BY id DESC LIMIT 8");
        $ultimosPedidos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro no dashboard admin: " . $e->getMessage());
}

// Cálculo do delta de faturamento
$deltaFaturamento = null;
if ($faturamentoMesAnterior > 0) {
    $deltaFaturamento = (($faturamentoMes - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100;
} elseif ($faturamentoMes > 0) {
    $deltaFaturamento = 100.0;
}

// Cálculo do delta de pedidos aprovados
$deltaPedidos = null;
if ($pedidosMesAnterior > 0) {
    $deltaPedidos = (($pedidosMes - $pedidosMesAnterior) / $pedidosMesAnterior) * 100;
} elseif ($pedidosMes > 0) {
    $deltaPedidos = 100.0;
}

// Montagem do histórico contínuo dos últimos 7 dias
$diasSemanaPt = [
    'Sun' => 'Dom',
    'Mon' => 'Seg',
    'Tue' => 'Ter',
    'Wed' => 'Qua',
    'Thu' => 'Qui',
    'Fri' => 'Sex',
    'Sat' => 'Sáb'
];

$ultimos7Dias = [];
for ($i = 6; $i >= 0; $i--) {
    $dataYmd = date('Y-m-d', strtotime("-$i days"));
    $diaSemanaIngles = date('D', strtotime($dataYmd));
    $ultimos7Dias[$dataYmd] = [
        'dia' => $dataYmd,
        'label' => $diasSemanaPt[$diaSemanaIngles] ?? $diaSemanaIngles,
        'data_formatada' => date('d/m', strtotime($dataYmd)),
        'total' => 0.0,
        'qtd' => 0,
        'is_hoje' => ($i === 0)
    ];
}

foreach ($vendas7dias as $v) {
    $diaKey = $v['dia'] ?? '';
    if (isset($ultimos7Dias[$diaKey])) {
        $ultimos7Dias[$diaKey]['total'] = (float)$v['total'];
        $ultimos7Dias[$diaKey]['qtd'] = (int)$v['qtd'];
    }
}

$maiorValorDia = 0.0;
$totalValor7Dias = 0.0;
$totalQtd7Dias = 0;
foreach ($ultimos7Dias as $d) {
    $totalValor7Dias += $d['total'];
    $totalQtd7Dias += $d['qtd'];
    if ($d['total'] > $maiorValorDia) {
        $maiorValorDia = $d['total'];
    }
}

// Processamento dos métodos de pagamento
$totalMetodosQtd = 0;
$metodoPix = ['qtd' => 0, 'total' => 0.0];
$metodoCartao = ['qtd' => 0, 'total' => 0.0];

foreach ($metodosPagamento as $m) {
    $tipo = strtolower($m['metodo_pagamento'] ?? '');
    $qtd = (int)($m['total_pedidos'] ?? 0);
    $val = (float)($m['total_valor'] ?? 0.0);
    $totalMetodosQtd += $qtd;

    if (strpos($tipo, 'pix') !== false) {
        $metodoPix['qtd'] += $qtd;
        $metodoPix['total'] += $val;
    } else {
        $metodoCartao['qtd'] += $qtd;
        $metodoCartao['total'] += $val;
    }
}

$percPix = $totalMetodosQtd > 0 ? round(($metodoPix['qtd'] / $totalMetodosQtd) * 100) : 0;
$percCartao = $totalMetodosQtd > 0 ? (100 - $percPix) : 0;
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Olá, <?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="text-muted small mb-0">Resumo consolidado em tempo real da saúde da Rede Nerd.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/noticias/?action=novo" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Nova Novidade</a>
        <a href="/admin/cupons/?action=novo" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-tags me-1"></i> Novo Cupom</a>
        <a href="/admin/servidores/?action=novo" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-server me-1"></i> Novo Servidor</a>
    </div>
</div>

<!-- GRID DE CARDS KPI -->
<div class="kpi-grid">
    <!-- Card 1: Faturamento do Mês (Abre modal detalhado com gráficos ao clicar) -->
    <div class="kpi-card kpi-card-clickable" role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#modalFaturamentoDetalhes" title="Clique para ver o gráfico e histórico de faturamento">
        <div class="kpi-card-top">
            <span class="kpi-label d-inline-flex align-items-center gap-1">
                Faturamento do Mês
                <i class="fa-solid fa-arrow-up-right-from-square text-muted" style="font-size: 0.65rem;" title="Clique para abrir detalhes"></i>
            </span>
            <?php if ($deltaFaturamento !== null): ?>
                <?php if ($deltaFaturamento > 0): ?>
                    <span class="kpi-delta positive" title="Variação em relação ao mês anterior">
                        <i class="fa-solid fa-arrow-up"></i> +<?php echo number_format($deltaFaturamento, 1); ?>%
                    </span>
                <?php elseif ($deltaFaturamento < 0): ?>
                    <span class="kpi-delta negative" title="Variação em relação ao mês anterior">
                        <i class="fa-solid fa-arrow-down"></i> <?php echo number_format($deltaFaturamento, 1); ?>%
                    </span>
                <?php else: ?>
                    <span class="kpi-delta neutral">0%</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="kpi-delta neutral">Primeiro mês</span>
            <?php endif; ?>
        </div>
        <div class="kpi-info">
            <div class="kpi-value text-success">R$ <?php echo number_format($faturamentoMes, 2, ',', '.'); ?></div>
            <div class="kpi-subtext">
                <span>R$ <?php echo number_format($totalFaturamento, 2, ',', '.'); ?> no acumulado total</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Vendas do Mês -->
    <div class="kpi-card">
        <div class="kpi-card-top">
            <span class="kpi-label">Vendas Aprovadas</span>
            <?php if ($deltaPedidos !== null): ?>
                <?php if ($deltaPedidos > 0): ?>
                    <span class="kpi-delta positive" title="Variação de pedidos em relação ao mês anterior">
                        <i class="fa-solid fa-arrow-up"></i> +<?php echo number_format($deltaPedidos, 1); ?>%
                    </span>
                <?php elseif ($deltaPedidos < 0): ?>
                    <span class="kpi-delta negative" title="Variação de pedidos em relação ao mês anterior">
                        <i class="fa-solid fa-arrow-down"></i> <?php echo number_format($deltaPedidos, 1); ?>%
                    </span>
                <?php else: ?>
                    <span class="kpi-delta neutral">0%</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="kpi-info">
            <div class="kpi-value"><?php echo $pedidosMes; ?> <small class="text-muted fs-6 fw-normal">neste mês</small></div>
            <div class="kpi-subtext">
                <?php if ($totalPedidosPendentes > 0): ?>
                    <a href="/admin/pedidos/?status=pendente" class="text-warning text-decoration-none fw-semibold d-inline-flex align-items-center gap-1">
                        <i class="fa-solid fa-clock"></i> <?php echo pluralizar($totalPedidosPendentes, 'pedido pendente', 'pedidos pendentes'); ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i>Nenhum pedido pendente</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Card 3: Ticket Médio -->
    <div class="kpi-card">
        <div class="kpi-card-top">
            <span class="kpi-label">Ticket Médio</span>
        </div>
        <div class="kpi-info">
            <div class="kpi-value">R$ <?php echo number_format($ticketMedioMes, 2, ',', '.'); ?></div>
            <div class="kpi-subtext">
                <span><?php echo $totalPedidosPagos; ?> compras confirmadas na história</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Meios de Pagamento -->
    <div class="kpi-card">
        <div class="kpi-card-top">
            <span class="kpi-label">Meios de Pagamento</span>
        </div>
        <div class="kpi-info">
            <div class="kpi-value text-primary"><?php echo $percPix; ?>% <small class="text-muted fs-6 fw-normal">PIX</small></div>
            <div class="kpi-subtext">
                <span><?php echo $metodoPix['qtd']; ?> via PIX &bull; <?php echo $metodoCartao['qtd']; ?> via Cartão (<?php echo $percCartao; ?>%)</span>
            </div>
        </div>
    </div>
</div>

<!-- TABELA DE ATIVIDADES RECENTES (ÚLTIMOS PEDIDOS) -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="fa-solid fa-clock-rotate-left text-muted me-1"></i> Últimos Pedidos da Loja
        </h5>
        <a href="/admin/pedidos/" class="btn btn-outline-primary btn-sm">Ver todos os pedidos →</a>
    </div>
    <div class="card-body p-0">
        <?php 
            $pedidos = $ultimosPedidos;
            $emptyMessage = 'Nenhum pedido registrado ainda.';
            $mostrarPaginacao = false;
            require __DIR__ . "/includes/pedidos_table.php";
        ?>
    </div>
</div>

<!-- MODAL: DETALHES DO FATURAMENTO & HISTÓRICO -->
<div class="modal fade" id="modalFaturamentoDetalhes" tabindex="-1" aria-labelledby="modalFaturamentoDetalhesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalFaturamentoDetalhesLabel">
                    <i class="fa-solid fa-chart-column text-primary me-2"></i> Detalhes do Faturamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Mini Cards de Resumo -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="p-3 rounded kpi-modal-stat-box">
                            <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Neste Mês</span>
                            <h4 class="fw-bold text-success mb-1">R$ <?php echo number_format($faturamentoMes, 2, ',', '.'); ?></h4>
                            <?php if ($deltaFaturamento !== null): ?>
                                <span class="small fw-semibold <?php echo ($deltaFaturamento >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fa-solid <?php echo ($deltaFaturamento >= 0) ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i><?php echo ($deltaFaturamento >= 0 ? '+' : '') . number_format($deltaFaturamento, 1); ?>% vs mês anterior
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 rounded kpi-modal-stat-box">
                            <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Mês Anterior</span>
                            <h4 class="fw-bold text-main mb-1">R$ <?php echo number_format($faturamentoMesAnterior, 2, ',', '.'); ?></h4>
                            <span class="text-muted small"><?php echo $pedidosMesAnterior; ?> pedidos aprovados</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 rounded kpi-modal-stat-box">
                            <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Acumulado Histórico</span>
                            <h4 class="fw-bold text-main mb-1">R$ <?php echo number_format($totalFaturamento, 2, ',', '.'); ?></h4>
                            <span class="text-muted small"><?php echo $totalPedidosPagos; ?> pedidos totais</span>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Barras dos Últimos 7 Dias -->
                <div class="kpi-modal-stat-box rounded p-3 mb-0">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h6 class="fw-bold mb-0">
                            <i class="fa-solid fa-chart-simple text-primary me-1"></i> Evolução nos Últimos 7 Dias
                        </h6>
                        <span class="text-muted small">
                            Total no período: <strong class="text-main">R$ <?php echo number_format($totalValor7Dias, 2, ',', '.'); ?></strong> (<?php echo $totalQtd7Dias; ?> <?php echo $totalQtd7Dias === 1 ? 'venda' : 'vendas'; ?>)
                        </span>
                    </div>

                    <div class="trend-bars-container">
                        <?php foreach ($ultimos7Dias as $d): 
                            $alturaPercentual = ($maiorValorDia > 0) ? max(6, round(($d['total'] / $maiorValorDia) * 100)) : 6;
                        ?>
                            <div class="trend-bar-col" title="<?php echo htmlspecialchars($d['label'] . ' (' . $d['data_formatada'] . '): R$ ' . number_format($d['total'], 2, ',', '.') . ' em ' . $d['qtd'] . ' pedidos', ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="trend-bar-val">
                                    <?php if ($d['total'] > 0): ?>
                                        R$ <?php echo number_format($d['total'], 0, ',', '.'); ?>
                                    <?php else: ?>
                                        <span class="opacity-50">0</span>
                                    <?php endif; ?>
                                </div>
                                <div class="trend-bar-track">
                                    <div class="trend-bar-fill <?php echo $d['is_hoje'] ? 'active' : ''; ?>" style="height: <?php echo $alturaPercentual; ?>%;"></div>
                                </div>
                                <div class="trend-bar-day <?php echo $d['is_hoje'] ? 'fw-bold text-primary' : ''; ?>">
                                    <?php echo $d['label']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.kpi-card-clickable').forEach(function(card) {
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>
