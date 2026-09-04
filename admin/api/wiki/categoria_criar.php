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
require_once __DIR__ . "/../../../wiki/wiki_helper.php";

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
          || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Método inválido."]);
        exit;
    }
    header("Location: /admin/wiki/categorias.php");
    exit;
}

// CSRF
$tokenRecebido = $_POST['csrf_token'] ?? '';
if (!validarCsrfToken($tokenRecebido)) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Token de segurança CSRF inválido ou expirado."]);
        exit;
    }
    header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Token CSRF inválido."));
    exit;
}

$servidorId = (int)($_POST['servidor_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$icone = trim($_POST['icone'] ?? 'fa-solid fa-folder');
$ordem = (int)($_POST['ordem'] ?? 0);

if ($servidorId <= 0 || empty($nome)) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Preencha o nome da categoria e selecione o servidor."]);
        exit;
    }
    header("Location: /admin/wiki/categorias.php?servidor_id=$servidorId&erro=" . urlencode("Nome da categoria é obrigatório."));
    exit;
}

if (empty($icone)) {
    $icone = 'fa-solid fa-folder';
}

// Gera slug
$slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)), '-'));
if (empty($slugBase)) {
    $slugBase = 'categoria-' . time();
}

$slug = $slugBase;
$contador = 1;
while (true) {
    $stmtCheck = $pdo->prepare("SELECT id FROM wiki_categorias WHERE servidor_id = :servidor_id AND slug = :slug LIMIT 1");
    $stmtCheck->execute([':servidor_id' => $servidorId, ':slug' => $slug]);
    if (!$stmtCheck->fetch()) {
        break;
    }
    $slug = $slugBase . '-' . (++$contador);
}

try {
    $stmtInsert = $pdo->prepare("
        INSERT INTO wiki_categorias (servidor_id, nome, slug, icone, ordem) 
        VALUES (:servidor_id, :nome, :slug, :icone, :ordem)
    ");
    $stmtInsert->execute([
        ':servidor_id' => $servidorId,
        ':nome'        => $nome,
        ':slug'        => $slug,
        ':icone'       => $icone,
        ':ordem'       => $ordem
    ]);
    $newId = (int)$pdo->lastInsertId();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => true,
            "categoria" => [
                "id"     => $newId,
                "nome"   => $nome,
                "slug"   => $slug,
                "icone"  => $icone,
                "ordem"  => $ordem
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: /admin/wiki/categorias.php?servidor_id=$servidorId&sucesso=criado");
    exit;

} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Erro no banco de dados: " . $e->getMessage()]);
        exit;
    }
    header("Location: /admin/wiki/categorias.php?servidor_id=$servidorId&erro=" . urlencode("Erro ao cadastrar categoria."));
    exit;
}
