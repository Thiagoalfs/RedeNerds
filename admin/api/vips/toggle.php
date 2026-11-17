<?php
require_once __DIR__ . "/../../sessao.php";


exigirCSRF();

$input = $_POST;
if (empty($input['id'])) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($input['format']) && $input['format'] === 'json');

if ($id <= 0) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "ID inválido."]);
        exit;
    }
    header("Location: /admin/vips/?erro=" . urlencode("ID de VIP inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $estadoEsperado = isset($input['estado_esperado']) && $input['estado_esperado'] !== '' ? (int)$input['estado_esperado'] : null;
        $res = executarToggleAtomico($pdo, 'vips', 'ativo', $id, 'nome', $estadoEsperado);
        if (!$res['ok']) {
            responderErroConcorrencia((int)$res['status'], (string)$res['erro'], $isAjax, '/admin/vips/');
        }
        $vip = $res['row'];
        $novoStatus = $res['novo'];
        registrarLogAdmin($pdo, 'toggle', 'vips', ($novoStatus ? 'Ativou' : 'Desativou') . " VIP '{$vip['nome']}' (#{$id})", $id);

        require_once __DIR__ . "/../../../api/cache_helper.php";
        invalidarCache('vips');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "success" => true,
                "ativo" => $novoStatus,
                "_versao_hash" => obterHashAtualRegistro($pdo, 'vips', $id)
            ]);
            exit;
        }

        $msg = $novoStatus ? "VIP '{$vip['nome']}' ativado com sucesso!" : "VIP '{$vip['nome']}' desativado com sucesso!";
        header("Location: /admin/vips/?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao alternar status do VIP: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro interno no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/vips/");
exit;