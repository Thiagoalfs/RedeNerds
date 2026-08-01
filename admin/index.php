<?php

session_start();
require_once "../../config.php";

// Se já estiver logado como admin, vai direto para o dashboard
if (isset($_SESSION['usuario_id']) && isset($_SESSION['admin']) && $_SESSION['admin'] == 1) {
    header("Location: dashboard.php");
    exit;
}

$mensagem_erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($usuario === '' || $senha === '') {
        $mensagem_erro = "Preencha todos os campos.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, usuario, senha, admin FROM usuarios WHERE usuario = :usuario LIMIT 1");
            $stmt->execute([':usuario' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                if ((int)$user['admin'] === 1) {
                    // Autenticação válida e é admin
                    $_SESSION['usuario_id'] = (int)$user['id'];
                    $_SESSION['usuario_nome'] = $user['usuario'];
                    $_SESSION['admin'] = 1;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $mensagem_erro = "Este usuário não possui permissão de administrador.";
                }
            } else {
                $mensagem_erro = "Usuário ou senha inválidos.";
            }
        } catch (PDOException $e) {
            $mensagem_erro = "Erro ao consultar o banco de dados.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-login {
            max-width: 420px;
            width: 100%;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card card-login mx-auto">
            <div class="card-body p-4 p-md-5">
                <h3 class="text-center mb-4">🔐 Painel Administrativo</h3>
                <p class="text-center text-muted mb-4">Acesso restrito para administradores</p>

                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php" autocomplete="off">
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuário</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
