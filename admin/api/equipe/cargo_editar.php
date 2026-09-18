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
$nome = trim($_POST['nome'] ?? '');
$ordem = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;

if ($id <= 0 || empty($nome)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Identificador e nome do cargo são obrigatórios."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Identificador e nome do cargo são obrigatórios."));
    exit;
}

if (mb_strlen($nome) > 100) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "O nome do cargo pode ter no máximo 100 caracteres."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("O nome do cargo pode ter no máximo 100 caracteres."));
    exit;
}

try {
    $stmtCargo = $pdo->prepare("SELECT id, nome, ordem FROM equipe_cargos WHERE id = :id LIMIT 1");
    $stmtCargo->execute([':id' => $id]);
    $cargoAtual = $stmtCargo->fetch(PDO::FETCH_ASSOC);

    if (!$cargoAtual) {
        if ($isAjax) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Cargo não encontrado."]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?erro=" . urlencode("Cargo não encontrado."));
        exit;
    }

    $nomeAntigo = $cargoAtual['nome'];

    // Verifica se outro cargo já usa este novo nome
    $stmtCheck = $pdo->prepare("SELECT id FROM equipe_cargos WHERE LOWER(nome) = LOWER(:nome) AND id != :id LIMIT 1");
    $stmtCheck->execute([':nome' => $nome, ':id' => $id]);
    if ($stmtCheck->fetch()) {
        if ($isAjax) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Já existe outro cargo com este nome."]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?erro=" . urlencode("Já existe outro cargo com este nome."));
        exit;
    }

    $pdo->beginTransaction();

    // Atualiza cargo
    $stmtUpd = $pdo->prepare("UPDATE equipe_cargos SET nome = :nome, ordem = :ordem WHERE id = :id");
    $stmtUpd->execute([':nome' => $nome, ':ordem' => $ordem, ':id' => $id]);

    // Se o nome do cargo mudou, atualiza os membros vinculados na tabela equipe
    if ($nomeAntigo !== $nome) {
        $stmtSync = $pdo->prepare("UPDATE equipe SET cargo = :novo_nome WHERE cargo = :antigo_nome");
        $stmtSync->execute([':novo_nome' => $nome, ':antigo_nome' => $nomeAntigo]);
    }

    $pdo->commit();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => true,
            "cargo" => [
                "id" => $id,
                "nome" => $nome,
                "ordem" => $ordem
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: /admin/equipe/manage.php?sucesso=editado");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao editar cargo: " . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Erro no banco de dados ao editar cargo."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Erro ao editar cargo."));
    exit;
}
