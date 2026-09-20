<?php
$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

require_once __DIR__ . "/../wiki_helper.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = !empty($_SESSION['usuario_id']) || !empty($_SESSION['admin_logado']);
$wikiHabilitada = (isset($pdo) && $pdo instanceof PDO) ? isWikiHabilitada($pdo) : true;

if (!$wikiHabilitada && !$isAdmin) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wiki Indisponível - Rede Nerds</title>
        <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { background: #0b0f19; color: #f1f5f9; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; text-align: center; }
            .m-card { background: #131b2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 40px 24px; max-width: 480px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
            .m-icon { font-size: 3rem; color: #38bdf8; margin-bottom: 16px; }
            h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 10px; color: #ffffff; }
            p { font-size: 0.92rem; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
            .btn-home { display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background 0.2s ease; }
            .btn-home:hover { background: #1d4ed8; }
        </style>
    </head>
    <body>
        <div class="m-card">
            <i class="fa-solid fa-book-bookmark m-icon"></i>
            <h1>Wiki Temporariamente Indisponível</h1>
            <p>A documentação e base de conhecimento da Rede Nerds está passando por atualizações.</p>
            <a href="/" class="btn-home"><i class="fa-solid fa-house"></i> Voltar ao Início</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$servidoresAtivos = (isset($pdo) && $pdo instanceof PDO) ? getServidoresWikiAtivos($pdo) : [];
$servidorAtual = $servidorAtual ?? null;
$tituloPagina = $tituloPagina ?? 'Wiki & Guias de Modpacks';

$dropdownLabel = "Selecione o servidor";
if ($servidorAtual && !empty($servidorAtual['servername'])) {
    $dropdownLabel = $servidorAtual['servername'];
}
$ogTitle = $ogTitle ?? ($tituloPagina . " - Wiki Rede Nerds");
$ogDescription = $ogDescription ?? "Central de documentação, guias de modpacks, tutoriais e comandos dos servidores da Rede Nerds.";
$ogImage = $ogImage ?? (!empty($servidorAtual['icon']) && strpos($servidorAtual['icon'], 'http') === 0 ? $servidorAtual['icon'] : "https://redenerds.com.br/assets/images/logo.webp");
$ogUrl = $ogUrl ?? ("https://redenerds.com.br" . ($_SERVER['REQUEST_URI'] ?? '/wiki/'));
$themeColor = $themeColor ?? ($servidorAtual['themecolor'] ?? '#6366f1');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?> - Wiki Rede Nerds</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    
    <!-- OPEN GRAPH / DISCORD EMBEDS -->
    <meta name="theme-color" content="<?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="Rede Nerds • Wiki Oficial">
    <meta property="og:type" content="<?php echo !empty($isArticle) ? 'article' : 'website'; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME & BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DESIGN TOKENS OFICIAIS DA REDE NERDS -->
    <link rel="stylesheet" href="/shared/tokens.css?v=1">

    <!-- CSS DA WIKI (Compatível tanto em /wiki/ quanto em root) -->
    <link rel="stylesheet" href="wiki.css?v=6">
</head>
<body>
    <!-- NAVBAR MINIMALISTA -->
    <nav class="wiki-navbar">
        <div class="wiki-nav-container">
            <!-- LADO ESQUERDO: BRAND + DROPDOWN LADO A LADO -->
            <div class="wiki-nav-left d-flex align-items-center gap-3">
                <a href="https://redenerds.com.br/" class="wiki-brand">
                    <img src="/assets/images/logo.webp" alt="Rede Nerds" onerror="this.src='../assets/images/logo.webp'">
                    <span>Rede Nerds</span>
                    <span class="wiki-brand-tag">Wiki</span>
                </a>

                <!-- DROPDOWN SELETOR DE SERVIDOR AO LADO DO NOME -->
                <div class="dropdown wiki-server-dropdown">
                    <button class="btn btn-server-select dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-server"></i>
                        <span><?php echo htmlspecialchars($dropdownLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark-solid">
                        <li><h6 class="dropdown-header text-muted font-monospace text-uppercase" style="font-size: 0.65rem;">Servidores Ativos</h6></li>
                        <li>
                            <a class="dropdown-item <?php echo (!$servidorAtual) ? 'active' : ''; ?>" href="index.php">
                                <i class="fa-solid fa-house"></i>
                                <span>Hub Principal da Wiki</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider border-secondary my-1"></li>
                        <?php if (empty($servidoresAtivos)): ?>
                            <li><span class="dropdown-item text-muted">Nenhum servidor ativo</span></li>
                        <?php else: ?>
                            <?php foreach ($servidoresAtivos as $srv): 
                                $isCurrent = ($servidorAtual && (int)$servidorAtual['id'] === (int)$srv['id']);
                            ?>
                                <li>
                                    <a class="dropdown-item <?php echo $isCurrent ? 'active' : ''; ?>" href="servidor.php?s=<?php echo urlencode($srv['nome']); ?>">
                                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                                        <span><?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- LADO DIREITO: LINKS EXTERNOS -->
            <div class="wiki-nav-links d-none d-md-flex">
                <a href="/loja/" class="wiki-nav-link" target="_blank"><i class="fa-solid fa-cart-shopping me-1"></i> Loja</a>
                <a href="/" class="wiki-nav-link"><i class="fa-solid fa-arrow-left me-1"></i> Voltar ao Site</a>
            </div>
        </div>
    </nav>
    <?php if ($isAdmin && !$wikiHabilitada): ?>
        <div class="bg-warning text-dark text-center py-2 px-3 fw-semibold small shadow-sm" style="font-size: 0.82rem; z-index: 999; position: relative;">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Modo Administrador:</strong> A visualização pública da Wiki está desativada (oculta para visitantes e na barra de navegação).
        </div>
    <?php endif; ?>