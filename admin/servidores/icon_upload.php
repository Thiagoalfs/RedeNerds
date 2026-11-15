<?php

require_once __DIR__ . '/../includes/image_upload_helper.php';

define('ICON_UPLOAD_DIR_PUBLICA', '/assets/servidores/icons/');

function getIconUploadDirFisica(): string
{
    return adminUploadDirFisica(ICON_UPLOAD_DIR_PUBLICA);
}

function obterMimeIcone(string $caminho, ?string $fallbackMime = null): string
{
    return adminObterMimeImagem($caminho, $fallbackMime);
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
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        error_log("icon_upload: falha ao criar diretório '{$uploadDir}'");
        return [$iconeAtual, "Diretório de ícones sem permissão de criação/escrita no servidor."];
    }
    if (!is_writable($uploadDir)) {
        error_log("icon_upload: diretório sem permissão de escrita '{$uploadDir}'");
        return [$iconeAtual, "Diretório de ícones sem permissão de escrita no servidor."];
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

function converterParaWebpIcone(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 85): bool
{
    return adminConverterParaWebp($caminhoOrigem, $caminhoDestino, $mime, $qualidade);
}

function apagarIconeAntigoSeForUpload(?string $iconeAtual, string $iconeNovo): void
{
    adminApagarUploadAntigo($iconeAtual, $iconeNovo, ICON_UPLOAD_DIR_PUBLICA);
}

/**
 * Retorna o tipo de ícone com base no valor salvo, para decidir como exibir no HTML.
 * 'fa'  -> classe FontAwesome (ex: "fa-solid fa-skull")
 * 'img' -> upload próprio ou link externo (renderizar como <img>)
 *
 * Lógica unificada com api/servidores_api.php e protegida contra
 * redeclaração caso ambos os arquivos sejam carregados juntos.
 */
if (!function_exists('tipoDoIcone')) {
    function tipoDoIcone(?string $icone): string
    {
        if (!$icone) {
            return 'fa';
        }
        $iconeTrim = trim($icone);
        if (strpos($iconeTrim, ICON_UPLOAD_DIR_PUBLICA) === 0
            || strpos($iconeTrim, '/assets/') === 0
            || preg_match('#^https?://#i', $iconeTrim)
            || preg_match('/\.(png|jpe?g|webp|gif|svg)(\?.*)?$/i', $iconeTrim)) {
            return 'img';
        }
        return 'fa';
    }
}

