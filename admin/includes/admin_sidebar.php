<?php
// Conta pendências rápidas se o PDO estiver disponível
$pedidosPendentesCount = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pedidosPendentesCount = (int)$pdo->query("SELECT COUNT(*) FROM pedidos_vip WHERE status = 'pendente'")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!-- OVERLAY PARA MOBILE -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <img src="/assets/images/logo.webp" alt="Rede Nerds">
        <div>
            <h1 class="sidebar-brand-title">Rede Nerds</h1>
            <span class="sidebar-brand-badge">Painel Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- GERAL -->
        <span class="sidebar-group-title">Geral</span>
        <a href="dashboard.php" class="nav-item-link <?php echo ($paginaAtiva === 'dashboard') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </div>
        </a>

        <!-- LOJA & VENDAS -->
        <span class="sidebar-group-title">Loja & Vendas</span>
        <a href="pedidos.php" class="nav-item-link <?php echo ($paginaAtiva === 'pedidos') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-receipt"></i>
                <span>Pedidos VIP</span>
            </div>
            <?php if ($pedidosPendentesCount > 0): ?>
                <span class="badge bg-warning text-dark font-monospace" style="font-size: 0.7rem;"><?php echo $pedidosPendentesCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="cupons.php" class="nav-item-link <?php echo ($paginaAtiva === 'cupons') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-tags"></i>
                <span>Cupons</span>
            </div>
        </a>

        <!-- REDE & CONTEÚDO -->
        <span class="sidebar-group-title">Rede & Comunidade</span>
        <a href="servidores.php" class="nav-item-link <?php echo ($paginaAtiva === 'servidores') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-server"></i>
                <span>Servidores</span>
            </div>
        </a>
        <a href="equipe.php" class="nav-item-link <?php echo ($paginaAtiva === 'equipe') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-users"></i>
                <span>Equipe</span>
            </div>
        </a>
                <a href="wiki.php" class="nav-item-link <?php echo ($paginaAtiva === 'wiki') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-book-open"></i>
                <span>Wiki & Guias</span>
            </div>
        </a>
        <a href="noticias.php" class="nav-item-link <?php echo ($paginaAtiva === 'noticias') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-newspaper"></i>
                <span>Novidades</span>
            </div>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile-mini">
            <img class="user-avatar-mini" src="https://mc-heads.net/avatar/<?php echo urlencode($nome_usuario); ?>/32" alt="Avatar" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
            <div class="user-info-text">
                <p class="user-info-name"><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="user-info-role">Administrador</p>
            </div>
        </div>
        <a href="logout.php" class="btn-sidebar-logout" title="Sair do Painel">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>