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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        require_once $cp;
        break;
    }
}
require_once __DIR__ . "/includes/permissoes.php";

$erro = '';
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . "/../api/rate_limiter.php";
    exigirRateLimit('admin_esqueci_senha', 5, 300);

    $csrfOk = !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    $identificador = trim((string)($_POST['identificador'] ?? ''));

    if (!$csrfOk) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif ($identificador === '') {
        $erro = 'Informe seu usuário ou e-mail da empresa.';
    } elseif (!isset($pdo) || !($pdo instanceof PDO)) {
        $erro = 'Conexão com o banco de dados indisponível no momento.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.usuario, u.email, u.email_recuperacao, u.admin, e.nick, e.cargo
                FROM usuarios u
                INNER JOIN equipe e ON (u.equipe_id = e.id OR (u.equipe_id IS NULL AND LOWER(e.nick) COLLATE utf8mb4_unicode_ci = LOWER(u.usuario) COLLATE utf8mb4_unicode_ci))
                WHERE LOWER(u.usuario) = LOWER(:id1)
                   OR (u.email IS NOT NULL AND u.email <> '' AND LOWER(u.email) = LOWER(:id2))
                LIMIT 1
            ");
            $stmt->execute([':id1' => $identificador, ':id2' => $identificador]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (int)$user['admin'] === 1 && normalizarCargoAdmin($user['cargo']) !== null) {
                $res = enviarResetSenhaUsuario($pdo, $user);
                if (!$res['ok']) {
                    error_log('Falha no esqueci_senha para user ' . $user['id'] . ': ' . ($res['erro'] ?? ''));
                }
            }
            // Mensagem neutra para não revelar existência de conta nem e-mail de recuperação
            $enviado = true;
        } catch (PDOException $e) {
            error_log('Erro em esqueci_senha.php: ' . $e->getMessage());
            $erro = 'Erro ao processar solicitação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci Minha Senha - Painel Administrativo | Rede Nerd</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/admin/admin.css?v=<?php echo file_exists(__DIR__ . '/admin.css') ? filemtime(__DIR__ . '/admin.css') : '6'; ?>">
    <script>
    (function() {
        document.documentElement.setAttribute('data-theme', localStorage.getItem('admin_theme') || 'dark');
    })();
    function alternarTemaAdmin() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('admin_theme', next);
    }
    </script>
</head>
<body class="admin-reset-body">
    <header class="reset-topbar">
        <a href="/admin/index.php" class="reset-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar ao Login</span>
        </a>
        <button type="button" class="btn-theme-toggle" onclick="alternarTemaAdmin()" title="Alternar tema">
            <i class="fa-solid fa-moon theme-icon-dark"></i>
            <i class="fa-solid fa-sun theme-icon-light"></i>
        </button>
    </header>

    <main class="card reset-card">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <div class="reset-logo-wrap">
                    <img src="/assets/images/logo.webp" alt="Rede Nerd" width="48" height="48">
                </div>
                <h1 class="reset-title">Esqueci Minha Senha</h1>
                <p class="reset-subtitle">Painel Administrativo Rede Nerd</p>
            </div>

            <?php if ($enviado): ?>
                <div class="alert alert-success small d-flex align-items-start gap-2">
                    <i class="fa-solid fa-circle-check mt-1"></i>
                    <span>Se a conta existir e tiver acesso ao painel, enviamos um link de redefinição para o e-mail de recuperação (ou e-mail da empresa) cadastrado.</span>
                </div>
                <a href="/admin/index.php" class="btn btn-reset-submit w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Voltar para o Login</span>
                </a>
            <?php else: ?>
                <?php if ($erro !== ''): ?>
                    <div class="alert alert-danger small d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="esqueci_senha.php" onsubmit="const b=this.querySelector('[type=submit]');if(b){b.disabled=true;b.innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i><span>Enviando...</span>';}">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-4">
                        <label for="identificador" class="reset-label">Usuário ou E-Mail da Empresa</label>
                        <div class="input-group reset-input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" class="form-control" id="identificador" name="identificador" required autofocus placeholder="Seu nick ou e-mail da empresa">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-reset-submit w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Enviar Link de Redefinição</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
