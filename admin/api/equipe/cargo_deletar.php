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

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
          || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Método não permitido."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php");
    exit;
}

// CSRF check
$tokenRecebido = $_POST['csrf_token'] ?? '';
if (!validarCsrfToken($tokenRecebido)) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Token CSRF inválido ou expirado."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Token CSRF inválido ou expirado."));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "ID do cargo inválido."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("ID do cargo inválido."));
    exit;
}

try {
    $stmtCargo = $pdo->prepare("SELECT id, nome FROM equipe_cargos WHERE id = :id LIMIT 1");
    $stmtCargo->execute([':id' => $id]);
    $cargo = $stmtCargo->fetch(PDO::FETCH_ASSOC);

    if (!$cargo) {
        if ($isAjax) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Cargo não encontrado."]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?erro=" . urlencode("Cargo não encontrado."));
        exit;
    }

    // Verifica se existem membros vinculados a este cargo
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM equipe WHERE cargo = :nome");
    $stmtCount->execute([':nome' => $cargo['nome']]);
    $membrosCount = (int)$stmtCount->fetchColumn();

    if ($membrosCount > 0) {
        $msg = "Não é possível excluir o cargo '{$cargo['nome']}' pois existem {$membrosCount} membro(s) vinculados a ele. Altere ou remova os membros primeiro.";
        if ($isAjax) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => $msg]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?erro=" . urlencode($msg));
        exit;
    }

    $stmtDel = $pdo->prepare("DELETE FROM equipe_cargos WHERE id = :id");
    $stmtDel->execute([':id' => $id]);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => true, "id" => $id]);
        exit;
    }

    header("Location: /admin/equipe/manage.php?sucesso=deletado");
    exit;

} catch (Exception $e) {
    error_log("Erro ao excluir cargo: " . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Erro no banco de dados ao excluir cargo."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Erro ao excluir cargo."));
    exit;
}
