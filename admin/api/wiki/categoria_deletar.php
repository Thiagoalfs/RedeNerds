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
    header("Location: ../../wiki_categorias.php");
    exit;
}

// CSRF
$tokenRecebido = $_POST['csrf_token'] ?? '';
if (!validarCsrfToken($tokenRecebido)) {
    header("Location: ../../wiki_categorias.php?erro=" . urlencode("Token CSRF inválido ou expirado."));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: ../../wiki_categorias.php?erro=" . urlencode("ID de categoria inválido."));
    exit;
}

try {
    $stmtFind = $pdo->prepare("SELECT servidor_id FROM wiki_categorias WHERE id = :id LIMIT 1");
    $stmtFind->execute([':id' => $id]);
    $catAtual = $stmtFind->fetch(PDO::FETCH_ASSOC);
    if (!$catAtual) {
        header("Location: ../../wiki_categorias.php?erro=" . urlencode("Categoria não encontrada."));
        exit;
    }

    $servidorId = (int)$catAtual['servidor_id'];

    // Checa se há artigos vinculados
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM wiki_artigos WHERE categoria_id = :id");
    $stmtCount->execute([':id' => $id]);
    $totalArtigos = (int)$stmtCount->fetchColumn();

    if ($totalArtigos > 0) {
        header("Location: ../../wiki_categorias.php?servidor_id=$servidorId&erro=" . urlencode("Não é possível excluir esta categoria porque existem {$totalArtigos} artigo(s) vinculados a ela. Mova ou exclua os artigos primeiro."));
        exit;
    }

    // Exclui a categoria
    $stmtDelete = $pdo->prepare("DELETE FROM wiki_categorias WHERE id = :id");
    $stmtDelete->execute([':id' => $id]);

    header("Location: ../../wiki_categorias.php?servidor_id=$servidorId&sucesso=deletado");
    exit;

} catch (Exception $e) {
    header("Location: ../../wiki_categorias.php?erro=" . urlencode("Erro ao excluir categoria: " . $e->getMessage()));
    exit;
}
