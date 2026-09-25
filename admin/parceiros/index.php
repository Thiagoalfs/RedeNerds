<?php
$paginaAtiva = 'parceiros';
$tituloPagina = 'Parceiros';
require_once __DIR__ . "/../includes/admin_header.php";

// Auto-criação da tabela se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `parceiros` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `nome` VARCHAR(100) NOT NULL,
      `foto` VARCHAR(255) NULL DEFAULT NULL,
      `youtube` VARCHAR(255) NULL DEFAULT NULL,
      `tiktok` VARCHAR(255) NULL DEFAULT NULL,
      `instagram` VARCHAR(255) NULL DEFAULT NULL,
      `twitch` VARCHAR(255) NULL DEFAULT NULL,
      `kick` VARCHAR(255) NULL DEFAULT NULL,
      `ativo` TINYINT(1) NOT NULL DEFAULT 1,
      `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_ativo_nome (`ativo`, `nome`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) {}

$busca = trim($_GET['busca'] ?? '');
$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "nome LIKE :busca";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$parceiros = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM parceiros $whereSql ORDER BY nome ASC");
    $stmt->execute($params);
    $parceiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de Parceiros: " . $e->getMessage());
    $parceiros = [];
}

$totalParceiros = count($parceiros);
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-handshake text-primary me-2"></i> Parceiros e Criadores de Conteúdo</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo $totalParceiros; ?> parceiro(s)</span> cadastrado(s) (exibidos em ordem alfabética na página inicial)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/#parceiros-section" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver no Site</a>
        <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Parceiro</a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- FILTROS E BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por nome do parceiro..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <?php if ($busca !== ''): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PARCEIROS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;" class="text-center">Foto</th>
                        <th>Nome do Parceiro</th>
                        <th>Redes Sociais Conectadas</th>
                        <th style="width: 120px;" class="text-center">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parceiros)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Nenhum parceiro encontrado<?php echo $busca ? ' para os termos buscados' : ''; ?>. Clique em "+ Novo Parceiro" para cadastrar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parceiros as $p): 
                            $fotoUrl = !empty($p['foto']) ? $p['foto'] : '/assets/images/logo.webp';
                        ?>
                            <tr>
                                <td class="text-center">
                                    <img src="<?php echo htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="rounded-circle border shadow-sm"
                                         style="width: 42px; height: 42px; object-fit: cover;"
                                         onerror="this.src='/assets/images/logo.webp'">
                                </td>
                                <td>
                                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="text-muted small">Cadastrado em <?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <?php if (!empty($p['youtube'])): ?>
                                            <a href="<?php echo htmlspecialchars($p['youtube'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-pill" title="YouTube: <?php echo htmlspecialchars($p['youtube'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-brands fa-youtube me-1"></i> YouTube
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($p['tiktok'])): ?>
                                            <a href="<?php echo htmlspecialchars($p['tiktok'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-dark p-1 px-2 rounded-pill" title="TikTok: <?php echo htmlspecialchars($p['tiktok'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-brands fa-tiktok me-1"></i> TikTok
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($p['instagram'])): ?>
                                            <a href="<?php echo htmlspecialchars($p['instagram'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2 rounded-pill" style="color: #E4405F; border-color: #E4405F;" title="Instagram: <?php echo htmlspecialchars($p['instagram'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-brands fa-instagram me-1"></i> Instagram
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($p['twitch'])): ?>
                                            <a href="<?php echo htmlspecialchars($p['twitch'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-primary p-1 px-2 rounded-pill" style="color: #9146FF; border-color: #9146FF;" title="Twitch: <?php echo htmlspecialchars($p['twitch'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-brands fa-twitch me-1"></i> Twitch
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($p['kick'])): ?>
                                            <a href="<?php echo htmlspecialchars($p['kick'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-outline-success p-1 px-2 rounded-pill" style="color: #53FC18; border-color: #53FC18;" title="Kick: <?php echo htmlspecialchars($p['kick'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-brands fa-kick me-1"></i> Kick
                                            </a>
                                        <?php endif; ?>

                                        <?php if (empty($p['youtube']) && empty($p['tiktok']) && empty($p['instagram']) && empty($p['twitch']) && empty($p['kick'])): ?>
                                            <span class="text-muted small fst-italic">Nenhuma rede social configurada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($p['ativo'])): ?>
                                        <span class="badge-status ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="editar.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary" title="Editar Parceiro">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/parceiros/toggle.php" 
                                                data-id="<?php echo (int)$p['id']; ?>"
                                                title="<?php echo !empty($p['ativo']) ? 'Desativar na Home' : 'Ativar na Home'; ?>">
                                            <i class="fa-solid <?php echo !empty($p['ativo']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/parceiros/deletar.php" 
                                                data-id="<?php echo (int)$p['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja remover o parceiro <?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar Parceiro">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>