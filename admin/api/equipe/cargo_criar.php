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

$nome = trim($_POST['nome'] ?? '');
$cor = trim($_POST['cor'] ?? '#27acff');
$ordem = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;

if (empty($nome)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "O nome do cargo é obrigatório."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("O nome do cargo é obrigatório."));
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

// Validação da cor Hex
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
    $cor = '#27acff';
}

try {
    // Garante que a tabela existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `equipe_cargos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nome` VARCHAR(100) NOT NULL UNIQUE,
        `cor` VARCHAR(20) NOT NULL DEFAULT '#27acff',
        `ordem` INT NOT NULL DEFAULT 0,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Garante que a coluna cor existe
    try {
        $pdo->exec("ALTER TABLE `equipe_cargos` ADD COLUMN `cor` VARCHAR(20) NOT NULL DEFAULT '#27acff' AFTER `nome`");
    } catch (Exception $ignored) {}

    // Verifica se já existe um cargo com esse nome
    $stmtCheck = $pdo->prepare("SELECT id FROM equipe_cargos WHERE LOWER(nome) = LOWER(:nome) LIMIT 1");
    $stmtCheck->execute([':nome' => $nome]);
    if ($stmtCheck->fetch()) {
        if ($isAjax) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Já existe um cargo com este nome."]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?erro=" . urlencode("Já existe um cargo com este nome."));
        exit;
    }

    // Se ordem for 0, define como o maior valor atual + 1
    if ($ordem <= 0) {
        $maxOrdem = (int)$pdo->query("SELECT MAX(ordem) FROM equipe_cargos")->fetchColumn();
        $ordem = $maxOrdem + 1;
    }

    $stmt = $pdo->prepare("INSERT INTO equipe_cargos (nome, cor, ordem) VALUES (:nome, :cor, :ordem)");
    $stmt->execute([':nome' => $nome, ':cor' => $cor, ':ordem' => $ordem]);
    $newId = (int)$pdo->lastInsertId();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => true,
            "cargo" => [
                "id" => $newId,
                "nome" => $nome,
                "cor" => $cor,
                "ordem" => $ordem
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: /admin/equipe/manage.php?sucesso=criado");
    exit;

} catch (Exception $e) {
    error_log("Erro ao criar cargo: " . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Erro no banco de dados ao criar cargo."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Erro ao criar cargo no banco de dados."));
    exit;
}
