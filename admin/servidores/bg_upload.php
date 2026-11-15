<?php

require_once __DIR__ . '/../includes/image_upload_helper.php';

define('BG_UPLOAD_DIR_PUBLICA', '/assets/servidores/');

function getBgUploadDirFisica(): string
{
    return adminUploadDirFisica(BG_UPLOAD_DIR_PUBLICA);
}

function obterMimeBg(string $caminho, ?string $fallbackMime = null): string
{
    return adminObterMimeImagem($caminho, $fallbackMime);
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
    if (!is_dir($diretorioFisico) && !@mkdir($diretorioFisico, 0775, true) && !is_dir($diretorioFisico)) {
        error_log("bg_upload: falha ao criar diretório '{$diretorioFisico}'");
        return [$bgAtual, "Diretório de imagens de fundo sem permissão de criação/escrita no servidor."];
    }
    if (!is_writable($diretorioFisico)) {
        error_log("bg_upload: diretório sem permissão de escrita '{$diretorioFisico}'");
        return [$bgAtual, "Diretório de imagens de fundo sem permissão de escrita no servidor."];
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

function converterImagemParaWebp(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 88): bool
{
    return adminConverterParaWebp($caminhoOrigem, $caminhoDestino, $mime, $qualidade);
}

function apagarBgAntigoSeForUpload(?string $bgAtual, string $bgNovo): void
{
    adminApagarUploadAntigo($bgAtual, $bgNovo, BG_UPLOAD_DIR_PUBLICA);
}
