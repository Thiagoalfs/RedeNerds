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
        $stmt = $pdo->prepare("SELECT codigo, ativo FROM cupons WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cupom) {
            $novoStatus = $cupom['ativo'] ? 0 : 1;
            $up = $pdo->prepare("UPDATE cupons SET ativo = :ativo WHERE id = :id");
            $up->execute([':ativo' => $novoStatus, ':id' => $id]);

            $msg = $novoStatus 
                ? "Cupom '{$cupom['codigo']}' ativado com sucesso!" 
                : "Cupom '{$cupom['codigo']}' desativado com sucesso!";
            header("Location: cupons.php?msg=" . urlencode($msg));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: cupons.php?erro=" . urlencode("Erro no banco: " . $e->getMessage()));
        exit;
    }
}

header("Location: cupons.php");
exit;