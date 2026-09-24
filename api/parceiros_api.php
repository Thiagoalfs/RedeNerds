<?php
/**
 * parceiros_api.php
 *
 * Endpoint público que retorna a lista de parceiros e criadores de conteúdo ativos
 * em ordem alfabética para renderização na landing page (index.html).
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

$parceirosFallback = [
    [
        'id'        => 1,
        'nome'      => 'Danrique',
        'foto'      => '/assets/images/danrique.webp',
        'youtube'   => 'https://www.youtube.com/@Danrique',
        'tiktok'    => null,
        'instagram' => null,
        'twitch'    => null,
        'kick'      => null,
        'redes'     => [
            ['tipo' => 'youtube', 'url' => 'https://www.youtube.com/@Danrique', 'icone' => 'fa-brands fa-youtube', 'classe' => 'yt', 'label' => 'YouTube']
        ]
    ],
    [
        'id'        => 2,
        'nome'      => 'Matuzzo',
        'foto'      => '/assets/images/matuzzo.jpg',
        'youtube'   => 'https://www.youtube.com/@Matuzzo-MTZ',
        'tiktok'    => 'https://www.tiktok.com/@matuzzo.mtz',
        'instagram' => 'https://www.instagram.com/matuzzo.mtz',
        'twitch'    => null,
        'kick'      => null,
        'redes'     => [
            ['tipo' => 'youtube', 'url' => 'https://www.youtube.com/@Matuzzo-MTZ', 'icone' => 'fa-brands fa-youtube', 'classe' => 'yt', 'label' => 'YouTube'],
            ['tipo' => 'tiktok', 'url' => 'https://www.tiktok.com/@matuzzo.mtz', 'icone' => 'fa-brands fa-tiktok', 'classe' => 'tiktok', 'label' => 'TikTok'],
            ['tipo' => 'instagram', 'url' => 'https://www.instagram.com/matuzzo.mtz', 'icone' => 'fa-brands fa-instagram', 'classe' => 'instagram', 'label' => 'Instagram']
        ]
    ],
    [
        'id'        => 3,
        'nome'      => 'Xonera Mil Grau',
        'foto'      => '/assets/images/xonera.png',
        'youtube'   => 'https://www.youtube.com/@XoneraMilGrau',
        'tiktok'    => 'https://www.tiktok.com/@xoneramilgrau',
        'instagram' => null,
        'twitch'    => null,
        'kick'      => null,
        'redes'     => [
            ['tipo' => 'youtube', 'url' => 'https://www.youtube.com/@XoneraMilGrau', 'icone' => 'fa-brands fa-youtube', 'classe' => 'yt', 'label' => 'YouTube'],
            ['tipo' => 'tiktok', 'url' => 'https://www.tiktok.com/@xoneramilgrau', 'icone' => 'fa-brands fa-tiktok', 'classe' => 'tiktok', 'label' => 'TikTok']
        ]
    ]
];

$linhas = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Garante que a tabela existe
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `parceiros` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `nome` VARCHAR(100) NOT NULL,
              `foto` VARCHAR(255) NULL DEFAULT NULL,
              `youtube` VARCHAR(255) NULL DEFAULT NULL,
              `tiktok` VARCHAR(255) NULL DEFAULT NULL,
              `instagram` VARCHAR(255) NULL DEFAULT NULL,
              `twitch` VARCHAR(255) NULL DEFAULT NULL,
              `kick` VARCHAR(255) NULL DEFAULT NULL,
              `ativo` TINYINT(1) NOT NULL DEFAULT 1,
              `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_ativo_nome (`ativo`, `nome`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Exception $e) {}

        $stmt = $pdo->query(
            "SELECT id, nome, foto, youtube, tiktok, instagram, twitch, kick
             FROM parceiros
             WHERE ativo = 1
             ORDER BY nome ASC"
        );
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro em parceiros_api: " . $e->getMessage());
    $linhas = [];
}

if (!empty($linhas)) {
    $parceiros = array_map(function ($p) {
        $redes = [];

        $validarLink = function(?string $url): ?string {
            $url = trim((string)$url);
            if ($url === '') return null;
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        };

        $ytUrl = $validarLink($p['youtube'] ?? null);
        if ($ytUrl) {
            $redes[] = [
                'tipo'   => 'youtube',
                'url'    => $ytUrl,
                'icone'  => 'fa-brands fa-youtube',
                'classe' => 'yt',
                'label'  => 'YouTube'
            ];
        }

        $ttUrl = $validarLink($p['tiktok'] ?? null);
        if ($ttUrl) {
            $redes[] = [
                'tipo'   => 'tiktok',
                'url'    => $ttUrl,
                'icone'  => 'fa-brands fa-tiktok',
                'classe' => 'tiktok',
                'label'  => 'TikTok'
            ];
        }

        $igUrl = $validarLink($p['instagram'] ?? null);
        if ($igUrl) {
            $redes[] = [
                'tipo'   => 'instagram',
                'url'    => $igUrl,
                'icone'  => 'fa-brands fa-instagram',
                'classe' => 'instagram',
                'label'  => 'Instagram'
            ];
        }

        $twUrl = $validarLink($p['twitch'] ?? null);
        if ($twUrl) {
            $redes[] = [
                'tipo'   => 'twitch',
                'url'    => $twUrl,
                'icone'  => 'fa-brands fa-twitch',
                'classe' => 'twitch',
                'label'  => 'Twitch'
            ];
        }

        $kickUrl = $validarLink($p['kick'] ?? null);
        if ($kickUrl) {
            $redes[] = [
                'tipo'   => 'kick',
                'url'    => $kickUrl,
                'icone'  => 'fa-brands fa-kick',
                'classe' => 'kick',
                'label'  => 'Kick'
            ];
        }

        $foto = !empty($p['foto']) ? $p['foto'] : '/assets/images/logo.webp';

        return [
            'id'        => (int)$p['id'],
            'nome'      => $p['nome'],
            'foto'      => $foto,
            'youtube'   => $ytUrl,
            'tiktok'    => $ttUrl,
            'instagram' => $igUrl,
            'twitch'    => $twUrl,
            'kick'      => $kickUrl,
            'redes'     => $redes
        ];
    }, $linhas);

    echo json_encode([
        'success'   => true,
        'parceiros' => $parceiros,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success'   => true,
    'parceiros' => $parceirosFallback,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);