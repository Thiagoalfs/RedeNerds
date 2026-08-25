<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php"
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (file_exists($cp)) {
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

// Detecta automaticamente se o config.php usa $pdo ou $conn
$usePDO = false;
$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $usePDO = true;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
    $usePDO = false;
} else {
    echo json_encode(["erro" => "Nenhuma conexão válida com o banco de dados ($pdo ou $conn) foi configurada."], JSON_UNESCAPED_UNICODE);
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

    $whereSql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
    $isPaginado = isset($_GET["page"]) || isset($_GET["per_page"]);

    if ($usePDO) {
        if ($isPaginado) {
            $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
            $perPage = isset($_GET["per_page"]) ? intval($_GET["per_page"]) : 5;
            if ($perPage < 1) $perPage = 5;
            if ($perPage > 50) $perPage = 50;
            $offset = ($page - 1) * $perPage;

            $stmtCount = $db->prepare("SELECT COUNT(*) FROM novidades" . $whereSql);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

            $sql = "SELECT * FROM novidades" . $whereSql . " ORDER BY criado_em DESC LIMIT $perPage OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $novidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "data" => $novidades,
                "total" => $total,
                "page" => $page,
                "per_page" => $perPage,
                "total_pages" => $totalPages
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Modo simples (Top 3 no index)
        $sql = "SELECT * FROM novidades" . $whereSql . " ORDER BY criado_em DESC";
        if (isset($_GET["limit"]) && $_GET["limit"] !== "") {
            $limit = max(1, min(100, intval($_GET["limit"])));
            $sql .= " LIMIT $limit";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $novidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($novidades, JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Fallback MySQLi caso config.php use $conn
        $sql = "SELECT * FROM novidades" . $whereSql . " ORDER BY criado_em DESC";
        if (isset($_GET["limit"])) {
            $limit = intval($_GET["limit"]);
            $sql .= " LIMIT $limit";
        }
        $res = $db->query($sql);
        $novidades = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode($novidades, JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["erro" => "Erro na consulta: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>