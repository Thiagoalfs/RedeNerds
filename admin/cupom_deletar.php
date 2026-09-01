<?php
require_once "sessao.php";
$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if (!$configPath) {
    die("Erro: Arquivo config.php não encontrado.");
}
require_once $configPath;

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT codigo FROM cupons WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cupom) {
            $del = $pdo->prepare("DELETE FROM cupons WHERE id = :id");
            $del->execute([':id' => $id]);

            header("Location: cupons.php?msg=" . urlencode("Cupom '{$cupom['codigo']}' deletado com sucesso!"));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: cupons.php?erro=" . urlencode("Erro ao deletar cupom: " . $e->getMessage()));
        exit;
    }
}

header("Location: cupons.php");
exit;