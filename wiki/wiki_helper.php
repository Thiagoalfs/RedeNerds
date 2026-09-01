<?php
/**
 * wiki_helper.php - Funções e Utilitários da Wiki (Rede Nerds)
 */

if (!function_exists('garantirCategoriasPadraoWiki')) {
    function garantirCategoriasPadraoWiki(PDO $pdo, int $servidorId): void {
        if ($servidorId <= 0) return;

        $categoriasPadrao = [
            ['nome' => 'Primeiros Passos',    'slug' => 'primeiros-passos',    'icone' => 'fa-solid fa-compass',             'ordem' => 1],
            ['nome' => 'Máquinas & Energia',  'slug' => 'maquinas-energia',  'icone' => 'fa-solid fa-gears',               'ordem' => 2],
            ['nome' => 'Magia & Alquimia',    'slug' => 'magia-alquimia',    'icone' => 'fa-solid fa-wand-magic-sparkles', 'ordem' => 3],
            ['nome' => 'Terrenos & Comandos', 'slug' => 'terrenos-comandos', 'icone' => 'fa-solid fa-shield-halved',       'ordem' => 4],
        ];

        $stmtCheck = $pdo->prepare("SELECT id FROM wiki_categorias WHERE servidor_id = :servidor_id AND slug = :slug LIMIT 1");
        $stmtInsert = $pdo->prepare("INSERT INTO wiki_categorias (servidor_id, nome, slug, icone, ordem) VALUES (:servidor_id, :nome, :slug, :icone, :ordem)");

        foreach ($categoriasPadrao as $cat) {
            $stmtCheck->execute([':servidor_id' => $servidorId, ':slug' => $cat['slug']]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert->execute([
                    ':servidor_id' => $servidorId,
                    ':nome'        => $cat['nome'],
                    ':slug'        => $cat['slug'],
                    ':icone'       => $cat['icone'],
                    ':ordem'       => $cat['ordem']
                ]);
            }
        }
    }
}

if (!function_exists('getServidoresWikiAtivos')) {
    function getServidoresWikiAtivos(PDO $pdo): array {
        try {
            $stmt = $pdo->query("SELECT id, servername, nome, title, icon, descricao, ip, themecolor FROM servidores WHERE enabled = 1 ORDER BY servername ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getServidorWikiPorSlug')) {
    function getServidorWikiPorSlug(PDO $pdo, string $slug): ?array {
        if (empty($slug)) return null;
        $slugClean = strtolower(str_replace(['-', '_', ' '], '', $slug));
        try {
            $stmt = $pdo->prepare("
                SELECT id, servername, nome, title, icon, descricao, ip, themecolor 
                FROM servidores 
                WHERE (
                    REPLACE(REPLACE(LOWER(nome), '-', ''), '_', '') = :clean
                    OR REPLACE(REPLACE(REPLACE(LOWER(servername), ' ', ''), '-', ''), '_', '') = :clean
                    OR (id = :id_num AND :id_num > 0)
                ) AND enabled = 1 
                LIMIT 1
            ");
            $idNum = is_numeric($slug) ? (int)$slug : 0;
            $stmt->execute([':clean' => $slugClean, ':id_num' => $idNum]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('getCategoriasServidorWiki')) {
    function getCategoriasServidorWiki(PDO $pdo, int $servidorId): array {
        if ($servidorId <= 0) return [];
        garantirCategoriasPadraoWiki($pdo, $servidorId);

        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.servidor_id, c.nome, c.slug, c.icone, c.ordem,
                       COUNT(a.id) AS total_artigos
                FROM wiki_categorias c
                LEFT JOIN wiki_artigos a ON a.categoria_id = c.id AND a.publicado = 1
                WHERE c.servidor_id = :servidor_id
                GROUP BY c.id, c.servidor_id, c.nome, c.slug, c.icone, c.ordem
                ORDER BY c.ordem ASC, c.nome ASC
            ");
            $stmt->execute([':servidor_id' => $servidorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('renderIconeWiki')) {
    function renderIconeWiki(?string $icone, string $fallbackClass = 'fa-solid fa-book'): string {
        if (empty($icone)) {
            return '<i class="' . htmlspecialchars($fallbackClass, ENT_QUOTES, 'UTF-8') . '"></i>';
        }
        if (strpos($icone, '/') !== false || strpos($icone, '.') !== false) {
            return '<img src="' . htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') . '" class="wiki-icon-img" alt="">';
        }
        return '<i class="' . htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') . '"></i>';
    }
}

if (!function_exists('parseMarkdownWiki')) {
    function parseMarkdownWiki(string $markdown, array &$headings = []): string {
        $markdown = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
        $headings = [];

        // 1. Code blocks
        $markdown = preg_replace_callback('/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/m', function ($matches) {
            $lang = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $code = $matches[2];
            return '<div class="wiki-code-block"><div class="code-header"><span class="code-lang">' . ($lang ?: 'código') . '</span></div><pre><code>' . $code . '</code></pre></div>';
        }, $markdown);

        // 2. Inline code
        $markdown = preg_replace('/`([^`]+)`/', '<code class="wiki-inline-code">$1</code>', $markdown);

        // 3. Callouts
        $markdown = preg_replace_callback('/^&gt; \[!(DICA|TIP)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return '<div class="wiki-callout wiki-callout-tip"><div class="callout-header"><i class="fa-solid fa-lightbulb"></i> Dica</div><div class="callout-body">' . $text . '</div></div>';
        }, $markdown);

        $markdown = preg_replace_callback('/^&gt; \[!(AVISO|WARNING|ATENCAO)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return '<div class="wiki-callout wiki-callout-warning"><div class="callout-header"><i class="fa-solid fa-triangle-exclamation"></i> Atenção</div><div class="callout-body">' . $text . '</div></div>';
        }, $markdown);

        $markdown = preg_replace_callback('/^&gt; \[!(INFO|NOTE)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return '<div class="wiki-callout wiki-callout-info"><div class="callout-header"><i class="fa-solid fa-circle-info"></i> Informação</div><div class="callout-body">' . $text . '</div></div>';
        }, $markdown);

        // Blockquotes normais
        $markdown = preg_replace('/^&gt; (.*)$/m', '<blockquote class="wiki-quote">$1</blockquote>', $markdown);

        // 4. Headers com IDs automáticos
        $markdown = preg_replace_callback('/^(#{1,4})\s+(.+)$/m', function ($matches) use (&$headings) {
            $level = strlen($matches[1]);
            $title = trim($matches[2]);
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $title)));
            $slug = trim($slug, '-');
            
            if ($level === 2 || $level === 3) {
                $headings[] = [
                    'level' => $level,
                    'title' => $title,
                    'id'    => $slug
                ];
            }
            return "<h{$level} id=\"{$slug}\" class=\"wiki-h{$level}\">{$title}</h{$level}>";
        }, $markdown);

        // 5. Negrito e Itálico
        $markdown = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $markdown);
        $markdown = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $markdown);

        // 6. Links
        $markdown = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s]+)\)/', '<a href="$2" target="_blank" rel="noopener" class="wiki-link">$1 <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>', $markdown);

        // 7. Parágrafos
        $paragraphs = explode("\n\n", $markdown);
        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            if (preg_match('/^<(h[1-6]|div|blockquote|table|pre|ul|ol)/', $para)) {
                $html .= $para . "\n\n";
            } else {
                $html .= '<p class="wiki-p">' . nl2br($para) . "</p>\n\n";
            }
        }

        return $html;
    }
}