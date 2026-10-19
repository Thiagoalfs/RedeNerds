<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../parceiros/parceiro_upload.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de parceiro inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM parceiros WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$parceiro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parceiro) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Parceiro não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$youtube = trim($_POST['youtube'] ?? '');
$tiktok = trim($_POST['tiktok'] ?? '');
$instagram = trim($_POST['instagram'] ?? '');
$twitch = trim($_POST['twitch'] ?? '');
$kick = trim($_POST['kick'] ?? '');
$ativo = !empty($_POST['ativo']) ? 1 : 0;

if (empty($nome)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O nome do parceiro é obrigatório."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($nome) > 100) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O nome do parceiro pode ter no máximo 100 caracteres."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normaliza URLs
$normalizarUrl = function(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return $url;
};

$youtube = $normalizarUrl($youtube);
$tiktok = $normalizarUrl($tiktok);
$instagram = $normalizarUrl($instagram);
$twitch = $normalizarUrl($twitch);
$kick = $normalizarUrl($kick);

// Processa upload ou remoção ou manutenção da foto
list($fotoSalvar, $erroFoto) = processarFotoParceiro($parceiro['foto'], $nome);

if ($erroFoto) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erroFoto], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $upd = $pdo->prepare("
        UPDATE parceiros 
        SET nome = :nome, foto = :foto, youtube = :youtube, tiktok = :tiktok, 
            instagram = :instagram, twitch = :twitch, kick = :kick, ativo = :ativo 
        WHERE id = :id
    ");
    $upd->execute([
        ':nome' => $nome,
        ':foto' => $fotoSalvar,
        ':youtube' => $youtube,
        ':tiktok' => $tiktok,
        ':instagram' => $instagram,
        ':twitch' => $twitch,
        ':kick' => $kick,
        ':ativo' => $ativo,
        ':id' => $id
    ]);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('parceiros');

    $busca = $pdo->prepare("SELECT * FROM parceiros WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $parceiroAtualizado = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Parceiro '{$nome}' atualizado com sucesso!",
        "parceiro" => $parceiroAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar parceiro via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao atualizar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}
