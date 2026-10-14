<?php
require_once __DIR__ . "/../../sessao.php";


require_once __DIR__ . "/../../../wiki/wiki_helper.php";

exigirCSRF();

$habilitar = isset($_POST['wiki_habilitada']) && ((string)$_POST['wiki_habilitada'] === '1' || $_POST['wiki_habilitada'] === true);
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if (isset($pdo) && $pdo instanceof PDO) {
    setWikiHabilitada($pdo, $habilitar);
}

if ($isAjax) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => true, "wiki_habilitada" => $habilitar]);
    exit;
}

$statusMsg = $habilitar ? "Visualização pública da Wiki ATIVADA com sucesso!" : "Visualização pública da Wiki DESATIVADA com sucesso (ocultada na navbar)!";
$referer = $_SERVER['HTTP_REFERER'] ?? '/admin/wiki/';
$redirectUrl = (strpos($referer, '?') !== false) ? $referer . '&msg=' . urlencode($statusMsg) : $referer . '?msg=' . urlencode($statusMsg);

header("Location: " . $redirectUrl);
exit;
