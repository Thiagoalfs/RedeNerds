<?php
/**
 * admin/pedidos/exportar.php
 * Endpoint de exportação de pedidos para formato CSV compatível com Excel.
 */

// Inicia buffer para garantir envio limpo de cabeçalhos e arquivo
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . "/../sessao.php";

// Rate limit específico da exportação de pedidos (protege contra downloads em massa)
require_once __DIR__ . "/../../api/rate_limiter.php";
exigirRateLimit('admin_exportar_pedidos', 10, 60);

/**
 * Exibe uma mensagem de erro amigável ao usuário (sem vazar detalhes internos)
 * e encerra a execução sem gerar nenhum arquivo.
 */
if (!function_exists('exportarPedidosErro')) {
    function exportarPedidosErro(string $mensagem, int $codigoHttp = 500): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($codigoHttp);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro ao exportar pedidos</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background: #f8f9fa; color: #212529; margin: 0; padding: 40px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 24px;">
        <h1 style="font-size: 18px; margin: 0 0 12px;">Não foi possível exportar os pedidos</h1>
        <p style="margin: 0 0 16px; color: #6c757d;">' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>
        <a href="index.php" style="display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 8px 14px; border-radius: 6px; font-size: 14px;">Voltar para Pedidos</a>
    </div>
</body>
</html>';
        exit;
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Exportar pedidos: conexão com o banco de dados não disponível.");
    exportarPedidosErro("A conexão com o banco de dados não está disponível no momento. Tente novamente em alguns instantes ou contate o suporte.", 500);
}

$todoPeriodo = !empty($_GET['todo_periodo']) && ($_GET['todo_periodo'] === '1' || $_GET['todo_periodo'] === 'on');
$dataInicio = trim($_GET['data_inicio'] ?? '');
$dataFim = trim($_GET['data_fim'] ?? '');
$filtroServidor = trim($_GET['servidor'] ?? '');
$apenasAprovados = !empty($_GET['apenas_aprovados']) && ($_GET['apenas_aprovados'] === '1' || $_GET['apenas_aprovados'] === 'on');
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
if ($apenasAprovados) {
    $where[] = "status = 'pago'";
} elseif (!empty($filtroStatus)) {
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
        // Falha ao identificar o servidor: NÃO exportar tudo silenciosamente.
        error_log("Erro ao buscar servidor no exportar: " . $e->getMessage());
        exportarPedidosErro("Não foi possível aplicar o filtro de servidor selecionado. A exportação foi cancelada para evitar o download de registros fora do filtro. Tente novamente.", 500);
    }
}

// Filtro de busca textual
if (!empty($busca)) {
    $where[] = "(nick LIKE :busca OR txid LIKE :busca OR payer_email LIKE :busca OR payer_cpf LIKE :busca OR servidor LIKE :busca OR vip_nome LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/**
 * Protege campos de texto contra "CSV Injection" (fórmula maliciosa executada ao abrir no Excel).
 * Qualquer valor iniciado por =, +, - ou @ (ou contendo tabulação/início de linha) recebe
 * o prefixo de apóstrofo (') para ser tratado como texto puro pela planilha.
 */
if (!function_exists('escaparCsv')) {
    function escaparCsv($valor) {
        if ($valor === null || is_array($valor) || is_object($valor)) {
            return '';
        }

        $valor = (string) $valor;
        if ($valor === '') {
            return $valor;
        }

        $inicioRisco = ["=", "+", "-", "@", "\t", "\r", "\n"];
        if (in_array(mb_substr($valor, 0, 1), $inicioRisco, true) || preg_match('/^[\x00-\x1F]/', $valor)) {
            return "'" . $valor;
        }

        return $valor;
    }
}

try {
    // Mapa de servidores para preenchimento de nomes caso necessário
    $servidoresMap = [];
    $stmtSrvAll = $pdo->query("SELECT id, servername, nome FROM servidores");
    if ($stmtSrvAll) {
        while ($s = $stmtSrvAll->fetch(PDO::FETCH_ASSOC)) {
            $servidoresMap[$s['id']] = $s;
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM pedidos $whereSql ORDER BY id DESC");
    $stmt->execute($params);

    // Limpa quaisquer buffers anteriores para saída limpa do arquivo binário/texto
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "pedidos_" . date('Y-m-d_His') . ".csv";

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

    fputcsv($output, $cabecalhos, ';');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $st = strtolower($row['status'] ?? '');
        $statusFormatado = 'Desconhecido';
        if ($st === 'pago') {
            $statusFormatado = 'Aprovado';
        } elseif ($st === 'pendente') {
            $statusFormatado = 'Pendente';
        } elseif ($st === 'cancelado') {
            $statusFormatado = 'Cancelado';
        } elseif ($st === 'recusado') {
            $statusFormatado = 'Recusado';
        } elseif ($st === 'expirado') {
            $statusFormatado = 'Expirado';
        } elseif (!empty($row['status'])) {
            $statusFormatado = ucfirst($row['status']);
        }

        $mp = strtolower($row['metodo_pagamento'] ?? '');
        $metodoFormatado = 'PIX';
        if ($mp === 'cartao') {
            $metodoFormatado = 'Cartão de Crédito';
        } elseif ($mp === 'pix') {
            $metodoFormatado = 'PIX';
        } elseif (!empty($row['metodo_pagamento'])) {
            $metodoFormatado = strtoupper($row['metodo_pagamento']);
        }

        $entregueFormatado = (!empty($row['entregue']) && $row['entregue'] == 1) ? 'Sim' : 'Não';
        $dataCriado = !empty($row['criado_em']) ? date('d/m/Y H:i:s', strtotime($row['criado_em'])) : '';
        $dataPago = !empty($row['pago_em']) ? date('d/m/Y H:i:s', strtotime($row['pago_em'])) : '';

        $servidorNome = $row['servidor'] ?? '';
        if (empty($servidorNome) && !empty($row['servidor_id']) && isset($servidoresMap[$row['servidor_id']])) {
            $servidorNome = $servidoresMap[$row['servidor_id']]['servername'] ?: $servidoresMap[$row['servidor_id']]['nome'];
        }

        $linha = [
            $row['id'] ?? '',
            escaparCsv($row['txid'] ?? ''),
            $dataCriado,
            $dataPago,
            escaparCsv($row['nick'] ?? ''),
            escaparCsv(ucfirst($row['tipo_conta'] ?? 'Original')),
            escaparCsv($row['vip_nome'] ?? ''),
            escaparCsv($servidorNome ?: 'Geral'),
            number_format((float)($row['valor_original'] ?? $row['valor'] ?? 0), 2, ',', '.'),
            number_format((float)($row['desconto_aplicado'] ?? 0), 2, ',', '.'),
            number_format((float)($row['valor'] ?? $row['valor_total'] ?? 0), 2, ',', '.'),
            escaparCsv($row['cupom_codigo'] ?? ''),
            escaparCsv($metodoFormatado),
            $row['parcelas'] ?? 1,
            escaparCsv($statusFormatado),
            escaparCsv($row['status_detail'] ?? ''),
            escaparCsv($row['payer_email'] ?? ''),
            escaparCsv($row['payer_cpf'] ?? ''),
            escaparCsv($entregueFormatado),
            escaparCsv($row['mp_payment_id'] ?? '')
        ];

        fputcsv($output, $linha, ';');
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log("Erro ao exportar pedidos: " . $e->getMessage());
    exportarPedidosErro("Ocorreu um erro inesperado ao gerar o arquivo de exportação. Tente novamente ou contate o suporte se o problema persistir.", 500);
}
