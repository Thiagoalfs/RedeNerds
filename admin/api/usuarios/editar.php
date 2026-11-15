<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$alvoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$email = trim((string)($_POST['email'] ?? ''));
$emailRecuperacao = trim((string)($_POST['email_recuperacao'] ?? ''));

if ($alvoId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "ID de usuário inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Informe um e-mail corporativo válido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($emailRecuperacao !== '' && !filter_var($emailRecuperacao, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "E-mail de recuperação inválido."], JSON_UNESCAPED_UNICODE);
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

    if (!podeEditarUsuario($atorNivel, $atorId, $alvoNivel, $alvoId)) {
        http_response_code(403);
        echo json_encode(["success" => false, "erro" => "Seu cargo não tem permissão para editar este usuário."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $emailNorm = $email !== '' ? mb_strtolower($email, 'UTF-8') : null;
    if ($emailNorm !== null) {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(:email) AND id <> :id LIMIT 1");
        $chk->execute([':email' => $emailNorm, ':id' => $alvoId]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(["success" => false, "erro" => "Este e-mail já está em uso por outra conta."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $upd = $pdo->prepare("UPDATE usuarios SET email = :email, email_recuperacao = :email_recuperacao WHERE id = :id LIMIT 1");
    $upd->execute([
        ':email' => $emailNorm,
        ':email_recuperacao' => $emailRecuperacao !== '' ? mb_strtolower($emailRecuperacao, 'UTF-8') : null,
        ':id' => $alvoId,
    ]);

    if ($alvoId === $atorId) {
        $_SESSION['usuario_email'] = $emailNorm;
    }

    echo json_encode([
        "success" => true,
        "mensagem" => "Configurações de '{$alvo['usuario']}' atualizadas com sucesso!",
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Erro ao editar usuário admin: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao atualizar usuário no banco de dados."], JSON_UNESCAPED_UNICODE);
}
