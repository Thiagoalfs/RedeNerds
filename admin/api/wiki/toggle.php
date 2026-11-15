<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $estadoEsperado = isset($_POST['estado_esperado']) && $_POST['estado_esperado'] !== '' ? (int)$_POST['estado_esperado'] : null;
        $res = executarToggleAtomico($pdo, 'wiki_artigos', 'publicado', $id, 'titulo', $estadoEsperado);
        if (!$res['ok']) {
            responderErroConcorrencia((int)$res['status'], (string)$res['erro'], $isAjax, '/admin/wiki/');
        }
        $novo = $res['novo'];
        registrarLogAdmin($pdo, 'toggle', 'wiki', ($novo ? 'Publicou' : 'Ocultou') . " artigo da wiki (#{$id})", $id);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "publicado" => $novo]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Erro ao alternar status artigo wiki: " . $e->getMessage());
    }
}
header("Location: /admin/wiki/");
exit;