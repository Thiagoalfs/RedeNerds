<?php
/**
 * novidades_api.php
 * Endpoint para retornar lista de novidades/atualizações com filtros e paginação.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

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

// Detecta se há conexão válida ($pdo ou $conn)
$usePDO = false;
$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $usePDO = true;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
    $usePDO = false;
} else {
    // Se o banco não puder ser conectado (ex: falta de driver local), responde vazio com segurança
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

try {
    // 1. Busca por ID específico
    if (isset($_GET["id"])) {
        $id = intval($_GET["id"]);
        if ($usePDO) {
            $stmt = $db->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $news = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $res = $db->query("SELECT * FROM novidades WHERE id = $id LIMIT 1");
            $news = $res ? $res->fetch_assoc() : null;
        }
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

        $sql = "SELECT id, titulo, autor, capa, category, criado_em 
                FROM novidades 
                $whereSQL 
                ORDER BY criado_em DESC 
                LIMIT $limit";

        if ($usePDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // MySQLi simples
            $res = $db->query($sql);
            $news = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) $news[] = $row;
            }
        }
        echo json_encode($news, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. Paginação Geral
    $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $perPage = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 5;
    $offset = ($page - 1) * $perPage;

    if ($usePDO) {
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
    } else {
        $countSql = "SELECT COUNT(*) as total FROM novidades $whereSQL";
        $resCount = $db->query($countSql);
        $total = $resCount ? intval($resCount->fetch_assoc()['total']) : 0;

        $sql = "SELECT id, titulo, autor, capa, category, conteudo, criado_em 
                FROM novidades 
                $whereSQL 
                ORDER BY criado_em DESC 
                LIMIT $perPage OFFSET $offset";

        $res = $db->query($sql);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
    }

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