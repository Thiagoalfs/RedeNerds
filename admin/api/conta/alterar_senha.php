<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$senhaAtual = (string)($_POST['senha_atual'] ?? '');
$novaSenha = (string)($_POST['nova_senha'] ?? '');
$confirmar = (string)($_POST['confirmar_senha'] ?? '');

if ($usuarioId <= 0 || $senhaAtual === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Informe sua senha atual."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($erroSenha = validarSenhaForte($novaSenha)) !== null) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => $erroSenha], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($novaSenha !== $confirmar) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "A confirmação da nova senha não confere."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT email, senha FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $usuarioId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senhaAtual, $user['senha'])) {
        http_response_code(403);
        echo json_encode(["success" => false, "erro" => "Senha atual incorreta."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($user['email'])) {
        $erroDa = null;
        if (!atualizarSenhaEmailDirectAdmin((string)$user['email'], $novaSenha, $erroDa)) {
            http_response_code(502);
            echo json_encode(["success" => false, "erro" => $erroDa ?: "Erro ao atualizar senha do e-mail no servidor."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $upd = $pdo->prepare("
        UPDATE usuarios
        SET senha = :senha, reset_token_hash = NULL, reset_expira_em = NULL
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
        ':id' => $usuarioId,
    ]);
    registrarLogAdmin($pdo, 'editar', 'conta', 'Alterou a própria senha', (int)$usuarioId);

    echo json_encode([
        "success" => true,
        "mensagem" => "Sua senha foi alterada com sucesso!",
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Erro ao alterar própria senha: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao atualizar senha no banco de dados."], JSON_UNESCAPED_UNICODE);
}
