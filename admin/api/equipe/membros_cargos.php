<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$input = $_POST;
if (empty($input['membro_cargos'])) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$membroCargos = isset($input['membro_cargos']) && is_array($input['membro_cargos']) ? $input['membro_cargos'] : null;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($input['format']) && $input['format'] === 'json');

if (empty($membroCargos)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Nenhuma atribuição de cargo enviada."]);
        exit;
    }
    header("Location: /admin/equipe/?erro=" . urlencode("Nenhuma atribuição de cargo enviada."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Cargos válidos: hierarquia cadastrada + cargos atualmente em uso
        $cargosValidos = $pdo->query("SELECT nome FROM equipe_cargos")->fetchAll(PDO::FETCH_COLUMN);
        $emUso = $pdo->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != ''")->fetchAll(PDO::FETCH_COLUMN);
        $cargosValidos = array_unique(array_merge($cargosValidos, $emUso));

        $upd = $pdo->prepare("UPDATE equipe SET cargo = :cargo WHERE id = :id");
        $atualizados = 0;
        foreach ($membroCargos as $membroId => $cargo) {
            $membroId = (int)$membroId;
            $cargo = trim((string)$cargo);
            if ($membroId <= 0 || $cargo === '' || !in_array($cargo, $cargosValidos, true)) {
                continue;
            }
            $upd->execute([':cargo' => $cargo, ':id' => $membroId]);
            $atualizados++;
        }

        require_once __DIR__ . "/../../../api/cache_helper.php";
        invalidarCache('equipe');

        $msg = $atualizados > 0
            ? "Atribuição de cargos salva com sucesso! ({$atualizados} membro(s) atualizado(s))"
            : "Nenhuma alteração válida para salvar.";

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "mensagem" => $msg]);
            exit;
        }
        header("Location: /admin/equipe/?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao salvar atribuição de cargos: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao salvar atribuição de cargos."]);
            exit;
        }
        header("Location: /admin/equipe/?erro=" . urlencode("Erro ao salvar atribuição de cargos."));
        exit;
    }
}

header("Location: /admin/equipe/");
exit;
