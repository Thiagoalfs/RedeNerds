<?php
require_once "sessao.php";
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

const POR_PAGINA = 10;

$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$totalNoticias = 0;
$totalPaginas = 1;
$noticias = [];

try {
    $totalNoticias = (int) $pdo->query("SELECT COUNT(*) FROM novidades")->fetchColumn();
    $totalPaginas = $totalNoticias > 0 ? (int) ceil($totalNoticias / POR_PAGINA) : 1;

    // Evita pedir uma página além do total (ex: item deletado)
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
        $offset = ($pagina - 1) * POR_PAGINA;
    }

    $stmt = $pdo->prepare("SELECT id, titulo, category, categoria_envio, capa, criado_em, autor FROM novidades ORDER BY criado_em DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', POR_PAGINA, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $noticias = [];
}

// Mapa com variações (servername, slug, sem espaço, singular/plural) => themecolor
$coresPorServidor = [];
try {
    $stmt = $pdo->query("SELECT servername, nome, themecolor FROM servidores");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $cor = $s['themecolor'];
        $sn = trim($s['servername'] ?? '');
        $nm = trim($s['nome'] ?? '');

        if ($sn !== '') {
            $coresPorServidor[$sn] = $cor;
            $cleanSn = strtolower(str_replace(' ', '', $sn));
            $coresPorServidor[$cleanSn] = $cor;
            $coresPorServidor[rtrim($cleanSn, 's')] = $cor;
            $coresPorServidor[$cleanSn . 's'] = $cor;
        }

        if ($nm !== '') {
            $coresPorServidor[$nm] = $cor;
            $cleanNm = strtolower(str_replace(' ', '', $nm));
            $coresPorServidor[$cleanNm] = $cor;
            $coresPorServidor[rtrim($cleanNm, 's')] = $cor;
            $coresPorServidor[$cleanNm . 's'] = $cor;
        }
    }
} catch (PDOException $e) {
    $coresPorServidor = [];
}

function corDoServidor($nome, $mapa) {
    if (!$nome) return '#6c757d';
    if (isset($mapa[$nome])) return $mapa[$nome];

    $clean = strtolower(str_replace(' ', '', trim($nome)));
    if (isset($mapa[$clean])) return $mapa[$clean];

    $cleanNoS = rtrim($clean, 's');
    if (isset($mapa[$cleanNoS])) return $mapa[$cleanNoS];

    $cleanWithS = $clean . 's';
    if (isset($mapa[$cleanWithS])) return $mapa[$cleanWithS];

    return '#6c757d';
}

function corTextoContraste($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 150) ? '#1a1a1a' : '#ffffff';
}

// Monta a URL de uma página de paginação preservando outros parâmetros da query string
function urlPagina($pagina) {
    $params = $_GET;
    $params['page'] = $pagina;
    return 'dashboard.php?' . http_build_query($params);
}

function formatarData($data) {
    $ts = strtotime($data);
    if (!$ts) return '-';
    return date('d/m/Y H:i', $ts);
}

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Painel Administrativo</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=1" rel="stylesheet">
    <style>
        /* Ajustes específicos desta página */
        .noticia-thumb-table {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel</a>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white small d-none d-sm-inline">
                    Olá, <strong><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
                <a href="#" class="btn btn-outline-light btn-sm">📰 Notícias</a>
                <a href="equipe.php" class="btn btn-outline-light btn-sm">🧑‍🤝‍🧑 Equipe</a>
                <a href="servidores.php" class="btn btn-outline-light btn-sm">🖥️ Servidores</a>
                <a href="cupons.php" class="btn btn-outline-light btn-sm">🏷️ Cupons</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 mt-3">
        <div class="page-header">
            <div>
                <h5 class="mb-0 fw-bold">Notícias</h5>
                <small class="text-muted"><?php echo $totalNoticias; ?> publicada(s)<?php if ($totalPaginas > 1): ?> · página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?><?php endif; ?></small>
            </div>
            <a href="criar.php" class="btn btn-success btn-sm">➕ Nova</a>
        </div>

        <!-- MOBILE: cards -->
        <div class="cards-mobile">
            <?php if (empty($noticias)): ?>
                <p class="text-center text-muted py-4">Nenhuma notícia cadastrada.</p>
            <?php else: ?>
                <?php foreach ($noticias as $n): ?>
                    <div class="list-card" style="--accent: <?php echo htmlspecialchars(corDoServidor($n['category'], $coresPorServidor), ENT_QUOTES, 'UTF-8'); ?>;">
                        <div class="thumb-square thumb-lg">
                            <?php if (!empty($n['capa'])): ?>
                                <img src="<?php echo htmlspecialchars($n['capa'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="capa" onerror="this.closest('.thumb-square').innerHTML='🖼️'">
                            <?php else: ?>
                                🖼️
                            <?php endif; ?>
                        </div>

                        <div class="info">
                            <div class="title"><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="meta">
                                <div>
                                    <?php 
                                        $corBg = corDoServidor($n['category'], $coresPorServidor);
                                        $corTxt = corTextoContraste($corBg);
                                    ?>
                                    <span class="badge me-1" style="background-color: <?php echo htmlspecialchars($corBg, ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo $corTxt; ?>;" title="Servidor">🌐 <?php echo htmlspecialchars($n['category'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="badge bg-secondary me-1" title="Categoria de Envio">📢 <?php echo htmlspecialchars($n['categoria_envio'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="mt-1">
                                    👤 <?php echo htmlspecialchars($n['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?> · 🕒 <?php echo formatarData($n['criado_em']); ?>
                                </div>
                            </div>
                            <div class="actions">
                                <a href="editar.php?id=<?php echo (int)$n['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="deletar.php?id=<?php echo (int)$n['id']; ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Deletar esta notícia?');">🗑️ Deletar</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginação de notícias" class="d-flex justify-content-center mt-3">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(urlPagina(max(1, $pagina - 1)), ENT_QUOTES, 'UTF-8'); ?>">‹</a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                            <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(urlPagina($p), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(urlPagina(min($totalPaginas, $pagina + 1)), ENT_QUOTES, 'UTF-8'); ?>">›</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- DESKTOP: tabela -->
        <div class="tabela-desktop">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:70px;">Capa</th>
                                    <th>Título</th>
                                    <th style="width:110px;">Servidor</th>
                                    <th style="width:130px;">Categoria</th>
                                    <th style="width:120px;">Autor</th>
                                    <th style="width:130px;">Data</th>
                                    <th class="text-center text-nowrap" style="width:140px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($noticias)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Nenhuma notícia cadastrada.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                <?php foreach ($noticias as $n): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($n['capa'])): ?>
                                                    <img src="<?php echo htmlspecialchars($n['capa'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         alt="capa" class="noticia-thumb-table"
                                                         onerror="this.style.display='none'">
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php 
                                                    $corBg = corDoServidor($n['category'], $coresPorServidor);
                                                    $corTxt = corTextoContraste($corBg);
                                                ?>
                                                <span class="badge" style="background-color: <?php echo htmlspecialchars($corBg, ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo $corTxt; ?>;">
                                                    <?php echo htmlspecialchars($n['category'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($n['categoria_envio'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($n['autor'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><small><?php echo formatarData($n['criado_em']); ?></small></td>
                                            <td class="text-center text-nowrap">
                                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                                    <a href="editar.php?id=<?php echo (int)$n['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                    <a href="deletar.php?id=<?php echo (int)$n['id']; ?>"
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Deletar esta notícia? Essa ação não pode ser desfeita.');">🗑️</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginação de notícias" class="d-flex justify-content-center mt-3">
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(urlPagina(max(1, $pagina - 1)), ENT_QUOTES, 'UTF-8'); ?>">‹ Anterior</a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                            <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(urlPagina($p), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(urlPagina(min($totalPaginas, $pagina + 1)), ENT_QUOTES, 'UTF-8'); ?>">Próxima ›</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
