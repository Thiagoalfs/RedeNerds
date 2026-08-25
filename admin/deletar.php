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

if (file_exists("webhook_helper.php")) {
    require_once "webhook_helper.php";
} elseif (file_exists(__DIR__ . "/webhook_helper.php")) {
    require_once __DIR__ . "/webhook_helper.php";
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

try {
    // 1. Busca a notícia para pegar os dados da mensagem do Discord, categoria e capa
    $stmt = $pdo->prepare("SELECT id, categoria_envio, mensagemID, capa FROM novidades WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($noticia) {
        // 2. Se houver mensagemID gravado, apaga a mensagem do Discord
        if (!empty($noticia['mensagemID']) && !empty($noticia['categoria_envio']) && function_exists('deletarWebhookDiscord')) {
            try {
                deletarWebhookDiscord($noticia['categoria_envio'], $noticia['mensagemID']);
            } catch (Throwable $t) {
                // Silencia se a mensagem já tiver sido apagada no Discord manualmente
            }
        }

        // 3. Se a capa for um upload local, apaga o arquivo físico do servidor
        if (!empty($noticia['capa']) && strpos($noticia['capa'], '../assets/novidades/') === 0) {
            $caminhoFisico = __DIR__ . '/../assets/novidades/' . basename($noticia['capa']);
            if (is_file($caminhoFisico)) {
                @unlink($caminhoFisico);
            }
        }

        // 4. Deleta o registro do banco de dados
        $stmtDelete = $pdo->prepare("DELETE FROM novidades WHERE id = :id");
        $stmtDelete->execute([':id' => $id]);
    }
} catch (PDOException $e) {
    // Evita tela branca de erro PDO
}

header("Location: dashboard.php");
exit;