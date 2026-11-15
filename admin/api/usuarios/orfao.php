<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$atorNivel = $_SESSION['cargo_nivel'] ?? null;
if (!in_array($atorNivel, ['fundador', 'diretor'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "erro" => "Seu cargo não tem permissão para gerenciar e-mails órfãos."], JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = trim((string)($_POST['acao'] ?? ''));
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "E-mail inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Impede alterar ou apagar por este endpoint um e-mail que já pertence a um usuário cadastrado.
if (isset($pdo) && $pdo instanceof PDO) {
    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1");
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(["success" => false, "erro" => "Este e-mail já está vinculado a um usuário."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$erroDa = null;
if ($acao === 'editar_senha') {
    $novaSenha = (string)($_POST['nova_senha'] ?? '');
    if ($novaSenha === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "erro" => "Informe a nova senha."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!atualizarSenhaEmailDirectAdmin($email, $novaSenha, $erroDa)) {
        http_response_code(502);
        echo json_encode(["success" => false, "erro" => $erroDa ?: "Não foi possível atualizar a senha do e-mail."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(["success" => true, "mensagem" => "Senha do e-mail '{$email}' atualizada com sucesso!"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($acao === 'deletar') {
    if (!deletarEmailDirectAdmin($email, $erroDa)) {
        http_response_code(502);
        echo json_encode(["success" => false, "erro" => $erroDa ?: "Não foi possível deletar o e-mail."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(["success" => true, "mensagem" => "E-mail '{$email}' removido com sucesso!"], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(["success" => false, "erro" => "Ação inválida."], JSON_UNESCAPED_UNICODE);
