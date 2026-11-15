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
    header("Location: /admin/servidores/?erro=" . urlencode("ID de servidor inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $estadoEsperado = isset($input['estado_esperado']) && $input['estado_esperado'] !== '' ? (int)$input['estado_esperado'] : null;
        $res = executarToggleAtomico($pdo, 'servidores', 'enabled', $id, 'servername', $estadoEsperado);
        if (!$res['ok']) {
            responderErroConcorrencia((int)$res['status'], (string)$res['erro'], $isAjax, '/admin/servidores/');
        }
        $srv = $res['row'];
        $novoStatus = $res['novo'];
        registrarLogAdmin($pdo, 'toggle', 'servidores', ($novoStatus ? 'Ativou' : 'Desativou') . " servidor '{$srv['servername']}' (#{$id})", $id);

        require_once __DIR__ . "/../../../api/cache_helper.php";
        invalidarCache('servidores');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "ativo" => $novoStatus]);
            exit;
        }

        $msg = $novoStatus
            ? "Servidor '{$srv['servername']}' ativado com sucesso!"
            : "Servidor '{$srv['servername']}' ocultado com sucesso!";
        header("Location: /admin/servidores/?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao alternar status do servidor: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/servidores/");
exit;