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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/wiki/categorias.php");
    exit;
}

// CSRF
$tokenRecebido = $_POST['csrf_token'] ?? '';
if (!validarCsrfToken($tokenRecebido)) {
    header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Token CSRF inválido ou expirado."));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$icone = trim($_POST['icone'] ?? 'fa-solid fa-folder');
$ordem = (int)($_POST['ordem'] ?? 0);

if ($id <= 0 || empty($nome)) {
    header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Dados inválidos para edição."));
    exit;
}

if (empty($icone)) {
    $icone = 'fa-solid fa-folder';
}

try {
    $stmtFind = $pdo->prepare("SELECT servidor_id FROM wiki_categorias WHERE id = :id LIMIT 1");
    $stmtFind->execute([':id' => $id]);
    $catAtual = $stmtFind->fetch(PDO::FETCH_ASSOC);
    if (!$catAtual) {
        header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Categoria não encontrada."));
        exit;
    }

    $servidorId = (int)$catAtual['servidor_id'];

    $stmtUpdate = $pdo->prepare("
        UPDATE wiki_categorias 
        SET nome = :nome, icone = :icone, ordem = :ordem 
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':nome'  => $nome,
        ':icone' => $icone,
        ':ordem' => $ordem,
        ':id'    => $id
    ]);

    header("Location: /admin/wiki/categorias.php?servidor_id=$servidorId&sucesso=editado");
    exit;

} catch (Exception $e) {
    header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Erro ao atualizar categoria: " . $e->getMessage()));
    exit;
}
