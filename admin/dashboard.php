<?php
require_once "sessao.php";
require_once "../../config.php";

// Buscar todas as notícias (mais recentes primeiro)
try {
    $stmt = $pdo->query("SELECT id, titulo, category, capa, criado_em, autor FROM novidades ORDER BY criado_em DESC");
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $noticias = [];
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .navbar-brand { font-weight: 600; }
        .capa-thumb {
            width: 60px; height: 40px; object-fit: cover;
            border-radius: 4px; border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚙️ Painel de Notícias</a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">
                    Olá, <strong><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Notícias</h2>
                <small class="text-muted">Gerencie as notícias publicadas no site</small>
            </div>
            <a href="criar.php" class="btn btn-success">
                ➕ Nova Notícia
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">Capa</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Autor</th>
                                <th>Data</th>
                                <th class="text-center" style="width: 180px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($noticias)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Nenhuma notícia cadastrada. Clique em <strong>"Nova Notícia"</strong> para começar.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($noticias as $n): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($n['capa']) && (str_starts_with($n['capa'], 'http://') || str_starts_with($n['capa'], 'https://'))): ?>
                                                <img src="<?php echo htmlspecialchars($n['capa'], ENT_QUOTES, 'UTF-8'); ?>" alt="capa" class="capa-thumb" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo htmlspecialchars($n['category'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($n['autor'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><small><?php echo formatarData($n['criado_em']); ?></small></td>
                                        <td class="text-center">
                                            <a href="editar.php?id=<?php echo (int)$n['id']; ?>" class="btn btn-sm btn-primary">
                                                ✏️ Editar
                                            </a>
                                            <a href="deletar.php?id=<?php echo (int)$n['id']; ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Tem certeza que deseja deletar esta notícia? Esta ação não pode ser desfeita.');">
                                                🗑️ Deletar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p class="text-muted text-center mt-3 small">
            Total de notícias: <strong><?php echo count($noticias); ?></strong>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
