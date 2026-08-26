<?php

define('BG_UPLOAD_DIR_PUBLICA', '/assets/servidores/');

/**
 * Retorna o caminho físico do diretório de assets/servidores.
 */
function getBgUploadDirFisica(): string
{
    $candidatos = [
        realpath(__DIR__ . '/../') . '/assets/servidores/',
        realpath(__DIR__ . '/../../') . '/public_html/assets/servidores/',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/servidores/'
    ];

    foreach ($candidatos as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            return $dir;
        }
    }

    $fallback = realpath(__DIR__ . '/../') . '/assets/servidores/';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return $fallback;
}

/**
 * Processa o upload de imagem de fundo para o servidor.
 * Salva fisicamente em assets/servidores/{slug}.webp
 * e retorna o caminho relativo para o banco (/assets/servidores/{slug}.webp).
 *
 * @param string $serverSlug Slug do servidor (ex: potatonerds, nerddead)
 * @param string|null $bgAtual Valor atual no banco (edição)
 * @return array{0: string|null, 1: string|null} [$caminhoParaSalvar, $mensagemDeErro]
 */
function processarBgServidor(string $serverSlug, ?string $bgAtual = null): array
{
    $remover = isset($_POST['remover_bg']) && $_POST['remover_bg'] === '1';
    if ($remover) {
        apagarBgAntigoSeForUpload($bgAtual, '');
        return [null, null];
    }

    $temArquivo = isset($_FILES['bg_upload']) && $_FILES['bg_upload']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($temArquivo) {
        return processarUploadBgServidor($serverSlug, $bgAtual);
    }

    $url = trim($_POST['bg_url'] ?? '');
    if ($url !== '') {
        if (strlen($url) > 500) {
            return [$bgAtual, "O link da imagem de fundo é muito longo (máx. 500 caracteres)."];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^/assets/#i', $url)) {
            return [$bgAtual, "Link da imagem de fundo inválido."];
        }
        return [$url, null];
    }

    // Mantém o valor atual se nada foi alterado
    return [$bgAtual, null];
}

/**
 * Processa o arquivo enviado, converte para WebP e salva como {slug}.webp.
 */
function processarUploadBgServidor(string $serverSlug, ?string $bgAtual = null): array
{
    $arquivo = $_FILES['bg_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return [$bgAtual, "Erro ao enviar a imagem de fundo (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 10MB (wallpapers em alta resolução)
    $tamanhoMaximo = 10 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$bgAtual, "A imagem de fundo deve ter no máximo 10MB."];
    }

    $tiposPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $tiposPermitidos)) {
        return [$bgAtual, "Formato de imagem de fundo inválido. Use JPG, PNG, WEBP ou GIF."];
    }

    $diretorioFisico = getBgUploadDirFisica();
    if (!is_dir($diretorioFisico)) {
        @mkdir($diretorioFisico, 0755, true);
    }

    // Nome baseado no slug do servidor: ex: potatonerds.webp
    $cleanSlug = preg_replace('/[^a-z0-9_-]/', '', strtolower($serverSlug));
    if (empty($cleanSlug)) {
        $cleanSlug = 'servidor_' . time();
    }
    $nomeArquivo = $cleanSlug . '.webp';
    $caminhoDestino = $diretorioFisico . $nomeArquivo;

    // Converte e salva para WebP
    if (!converterImagemParaWebp($arquivo['tmp_name'], $caminhoDestino, $mime, 85)) {
        return [$bgAtual, "Erro ao processar e converter a imagem de fundo para WebP."];
    }

    $caminhoBanco = BG_UPLOAD_DIR_PUBLICA . $nomeArquivo;

    return [$caminhoBanco, null];
}

/**
 * Converte a imagem enviada para WebP preservando qualidade.
 */
function converterImagemParaWebp(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 85): bool
{
    switch ($mime) {
        case 'image/jpeg':
            $imagem = @imagecreatefromjpeg($caminhoOrigem);
            break;
        case 'image/png':
            $imagem = @imagecreatefrompng($caminhoOrigem);
            if ($imagem) {
                imagepalettetotruecolor($imagem);
                imagealphablending($imagem, true);
                imagesavealpha($imagem, true);
            }
            break;
        case 'image/webp':
            $imagem = @imagecreatefromwebp($caminhoOrigem);
            break;
        case 'image/gif':
            $imagem = @imagecreatefromgif($caminhoOrigem);
            break;
        default:
            return false;
    }

    if (!$imagem) {
        return false;
    }

    $sucesso = imagewebp($imagem, $caminhoDestino, $qualidade);
    imagedestroy($imagem);

    return $sucesso;
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
