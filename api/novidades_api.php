<?php
/**
 * novidades_api.php
 * Endpoint para retornar lista de novidades/atualizações com filtros e paginação.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/rate_limiter.php";
exigirRateLimit('api_novidades', 60, 60);

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
    echo json_encode(["erro" => "Arquivo config.php não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/auth_api.php";
verificarAcessoApi();

require_once __DIR__ . "/cache_helper.php";
$queryFingerprint = !empty($_GET) ? http_build_query($_GET) : 'all';
verificarEtagCache('novidades', $queryFingerprint);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($_GET["limit"])) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "data" => [],
            "total" => 0,
            "page" => 1,
            "total_pages" => 0,
            "per_page" => intval($_GET["per_page"] ?? 5)
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$db = $pdo;

try {
    // 1. Busca por ID específico
    if (isset($_GET["id"])) {
        $id = intval($_GET["id"]);
        $stmt = $db->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($news ?? ["erro" => "Notícia não encontrada"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Filtros
    $where = [];
    $params = [];

    if (isset($_GET["category"]) && $_GET["category"] !== "" && $_GET["category"] !== "all") {
        $where[] = "category = :category";
        $params[':category'] = $_GET["category"];
    }

    if (isset($_GET["q"]) && trim($_GET["q"]) !== "") {
        $termo = trim($_GET["q"]);
        $palavras = preg_split('/\s+/', $termo);
        $subGroups = [];
        $i = 0;
        foreach ($palavras as $palavra) {
            $palavraLimpa = preg_replace('/[^\p{L}\p{N}]/u', '', $palavra);
            if ($palavraLimpa === "") continue;

            $key = ":q" . $i;
            $subGroups[] = "(titulo LIKE $key OR conteudo LIKE $key OR autor LIKE $key)";
            $params[$key] = "%" . $palavraLimpa . "%";
            $i++;
        }
        if (!empty($subGroups)) {
            $where[] = "(" . implode(" AND ", $subGroups) . ")";
        }
    }

    $whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // 3. Consulta de Novidades Mais Recentes (Top N)
    if (isset($_GET["limit"])) {
        $limit = intval($_GET["limit"]);
        if ($limit <= 0) $limit = 3;

        $sql = "SELECT id, titulo, autor, capa, category, conteudo, criado_em 
                FROM novidades 
                $whereSQL 
                ORDER BY criado_em DESC 
                LIMIT $limit";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($news, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. Paginação Geral
    $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $perPage = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 5;
    $offset = ($page - 1) * $perPage;

    $countSql = "SELECT COUNT(*) FROM novidades $whereSQL";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $total = intval($stmtCount->fetchColumn());

    $sql = "SELECT id, titulo, autor, capa, category, conteudo, criado_em 
            FROM novidades 
            $whereSQL 
            ORDER BY criado_em DESC 
            LIMIT $perPage OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "data" => $data,
        "total" => $total,
        "page" => $page,
        "total_pages" => ceil($total / $perPage),
        "per_page" => $perPage
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($_GET["limit"])) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "data" => [],
            "total" => 0,
            "page" => 1,
            "total_pages" => 0,
            "per_page" => 5
        ], JSON_UNESCAPED_UNICODE);
    }
}