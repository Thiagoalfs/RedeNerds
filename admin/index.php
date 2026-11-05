<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Token CSRF do formulário de login
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if (!$configPath) {
    die("Erro: Arquivo config.php não encontrado.");
}
require_once $configPath;

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
    require_once __DIR__ . "/../api/rate_limiter.php";
    exigirRateLimit('admin_login_post', 5, 300);

    // CSRF do formulário de login
    $csrfValido = !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if (!$csrfValido) {
        $mensagem_erro = "Sessão expirada. Recarregue a página e tente novamente.";
    } else {

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
                $mensagem_erro = "Muitas tentativas. Tente novamente em {$minutosRestantes} " . ($minutosRestantes == 1 ? "minuto." : "minutos.");
            }
        }

        if (!$bloqueado) {
            try {
                $stmt = $pdo->prepare("SELECT id, usuario, senha, admin FROM usuarios WHERE usuario = :usuario LIMIT 1");
                $stmt->execute([':usuario' => $usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($senha, $user['senha']) && (int)$user['admin'] === 1) {
                    // Login OK -> limpa tentativas
                    $pdo->prepare("DELETE FROM tentativas_login WHERE identificador = :id")
                        ->execute([':id' => $identificador]);

                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = (int)$user['id'];
                    $_SESSION['usuario_nome'] = $user['usuario'];
                    $_SESSION['admin'] = 1;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Falha de credencial OU usuário sem permissão: mensagem genérica
                    // (não revela a existência do usuário) e conta como tentativa
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
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo | Rede Nerds</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    
    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Outfit:wght@500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME & BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FOLHA DE ESTILOS CENTRALIZADA DO PAINEL -->
    <link rel="stylesheet" href="/admin/admin.css?v=<?php echo file_exists(__DIR__ . '/admin.css') ? filemtime(__DIR__ . '/admin.css') : '6'; ?>">

    <!-- SCRIPT DE TEMA (ANTI-FLICKER - PADRÃO DARK) -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('admin_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();

    function alternarTemaAdmin() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('admin_theme') || 'dark';
        const nextTheme = (currentTheme === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', nextTheme);
        localStorage.setItem('admin_theme', nextTheme);
    }
    </script>

    <style>
        /* === TELA DE LOGIN UNIFICADA COM O DESIGN SYSTEM DO ADMIN === */
        body.admin-login-body {
            background-color: var(--admin-bg);
            background-image: radial-gradient(circle at 50% 15%, rgba(37, 99, 235, 0.12) 0%, transparent 60%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            position: relative;
            color: var(--text-main);
            font-family: var(--font-main);
        }

        /* Topbar com navegação externa e alternador de tema */
        .admin-login-topbar {
            position: absolute;
            top: 1.25rem;
            left: 1.25rem;
            right: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            pointer-events: none;
        }

        .admin-login-topbar a,
        .admin-login-topbar button {
            pointer-events: auto;
        }

        .login-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.5rem 0.85rem;
            border-radius: var(--radius-sm, 6px);
            background: var(--admin-surface);
            border: 1px solid var(--border-color);
            transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease;
        }

        .login-back-link:hover {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }

        /* Card Master de Autenticação */
        .admin-login-card {
            max-width: 440px;
            width: 100%;
            background-color: var(--admin-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg, 14px);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        [data-theme="dark"] .admin-login-card {
            border-color: var(--sidebar-border);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
        }

        .admin-login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.25);
            margin-bottom: 1rem;
            padding: 6px;
        }

        .login-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .login-title {
            font-family: 'Outfit', var(--font-main);
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0.35rem 0 0 0;
        }

        /* Formulário e Controles */
        .login-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        .login-input-group {
            border-radius: var(--radius-sm, 6px);
            overflow: hidden;
            border: 1px solid var(--border-color);
            background-color: var(--admin-bg);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        [data-theme="dark"] .login-input-group {
            border-color: var(--sidebar-border);
        }

        .login-input-group:focus-within {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .login-input-group .input-group-text {
            background-color: transparent;
            border: none;
            color: var(--text-muted);
            padding-left: 0.95rem;
            padding-right: 0.5rem;
            font-size: 0.95rem;
        }

        .login-input-group .form-control {
            background-color: transparent;
            border: none;
            color: var(--text-main);
            padding: 0.7rem 0.85rem 0.7rem 0.4rem;
            font-size: 0.9rem;
            box-shadow: none;
        }

        .login-input-group .form-control::placeholder {
            color: var(--input-placeholder);
            font-size: 0.85rem;
        }

        .login-input-group .btn-toggle-password {
            background-color: transparent;
            border: none;
            color: var(--text-muted);
            padding: 0 0.95rem;
            cursor: pointer;
            transition: color 0.15s ease;
        }

        .login-input-group .btn-toggle-password:hover {
            color: var(--text-main);
        }

        /* Botão de Submit */
        .btn-login-submit {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: #ffffff;
            font-size: 0.92rem;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm, 6px);
            transition: background-color 0.15s ease, transform 0.1s ease, box-shadow 0.15s ease;
        }

        .btn-login-submit:hover {
            background-color: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-login-submit:active {
            transform: scale(0.99);
        }

        /* Alerta de Erro Harmonizado */
        .login-alert {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: var(--color-danger);
            border-radius: var(--radius-sm, 6px);
            padding: 0.75rem 0.95rem;
            font-size: 0.84rem;
            margin-bottom: 1.5rem;
        }

        /* Rodapé de Segurança */
        .login-footer-security {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-color);
        }

        [data-theme="dark"] .login-footer-security {
            border-color: var(--sidebar-border);
        }

        @media (max-width: 576px) {
            .admin-login-topbar {
                position: static;
                margin-bottom: 1.5rem;
                width: 100%;
            }
            .admin-login-card {
                box-shadow: var(--shadow-md);
            }
        }
    </style>
</head>
<body class="admin-login-body">
    <!-- BARRA SUPERIOR DE APOIO -->
    <header class="admin-login-topbar">
        <a href="/" class="login-back-link" title="Voltar para a página inicial">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar ao site</span>
        </a>

        <!-- BOTÃO ALTERNAR TEMA (DARK / LIGHT) -->
        <button type="button" class="btn-theme-toggle" id="btn-theme-toggle" onclick="alternarTemaAdmin()" title="Alternar tema (Claro / Escuro)" aria-label="Alternar tema">
            <i class="fa-solid fa-moon theme-icon-dark"></i>
            <i class="fa-solid fa-sun theme-icon-light"></i>
            <span class="theme-label d-none d-sm-inline">Tema</span>
        </button>
    </header>

    <!-- CARD PRINCIPAL DE LOGIN -->
    <main class="card admin-login-card mx-auto">
        <div class="card-body p-4 p-sm-5">
            <!-- CABEÇALHO DO LOGIN -->
            <div class="login-header">
                <div class="login-logo-wrap">
                    <img src="/assets/images/logo.webp" alt="Rede Nerds" width="48" height="48">
                </div>
                <h1 class="login-title">
                    <span>Rede Nerds</span>
                    <span class="sidebar-brand-badge">ADMIN</span>
                </h1>
                <p class="login-subtitle">Acesso restrito ao painel de gerenciamento</p>
            </div>

            <!-- FEEDBACK DE ERRO / TENTATIVAS -->
            <?php if (!empty($mensagem_erro)): ?>
                <div class="login-alert d-flex align-items-center gap-2" role="alert">
                    <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endif; ?>

            <!-- FORMULÁRIO DE LOGIN -->
            <form method="POST" action="index.php" autocomplete="off" id="form-admin-login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label for="usuario" class="login-label d-block">Usuário</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Seu nome de usuário" required autofocus value="<?php echo htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="senha" class="login-label d-block">Senha</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Sua chave de acesso" required>
                        <button type="button" class="btn-toggle-password" id="btn-toggle-senha" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha">
                            <i class="fa-solid fa-eye" id="icone-toggle-senha"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login-submit w-100 d-flex align-items-center justify-content-center gap-2" id="btn-submit-login">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Entrar no Painel</span>
                </button>
            </form>

            <!-- NOTA DE SEGURANÇA E CONEXÃO -->
            <div class="login-footer-security">
                <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                <span>Área protegida por Rate Limit & CSRF</span>
            </div>
        </div>
    </main>

    <!-- SCRIPT DE INTERATIVIDADE -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnToggleSenha = document.getElementById('btn-toggle-senha');
        const inputSenha = document.getElementById('senha');
        const iconeToggle = document.getElementById('icone-toggle-senha');
        const formLogin = document.getElementById('form-admin-login');
        const btnSubmit = document.getElementById('btn-submit-login');

        // Mostrar / Ocultar Senha
        if (btnToggleSenha && inputSenha && iconeToggle) {
            btnToggleSenha.addEventListener('click', function() {
                const isPassword = inputSenha.getAttribute('type') === 'password';
                inputSenha.setAttribute('type', isPassword ? 'text' : 'password');
                iconeToggle.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        }

        // Feedback de carregamento ao submeter
        if (formLogin && btnSubmit) {
            formLogin.addEventListener('submit', function() {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Autenticando...</span>';
            });
        }
    });
    </script>
</body>
</html>
