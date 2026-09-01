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
require_once "icon_upload.php";

try {
    $stmt = $pdo->query("SELECT id, servername, nome, title, icon, ip, themecolor, enabled FROM servidores ORDER BY servername ASC");
    $servidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $servidores = [];
}

/**
 * Retorna o HTML do ícone (FontAwesome ou imagem) já pronto para exibir.
 */
function renderIcone(?string $icone, string $classeExtra = ''): string
{
    if (!$icone) {
        return '<i class="fa-solid fa-server ' . $classeExtra . '"></i>';
    }
    if (tipoDoIcone($icone) === 'img') {
        $src = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="' . $classeExtra . '" alt="" onerror="this.style.display=\'none\'">';
    }
    $classe = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
    return '<i class="' . $classe . ' ' . $classeExtra . '"></i>';
}

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servidores - Painel Administrativo</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=1" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-size: 15px; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        /* Cards no mobile */
        .servidor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border-left: 5px solid var(--cor, #6c757d);
        }
        .servidor-card .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.4rem;
            color: var(--cor, #6c757d);
        }
        .servidor-card .icon-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .servidor-card .info { flex: 1; min-width: 0; }
        .servidor-card .nome { font-weight: 700; font-size: 0.95rem; color: #1a1a1a; }
        .servidor-card .slug { font-size: 0.78rem; color: #6c757d; }
        .servidor-card .ip { font-size: 0.82rem; font-family: monospace; color: #495057; margin: 4px 0 8px; }
        .servidor-card .acoes { display: flex; gap: 6px; flex-wrap: wrap; }

        .tabela-desktop { display: none; }
        .icon-box-table {
            width: 40px; height: 40px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            background: #f0f2f5; font-size: 1.1rem;
        }
        .icon-box-table img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .cor-swatch {
            display: inline-block; width: 18px; height: 18px; border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.15); vertical-align: middle; margin-right: 6px;
        }

        @media (min-width: 768px) {
            .cards-mobile { display: none; }
            .tabela-desktop { display: block; }
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
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">📰 Notícias</a>
                <a href="equipe.php" class="btn btn-outline-light btn-sm">🧑‍🤝‍🧑 Equipe</a>
                <a href="servidores.php" class="btn btn-light btn-sm text-dark fw-bold">🖥️ Servidores</a>
                <a href="cupons.php" class="btn btn-outline-light btn-sm">🏷️ Cupons</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 mt-3 mb-5">
        <div class="page-header">
            <div>
                <h5 class="mb-0 fw-bold">Servidores</h5>
                <small class="text-muted"><?php echo count($servidores); ?> servidor(es) cadastrado(s)</small>
            </div>
            <a href="servidor_criar.php" class="btn btn-success btn-sm">➕ Novo servidor</a>
        </div>

        <!-- MOBILE: cards -->
        <div class="cards-mobile">
            <?php if (empty($servidores)): ?>
                <p class="text-center text-muted py-4">Nenhum servidor cadastrado ainda.</p>
            <?php else: ?>
                <?php foreach ($servidores as $s): ?>
                    <div class="servidor-card" style="--cor: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>;">
                        <div class="icon-box"><?php echo renderIcone($s['icon']); ?></div>
                        <div class="info">
                            <div class="nome">
                                <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!$s['enabled']): ?>
                                    <span class="badge bg-secondary ms-1">Oculto</span>
                                <?php endif; ?>
                            </div>
                            <div class="slug">/<?php echo htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="ip">🌐 <?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="acoes">
                                <a href="servidor_editar.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                <a href="servidor_toggle.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <?php echo $s['enabled'] ? '🙈 Ocultar' : '👁️ Exibir'; ?>
                                </a>
                                <a href="servidor_deletar.php?id=<?php echo (int)$s['id']; ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Deletar o servidor <?php echo htmlspecialchars(addslashes($s['servername']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');">🗑️</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                                    <th style="width:60px;">Ícone</th>
                                    <th>Servidor</th>
                                    <th style="width:140px;">Slug (nome)</th>
                                    <th style="width:220px;">IP</th>
                                    <th style="width:110px;">Cor</th>
                                    <th style="width:90px;">Status</th>
                                    <th class="text-center" style="width:220px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($servidores)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Nenhum servidor cadastrado ainda.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($servidores as $s): ?>
                                        <tr>
                                            <td>
                                                <div class="icon-box-table" style="color: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>;">
                                                    <?php echo renderIcone($s['icon']); ?>
                                                </div>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td><small class="font-monospace"><?php echo htmlspecialchars($s['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                            <td>
                                                <span class="cor-swatch" style="background: <?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                                <small><?php echo htmlspecialchars($s['themecolor'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($s['enabled']): ?>
                                                    <span class="badge bg-success">Visível</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Oculto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                                    <a href="servidor_editar.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                                    <a href="servidor_toggle.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <?php echo $s['enabled'] ? '🙈' : '👁️'; ?>
                                                    </a>
                                                    <a href="servidor_deletar.php?id=<?php echo (int)$s['id']; ?>"
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Deletar o servidor <?php echo htmlspecialchars(addslashes($s['servername']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');">🗑️</a>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
