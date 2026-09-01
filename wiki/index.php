<?php
$servidorAtual = null;
$tituloPagina = 'Wiki & Guias de Modpacks';
require_once __DIR__ . "/includes/wiki_header.php";

$servidores = $servidoresAtivos;
?>

<div class="wiki-wrapper">
    <!-- CABEÇALHO DO HUB -->
    <div class="wiki-header-box">
        <h1 class="wiki-header-title">Base de Conhecimento & Guias</h1>
        <p class="wiki-header-sub">Selecione o servidor de modpack abaixo para acessar os tutoriais, comandos e documentação.</p>
        
        <!-- BARRA DE BUSCA -->
        <div class="wiki-search-box">
            <i class="fa-solid fa-magnifying-glass wiki-search-icon"></i>
            <input type="text" id="wiki-search-input" class="wiki-search-input" placeholder="Filtrar servidores..." autocomplete="off">
        </div>
    </div>

    <!-- LISTA DE SERVIDORES ATIVOS -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="text-uppercase fw-bold text-muted small mb-0" style="letter-spacing: 0.5px;">
            <i class="fa-solid fa-server me-1"></i> Servidores Disponíveis (<?php echo count($servidores); ?>)
        </h2>
    </div>

    <?php if (empty($servidores)): ?>
        <div class="wiki-category-card p-4 text-center text-muted">
            <i class="fa-solid fa-circle-exclamation fs-3 text-muted mb-2 d-block"></i>
            Nenhum servidor habilitado no momento.
        </div>
    <?php else: ?>
        <div class="wiki-server-grid" id="wiki-servers-container">
            <?php foreach ($servidores as $srv): 
                $categoriasCount = 4;
            ?>
                <a href="servidor.php?s=<?php echo urlencode($srv['nome']); ?>" class="wiki-server-card srv-card" data-name="<?php echo strtolower($srv['servername']); ?>">
                    <div>
                        <div class="wiki-server-card-top">
                            <div class="wiki-server-icon-box" style="color: <?php echo htmlspecialchars($srv['themecolor'], ENT_QUOTES, 'UTF-8'); ?>;">
                                <?php echo renderIconeWiki($srv['icon'], 'fa-solid fa-server'); ?>
                            </div>
                            <div>
                                <h3 class="wiki-server-card-name"><?php echo htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php if (!empty($srv['ip'])): ?>
                                    <span class="font-monospace text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($srv['ip'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="wiki-server-card-desc"><?php echo htmlspecialchars($srv['descricao'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="wiki-server-card-footer">
                        <span><i class="fa-solid fa-folder me-1"></i> <?php echo $categoriasCount; ?> seções</span>
                        <span class="wiki-server-card-link">Acessar guias <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('wiki-search-input');
    const cards = document.querySelectorAll('.srv-card');

    if (input) {
        input.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase().trim();
            cards.forEach(card => {
                const name = card.dataset.name || '';
                if (name.includes(val)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once __DIR__ . "/includes/wiki_footer.php"; ?>