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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => true, "mensagem" => "Nenhuma alteração de cargo pendente."]);
        exit;
    }
    header("Location: /admin/equipe/?msg=" . urlencode("Nenhuma alteração de cargo pendente."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Cargos válidos: hierarquia cadastrada + cargos atualmente em uso
        $cargosValidos = $pdo->query("SELECT nome FROM equipe_cargos")->fetchAll(PDO::FETCH_COLUMN);
        $emUso = $pdo->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != ''")->fetchAll(PDO::FETCH_COLUMN);
        $cargosValidos = array_unique(array_merge($cargosValidos, $emUso));

        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE equipe SET cargo = :cargo WHERE id = :id AND cargo != :cargo_check");
        $atualizados = 0;
        foreach ($membroCargos as $membroId => $cargo) {
            $membroId = (int)$membroId;
            $cargo = trim((string)$cargo);
            if ($membroId <= 0 || $cargo === '' || !in_array($cargo, $cargosValidos, true)) {
                continue;
            }
            $upd->execute([':cargo' => $cargo, ':id' => $membroId, ':cargo_check' => $cargo]);
            if ($upd->rowCount() > 0) {
                $atualizados++;
            }
        }

        $pdo->commit();

        if ($atualizados > 0) {
            require_once __DIR__ . "/../../../api/cache_helper.php";
            invalidarCache('equipe');
        }

        $msg = $atualizados > 0
            ? "Atribuição de cargos salva com sucesso! (" . pluralizar($atualizados, 'membro atualizado', 'membros atualizados') . ")"
            : "Nenhuma alteração necessária.";

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "mensagem" => $msg]);
            exit;
        }
        header("Location: /admin/equipe/?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
