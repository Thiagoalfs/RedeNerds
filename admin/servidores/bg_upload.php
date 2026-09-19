<?php

define('BG_UPLOAD_DIR_PUBLICA', '/assets/servidores/');

/**
 * Retorna o caminho físico do diretório de assets/servidores.
 */
function getBgUploadDirFisica(): string
{
    $candidatos = [
        realpath(__DIR__ . '/../../') . '/assets/servidores/',
        realpath(__DIR__ . '/../') . '/assets/servidores/',
        realpath(__DIR__ . '/../../../') . '/public_html/assets/servidores/',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/servidores/'
    ];

    foreach ($candidatos as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            return rtrim($dir, '/\\') . '/';
        }
    }

    $fallback = (realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2)) . '/assets/servidores/';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return rtrim($fallback, '/\\') . '/';
}

/**
 * Obtém o MIME Type de forma segura sem quebrar se extensões estiverem ausentes.
 */
function obterMimeBg(string $caminho, ?string $fallbackMime = null): string
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

/**
 * Processa o upload de imagem de fundo para o servidor.
 * Salva fisicamente em assets/servidores/{hash}.webp
 * e retorna o caminho relativo para o banco (/assets/servidores/{hash}.webp).
 *
 * @param string|null $bgAtual Valor atual no banco (edição)
 * @return array{0: string|null, 1: string|null} [$caminhoParaSalvar, $mensagemDeErro]
 */
function processarBgServidor(?string $bgAtual = null): array
{
    try {
        $remover = isset($_POST['remover_bg']) && $_POST['remover_bg'] === '1';
        if ($remover) {
            apagarBgAntigoSeForUpload($bgAtual, '');
            return [null, null];
        }

        $temArquivo = isset($_FILES['bg_upload']) && is_array($_FILES['bg_upload']) && ($_FILES['bg_upload']['error'] !== UPLOAD_ERR_NO_FILE);
        if ($temArquivo) {
            return processarUploadBgServidor($bgAtual);
        }

        $url = trim($_POST['bg_url'] ?? '');
        if ($url !== '') {
            if (strlen($url) > 500) {
                return [$bgAtual, "O link da imagem de fundo é muito longo (máx. 500 caracteres)."];
            }
            if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^/assets/#i', $url)) {
                return [$bgAtual, "Link da imagem de fundo inválido."];
            }
            apagarBgAntigoSeForUpload($bgAtual, $url);
            return [$url, null];
        }

        return [$bgAtual, null];
    } catch (\Throwable $e) {
        error_log("Erro em processarBgServidor: " . $e->getMessage());
        return [$bgAtual, "Erro ao processar imagem de fundo: " . $e->getMessage()];
    }
}

/**
 * Processa o arquivo enviado, converte para WebP e salva com hash único.
 */
function processarUploadBgServidor(?string $bgAtual = null): array
{
    $arquivo = $_FILES['bg_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE   => "O arquivo excede o limite permitido pelo servidor (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "O arquivo excede o limite do formulário.",
            UPLOAD_ERR_PARTIAL    => "O upload foi feito parcialmente. Tente novamente.",
            UPLOAD_ERR_NO_TMP_DIR => "Pasta temporária ausente no servidor.",
            UPLOAD_ERR_CANT_WRITE => "Falha ao gravar arquivo em disco no servidor.",
            UPLOAD_ERR_EXTENSION  => "Upload interrompido por extensão do PHP."
        ];
        return [$bgAtual, $erros[$arquivo['error']] ?? "Erro no upload da imagem de fundo (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 15MB
    $tamanhoMaximo = 15 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$bgAtual, "A imagem de fundo deve ter no máximo 15MB."];
    }

    $tiposPermitidos = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp',
        'image/gif',
        'image/avif'
    ];

    $mime = obterMimeBg($arquivo['tmp_name'], $arquivo['type'] ?? null);

    if (!in_array($mime, $tiposPermitidos, true)) {
        return [$bgAtual, "Formato de imagem inválido ({$mime}). Use JPG, PNG, WEBP ou GIF."];
    }

    $diretorioFisico = getBgUploadDirFisica();
    if (!is_dir($diretorioFisico)) {
        @mkdir($diretorioFisico, 0755, true);
    }

    // Tenta conversão para WebP se GD estiver disponível
    if (function_exists('imagewebp')) {
        do {
            $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.webp';
            $caminhoDestino = $diretorioFisico . $nomeArquivo;
        } while (file_exists($caminhoDestino));

        if (converterImagemParaWebp($arquivo['tmp_name'], $caminhoDestino, $mime, 88)) {
            $caminhoBanco = BG_UPLOAD_DIR_PUBLICA . $nomeArquivo;
            apagarBgAntigoSeForUpload($bgAtual, $caminhoBanco);
            return [$caminhoBanco, null];
        }
    }

    // Fallback caso GD WebP não esteja disponível ou a conversão falhe: copia o arquivo original
    $extOriginal = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION) ?: 'webp');
    if (!in_array($extOriginal, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $extOriginal = 'webp';
    }

    do {
        $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.' . $extOriginal;
        $caminhoDestino = $diretorioFisico . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    if (@move_uploaded_file($arquivo['tmp_name'], $caminhoDestino) || @copy($arquivo['tmp_name'], $caminhoDestino)) {
        $caminhoBanco = BG_UPLOAD_DIR_PUBLICA . $nomeArquivo;
        apagarBgAntigoSeForUpload($bgAtual, $caminhoBanco);
        return [$caminhoBanco, null];
    }

    return [$bgAtual, "Não foi possível gravar a imagem de fundo no diretório do servidor."];
}

/**
 * Converte a imagem enviada para WebP preservando qualidade e transparência.
 */
function converterImagemParaWebp(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 88): bool
{
    @ini_set('memory_limit', '256M');

    $imagem = null;

    try {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $imagem = @imagecreatefromjpeg($caminhoOrigem);
                }
                break;
            case 'image/png':
            case 'image/x-png':
                if (function_exists('imagecreatefrompng')) {
                    $imagem = @imagecreatefrompng($caminhoOrigem);
                }
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $imagem = @imagecreatefromwebp($caminhoOrigem);
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $imagem = @imagecreatefromgif($caminhoOrigem);
                }
                break;
            case 'image/avif':
                if (function_exists('imagecreatefromavif')) {
                    $imagem = @imagecreatefromavif($caminhoOrigem);
                }
                break;
        }

        // Fallback genérico via string de bytes
        if (!$imagem && function_exists('imagecreatefromstring')) {
            $conteudo = @file_get_contents($caminhoOrigem);
            if ($conteudo !== false) {
                $imagem = @imagecreatefromstring($conteudo);
            }
        }

        if (!$imagem) {
            return false;
        }

        if (function_exists('imageistruecolor') && !imageistruecolor($imagem)) {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($imagem);
            }
        }
        if (function_exists('imagealphablending')) {
            @imagealphablending($imagem, true);
        }
        if (function_exists('imagesavealpha')) {
            @imagesavealpha($imagem, true);
        }

        $sucesso = @imagewebp($imagem, $caminhoDestino, $qualidade);

        if (PHP_VERSION_ID < 80000 && is_resource($imagem)) {
            @imagedestroy($imagem);
        }

        return (bool)$sucesso;
    } catch (\Throwable $e) {
        error_log("Erro em converterImagemParaWebp: " . $e->getMessage());
        return false;
    }
}

/**
 * Apaga o arquivo físico antigo caso necessário.
 */
function apagarBgAntigoSeForUpload(?string $bgAtual, string $bgNovo): void
{
    if (
        $bgAtual
        && strpos($bgAtual, BG_UPLOAD_DIR_PUBLICA) === 0
        && $bgAtual !== $bgNovo
    ) {
        $antigoCaminhoFisico = getBgUploadDirFisica() . basename($bgAtual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}
