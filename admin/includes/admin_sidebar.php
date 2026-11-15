<?php
// Sidebar de navegação do painel administrativo
?>
<!-- OVERLAY PARA MOBILE -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <img src="/assets/images/logo.webp" alt="Rede Nerd">
        <div>
            <h1 class="sidebar-brand-title">Rede Nerd</h1>
            <span class="sidebar-brand-badge">Painel Admin</span>
        </div>
    </div>

    <?php
    $cargoNivel = $_SESSION['cargo_nivel'] ?? null;
    $cargoExibicao = $_SESSION['cargo'] ?? 'Administrador';
    $podeVerGeral = usuarioPodeAcessar('dashboard', $cargoNivel) || usuarioPodeAcessar('email', $cargoNivel) || usuarioPodeAcessar('usuarios', $cargoNivel);
    $podeVerLoja = usuarioPodeAcessar('pedidos', $cargoNivel) || usuarioPodeAcessar('vips', $cargoNivel) || usuarioPodeAcessar('chaves', $cargoNivel) || usuarioPodeAcessar('cupons', $cargoNivel);
    $podeVerRede = usuarioPodeAcessar('servidores', $cargoNivel) || usuarioPodeAcessar('equipe', $cargoNivel) || usuarioPodeAcessar('parceiros', $cargoNivel) || usuarioPodeAcessar('wiki', $cargoNivel) || usuarioPodeAcessar('noticias', $cargoNivel);
    ?>
    <nav class="sidebar-nav">
        <?php if ($podeVerGeral): ?>
        <!-- GERAL -->
        <span class="sidebar-group-title">Geral</span>
        <?php if (usuarioPodeAcessar('dashboard', $cargoNivel)): ?>
        <a href="/admin/dashboard.php" class="nav-item-link <?php echo ($paginaAtiva === 'dashboard') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('email', $cargoNivel)): ?>
        <a href="/admin/email/" class="nav-item-link <?php echo ($paginaAtiva === 'email') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-inbox"></i>
                <span>E-Mail</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('logs', $cargoNivel)): ?>
        <a href="/admin/logs/" class="nav-item-link <?php echo ($paginaAtiva === 'logs') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Logs</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('usuarios', $cargoNivel)): ?>
        <a href="/admin/usuarios/" class="nav-item-link <?php echo ($paginaAtiva === 'usuarios') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-user-shield"></i>
                <span>Usuários</span>
            </div>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($podeVerLoja): ?>
        <!-- LOJA & VENDAS -->
        <span class="sidebar-group-title">Loja & Vendas</span>
        <?php if (usuarioPodeAcessar('pedidos', $cargoNivel)): ?>
        <a href="/admin/pedidos/" class="nav-item-link <?php echo ($paginaAtiva === 'pedidos') ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-receipt"></i>
                <span>Pedidos</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('vips', $cargoNivel)): ?>
        <a href="/admin/vips/" class="nav-item-link <?php echo in_array($paginaAtiva, ['vips', 'vip_criar', 'vip_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-gem"></i>
                <span>Pacotes VIP</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('chaves', $cargoNivel)): ?>
        <a href="/admin/chaves/" class="nav-item-link <?php echo in_array($paginaAtiva, ['chaves', 'chave_criar', 'chave_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-key"></i>
                <span>Chaves & Caixas</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('cupons', $cargoNivel)): ?>
        <a href="/admin/cupons/" class="nav-item-link <?php echo in_array($paginaAtiva, ['cupons', 'cupom_criar', 'cupom_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-tags"></i>
                <span>Cupons</span>
            </div>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($podeVerRede): ?>
        <!-- REDE & CONTEÚDO -->
        <span class="sidebar-group-title">Rede & Comunidade</span>
        <?php if (usuarioPodeAcessar('servidores', $cargoNivel)): ?>
        <a href="/admin/servidores/" class="nav-item-link <?php echo in_array($paginaAtiva, ['servidores', 'servidor_criar', 'servidor_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-server"></i>
                <span>Servidores</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('equipe', $cargoNivel)): ?>
        <a href="/admin/equipe/" class="nav-item-link <?php echo in_array($paginaAtiva, ['equipe', 'equipe_criar', 'equipe_editar', 'equipe_manage']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-users"></i>
                <span>Equipe</span>
            </div>
        </a>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('parceiros', $cargoNivel)): ?>
        <a href="/admin/parceiros/" class="nav-item-link <?php echo in_array($paginaAtiva, ['parceiros', 'parceiro_criar', 'parceiro_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-handshake"></i>
                <span>Parceiros</span>
            </div>
        </a>
        <?php endif; ?>

        <?php if (usuarioPodeAcessar('wiki', $cargoNivel)): ?>
        <?php $isWikiAtiva = in_array($paginaAtiva, ['wiki', 'wiki_categorias']); ?>
        <a href="/admin/wiki/" class="nav-item-link <?php echo $isWikiAtiva ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-book-open"></i>
                <span>Wiki & Tutoriais</span>
            </div>
        </a>
        <?php if ($isWikiAtiva): ?>
            <div class="sidebar-subnav ps-4 my-1 d-flex flex-column gap-1">
                <a href="/admin/wiki/" class="small text-decoration-none py-1 px-2 rounded d-flex align-items-center <?php echo ($paginaAtiva === 'wiki') ? 'bg-primary text-white fw-semibold' : ''; ?>" style="<?php echo ($paginaAtiva === 'wiki') ? '' : 'color: rgb(148, 163, 184);'; ?>">
                    <i class="fa-regular fa-file-lines me-2"></i> <span>Artigos</span>
                </a>
                <a href="/admin/wiki/categorias.php" class="small text-decoration-none py-1 px-2 rounded d-flex align-items-center <?php echo ($paginaAtiva === 'wiki_categorias') ? 'bg-primary text-white fw-semibold' : ''; ?>" style="<?php echo ($paginaAtiva === 'wiki_categorias') ? '' : 'color: rgb(148, 163, 184);'; ?>">
                    <i class="fa-solid fa-folder-tree me-2"></i> <span>Categorias</span>
                </a>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (usuarioPodeAcessar('noticias', $cargoNivel)): ?>
        <a href="/admin/noticias/" class="nav-item-link <?php echo in_array($paginaAtiva, ['noticias', 'noticia_criar', 'noticia_editar']) ? 'active' : ''; ?>">
            <div class="icon-wrap">
                <i class="fa-solid fa-newspaper"></i>
                <span>Novidades</span>
            </div>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile-mini">
            <img class="user-avatar-mini" src="https://mc-heads.net/avatar/<?php echo urlencode($nome_usuario); ?>" alt="Avatar" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve'">
            <div class="user-info-text">
                <p class="user-info-name"><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="user-info-role"><?php echo htmlspecialchars($cargoExibicao, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <button type="button" class="btn-sidebar-logout" data-bs-toggle="modal" data-bs-target="#modalAlterarPropriaSenha" title="Alterar minha senha" aria-label="Alterar minha senha">
                <i class="fa-solid fa-key"></i>
            </button>
            <a href="/admin/logout.php" class="btn-sidebar-logout" title="Sair do Painel">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>