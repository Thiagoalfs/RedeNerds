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