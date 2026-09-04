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
        <a href="/admin/dashboard.php" class="nav-item-link <?php echo ($paginaAtiva === 'dashboard') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </div>
        </a>

        <!-- LOJA & VENDAS -->
        <span class="sidebar-group-title">Loja & Vendas</span>
        <a href="/admin/pedidos/" class="nav-item-link <?php echo ($paginaAtiva === 'pedidos') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-receipt"></i>
                <span>Pedidos VIP</span>
            </div>
            <?php if ($pedidosPendentesCount > 0): ?>
                <span class="badge bg-warning text-dark font-monospace" style="font-size: 0.7rem;"><?php echo $pedidosPendentesCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/vips/" class="nav-item-link <?php echo in_array($paginaAtiva, ['vips', 'vip_criar', 'vip_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-gem"></i>
                <span>Pacotes VIP</span>
            </div>
        </a>
        <a href="/admin/cupons/" class="nav-item-link <?php echo in_array($paginaAtiva, ['cupons', 'cupom_criar', 'cupom_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-tags"></i>
                <span>Cupons</span>
            </div>
        </a>

        <!-- REDE & CONTEÚDO -->
        <span class="sidebar-group-title">Rede & Comunidade</span>
        <a href="/admin/servidores/" class="nav-item-link <?php echo in_array($paginaAtiva, ['servidores', 'servidor_criar', 'servidor_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-server"></i>
                <span>Servidores</span>
            </div>
        </a>
        <a href="/admin/equipe/" class="nav-item-link <?php echo in_array($paginaAtiva, ['equipe', 'equipe_criar', 'equipe_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-users"></i>
                <span>Equipe</span>
            </div>
        </a>
        <?php $isWikiAtiva = in_array($paginaAtiva, ['wiki', 'wiki_categorias', 'wiki_artigo_criar', 'wiki_artigo_editar']); ?>
        <a href="/admin/wiki/" class="nav-item-link <?php echo $isWikiAtiva ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-book-open"></i>
                <span>Wiki & Guias</span>
            </div>
        </a>
        <?php if ($isWikiAtiva): ?>
            <div class="sidebar-subnav ps-4 my-1 d-flex flex-column gap-1">
                <a href="/admin/wiki/" class="small text-decoration-none py-1 px-2 rounded d-flex align-items-center <?php echo ($paginaAtiva === 'wiki' || $paginaAtiva === 'wiki_artigo_criar' || $paginaAtiva === 'wiki_artigo_editar') ? 'bg-primary text-white fw-semibold' : ''; ?>" style="<?php echo ($paginaAtiva === 'wiki' || $paginaAtiva === 'wiki_artigo_criar' || $paginaAtiva === 'wiki_artigo_editar') ? '' : 'color: rgb(148, 163, 184);'; ?>">
                    <i class="fa-regular fa-file-lines me-2"></i> <span>Artigos</span>
                </a>
                <a href="/admin/wiki/categorias.php" class="small text-decoration-none py-1 px-2 rounded d-flex align-items-center <?php echo ($paginaAtiva === 'wiki_categorias') ? 'bg-primary text-white fw-semibold' : ''; ?>" style="<?php echo ($paginaAtiva === 'wiki_categorias') ? '' : 'color: rgb(148, 163, 184);'; ?>">
                    <i class="fa-solid fa-folder-tree me-2"></i> <span>Categorias</span>
                </a>
            </div>
        <?php endif; ?>
        <a href="/admin/noticias/" class="nav-item-link <?php echo in_array($paginaAtiva, ['noticias', 'noticia_criar', 'noticia_editar']) ? 'active' : ''; ?>">
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
        <a href="/admin/logout.php" class="btn-sidebar-logout" title="Sair do Painel">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>