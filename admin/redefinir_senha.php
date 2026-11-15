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
require_once __DIR__ . "/includes/logger.php";

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenFormatoValido = ($token !== '' && strlen($token) === 64 && ctype_xdigit($token));

$erro = '';
$sucesso = '';
$usuarioAlvo = null;

if ($tokenFormatoValido && isset($pdo) && $pdo instanceof PDO) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.usuario, u.email, e.nick
        FROM usuarios u
        LEFT JOIN equipe e ON (u.equipe_id = e.id OR (u.equipe_id IS NULL AND LOWER(e.nick) COLLATE utf8mb4_unicode_ci = LOWER(u.usuario) COLLATE utf8mb4_unicode_ci))
        WHERE u.reset_token_hash = :hash
          AND u.reset_expira_em IS NOT NULL
          AND u.reset_expira_em > NOW()
        LIMIT 1
    ");
    $stmt->execute([':hash' => hash('sha256', $token)]);
    $usuarioAlvo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . "/../api/rate_limiter.php";
    exigirRateLimit('admin_reset_pwd', 10, 300);

    $csrfOk = !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    if (!$csrfOk) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (!$usuarioAlvo) {
        $erro = 'Link de redefinição inválido ou expirado.';
    } else {
        $novaSenha = (string)($_POST['nova_senha'] ?? '');
        $confirmar = (string)($_POST['confirmar_senha'] ?? '');
        if (($erroSenha = validarSenhaForte($novaSenha)) !== null) {
            $erro = $erroSenha;
        } elseif ($novaSenha !== $confirmar) {
            $erro = 'As senhas não conferem.';
        } elseif (!empty($usuarioAlvo['email']) && !atualizarSenhaEmailDirectAdmin((string)$usuarioAlvo['email'], $novaSenha, $erroDa)) {
            $erro = $erroDa ?: 'Não foi possível atualizar a senha do e-mail no servidor.';
        } else {
            $upd = $pdo->prepare("
                UPDATE usuarios
                SET senha = :senha, reset_token_hash = NULL, reset_expira_em = NULL
                WHERE id = :id
                LIMIT 1
            ");
            $upd->execute([
                ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
                ':id' => (int)$usuarioAlvo['id'],
            ]);
            registrarLogAdmin($pdo, 'reset_senha', 'auth', 'Redefiniu a senha via link de recuperação', (int)$usuarioAlvo['id'], null, [
                'usuario_id' => (int)$usuarioAlvo['id'],
                'usuario_nome' => $usuarioAlvo['nick'] ?: $usuarioAlvo['usuario'],
            ]);
            $sucesso = 'Senha redefinida com sucesso! Você já pode entrar no painel.';
            $usuarioAlvo = null;
        }
    }
} elseif (!$usuarioAlvo) {
    $erro = 'Link de redefinição inválido ou expirado.';
}

$nickExibir = $usuarioAlvo ? ($usuarioAlvo['nick'] ?: $usuarioAlvo['usuario']) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Painel Administrativo | Rede Nerd</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            <span>Ir para o Login</span>
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
                <h1 class="reset-title">Redefinir Senha</h1>
                <p class="reset-subtitle">Painel Administrativo Rede Nerd</p>
            </div>

            <?php if ($sucesso !== ''): ?>
                <div class="alert alert-success small d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <a href="/admin/index.php" class="btn btn-reset-submit w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Entrar no Painel</span>
                </a>
            <?php elseif ($erro !== '' && !$usuarioAlvo): ?>
                <div class="alert alert-danger small d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <a href="/admin/index.php" class="btn btn-outline-secondary w-100">Voltar ao Login</a>
            <?php else: ?>
                <div class="reset-user-pill">
                    <img src="https://mc-heads.net/avatar/<?php echo urlencode($nickExibir); ?>" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve'">
                    <div>
                        <div class="reset-user-nick"><?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="reset-user-hint">Conta vinculada ao painel</div>
                    </div>
                </div>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-danger small d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="redefinir_senha.php" id="formRedefinirSenha" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-3">
                        <label for="nova_senha" class="reset-label">Nova Senha</label>
                        <div class="input-group reset-input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required autocomplete="new-password" placeholder="Crie uma senha forte">
                            <button type="button" class="btn-toggle-pass" data-target="nova_senha" title="Mostrar ou ocultar">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="confirmar_senha" class="reset-label">Confirmar Nova Senha</label>
                        <div class="input-group reset-input-group">
                            <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required autocomplete="new-password" placeholder="Repita a nova senha">
                            <button type="button" class="btn-toggle-pass" data-target="confirmar_senha" title="Mostrar ou ocultar">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="pw-rules" id="pwRules">
                        <div class="pw-rule" data-rule="len"><i class="fa-regular fa-circle"></i><span>Mínimo de 8 caracteres</span></div>
                        <div class="pw-rule" data-rule="num"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 número (0–9)</span></div>
                        <div class="pw-rule" data-rule="sym"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 caractere especial (!@#$...)</span></div>
                        <div class="pw-rule" data-rule="match"><i class="fa-regular fa-circle"></i><span>As duas senhas conferem</span></div>
                    </div>

                    <button type="submit" class="btn btn-reset-submit w-100 d-flex align-items-center justify-content-center gap-2" id="btnSalvarNovaSenha" disabled>
                        <i class="fa-solid fa-key"></i>
                        <span>Salvar Nova Senha</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-toggle-pass').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                btn.querySelector('i').className = isPwd ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        });

        const s1 = document.getElementById('nova_senha');
        const s2 = document.getElementById('confirmar_senha');
        const submitBtn = document.getElementById('btnSalvarNovaSenha');
        if (!s1 || !s2 || !submitBtn) return;

        const setRule = (name, ok) => {
            const el = document.querySelector(`.pw-rule[data-rule="${name}"]`);
            if (!el) return;
            el.classList.toggle('ok', ok);
            el.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
        };

        const validar = () => {
            const v1 = s1.value;
            const v2 = s2.value;
            const checks = {
                len: v1.length >= 8,
                num: /[0-9]/.test(v1),
                sym: /[^A-Za-z0-9]/.test(v1),
                match: v1.length > 0 && v1 === v2
            };
            Object.entries(checks).forEach(([k, ok]) => setRule(k, ok));
            submitBtn.disabled = !Object.values(checks).every(Boolean);
        };

        s1.addEventListener('input', validar);
        s2.addEventListener('input', validar);
    });
    </script>
</body>
</html>
