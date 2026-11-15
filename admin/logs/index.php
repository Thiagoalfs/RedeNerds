<?php
$paginaAtiva = 'logs';
$tituloPagina = 'Logs de Auditoria';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/paginacao.php";

$q = trim((string)($_GET['q'] ?? ''));
$filtroModulo = trim((string)($_GET['modulo'] ?? ''));
$filtroAcao = trim((string)($_GET['acao'] ?? ''));
$dataInicio = trim((string)($_GET['data_inicio'] ?? ''));
$dataFim = trim((string)($_GET['data_fim'] ?? ''));
$pagina = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 15;

$modulos = [
    'auth' => 'Autenticação',
    'conta' => 'Minha Conta',
    'usuarios' => 'Usuários',
    'pedidos' => 'Pedidos',
    'vips' => 'VIPs',
    'chaves' => 'Chaves',
    'cupons' => 'Cupons',
    'servidores' => 'Servidores',
    'equipe' => 'Equipe',
    'parceiros' => 'Parceiros',
    'wiki' => 'Wiki',
    'wiki_categorias' => 'Wiki Categorias',
    'noticias' => 'Notícias',
];

$acoes = [
    'login' => ['label' => 'Login', 'cls' => 'bg-success-subtle text-success border border-success-subtle'],
    'login_falha' => ['label' => 'Login Falho', 'cls' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    'logout' => ['label' => 'Logout', 'cls' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    'criar' => ['label' => 'Criação', 'cls' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    'editar' => ['label' => 'Edição', 'cls' => 'bg-warning-subtle text-warning border border-warning-subtle'],
    'deletar' => ['label' => 'Deleção', 'cls' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    'toggle' => ['label' => 'Status', 'cls' => 'bg-info-subtle text-info border border-info-subtle'],
    'reordenar' => ['label' => 'Ordem', 'cls' => 'bg-info-subtle text-info border border-info-subtle'],
    'reset_senha' => ['label' => 'Reset Senha', 'cls' => 'bg-warning-subtle text-warning border border-warning-subtle'],
    'exportar' => ['label' => 'Exportação', 'cls' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    'upload' => ['label' => 'Upload', 'cls' => 'bg-primary-subtle text-primary border border-primary-subtle'],
];

$logs = [];
$total = 0;
$totalPaginas = 1;
$erroTabela = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(usuario_nome LIKE :q1 OR descricao LIKE :q2 OR ip LIKE :q3)";
            $like = '%' . $q . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }
        if ($filtroModulo !== '' && isset($modulos[$filtroModulo])) {
            $where[] = "modulo = :modulo";
            $params[':modulo'] = $filtroModulo;
        }
        if ($filtroAcao !== '' && isset($acoes[$filtroAcao])) {
            $where[] = "acao = :acao";
            $params[':acao'] = $filtroAcao;
        }
        if ($dataInicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
            $where[] = "criado_em >= :data_inicio";
            $params[':data_inicio'] = $dataInicio . ' 00:00:00';
        }
        if ($dataFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
            $where[] = "criado_em <= :data_fim";
            $params[':data_fim'] = $dataFim . ' 23:59:59';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM admin_logs {$whereSql}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }
        $offset = ($pagina - 1) * $porPagina;

        $stmt = $pdo->prepare("
            SELECT id, usuario_id, usuario_nome, cargo, acao, modulo, alvo_id, descricao, detalhes, ip, criado_em
            FROM admin_logs
            {$whereSql}
            ORDER BY id DESC
            LIMIT {$porPagina} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao carregar admin_logs: " . $e->getMessage());
        $erroTabela = "Tabela de logs ainda não disponível. Execute a migração migrations/2026_11_admin_logs.sql no banco.";
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold">Logs de Auditoria</h2>
        <p class="text-muted small mb-0">Histórico permanente de logins e alterações no painel administrativo (somente leitura).</p>
    </div>
</div>

<?php if ($erroTabela): ?>
<div class="alert alert-warning mb-4">
    <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($erroTabela); ?>
</div>
<?php endif; ?>

<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
                <label class="form-label small text-muted mb-1">Buscar (conta, ação ou IP)</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ex.: safiroko, cupom, 177.x.x.x">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Módulo</label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $k => $nomeMod): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filtroModulo === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nomeMod); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Ação</label>
                <select name="acao" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $k => $meta): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filtroAcao === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($meta['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">De</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dataInicio); ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Até</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dataFim); ?>">
            </div>
            <div class="col-12 col-lg-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <?php if ($q !== '' || $filtroModulo !== '' || $filtroAcao !== '' || $dataInicio !== '' || $dataFim !== ''): ?>
                    <a href="/admin/logs/" class="btn btn-outline-secondary btn-sm" title="Limpar filtros"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-admin table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 155px;">Data / Hora</th>
                    <th style="width: 170px;">Conta</th>
                    <th style="width: 115px;">Ação</th>
                    <th style="width: 120px;">Módulo</th>
                    <th>O que fez</th>
                    <th style="width: 145px;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $acaoInfo = $acoes[$log['acao']] ?? ['label' => $log['acao'], 'cls' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'];
                        $modLabel = $modulos[$log['modulo']] ?? $log['modulo'];
                        $dataFmt = !empty($log['criado_em']) ? date('d/m/Y H:i:s', strtotime($log['criado_em'])) : '-';
                        ?>
                        <tr>
                            <td class="small text-muted text-nowrap" style="font-family: var(--font-mono, monospace);">
                                <?php echo htmlspecialchars($dataFmt); ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?php echo htmlspecialchars($log['usuario_nome']); ?></div>
                                <?php if (!empty($log['cargo'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($log['cargo']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $acaoInfo['cls']; ?>">
                                    <?php echo htmlspecialchars($acaoInfo['label']); ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($modLabel); ?></td>
                            <td>
                                <div class="small"><?php echo htmlspecialchars($log['descricao']); ?></div>
                                <?php if (!empty($log['detalhes'])): ?>
                                    <details class="mt-1">
                                        <summary class="text-muted small" style="cursor: pointer; font-size: 0.74rem;">Detalhes</summary>
                                        <pre class="mb-0 mt-1 p-2 rounded small" style="background: var(--admin-bg); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.74rem; white-space: pre-wrap;"><?php echo htmlspecialchars($log['detalhes']); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="small"><?php echo htmlspecialchars($log['ip']); ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <?php renderPaginacaoAdmin($pagina, $totalPaginas, [
        'q' => $q,
        'modulo' => $filtroModulo,
        'acao' => $filtroAcao,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
    ]); ?>
</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
