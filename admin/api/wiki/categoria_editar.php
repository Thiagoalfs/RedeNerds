<?php
require_once __DIR__ . "/../../sessao.php";

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
    $versaoHash = trim($_POST['_versao_hash'] ?? '');
    $pdo->beginTransaction();
    $catAtual = exigirConcorrenciaEdicao($pdo, 'wiki_categorias', $id, $versaoHash, [], false, '/admin/wiki/categorias.php');

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
    $pdo->commit();
    registrarLogAdmin($pdo, 'editar', 'wiki_categorias', "Editou categoria da wiki '{$nome}' (#{$id})", $id);

    header("Location: /admin/wiki/categorias.php?servidor_id=$servidorId&sucesso=editado");
    exit;

} catch (Exception $e) {
    header("Location: /admin/wiki/categorias.php?erro=" . urlencode("Erro ao atualizar categoria: " . $e->getMessage()));
    exit;
}
