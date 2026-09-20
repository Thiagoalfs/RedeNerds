<?php
/**
 * wiki_helper.php - Funções e Utilitários da Wiki (Rede Nerds)
 */

if (!function_exists('isWikiHabilitada')) {
    function isWikiHabilitada(PDO $pdo): bool {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_config (
                chave VARCHAR(50) PRIMARY KEY,
                valor TEXT,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $stmt = $pdo->prepare("SELECT valor FROM site_config WHERE chave = 'wiki_habilitada' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val === false) {
                $pdo->exec("INSERT INTO site_config (chave, valor) VALUES ('wiki_habilitada', '1') ON DUPLICATE KEY UPDATE valor = valor");
                return true;
            }
            return ((string)$val === '1' || strtolower((string)$val) === 'true');
        } catch (Exception $e) {
            return true;
        }
    }
}

if (!function_exists('setWikiHabilitada')) {
    function setWikiHabilitada(PDO $pdo, bool $habilitada): bool {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_config (
                chave VARCHAR(50) PRIMARY KEY,
                valor TEXT,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            $valStr = $habilitada ? '1' : '0';
            $stmt = $pdo->prepare("
                INSERT INTO site_config (chave, valor) 
                VALUES ('wiki_habilitada', :val) 
                ON DUPLICATE KEY UPDATE valor = :val
            ");
            return $stmt->execute([':val' => $valStr]);
        } catch (Exception $e) {
            return false;
        }
    }
}

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

        // 1. Code blocks com botão de copiar interativo
        $markdown = preg_replace_callback('/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/m', function ($matches) {
            $lang = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $code = $matches[2];
            $displayLang = $lang ? strtoupper($lang) : 'CÓDIGO';
            return "\n\n" . '<div class="wiki-code-block">
                <div class="code-header">
                    <span class="code-lang"><i class="fa-solid fa-terminal me-1"></i> ' . $displayLang . '</span>
                    <button type="button" class="btn-copy-code" onclick="copiarCodigoWiki(this)" title="Copiar código">
                        <i class="fa-regular fa-copy"></i> Copiar
                    </button>
                </div>
                <pre><code class="wiki-code-content">' . $code . '</code></pre>
            </div>' . "\n\n";
        }, $markdown);

        // 2. Inline code
        $markdown = preg_replace('/`([^`]+)`/', '<code class="wiki-inline-code">$1</code>', $markdown);

        // 3. Callouts Gamer Avançados
        // 3.1 DICA / TIP (Verde)
        $markdown = preg_replace_callback('/^&gt; \[!(DICA|TIP)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return "\n\n" . '<div class="wiki-callout wiki-callout-tip">
                <div class="callout-header"><i class="fa-solid fa-lightbulb"></i> Dica de Pro</div>
                <div class="callout-body">' . $text . '</div>
            </div>' . "\n\n";
        }, $markdown);

        // 3.2 AVISO / WARNING / ATENCAO (Amarelo / Laranja)
        $markdown = preg_replace_callback('/^&gt; \[!(AVISO|WARNING|ATENCAO)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return "\n\n" . '<div class="wiki-callout wiki-callout-warning">
                <div class="callout-header"><i class="fa-solid fa-triangle-exclamation"></i> Atenção</div>
                <div class="callout-body">' . $text . '</div>
            </div>' . "\n\n";
        }, $markdown);

        // 3.3 PERIGO / DANGER / BANIDO (Vermelho)
        $markdown = preg_replace_callback('/^&gt; \[!(PERIGO|DANGER|BANIDO|CAUTION)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return "\n\n" . '<div class="wiki-callout wiki-callout-danger">
                <div class="callout-header"><i class="fa-solid fa-shield-halved"></i> Cuidado / Regra Importante</div>
                <div class="callout-body">' . $text . '</div>
            </div>' . "\n\n";
        }, $markdown);

        // 3.4 COMANDO IN-GAME / COMMAND (Minecraft Terminal com botão de cópia)
        $markdown = preg_replace_callback('/^&gt; \[!(COMANDO|COMMAND)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            $cmdClean = trim(strip_tags(str_replace('<br />', '', $text)));
            return "\n\n" . '<div class="wiki-callout wiki-callout-command">
                <div class="callout-header">
                    <span><i class="fa-solid fa-terminal me-1"></i> Comando In-game</span>
                    <button type="button" class="btn-copy-code" onclick="copiarTextoDireto(this, \'' . htmlspecialchars(addslashes($cmdClean), ENT_QUOTES, 'UTF-8') . '\')">
                        <i class="fa-regular fa-copy"></i> Copiar
                    </button>
                </div>
                <div class="callout-body font-monospace">' . $text . '</div>
            </div>' . "\n\n";
        }, $markdown);

        // 3.5 INFO / NOTE (Azul)
        $markdown = preg_replace_callback('/^&gt; \[!(INFO|NOTE)\]\s*\n((?:&gt; .*\n?)+)/m', function ($matches) {
            $text = preg_replace('/^&gt; ?/m', '', trim($matches[2]));
            return "\n\n" . '<div class="wiki-callout wiki-callout-info">
                <div class="callout-header"><i class="fa-solid fa-circle-info"></i> Informação</div>
                <div class="callout-body">' . $text . '</div>
            </div>' . "\n\n";
        }, $markdown);

        // Blockquotes normais
        $markdown = preg_replace('/^&gt; (.*)$/m', "\n\n" . '<blockquote class="wiki-quote">$1</blockquote>' . "\n\n", $markdown);

        // 4. Headers com IDs automáticos para sumário
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
            return "\n\n<h{$level} id=\"{$slug}\" class=\"wiki-h{$level}\">{$title}</h{$level}>\n\n";
        }, $markdown);

        // 5. Imagens com suporte a Zoom / Lightbox
        $markdown = preg_replace_callback('/!\[(.*?)\]\((https?:\/\/[^\s\)]+|\/[^\s\)]+)\)/', function($matches) {
            $alt = $matches[1];
            $src = $matches[2];
            $caption = !empty($alt) ? '<figcaption class="wiki-figcaption">' . $alt . '</figcaption>' : '';
            return "\n\n" . '<figure class="wiki-figure">
                <img src="' . $src . '" alt="' . $alt . '" class="wiki-zoomable-img" loading="lazy" onclick="abrirLightboxWiki(this.src, this.alt)" title="Clique para ampliar">
                ' . $caption . '
            </figure>' . "\n\n";
        }, $markdown);

        // 6. Negrito e Itálico
        $markdown = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $markdown);
        $markdown = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $markdown);

        // 7. Links externos e internos
        $markdown = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s\)]+)\)/', '<a href="$2" target="_blank" rel="noopener" class="wiki-link">$1 <i class="fa-solid fa-arrow-up-right-from-square text-xs" style="font-size: 0.75rem;"></i></a>', $markdown);
        $markdown = preg_replace('/\[(.+?)\]\((\/[^\s\)]+)\)/', '<a href="$2" class="wiki-link">$1</a>', $markdown);

        // 8. Tabelas em Markdown
        $markdown = preg_replace_callback('/((?:^\|.+\|\r?\n)+)/m', function($matches) {
            $tableText = trim($matches[1]);
            $lines = explode("\n", str_replace("\r", "", $tableText));
            if (count($lines) < 2) return $tableText;

            $htmlTable = '<div class="table-responsive my-3"><table class="table wiki-table align-middle table-hover mb-0">';
            $isHeader = true;

            foreach ($lines as $i => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Linha divisória |---|---|
                if (preg_match('/^\|[\s\-:]+\|\s*$/', $line) || preg_match('/^\|(?:\s*:?-+:?\s*\|)+$/', $line)) {
                    $isHeader = false;
                    continue;
                }

                $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn($c) => $c !== ''));
                if (empty($cells)) continue;

                if ($isHeader && $i === 0) {
                    $htmlTable .= '<thead><tr>';
                    foreach ($cells as $cell) {
                        $htmlTable .= '<th>' . $cell . '</th>';
                    }
                    $htmlTable .= '</tr></thead><tbody>';
                } else {
                    $htmlTable .= '<tr>';
                    foreach ($cells as $cell) {
                        $htmlTable .= '<td>' . $cell . '</td>';
                    }
                    $htmlTable .= '</tr>';
                }
            }

            $htmlTable .= '</tbody></table></div>';
            return $htmlTable;
        }, $markdown);

        // 9. Parágrafos
        $paragraphs = explode("\n\n", $markdown);
        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            if (preg_match('/^<(h[1-6]|div|blockquote|table|pre|ul|ol|figure)/', $para)) {
                $html .= $para . "\n\n";
            } else {
                $html .= '<p class="wiki-p">' . nl2br($para) . "</p>\n\n";
            }
        }

        return $html;
    }
}