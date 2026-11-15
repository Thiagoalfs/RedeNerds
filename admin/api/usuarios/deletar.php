<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$input = $_POST;
if (empty($input['id'])) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$alvoId = isset($input['id']) ? (int)$input['id'] : 0;
if ($alvoId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "ID de usuário inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.usuario, e.cargo
        FROM usuarios u
        LEFT JOIN equipe e ON (u.equipe_id = e.id OR (u.equipe_id IS NULL AND LOWER(e.nick) COLLATE utf8mb4_unicode_ci = LOWER(u.usuario) COLLATE utf8mb4_unicode_ci))
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $alvoId]);
    $alvo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alvo) {
        http_response_code(404);
        echo json_encode(["success" => false, "erro" => "Usuário não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $atorId = (int)($_SESSION['usuario_id'] ?? 0);
    $atorNivel = $_SESSION['cargo_nivel'] ?? null;
    $alvoNivel = normalizarCargoAdmin($alvo['cargo'] ?? null);

    if (!podeDeletarUsuario($atorNivel, $atorId, $alvoNivel, $alvoId)) {
        http_response_code(403);
        $msg = ($atorId === $alvoId)
            ? "Você não pode excluir a própria conta."
            : "Seu cargo não tem permissão para excluir esta conta.";
        echo json_encode(["success" => false, "erro" => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM usuarios WHERE id = :id LIMIT 1");
    $del->execute([':id' => $alvoId]);
    registrarLogAdmin($pdo, 'deletar', 'usuarios', "Removeu usuário admin '{$alvo['usuario']}'", $alvoId);

    echo json_encode([
        "success" => true,
        "mensagem" => "Usuário '{$alvo['usuario']}' removido com sucesso!",
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Erro ao remover usuário admin: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao remover usuário."], JSON_UNESCAPED_UNICODE);
}
