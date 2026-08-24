<?php

define('ICON_UPLOAD_DIR_FISICA', realpath(__DIR__ . '/../../') . '/public_html/assets/servidores/icons/');
define('ICON_UPLOAD_DIR_PUBLICA', '/assets/servidores/icons/');

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
    $fa = trim($_POST['icon_fa'] ?? '');
    if ($fa !== '') {
        if (strlen($fa) > 100) {
            return [$iconeAtual, "A classe do ícone é muito longa (máx. 100 caracteres)."];
        }
        apagarIconeAntigoSeForUpload($iconeAtual, $fa);
        return [$fa, null];
    }

    $temArquivo = isset($_FILES['icon_upload']) && $_FILES['icon_upload']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($temArquivo) {
        return processarUploadIcone($iconeAtual);
    }

    $url = trim($_POST['icon_url'] ?? '');
    if ($url !== '') {
        return validarUrlIcone($url, $iconeAtual);
    }

    // Nada foi enviado: mantém o valor atual (usado na edição)
    return [$iconeAtual, null];
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

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return [$iconeAtual, "Link do ícone inválido. Use uma URL começando com http:// ou https://."];
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
        return [$iconeAtual, "Erro ao enviar o arquivo (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 2MB (ícone não precisa ser grande)
    $tamanhoMaximo = 2 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$iconeAtual, "A imagem do ícone deve ter no máximo 2MB."];
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
        return [$iconeAtual, "Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF."];
    }

    if (!is_dir(ICON_UPLOAD_DIR_FISICA)) {
        mkdir(ICON_UPLOAD_DIR_FISICA, 0755, true);
    }

    do {
        $nomeArquivo = bin2hex(random_bytes(16)) . '.webp';
        $caminhoDestino = ICON_UPLOAD_DIR_FISICA . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    if (!converterParaWebpIcone($arquivo['tmp_name'], $caminhoDestino, $mime)) {
        return [$iconeAtual, "Erro ao processar e converter a imagem para WebP."];
    }

    $caminhoPublico = ICON_UPLOAD_DIR_PUBLICA . $nomeArquivo;

    apagarIconeAntigoSeForUpload($iconeAtual, $caminhoPublico);

    return [$caminhoPublico, null];
}

/**
 * Converte qualquer imagem compatível para o formato WebP preservando transparência se houver.
 */
function converterParaWebpIcone(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 85): bool
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
        $antigoCaminhoFisico = ICON_UPLOAD_DIR_FISICA . basename($iconeAtual);
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
