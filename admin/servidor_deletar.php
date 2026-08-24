<?php
require_once "sessao.php";
require_once "../../config.php";
require_once "icon_upload.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: servidores.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, icon FROM servidores WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $servidor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($servidor) {
        // Se o ícone for um upload local, apaga o arquivo físico do servidor
        if (!empty($servidor['icon']) && tipoDoIcone($servidor['icon']) === 'img' && strpos($servidor['icon'], ICON_UPLOAD_DIR_PUBLICA) === 0) {
            $caminhoFisico = ICON_UPLOAD_DIR_FISICA . basename($servidor['icon']);
            if (is_file($caminhoFisico)) {
                @unlink($caminhoFisico);
            }
        }

        $stmtDelete = $pdo->prepare("DELETE FROM servidores WHERE id = :id");
        $stmtDelete->execute([':id' => $id]);
    }
} catch (PDOException $e) {
    // Evita tela branca de erro PDO
}

header("Location: servidores.php");
exit;
