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

require_once __DIR__ . "/rate_limiter.php";
exigirRateLimit('api_equipe', 60, 60);

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

require_once __DIR__ . "/cache_helper.php";
verificarEtagCache('equipe');

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

        // 2) Busca todos os membros e agrupa em memória (evita N+1 queries)
        $stmtMembros = $pdo->query("SELECT nick, cargo FROM equipe WHERE cargo IS NOT NULL AND cargo != '' ORDER BY id ASC");
        $membrosPorCargo = [];
        $cargosExistentes = [];
        while ($row = $stmtMembros->fetch(PDO::FETCH_ASSOC)) {
            $cg = $row['cargo'];
            if (!isset($membrosPorCargo[$cg])) {
                $membrosPorCargo[$cg] = [];
                $cargosExistentes[] = $cg;
            }
            $membrosPorCargo[$cg][] = $row['nick'];
        }

        // 3) Mescla garantindo que a hierarquia definida venha primeiro
        $cargos = [];
        foreach ($cargosHierarquia as $ch) {
            if (isset($membrosPorCargo[$ch])) {
                $cargos[] = $ch;
            }
        }
        foreach ($cargosExistentes as $ce) {
            if (!in_array($ce, $cargos, true)) {
                $cargos[] = $ce;
            }
        }

        foreach ($cargos as $cargo) {
            $corFinal = $cargosCores[$cargo] ?? ($cargosCores[mb_strtolower(trim($cargo))] ?? '#27acff');
            $resultado[] = [
                'categoryTitle' => $cargo,
                'cor'           => $corFinal,
                'members'       => $membrosPorCargo[$cargo],
            ];
        }
    }
} catch (Exception $e) {
    $resultado = [];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);