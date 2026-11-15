<?php
/**
 * Shared image upload utilities for admin modules (directory lookup, MIME detection, WebP conversion, cleanup).
 */

function adminUploadDirFisica(string $publicDir): string
{
    $rel = '/' . trim($publicDir, '/') . '/';
    $adminRoot = realpath(__DIR__ . '/../../');
    $candidatos = [
        ($adminRoot ?: dirname(__DIR__, 2)) . $rel,
        (realpath(__DIR__ . '/../../../') ?: dirname(__DIR__, 3)) . '/public_html' . $rel,
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . $rel,
    ];

    foreach ($candidatos as $dir) {
        if ($dir !== $rel && is_dir($dir)) {
            return rtrim($dir, '/\\') . '/';
        }
    }

    $fallback = ($adminRoot ?: dirname(__DIR__, 2)) . $rel;
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return rtrim($fallback, '/\\') . '/';
}

function adminObterMimeImagem(string $caminho, ?string $fallbackMime = null): string
{
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $caminho);
            if (PHP_VERSION_ID < 80500) {
                @finfo_close($finfo);
            }
            if (!empty($mime)) {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($caminho);
        if (!empty($mime)) {
            return strtolower($mime);
        }
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($caminho);
        if (!empty($info['mime'])) {
            return strtolower($info['mime']);
        }
    }

    return $fallbackMime ? strtolower($fallbackMime) : 'application/octet-stream';
}

function adminConverterParaWebp(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 88): bool
{
    @ini_set('memory_limit', '256M');
    $imagem = null;

    try {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                if (function_exists('imagecreatefromjpeg')) $imagem = @imagecreatefromjpeg($caminhoOrigem);
                break;
            case 'image/png':
            case 'image/x-png':
                if (function_exists('imagecreatefrompng')) $imagem = @imagecreatefrompng($caminhoOrigem);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) $imagem = @imagecreatefromwebp($caminhoOrigem);
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) $imagem = @imagecreatefromgif($caminhoOrigem);
                break;
            case 'image/avif':
                if (function_exists('imagecreatefromavif')) $imagem = @imagecreatefromavif($caminhoOrigem);
                break;
        }

        if (!$imagem && function_exists('imagecreatefromstring')) {
            $conteudo = @file_get_contents($caminhoOrigem);
            if ($conteudo !== false) {
                $imagem = @imagecreatefromstring($conteudo);
            }
        }

        if (!$imagem) {
            return false;
        }

        if (function_exists('imageistruecolor') && !imageistruecolor($imagem) && function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($imagem);
        }
        if (function_exists('imagealphablending')) @imagealphablending($imagem, true);
        if (function_exists('imagesavealpha')) @imagesavealpha($imagem, true);

        $sucesso = @imagewebp($imagem, $caminhoDestino, $qualidade);
        if (PHP_VERSION_ID < 80000 && is_resource($imagem)) {
            @imagedestroy($imagem);
        }
        return (bool)$sucesso;
    } catch (\Throwable $e) {
        error_log("Erro em adminConverterParaWebp: " . $e->getMessage());
        return false;
    }
}

function adminApagarUploadAntigo(?string $atual, string $nova, string $publicDir): void
{
    if ($atual && strpos($atual, $publicDir) === 0 && $atual !== $nova) {
        $antigoCaminhoFisico = adminUploadDirFisica($publicDir) . basename($atual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}
