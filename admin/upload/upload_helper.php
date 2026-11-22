<?php
require_once __DIR__ . '/../includes/image_upload_helper.php';

function uploadAssetsRootFisica(): string
{
    $root = realpath(__DIR__ . '/../../assets');
    if ($root && is_dir($root)) {
        return rtrim($root, '/\\') . '/';
    }
    return adminUploadDirFisica('/assets/');
}

function uploadBaseSiteUrl(): string
{
    return defined('SITE_URL') && !empty(SITE_URL) ? rtrim((string)SITE_URL, '/') : 'https://redenerds.com.br';
}

function uploadNormalizarPasta(string $pasta): ?string
{
    $p = trim(str_replace('\\', '/', $pasta), "/ \t\n\r\0\x0B");
    if ($p === '' || strlen($p) > 80 || str_contains($p, '..') || str_contains($p, "\0")) {
        return null;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*$/', $p)) {
        return null;
    }
    $primeiroSeg = strtolower(explode('/', $p, 2)[0]);
    if ($primeiroSeg === 'fonts') {
        return null;
    }
    return $p;
}

function uploadListarPastasAssets(): array
{
    $root = rtrim(uploadAssetsRootFisica(), '/\\');
    $pastas = [];
    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $entry) {
            if (!$entry->isDir()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($entry->getPathname(), strlen($root))), '/');
            $norm = uploadNormalizarPasta($rel);
            if ($norm !== null) {
                $pastas[] = $norm;
            }
        }
    }
    if (empty($pastas)) {
        $pastas = ['images', 'novidades', 'servidores'];
    }
    $pastas = array_values(array_unique($pastas));
    sort($pastas, SORT_NATURAL | SORT_FLAG_CASE);
    return $pastas;
}

function uploadListarImagensSite(?PDO $pdo, string $filtroPasta = '', string $busca = ''): array
{
    $registrados = [];
    if ($pdo instanceof PDO) {
        try {
            $rows = $pdo->query("
                SELECT id, nome, pasta, caminho_publico, tamanho_bytes, autor_nome, criado_em
                FROM site_uploads
                ORDER BY criado_em DESC, id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $registrados[$r['caminho_publico']] = $r;
            }
        } catch (Throwable $e) {
            error_log("Erro ao listar site_uploads: " . $e->getMessage());
        }
    }

    $itens = [];
    $root = rtrim(uploadAssetsRootFisica(), '/\\');
    $extPermitidas = ['webp', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'avif'];

    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extPermitidas, true)) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $posSlash = strrpos($rel, '/');
            if ($posSlash === false) {
                continue;
            }
            $pastaRel = substr($rel, 0, $posSlash);
            $normPasta = uploadNormalizarPasta($pastaRel);
            if ($normPasta === null) {
                continue;
            }

            $caminhoPublico = '/assets/' . $rel;
            $reg = $registrados[$caminhoPublico] ?? null;

            $itens[] = [
                'id' => $reg ? (int)$reg['id'] : null,
                'nome' => $reg ? (string)$reg['nome'] : $file->getFilename(),
                'pasta' => $normPasta,
                'caminho_publico' => $caminhoPublico,
                'tamanho_bytes' => $reg ? (int)$reg['tamanho_bytes'] : (int)$file->getSize(),
                'autor_nome' => $reg ? (string)$reg['autor_nome'] : 'Sistema',
                'criado_em' => $reg ? (string)$reg['criado_em'] : date('Y-m-d H:i:s', (int)$file->getMTime()),
                'registrado' => $reg !== null,
            ];
            unset($registrados[$caminhoPublico]);
        }
    }

    $itens = array_values(array_filter($itens, static function (array $item) use ($filtroPasta, $busca): bool {
        if ($filtroPasta !== '' && $item['pasta'] !== $filtroPasta) {
            return false;
        }
        if ($busca !== '') {
            $q = mb_strtolower($busca, 'UTF-8');
            $haystack = mb_strtolower($item['nome'] . ' ' . $item['caminho_publico'] . ' ' . $item['autor_nome'] . ' ' . $item['pasta'], 'UTF-8');
            if (!str_contains($haystack, $q)) {
                return false;
            }
        }
        return true;
    }));

    usort($itens, static function (array $a, array $b): int {
        return strcmp($b['criado_em'], $a['criado_em']);
    });

    return $itens;
}

function uploadFormatarTamanho(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
}
