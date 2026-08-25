<?php

session_start();
require_once "../../config.php";

if (isset($_SESSION['usuario_id']) && isset($_SESSION['admin']) && $_SESSION['admin'] == 1) {
    header("Location: dashboard.php");
    exit;
}

$mensagem_erro = "";

// --- Configurações do rate limit ---
const MAX_TENTATIVAS = 3;
const BLOQUEIO_MINUTOS = 15;

function getIdentificador(string $usuario): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    return $ip . '|' . strtolower($usuario);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($usuario === '' || $senha === '') {
        $mensagem_erro = "Preencha todos os campos.";
    } else {
        $identificador = getIdentificador($usuario);

        // Verifica se está bloqueado
        $stmt = $pdo->prepare("SELECT tentativas, bloqueado_ate FROM tentativas_login WHERE identificador = :id");
        $stmt->execute([':id' => $identificador]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        $bloqueado = false;
        if ($registro && $registro['bloqueado_ate'] !== null) {
            if (strtotime($registro['bloqueado_ate']) > time()) {
                $bloqueado = true;
                $minutosRestantes = ceil((strtotime($registro['bloqueado_ate']) - time()) / 60);
                $mensagem_erro = "Muitas tentativas. Tente novamente em {$minutosRestantes} minuto(s).";
            }
        }

        if (!$bloqueado) {
            try {
                $stmt = $pdo->prepare("SELECT id, usuario, senha, admin FROM usuarios WHERE usuario = :usuario LIMIT 1");
                $stmt->execute([':usuario' => $usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($senha, $user['senha'])) {
                    if ((int)$user['admin'] === 1) {
                        // Login OK -> limpa tentativas
                        $pdo->prepare("DELETE FROM tentativas_login WHERE identificador = :id")
                            ->execute([':id' => $identificador]);

                        $_SESSION['usuario_id'] = (int)$user['id'];
                        $_SESSION['usuario_nome'] = $user['usuario'];
                        $_SESSION['admin'] = 1;
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $mensagem_erro = "Este usuário não possui permissão de administrador.";
                    }
                } else {
                    // Login falhou -> incrementa tentativas
                    $novasTentativas = ($registro['tentativas'] ?? 0) + 1;

                    if ($novasTentativas >= MAX_TENTATIVAS) {
                        $bloqueadoAte = date('Y-m-d H:i:s', strtotime('+' . BLOQUEIO_MINUTOS . ' minutes'));
                        $mensagem_erro = "Muitas tentativas. Tente novamente em " . BLOQUEIO_MINUTOS . " minutos.";
                    } else {
                        $bloqueadoAte = null;
                        $mensagem_erro = "Usuário ou senha inválidos. Tentativa {$novasTentativas} de " . MAX_TENTATIVAS . ".";
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO tentativas_login (identificador, tentativas, bloqueado_ate, ultima_tentativa)
                        VALUES (:id, :tentativas, :bloqueado_ate, NOW())
                        ON DUPLICATE KEY UPDATE
                            tentativas = :tentativas2,
                            bloqueado_ate = :bloqueado_ate2,
                            ultima_tentativa = NOW()
                    ");
                    $stmt->execute([
                        ':id' => $identificador,
                        ':tentativas' => $novasTentativas,
                        ':bloqueado_ate' => $bloqueadoAte,
                        ':tentativas2' => $novasTentativas,
                        ':bloqueado_ate2' => $bloqueadoAte,
                    ]);
                }
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao consultar o banco de dados.";
            }
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
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* === Mobile-first === */
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card-login {
            max-width: 420px;
            width: 100%;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card-login .form-control {
            max-width: 100%;
            min-width: 0;
            word-break: break-word;
        }
        .card-login h3 { font-size: 1.25rem; }
        @media (min-width: 576px) {
            .card-login h3 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-3">
        <div class="card card-login mx-auto">
            <div class="card-body p-3 p-sm-4 p-md-5">
                <h3 class="text-center mb-3 mb-md-4">🔐 Painel Administrativo</h3>
                <p class="text-center text-muted mb-3 mb-md-4 small">Acesso restrito para administradores</p>

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