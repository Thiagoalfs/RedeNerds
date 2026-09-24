<?php
/**
 * equipe_api.php
 * Retorna os membros da equipe agrupados por cargo e com cores dinâmicas no formato esperado pelo equipe.js.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

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
    echo json_encode([]);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/auth_api.php";
verificarAcessoApi();

$resultado = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // 1) Busca os cargos da tabela equipe_cargos na ordem definida com suas cores
        $cargosHierarquia = [];
        $cargosCores = [];
        try {
            $stmtCargosDef = $pdo->query("SELECT nome, cor FROM equipe_cargos ORDER BY ordem ASC, id ASC");
            $rowsDef = $stmtCargosDef->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsDef as $rd) {
                $cargosHierarquia[] = $rd['nome'];
                $corVal = !empty($rd['cor']) ? trim($rd['cor']) : '#27acff';
                $cargosCores[$rd['nome']] = $corVal;
                $cargosCores[mb_strtolower(trim($rd['nome']))] = $corVal;
            }
        } catch (Exception $e) {
            $cargosHierarquia = [];
            $cargosCores = [];
        }

        // Descobre todos os cargos existentes na tabela equipe
        $stmtCargos = $pdo->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != ''");
        $cargosExistentes = $stmtCargos->fetchAll(PDO::FETCH_COLUMN, 0);

        // Mescla garantindo que a hierarquia definida venha primeiro
        $cargos = [];
        foreach ($cargosHierarquia as $ch) {
            if (in_array($ch, $cargosExistentes, true)) {
                $cargos[] = $ch;
            }
        }
        foreach ($cargosExistentes as $ce) {
            if (!in_array($ce, $cargos, true)) {
                $cargos[] = $ce;
            }
        }

        // 2) Busca os nicks de cada cargo
        $stmtMembros = $pdo->prepare("SELECT nick FROM equipe WHERE cargo = :cargo ORDER BY id ASC");
        foreach ($cargos as $cargo) {
            $stmtMembros->execute([':cargo' => $cargo]);
            $membros = $stmtMembros->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($membros)) {
                $corFinal = $cargosCores[$cargo] ?? ($cargosCores[mb_strtolower(trim($cargo))] ?? '#27acff');
                $resultado[] = [
                    'categoryTitle' => $cargo,
                    'cor'           => $corFinal,
                    'members'       => $membros,
                ];
            }
        }
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $cargos = [];
        $cargosCores = [];
        $resDef = $conn->query("SELECT nome, cor FROM equipe_cargos ORDER BY ordem ASC, id ASC");
        if ($resDef) {
            while ($row = $resDef->fetch_assoc()) {
                $cargos[] = $row['nome'];
                $cargosCores[$row['nome']] = $row['cor'] ?? '#27acff';
            }
        }

        $resExist = $conn->query("SELECT DISTINCT cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != ''");
        $cargosExist = [];
        if ($resExist) {
            while ($rowE = $resExist->fetch_row()) {
                $cargosExist[] = $rowE[0];
            }
        }

        $cargosFinal = [];
        foreach ($cargos as $ch) {
            if (in_array($ch, $cargosExist, true)) {
                $cargosFinal[] = $ch;
            }
        }
        foreach ($cargosExist as $ce) {
            if (!in_array($ce, $cargosFinal, true)) {
                $cargosFinal[] = $ce;
            }
        }

        foreach ($cargosFinal as $cargo) {
            $cargoEscaped = $conn->real_escape_string($cargo);
            $resM = $conn->query("SELECT nick FROM equipe WHERE cargo = '{$cargoEscaped}' ORDER BY id ASC");
            $membros = [];
            if ($resM) {
                while ($rowM = $resM->fetch_row()) {
                    $membros[] = $rowM[0];
                }
            }

            if (!empty($membros)) {
                $resultado[] = [
                    'categoryTitle' => $cargo,
                    'cor'           => $cargosCores[$cargo] ?? null,
                    'members'       => $membros,
                ];
            }
        }
    }
} catch (Exception $e) {
    $resultado = [];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);