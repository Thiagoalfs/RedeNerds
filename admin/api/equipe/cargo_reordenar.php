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

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
          || !empty($jsonData)
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
$tokenRecebido = $_POST['csrf_token'] ?? $jsonData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
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

try {
    // Modo 1: Array ordenado de IDs (Drag-and-Drop ou envio em lote)
    $ordemIds = $_POST['ordem_ids'] ?? $jsonData['ordem_ids'] ?? null;
    if (is_array($ordemIds) && !empty($ordemIds)) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE equipe_cargos SET ordem = :ordem WHERE id = :id");
        $pos = 1;
        foreach ($ordemIds as $cid) {
            $cid = (int)$cid;
            if ($cid > 0) {
                $stmt->execute([':ordem' => $pos++, ':id' => $cid]);
            }
        }
        $pdo->commit();

        require_once __DIR__ . "/../../../api/cache_helper.php";
        invalidarCache('equipe');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "mensagem" => "Hierarquia reordenada com sucesso."]);
            exit;
        }
        header("Location: /admin/equipe/manage.php?sucesso=reordenado");
        exit;
    }

    // Modo 2: Ação individual 'subir' ou 'descer'
    $id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($jsonData['id'] ?? 0);
    $acao = $_POST['acao'] ?? $jsonData['acao'] ?? '';

    if ($id > 0 && in_array($acao, ['subir', 'descer'], true)) {
        // Busca todos os cargos ordenados
        $cargos = $pdo->query("SELECT id, ordem FROM equipe_cargos ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Encontra o index do cargo alvo
        $index = -1;
        foreach ($cargos as $i => $c) {
            if ((int)$c['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index !== -1) {
            $targetIndex = ($acao === 'subir') ? $index - 1 : $index + 1;

            if ($targetIndex >= 0 && $targetIndex < count($cargos)) {
                // Troca a ordem dos dois cargos
                $currentId = (int)$cargos[$index]['id'];
                $otherId = (int)$cargos[$targetIndex]['id'];

                $pdo->beginTransaction();
                // Normaliza a ordem de todos para evitar conflitos de duplicidade
                $stmtNorm = $pdo->prepare("UPDATE equipe_cargos SET ordem = :ordem WHERE id = :id");
                
                // Realiza a troca no array local
                $temp = $cargos[$index];
                $cargos[$index] = $cargos[$targetIndex];
                $cargos[$targetIndex] = $temp;

                foreach ($cargos as $pos => $c) {
                    $stmtNorm->execute([':ordem' => $pos + 1, ':id' => (int)$c['id']]);
                }
                $pdo->commit();

                require_once __DIR__ . "/../../../api/cache_helper.php";
                invalidarCache('equipe');

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(["success" => true, "mensagem" => "Posição alterada com sucesso."]);
                    exit;
                }
                header("Location: /admin/equipe/manage.php?sucesso=reordenado");
                exit;
            }
        }
    }

    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Parâmetros de reordenação inválidos."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao reordenar cargos: " . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Erro ao reordenar cargos no banco de dados."]);
        exit;
    }
    header("Location: /admin/equipe/manage.php?erro=" . urlencode("Erro ao reordenar cargos."));
    exit;
}
