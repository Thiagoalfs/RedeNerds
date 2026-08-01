<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "../config.php";

if (isset($_GET["id"])) {
    // Retorna uma novidade específica
    $id = intval($_GET["id"]);
    $result = $conn->query("SELECT * FROM novidades WHERE id = $id LIMIT 1");
    if (!$result) {
        echo json_encode(["erro" => $conn->error]);
        exit;
    }
    $news = $result->fetch_assoc();
    echo json_encode($news ?? ["erro" => "não encontrado"], JSON_UNESCAPED_UNICODE);
} else {
    // Monta a query base com filtros opcionais
    $where = [];
    $params = [];
    $types = "";

    // Filtro por categoria (NerdSky, Potato Nerd, Nerd Dead)
    if (isset($_GET["category"]) && $_GET["category"] !== "" && $_GET["category"] !== "all") {
        $where[] = "category = ?";
        $params[] = $_GET["category"];
        $types .= "s";
    }

    // Busca por termo (titulo + conteudo + autor)
    // Usa tokenização: cada palavra vira um LIKE independente
    // Ex: "mods magicos" → "(titulo LIKE %mods% OR conteudo LIKE %mods% OR autor LIKE %mods%) AND (...OR LIKE %magicos%)"
    if (isset($_GET["q"]) && trim($_GET["q"]) !== "") {
        $termo = trim($_GET["q"]);
        $palavras = preg_split('/\s+/', $termo);

        $subGroups = [];
        foreach ($palavras as $palavra) {
            $palavraLimpa = preg_replace('/[^\p{L}\p{N}]/u', '', $palavra);
            if ($palavraLimpa === "") continue;

            $like = "%" . $palavraLimpa . "%";
            $subGroups[] = "(titulo LIKE ? OR conteudo LIKE ? OR autor LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= "sss";
        }

        if (!empty($subGroups)) {
            // AND entre palavras = todas devem aparecer (busca mais precisa)
            $where[] = "(" . implode(" AND ", $subGroups) . ")";
        }
    }

    // Monta SQL final
    $sql = "SELECT * FROM novidades";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY criado_em DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["erro" => $conn->error]);
        exit;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $novidades = [];
    while ($linha = $result->fetch_assoc()) {
        $novidades[] = $linha;
    }

    echo json_encode($novidades, JSON_UNESCAPED_UNICODE);
    $stmt->close();
}
?>