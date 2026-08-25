<?php
/**
 * equipe_api.php
 * Retorna os membros da equipe agrupados por cargo no formato esperado pelo equipe.js.
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

// Ordem fixa dos cargos na página.
$ordemCargos = [
    'Fundadores',
    'Diretores',
    'Coordenadores',
    'Administradores',
    'Moderadores',
    'Desenvolvedores',
    'Designers',
];

$resultado = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // 1) Descobre os cargos existentes
        $stmtCargos = $pdo->query("SELECT DISTINCT cargo FROM equipe");
        $cargos = $stmtCargos->fetchAll(PDO::FETCH_COLUMN, 0);

        // 2) Ordena pela lista fixa
        usort($cargos, function ($a, $b) use ($ordemCargos) {
            $posA = array_search($a, $ordemCargos, true);
            $posB = array_search($b, $ordemCargos, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;

            if ($posA === $posB) {
                return strcasecmp($a, $b);
            }
            return $posA <=> $posB;
        });

        // 3) Busca os nicks de cada cargo
        $stmtMembros = $pdo->prepare("SELECT nick FROM equipe WHERE cargo = :cargo ORDER BY id ASC");
        foreach ($cargos as $cargo) {
            $stmtMembros->execute([':cargo' => $cargo]);
            $membros = $stmtMembros->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($membros)) {
                $resultado[] = [
                    'categoryTitle' => $cargo,
                    'members'       => $membros,
                ];
            }
        }
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $resCargos = $conn->query("SELECT DISTINCT cargo FROM equipe");
        $cargos = [];
        if ($resCargos) {
            while ($row = $resCargos->fetch_row()) {
                $cargos[] = $row[0];
            }
        }

        usort($cargos, function ($a, $b) use ($ordemCargos) {
            $posA = array_search($a, $ordemCargos, true);
            $posB = array_search($b, $ordemCargos, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;

            if ($posA === $posB) {
                return strcasecmp($a, $b);
            }
            return $posA <=> $posB;
        });

        foreach ($cargos as $cargo) {
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
                    'members'       => $membros,
                ];
            }
        }
    }
} catch (Exception $e) {
    $resultado = [];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);