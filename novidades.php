<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "../config.php";

if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    $result = $conn->query("SELECT * FROM novidades WHERE id = $id LIMIT 1");
    if (!$result) {
        echo json_encode(["erro" => $conn->error]);
        exit;
    }
    $news = $result->fetch_assoc();
    echo json_encode($news ?? ["erro" => "não encontrado"], JSON_UNESCAPED_UNICODE);
} else {
    $result = $conn->query("SELECT * FROM novidades ORDER BY criado_em DESC");
    if (!$result) {
        echo json_encode(["erro" => $conn->error]);
        exit;
    }
    $novidades = [];
    while ($linha = $result->fetch_assoc()) {
        $novidades[] = $linha;
    }
    echo json_encode($novidades, JSON_UNESCAPED_UNICODE);
}
?>