<?php
/**
 * servidores_api.php
 *
 * Endpoint público (sem sessão/login) que devolve os servidores marcados
 * como "enabled" no banco, para a landing page montar os cards dinamicamente.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
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

const ICON_UPLOAD_DIR_PUBLICA = '/assets/servidores/icons/';

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

$servidoresFallback = [
    [
        'id'         => 1,
        'nome'       => 'xomaps',
        'servername' => 'Potato Nerd',
        'title'      => 'Potato Nerd',
        'icon'       => '/assets/images/xomaps.png',
        'icon_type'  => 'img',
        'descricao'  => 'Modpack clássico focado em tecnologia e automação com mods leves.',
        'features'   => [],
        'modpackurl' => '/download',
        'ip'         => 'jogar.redenerds.com.br',
        'themecolor' => '#7DB9DF',
    ],
    [
        'id'         => 2,
        'nome'       => 'nerddead',
        'servername' => 'NerdDead',
        'title'      => 'NerdDead',
        'icon'       => '/assets/images/nerddead.webp',
        'icon_type'  => 'img',
        'descricao'  => 'Sobrevivência hardcore em um mundo infestado por zumbis e parasitas.',
        'features'   => [],
        'modpackurl' => '/download',
        'ip'         => 'jogar.redenerds.com.br',
        'themecolor' => '#E85D5D',
    ]
];

$linhas = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query(
            "SELECT id, nome, servername, title, icon, bg_image, descricao, features, modpackurl, ip, themecolor
             FROM servidores
             WHERE enabled = 1
             ORDER BY id ASC"
        );
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query(
            "SELECT id, nome, servername, title, icon, bg_image, descricao, features, modpackurl, ip, themecolor
             FROM servidores
             WHERE enabled = 1
             ORDER BY id ASC"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $linhas[] = $row;
            }
        }
    }
} catch (Exception $e) {
    $linhas = [];
}

if (!empty($linhas)) {
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
            'bg_image'   => $s['bg_image'] ?? null,
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
    exit;
}

echo json_encode([
    'success'    => true,
    'servidores' => $servidoresFallback,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
