<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$equipeId = isset($_POST['equipe_id']) ? (int)$_POST['equipe_id'] : 0;
$email = trim((string)($_POST['email'] ?? ''));
$emailRecuperacao = trim((string)($_POST['email_recuperacao'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($equipeId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Selecione um membro da equipe."], JSON_UNESCAPED_UNICODE);
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

if (($erroSenha = validarSenhaForte($senha)) !== null) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => $erroSenha], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nick, cargo FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $equipeId]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        http_response_code(404);
        echo json_encode(["success" => false, "erro" => "Membro da equipe não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $alvoNivel = normalizarCargoAdmin($membro['cargo']);
    if ($alvoNivel === null) {
        http_response_code(400);
        echo json_encode(["success" => false, "erro" => "Apenas Fundadores, Diretores, Coordenadores e Desenvolvedores podem ter conta."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $atorNivel = $_SESSION['cargo_nivel'] ?? null;
    if (!podeCriarUsuarioParaCargo($atorNivel, $alvoNivel)) {
        http_response_code(403);
        echo json_encode(["success" => false, "erro" => "Seu cargo não tem permissão para criar conta para {$membro['cargo']}."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE equipe_id = :eid OR LOWER(usuario) COLLATE utf8mb4_unicode_ci = LOWER(:nick) COLLATE utf8mb4_unicode_ci LIMIT 1");
    $chk->execute([':eid' => $equipeId, ':nick' => $membro['nick']]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(["success" => false, "erro" => "Este membro já possui uma conta cadastrada."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($email !== '') {
        $chkEmail = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $chkEmail->execute([':email' => $email]);
        if ($chkEmail->fetch()) {
            http_response_code(409);
            echo json_encode(["success" => false, "erro" => "Este e-mail já está em uso por outra conta."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $vincularExistente = !empty($_POST['vincular_existente']);
    $erroDa = null;
    $okDa = $vincularExistente
        ? atualizarSenhaEmailDirectAdmin($email, $senha, $erroDa)
        : criarEmailDirectAdmin($email, $senha, $erroDa);
    if (!$okDa) {
        http_response_code(502);
        echo json_encode(["success" => false, "erro" => $erroDa ?: "Erro ao configurar conta de e-mail no servidor."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("
        INSERT INTO usuarios (equipe_id, usuario, email, email_recuperacao, senha, admin)
        VALUES (:equipe_id, :usuario, :email, :email_recuperacao, :senha, 1)
    ");
    $ins->execute([
        ':equipe_id' => (int)$membro['id'],
        ':usuario' => $membro['nick'],
        ':email' => mb_strtolower($email, 'UTF-8'),
        ':email_recuperacao' => $emailRecuperacao !== '' ? mb_strtolower($emailRecuperacao, 'UTF-8') : null,
        ':senha' => $hash,
    ]);
    $novoUsuarioId = (int)$pdo->lastInsertId();
    registrarLogAdmin($pdo, 'criar', 'usuarios', "Criou usuário admin '{$membro['nick']}' (" . mb_strtolower($email, 'UTF-8') . ")", $novoUsuarioId ?: null);

    echo json_encode([
        "success" => true,
        "mensagem" => "Conta criada para '{$membro['nick']}' com sucesso!",
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Erro ao criar usuário admin: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao salvar usuário no banco de dados."], JSON_UNESCAPED_UNICODE);
}
