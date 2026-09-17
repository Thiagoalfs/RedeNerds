<?php
/**
 * admin/api/wiki/imagem_upload.php
 * Endpoint assíncrono para upload de imagens direto do editor de artigos da Wiki.
 */

ob_start();
require_once __DIR__ . "/../../sessao.php";

$configPaths = [
    __DIR__ . "/../../../../config.php",
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        require_once $cp;
        break;
    }
}
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "erro" => "Método não permitido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$fileField = null;
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = $_FILES['imagem'];
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = $_FILES['file'];
} elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = $_FILES['image'];
}

if (!$fileField) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Nenhum arquivo de imagem enviado."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($fileField['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Erro no envio do arquivo (código: " . $fileField['error'] . ")."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Limite: 8MB
$maxSize = 8 * 1024 * 1024;
if ($fileField['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "A imagem excede o tamanho máximo de 8MB."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Valida MIME Type real
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fileField['tmp_name']);
finfo_close($finfo);

$tiposPermitidos = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($tiposPermitidos[$mime])) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Formato de arquivo inválido. Permitidos: JPG, PNG, WEBP e GIF."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Determina diretório físico
$candidatos = [
    realpath(__DIR__ . '/../../../') . '/assets/images/wiki/',
    realpath(__DIR__ . '/../../../../') . '/assets/images/wiki/',
    realpath(__DIR__ . '/../../') . '/assets/images/wiki/',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/images/wiki/'
];

$dirFisica = null;
foreach ($candidatos as $dir) {
    if (!empty($dir) && is_dir($dir)) {
        $dirFisica = rtrim($dir, '/\\') . '/';
        break;
    }
}

if (!$dirFisica) {
    $dirFisica = realpath(__DIR__ . '/../../../') . '/assets/images/wiki/';
    if (!is_dir($dirFisica)) {
        @mkdir($dirFisica, 0755, true);
    }
}

$nomeOriginal = pathinfo($fileField['name'], PATHINFO_FILENAME);
$slugOriginal = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($nomeOriginal));
$slugOriginal = substr(trim($slugOriginal, '-'), 0, 40) ?: 'wiki_img';

$novoNome = $slugOriginal . '_' . bin2hex(random_bytes(6));
$destinoFinal = null;
$urlPublica = null;

// Se for GIF animado, preserva como GIF
if ($mime === 'image/gif') {
    $destinoFinal = $dirFisica . $novoNome . '.gif';
    if (!move_uploaded_file($fileField['tmp_name'], $destinoFinal)) {
        http_response_code(500);
        echo json_encode(["success" => false, "erro" => "Falha ao salvar a imagem no servidor."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $urlPublica = '/assets/images/wiki/' . $novoNome . '.gif';
} else {
    // Converte para WebP para otimização máxima
    $destinoWebp = $dirFisica . $novoNome . '.webp';
    $conteudo = file_get_contents($fileField['tmp_name']);
    $img = @imagecreatefromstring($conteudo);

    if ($img !== false && function_exists('imagewebp')) {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        imagewebp($img, $destinoWebp, 85);
        imagedestroy($img);
        $urlPublica = '/assets/images/wiki/' . $novoNome . '.webp';
    } else {
        // Fallback: move original com a extensão correta
        $ext = $tiposPermitidos[$mime];
        $destinoOriginal = $dirFisica . $novoNome . '.' . $ext;
        if (!move_uploaded_file($fileField['tmp_name'], $destinoOriginal)) {
            http_response_code(500);
            echo json_encode(["success" => false, "erro" => "Falha ao salvar a imagem no servidor."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $urlPublica = '/assets/images/wiki/' . $novoNome . '.' . $ext;
    }
}

echo json_encode([
    "success"  => true,
    "url"      => $urlPublica,
    "filename" => $nomeOriginal,
    "mensagem" => "Imagem enviada com sucesso!"
], JSON_UNESCAPED_UNICODE);
