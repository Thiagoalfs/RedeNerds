<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$alvoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
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
        SELECT u.id, u.usuario, u.email, u.email_recuperacao, e.nick, e.cargo
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

    if (!podeEditarUsuario($atorNivel, $atorId, $alvoNivel, $alvoId)) {
        http_response_code(403);
        echo json_encode(["success" => false, "erro" => "Seu cargo não tem permissão para enviar redefinição para este usuário."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = enviarResetSenhaUsuario($pdo, $alvo);
    if (!$res['ok']) {
        http_response_code($res['destino'] === null ? 400 : 500);
        echo json_encode(["success" => false, "erro" => $res['erro']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    registrarLogAdmin($pdo, 'reset_senha', 'usuarios', "Enviou e-mail de redefinição de senha para '{$alvo['usuario']}' ({$res['destino']})", $alvoId);

    echo json_encode([
        "success" => true,
        "mensagem" => "E-mail de redefinição de senha enviado para {$res['destino']}!",
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Erro ao gerar reset de senha: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao gerar token de redefinição."], JSON_UNESCAPED_UNICODE);
}
