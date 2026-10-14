<?php
/**
 * admin/includes/paginacao.php
 * Componente de paginação reutilizável do painel (substitui as
 * implementações duplicadas de pedidos_table.php e noticias/index.php).
 *
 * Uso:
 *   renderPaginacaoAdmin($pagina, $totalPaginas, ['servidor' => 'x', 'busca' => 'y']);
 * Os parâmetros vazios são omitidos da URL automaticamente.
 */

if (!function_exists('renderPaginacaoAdmin')) {
    function renderPaginacaoAdmin(int $pagina, int $totalPaginas, array $params = []): void
    {
        if ($totalPaginas <= 1) {
            return;
        }

        $pagina = max(1, $pagina);

        $base = [];
        foreach ($params as $chave => $valor) {
            if ($valor !== '' && $valor !== null) {
                $base[$chave] = $valor;
            }
        }

        $urlPagina = function ($p) use ($base) {
            return '?' . http_build_query(array_merge($base, ['page' => (int)$p]));
        };

        // Janela de páginas visíveis: 3 primeiras + página atual (se fora da janela) + última
        $paginasVisiveis = [1, 2, 3];
        if ($pagina > 3 && $pagina < $totalPaginas) {
            $paginasVisiveis[] = $pagina;
        }
        $paginasVisiveis[] = $totalPaginas;
        $paginasVisiveis = array_values(array_unique(array_filter(
            $paginasVisiveis,
            fn($p) => $p >= 1 && $p <= $totalPaginas
        )));
        sort($paginasVisiveis);
        ?>
        <nav class="d-flex justify-content-center mt-3">
            <ul class="pagination pagination-sm">
                <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $urlPagina(max(1, $pagina - 1)); ?>">‹ Anterior</a>
                </li>
                <?php
                $paginaAnteriorExibida = 0;
                foreach ($paginasVisiveis as $p):
                    if ($p - $paginaAnteriorExibida > 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item <?php echo ($p === $pagina) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $urlPagina($p); ?>"><?php echo $p; ?></a>
                    </li>
                    <?php $paginaAnteriorExibida = $p; ?>
                <?php endforeach; ?>
                <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $urlPagina(min($totalPaginas, $pagina + 1)); ?>">Próxima ›</a>
                </li>
            </ul>
        </nav>
        <?php
    }
}
