<?php
require_once __DIR__ . "/../../sessao.php";

$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM vips WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $vip = $stmt->fetch(PDO::FETCH_ASSOC);

        $del = $pdo->prepare("DELETE FROM vips WHERE id = :id");
        $del->execute([':id' => $id]);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true]);
            exit;
        }

        $nomeVip = $vip ? $vip['nome'] : 'selecionado';
        header("Location: /admin/vips/?msg=" . urlencode("VIP '{$nomeVip}' deletado com sucesso."));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao deletar VIP: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao deletar VIP."]);
            exit;
        }
        header("Location: /admin/vips/?erro=" . urlencode("Erro ao deletar VIP do banco de dados."));
        exit;
    }
}

header("Location: /admin/vips/");
exit;