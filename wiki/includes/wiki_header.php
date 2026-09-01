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

$servidoresAtivos = (isset($pdo) && $pdo instanceof PDO) ? getServidoresWikiAtivos($pdo) : [];
$servidorAtual = $servidorAtual ?? null;
$tituloPagina = $tituloPagina ?? 'Wiki & Guias de Modpacks';

$dropdownLabel = "Selecione o servidor";
if ($servidorAtual && !empty($servidorAtual['servername'])) {
    $dropdownLabel = $servidorAtual['servername'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?> - Wiki Rede Nerds</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    
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
    <link rel="stylesheet" href="wiki.css?v=4">
</head>
<body>
    <!-- NAVBAR MINIMALISTA -->
    <nav class="wiki-navbar">
        <div class="wiki-nav-container">
            <!-- LADO ESQUERDO: BRAND + DROPDOWN LADO A LADO -->
            <div class="wiki-nav-left d-flex align-items-center gap-3">
                <a href="index.php" class="wiki-brand">
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