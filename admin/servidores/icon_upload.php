<?php

define('ICON_UPLOAD_DIR_PUBLICA', '/assets/servidores/icons/');

/**
 * Retorna o caminho físico do diretório de assets/servidores/icons.
 */
function getIconUploadDirFisica(): string
{
    $candidatos = [
        realpath(__DIR__ . '/../../') . '/assets/servidores/icons/',
        realpath(__DIR__ . '/../') . '/assets/servidores/icons/',
        realpath(__DIR__ . '/../../../') . '/public_html/assets/servidores/icons/',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/servidores/icons/'
    ];

    foreach ($candidatos as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            return rtrim($dir, '/\\') . '/';
        }
    }

    $fallback = (realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2)) . '/assets/servidores/icons/';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return rtrim($fallback, '/\\') . '/';
}

/**
 * Obtém o MIME Type de forma segura sem quebrar se extensões estiverem ausentes.
 */
function obterMimeIcone(string $caminho, ?string $fallbackMime = null): string
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
 * Ponto de entrada único do formulário de ícone.
 *
 * Aceita 3 formatos, na seguinte ordem de prioridade:
 *  1. Classe FontAwesome digitada (ex: "fa-solid fa-skull")
 *  2. Upload de imagem própria (convertida para WebP)
 *  3. Link externo de imagem
 *
 * @param string|null $iconeAtual Valor já existente (edição).
 * @return array{0: string|null, 1: string|null} [$valorParaSalvar, $mensagemDeErro]
 */
function processarIcone(?string $iconeAtual = null): array
{
    try {
        $fa = trim($_POST['icon_fa'] ?? '');
        if ($fa !== '') {
            if (strlen($fa) > 100) {
                return [$iconeAtual, "A classe do ícone é muito longa (máx. 100 caracteres)."];
            }
            apagarIconeAntigoSeForUpload($iconeAtual, $fa);
            return [$fa, null];
        }

        $temArquivo = isset($_FILES['icon_upload']) && is_array($_FILES['icon_upload']) && ($_FILES['icon_upload']['error'] !== UPLOAD_ERR_NO_FILE);
        if ($temArquivo) {
            return processarUploadIcone($iconeAtual);
        }

        $url = trim($_POST['icon_url'] ?? '');
        if ($url !== '') {
            return validarUrlIcone($url, $iconeAtual);
        }

        // Nada foi enviado: mantém o valor atual (usado na edição)
        return [$iconeAtual, null];
    } catch (\Throwable $e) {
        error_log("Erro em processarIcone: " . $e->getMessage());
        return [$iconeAtual, "Erro ao processar ícone: " . $e->getMessage()];
    }
}

/**
 * Valida uma URL digitada pelo usuário e, se o ícone atual era um upload
 * próprio, apaga o arquivo físico antigo (agora órfão).
 */
function validarUrlIcone(string $url, ?string $iconeAtual): array
{
    if (strlen($url) > 500) {
        return [$iconeAtual, "O link do ícone é muito longo (máx. 500 caracteres)."];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^/assets/#i', $url)) {
        return [$iconeAtual, "Link do ícone inválido."];
    }

    apagarIconeAntigoSeForUpload($iconeAtual, $url);

    return [$url, null];
}

/**
 * Processa o upload de $_FILES['icon_upload'] e converte para WebP.
 */
function processarUploadIcone(?string $iconeAtual = null): array
{
    $arquivo = $_FILES['icon_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE   => "O ícone excede o limite permitido pelo servidor (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "O ícone excede o limite do formulário.",
            UPLOAD_ERR_PARTIAL    => "O upload do ícone foi feito parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "Pasta temporária ausente no servidor.",
            UPLOAD_ERR_CANT_WRITE => "Falha ao gravar ícone em disco.",
            UPLOAD_ERR_EXTENSION  => "Upload interrompido por extensão do PHP."
        ];
        return [$iconeAtual, $erros[$arquivo['error']] ?? "Erro ao enviar o ícone (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 5MB
    $tamanhoMaximo = 5 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$iconeAtual, "A imagem do ícone deve ter no máximo 5MB."];
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

    $mime = obterMimeIcone($arquivo['tmp_name'], $arquivo['type'] ?? null);

    if (!in_array($mime, $tiposPermitidos, true)) {
        return [$iconeAtual, "Formato de imagem inválido ({$mime}). Use JPG, PNG, WEBP ou GIF."];
    }

    $uploadDir = getIconUploadDirFisica();
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    // Tenta conversão para WebP se GD estiver disponível
    if (function_exists('imagewebp')) {
        do {
            $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.webp';
            $caminhoDestino = $uploadDir . $nomeArquivo;
        } while (file_exists($caminhoDestino));

        if (converterParaWebpIcone($arquivo['tmp_name'], $caminhoDestino, $mime)) {
            $caminhoPublico = ICON_UPLOAD_DIR_PUBLICA . $nomeArquivo;
            apagarIconeAntigoSeForUpload($iconeAtual, $caminhoPublico);
            return [$caminhoPublico, null];
        }
    }

    // Fallback caso GD WebP não esteja disponível ou conversão falhe
    $extOriginal = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION) ?: 'webp');
    if (!in_array($extOriginal, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $extOriginal = 'webp';
    }

    do {
        $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.' . $extOriginal;
        $caminhoDestino = $uploadDir . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    if (@move_uploaded_file($arquivo['tmp_name'], $caminhoDestino) || @copy($arquivo['tmp_name'], $caminhoDestino)) {
        $caminhoPublico = ICON_UPLOAD_DIR_PUBLICA . $nomeArquivo;
        apagarIconeAntigoSeForUpload($iconeAtual, $caminhoPublico);
        return [$caminhoPublico, null];
    }

    return [$iconeAtual, "Não foi possível gravar a imagem do ícone no diretório do servidor."];
}

/**
 * Converte qualquer imagem compatível para o formato WebP preservando transparência se houver.
 */
function converterParaWebpIcone(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 85): bool
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
        error_log("Erro em converterParaWebpIcone: " . $e->getMessage());
        return false;
    }
}

/**
 * Apaga o arquivo físico do ícone antigo quando ele era um upload próprio
 * (dentro de ICON_UPLOAD_DIR_PUBLICA) e está sendo substituído por outra coisa.
 * Nunca apaga classes FontAwesome nem links externos.
 */
function apagarIconeAntigoSeForUpload(?string $iconeAtual, string $iconeNovo): void
{
    if (
        $iconeAtual
        && strpos($iconeAtual, ICON_UPLOAD_DIR_PUBLICA) === 0
        && $iconeAtual !== $iconeNovo
    ) {
        $antigoCaminhoFisico = getIconUploadDirFisica() . basename($iconeAtual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}

/**
 * Retorna o tipo de ícone com base no valor salvo, para decidir como exibir no HTML.
 * 'fa'  -> classe FontAwesome (ex: "fa-solid fa-skull")
 * 'img' -> upload próprio ou link externo (renderizar como <img>)
 */
function tipoDoIcone(?string $icone): string
{
    if (!$icone) {
        return 'fa';
    }
    if (strpos($icone, ICON_UPLOAD_DIR_PUBLICA) === 0 || preg_match('#^https?://#i', $icone)) {
        return 'img';
    }
    return 'fa';
}

