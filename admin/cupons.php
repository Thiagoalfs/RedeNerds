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

try {
    $stmt = $pdo->query("SELECT id, codigo, porcentagem_desconto, expira_em, ativo, usos_total, criado_em FROM cupons ORDER BY criado_em DESC");
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cupons = [];
}

function formatarDataHora(?string $data): string {
    if (!$data) return '-';
    $ts = strtotime($data);
    if (!$ts) return '-';
    return date('d/m/Y H:i', $ts);
}

function statusDoCupom(array $cupom): array {
    $now = time();
    $expiraTs = strtotime($cupom['expira_em']);
    $isExpirado = ($expiraTs && $expiraTs < $now);

    if ($isExpirado) {
        return [
            'label' => 'Expirado',
            'badge' => 'bg-danger text-white',
            'expirado' => true,
            'ativo' => false
        ];
    }

    if (!$cupom['ativo']) {
        return [
            'label' => 'Inativo',
            'badge' => 'bg-secondary text-white',
            'expirado' => false,
            'ativo' => false
        ];
    }

    return [
        'label' => 'Ativo',
        'badge' => 'bg-success text-white',
        'expirado' => false,
        'ativo' => true
    ];
}

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupons de Desconto - Painel Administrativo</title>
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

        .coupon-code-badge {
            font-family: 'JetBrains Mono', monospace, Consolas, sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 4px 8px;
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
            border-radius: 4px;
            display: inline-block;
        }

        .coupon-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border-left: 5px solid #6366f1;
        }
        .coupon-card.is-expired { border-left-color: #ef4444; }
        .coupon-card.is-inactive { border-left-color: #9ca3af; }

        .coupon-card .icon-box {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
            color: #4f46e5;
        }
        .coupon-card.is-expired .icon-box { background: #fee2e2; color: #dc2626; }
        .coupon-card.is-inactive .icon-box { background: #f3f4f6; color: #6b7280; }

        .coupon-card .info { flex: 1; min-width: 0; }
        .coupon-card .nome { font-weight: 700; font-size: 0.95rem; color: #1a1a1a; }
        .coupon-card .desc-row { font-size: 0.82rem; color: #4b5563; margin: 4px 0 8px; }
        .coupon-card .acoes { display: flex; gap: 6px; flex-wrap: wrap; }

        .tabela-desktop { display: none; }

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
                <a href="servidores.php" class="btn btn-outline-light btn-sm">🖥️ Servidores</a>
                <a href="cupons.php" class="btn btn-light btn-sm text-dark fw-bold">🏷️ Cupons</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 mt-3 mb-5">
        <div class="page-header">
            <div>
                <h5 class="mb-0 fw-bold">Cupons de Desconto</h5>
                <small class="text-muted"><?php echo count($cupons); ?> cupom(ns) cadastrado(s)</small>
            </div>
            <a href="cupom_criar.php" class="btn btn-success btn-sm">➕ Novo cupom</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- MOBILE: cards -->
        <div class="cards-mobile">
            <?php if (empty($cupons)): ?>
                <p class="text-center text-muted py-4">Nenhum cupom cadastrado ainda.</p>
            <?php else: ?>
                <?php foreach ($cupons as $c): 
                    $st = statusDoCupom($c);
                    $cardClass = $st['expirado'] ? 'is-expired' : (!$st['ativo'] ? 'is-inactive' : '');
                ?>
                    <div class="coupon-card <?php echo $cardClass; ?>">
                        <div class="icon-box">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <div class="info">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="coupon-code-badge"><?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge <?php echo $st['badge']; ?>"><?php echo $st['label']; ?></span>
                            </div>
                            <div class="desc-row">
                                <div><strong>Desconto:</strong> <?php echo number_format((float)$c['porcentagem_desconto'], 1, ',', '.'); ?>%</div>
                                <div><strong>Expira em:</strong> <?php echo formatarDataHora($c['expira_em']); ?></div>
                                <div><strong>Usos:</strong> <?php echo (int)$c['usos_total']; ?> compra(s)</div>
                            </div>
                            <div class="acoes">
                                <a href="cupom_editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="cupom_toggle.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid <?php echo $c['ativo'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                    <?php echo $c['ativo'] ? 'Desativar' : 'Ativar'; ?>
                                </a>
                                <a href="cupom_deletar.php?id=<?php echo (int)$c['id']; ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Deletar o cupom <?php echo htmlspecialchars(addslashes($c['codigo']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');">🗑️</a>
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
                                    <th style="width:180px;">Código</th>
                                    <th style="width:130px;">Desconto</th>
                                    <th style="width:200px;">Validade</th>
                                    <th style="width:110px;">Status</th>
                                    <th style="width:90px;">Usos</th>
                                    <th style="width:150px;">Criado em</th>
                                    <th class="text-end" style="width:180px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cupons)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Nenhum cupom cadastrado ainda.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cupons as $c): 
                                        $st = statusDoCupom($c);
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="coupon-code-badge"><?php echo htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td>
                                                <strong class="text-success fw-bold"><?php echo number_format((float)$c['porcentagem_desconto'], 1, ',', '.'); ?>% OFF</strong>
                                            </td>
                                            <td>
                                                <i class="fa-regular fa-clock text-muted me-1"></i>
                                                <?php echo formatarDataHora($c['expira_em']); ?>
                                                <?php if ($st['expirado']): ?>
                                                    <span class="badge bg-danger ms-1">Expirado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $st['badge']; ?>"><?php echo $st['label']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?php echo (int)$c['usos_total']; ?></span>
                                            </td>
                                            <td class="text-muted small">
                                                <?php echo formatarDataHora($c['criado_em']); ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                                    <a href="cupom_editar.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                    <a href="cupom_toggle.php?id=<?php echo (int)$c['id']; ?>"
                                                       class="btn btn-sm btn-outline-secondary"
                                                       title="<?php echo $c['ativo'] ? 'Desativar cupom' : 'Ativar cupom'; ?>">
                                                        <i class="fa-solid <?php echo $c['ativo'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                                    </a>
                                                    <a href="cupom_deletar.php?id=<?php echo (int)$c['id']; ?>"
                                                       class="btn btn-sm btn-danger"
                                                       title="Deletar"
                                                       onclick="return confirm('Deletar o cupom <?php echo htmlspecialchars(addslashes($c['codigo']), ENT_QUOTES, 'UTF-8'); ?>? Essa ação não pode ser desfeita.');">🗑️</a>
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