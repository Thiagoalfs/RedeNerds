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
    header("Location: /admin/parceiros/?erro=" . urlencode("ID de Parceiro inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $estadoEsperado = isset($input['estado_esperado']) && $input['estado_esperado'] !== '' ? (int)$input['estado_esperado'] : null;
        $res = executarToggleAtomico($pdo, 'parceiros', 'ativo', $id, 'nome', $estadoEsperado);
        if (!$res['ok']) {
            responderErroConcorrencia((int)$res['status'], (string)$res['erro'], $isAjax, '/admin/parceiros/');
        }
        $parceiro = $res['row'];
        $novoStatus = $res['novo'];
        registrarLogAdmin($pdo, 'toggle', 'parceiros', ($novoStatus ? 'Ativou' : 'Desativou') . " parceiro '{$parceiro['nome']}' (#{$id})", $id);

        require_once __DIR__ . "/../../../api/cache_helper.php";
        invalidarCache('parceiros');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "success" => true,
                "ativo" => $novoStatus,
                "_versao_hash" => obterHashAtualRegistro($pdo, 'parceiros', $id)
            ]);
            exit;
        }

        $msg = $novoStatus ? "Parceiro '{$parceiro['nome']}' ativado com sucesso!" : "Parceiro '{$parceiro['nome']}' desativado com sucesso!";
        header("Location: /admin/parceiros/?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao alternar status do parceiro: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro interno no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/parceiros/");
exit;