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

// Mesma ordem fixa usada em equipe_api.php
$ordemCargos = [
    'Fundadores',
    'Diretores',
    'Coordenadores',
    'Administradores',
    'Moderadores',
    'Desenvolvedores',
    'Designers',
];

try {
    $stmt = $pdo->query("SELECT id, nick, cargo FROM equipe ORDER BY nick ASC");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membros = [];
}

// Agrupa por cargo
$grupos = [];
foreach ($membros as $m) {
    $grupos[$m['cargo']][] = $m;
}

// Ordena os grupos pela lista fixa (cargos fora da lista vão pro final, alfabético)
uksort($grupos, function ($a, $b) use ($ordemCargos) {
    $posA = array_search($a, $ordemCargos, true);
    $posB = array_search($b, $ordemCargos, true);
    $posA = $posA === false ? PHP_INT_MAX : $posA;
    $posB = $posB === false ? PHP_INT_MAX : $posB;

    if ($posA === $posB) {
        return strcasecmp($a, $b);
    }
    return $posA <=> $posB;
});

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe - Painel Administrativo</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="dbcommon.css?v=2" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-size: 15px; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .cargo-titulo {
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 10px 0;
        }
        .membro-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 12px 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .membro-nick { font-weight: 600; }
        .membro-ordem { font-size: 0.78rem; color: #6c757d; }
        .avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            object-fit: cover;
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
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Voltar</a>
                <a href="servidores.php" class="btn btn-outline-light btn-sm">🖥️ Servidores</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 mt-3 mb-5">
        <div class="page-header">
            <div>
                <h5 class="mb-0 fw-bold">Equipe</h5>
                <small class="text-muted"><?php echo count($membros); ?> membro(s) em <?php echo count($grupos); ?> categoria(s)</small>
            </div>
            <a href="equipe_criar.php" class="btn btn-success btn-sm">➕ Nova pessoa</a>
        </div>

        <?php if (empty($grupos)): ?>
            <p class="text-center text-muted py-4">Nenhum membro cadastrado ainda.</p>
        <?php else: ?>
            <?php foreach ($grupos as $cargo => $lista): ?>
                <div class="mb-4">
                    <p class="cargo-titulo">🏷️ <?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php foreach ($lista as $m): ?>
                        <div class="membro-card">
                            <div class="d-flex align-items-center gap-2">
                                <img class="avatar-mini"
                                     src="https://mc-heads.net/avatar/<?php echo urlencode($m['nick']); ?>/32"
                                     alt="" onerror="this.style.display='none'">
                                <div>
                                    <div class="membro-nick"><?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="equipe_editar.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-primary">✏️</a>
                                <a href="equipe_deletar.php?id=<?php echo (int)$m['id']; ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Remover <?php echo htmlspecialchars(addslashes($m['nick']), ENT_QUOTES, 'UTF-8'); ?> da equipe?');">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>