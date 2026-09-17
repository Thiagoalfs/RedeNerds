<?php
/**
 * admin/pedidos/exportar.php
 * Endpoint de exportação de pedidos VIP para formato CSV compatível com Excel.
 */

// Buffer para garantir envio de headers e evitar poluição da saída
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . "/../sessao.php";
require_once __DIR__ . "/../../config.php";

$todoPeriodo = !empty($_GET['todo_periodo']) && ($_GET['todo_periodo'] === '1' || $_GET['todo_periodo'] === 'on');
$dataInicio = trim($_GET['data_inicio'] ?? '');
$dataFim = trim($_GET['data_fim'] ?? '');
$filtroServidor = trim($_GET['servidor'] ?? '');
$filtroStatus = trim($_GET['status'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$busca = trim($_GET['busca'] ?? '');

$where = [];
$params = [];

// Filtro de período por data de criação
if (!$todoPeriodo) {
    if (!empty($dataInicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
        $where[] = "criado_em >= :data_inicio";
        $params[':data_inicio'] = $dataInicio . " 00:00:00";
    }
    if (!empty($dataFim) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
        $where[] = "criado_em <= :data_fim";
        $params[':data_fim'] = $dataFim . " 23:59:59";
    }
}

// Filtro por status
if (!empty($filtroStatus)) {
    $where[] = "status = :status";
    $params[':status'] = $filtroStatus;
}

// Filtro por método de pagamento
if (!empty($filtroMetodo)) {
    $where[] = "metodo_pagamento = :metodo";
    $params[':metodo'] = $filtroMetodo;
}

// Filtro por servidor
if (!empty($filtroServidor)) {
    try {
        $stmtSrv = $pdo->prepare("SELECT id, servername, nome FROM servidores WHERE id = :sid OR servername = :sname OR nome = :snome LIMIT 1");
        $stmtSrv->execute([
            ':sid' => is_numeric($filtroServidor) ? (int)$filtroServidor : 0,
            ':sname' => $filtroServidor,
            ':snome' => $filtroServidor
        ]);
        $srvInfo = $stmtSrv->fetch(PDO::FETCH_ASSOC);

        if ($srvInfo) {
            $where[] = "(servidor_id = :srv_id OR servidor = :srv_name OR servidor = :srv_nome OR servidor LIKE :srv_like)";
            $params[':srv_id'] = (int)$srvInfo['id'];
            $params[':srv_name'] = $srvInfo['servername'];
            $params[':srv_nome'] = $srvInfo['nome'];
            $params[':srv_like'] = "%{$srvInfo['servername']}%";
        } else {
            $where[] = "(servidor_id = :srv_id OR servidor LIKE :srv_like)";
            $params[':srv_id'] = is_numeric($filtroServidor) ? (int)$filtroServidor : 0;
            $params[':srv_like'] = "%{$filtroServidor}%";
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar servidor no exportar: " . $e->getMessage());
    }
}

// Filtro de busca textual
if (!empty($busca)) {
    $where[] = "(nick LIKE :busca OR txid LIKE :busca OR payer_email LIKE :busca OR payer_cpf LIKE :busca OR servidor LIKE :busca OR vip_nome LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Mapa de servidores para preenchimento de nomes caso necessário
    $servidoresMap = [];
    $stmtSrvAll = $pdo->query("SELECT id, servername, nome FROM servidores");
    while ($s = $stmtSrvAll->fetch(PDO::FETCH_ASSOC)) {
        $servidoresMap[$s['id']] = $s;
    }

    $stmt = $pdo->prepare("SELECT * FROM pedidos_vip $whereSql ORDER BY id DESC");
    $stmt->execute($params);

    // Limpa quaisquer buffers anteriores para saída limpa do arquivo binário/texto
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "pedidos_vip_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM para garantir correta exibição e acentuação no Microsoft Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Cabeçalho das colunas do arquivo CSV
    $cabecalhos = [
        'ID Pedido',
        'TXID / Transação',
        'Data de Criação',
        'Data de Pagamento',
        'Nick do Jogador',
        'Tipo de Conta',
        'Pacote VIP',
        'Servidor',
        'Valor Original (R$)',
        'Desconto (R$)',
        'Valor Pago (R$)',
        'Cupom Aplicado',
        'Método de Pagamento',
        'Parcelas',
        'Status',
        'Status Detalhado',
        'E-mail Pagador',
        'CPF Pagador',
        'Entregue no Jogo',
        'MP Payment ID'
    ];

    fputcsv($output, $cabecalhos, ';', '"', "\\");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusFormatado = match(strtolower($row['status'] ?? '')) {
            'pago' => 'Aprovado',
            'pendente' => 'Pendente',
            'cancelado' => 'Cancelado',
            'recusado' => 'Recusado',
            'expirado' => 'Expirado',
            default => ucfirst($row['status'] ?? 'Desconhecido')
        };

        $metodoFormatado = match(strtolower($row['metodo_pagamento'] ?? '')) {
            'pix' => 'PIX',
            'cartao' => 'Cartão de Crédito',
            default => strtoupper($row['metodo_pagamento'] ?? 'PIX')
        };

        $entregueFormatado = (!empty($row['entregue']) && $row['entregue'] == 1) ? 'Sim' : 'Não';
        $dataCriado = !empty($row['criado_em']) ? date('d/m/Y H:i:s', strtotime($row['criado_em'])) : '';
        $dataPago = !empty($row['pago_em']) ? date('d/m/Y H:i:s', strtotime($row['pago_em'])) : '';

        $servidorNome = $row['servidor'] ?? '';
        if (empty($servidorNome) && !empty($row['servidor_id']) && isset($servidoresMap[$row['servidor_id']])) {
            $servidorNome = $servidoresMap[$row['servidor_id']]['servername'] ?: $servidoresMap[$row['servidor_id']]['nome'];
        }

        $linha = [
            $row['id'],
            $row['txid'] ?? '',
            $dataCriado,
            $dataPago,
            $row['nick'] ?? '',
            ucfirst($row['tipo_conta'] ?? 'Original'),
            $row['vip_nome'] ?? '',
            $servidorNome ?: 'Geral',
            number_format((float)($row['valor_original'] ?? $row['valor'] ?? 0), 2, ',', '.'),
            number_format((float)($row['desconto_aplicado'] ?? 0), 2, ',', '.'),
            number_format((float)($row['valor'] ?? $row['valor_total'] ?? 0), 2, ',', '.'),
            $row['cupom_codigo'] ?? '',
            $metodoFormatado,
            $row['parcelas'] ?? 1,
            $statusFormatado,
            $row['status_detail'] ?? '',
            $row['payer_email'] ?? '',
            $row['payer_cpf'] ?? '',
            $entregueFormatado,
            $row['mp_payment_id'] ?? ''
        ];

        fputcsv($output, $linha, ';', '"', "\\");
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log("Erro ao exportar pedidos VIP: " . $e->getMessage());
    http_response_code(500);
    echo "Erro ao exportar pedidos: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
