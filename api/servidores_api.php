<?php
/**
 * servidores_api.php
 *
 * Endpoint público (sem sessão/login) que devolve os servidores marcados
 * como "enabled" no banco, para a landing page montar os cards dinamicamente.
 *
 * Local esperado: ~/api/servidores_api.php
 * Ajuste o caminho do require abaixo se a estrutura de pastas for diferente
 * (aqui assumimos que config.php está uma pasta acima de /api/).
 */

$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php"
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}

if (!$configPath) {
    http_response_code(500);
    echo json_encode(["erro" => "Arquivo config.php não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/auth_api.php";
verificarAcessoApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

const ICON_UPLOAD_DIR_PUBLICA = '/assets/servidores/icons/';

/**
 * Descobre se o ícone salvo é uma classe FontAwesome ("fa") ou uma imagem
 * (upload próprio ou link externo -> "img").
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

try {
    $stmt = $pdo->query(
        "SELECT id, nome, servername, title, icon, descricao, features, modpackurl, ip, themecolor
         FROM servidores
         WHERE enabled = 1
         ORDER BY id ASC"
    );
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $servidores = array_map(function ($s) {
        $features = json_decode($s['features'] ?? '[]', true);
        if (!is_array($features)) {
            $features = [];
        }

        return [
            'id'         => (int)$s['id'],
            'nome'       => $s['nome'],
            'servername' => $s['servername'],
            'title'      => $s['title'],
            'icon'       => $s['icon'],
            'icon_type'  => tipoDoIcone($s['icon']),
            'descricao'  => $s['descricao'],
            'features'   => $features,
            'modpackurl' => $s['modpackurl'],
            'ip'         => $s['ip'],
            'themecolor' => $s['themecolor'],
        ];
    }, $linhas);

    echo json_encode([
        'success'    => true,
        'servidores' => $servidores,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao consultar os servidores.',
    ], JSON_UNESCAPED_UNICODE);
}
