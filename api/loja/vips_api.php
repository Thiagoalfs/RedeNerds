<?php
/**
 * vips_api.php
 * Retorna os servidores e seus respectivos pacotes VIP disponíveis para compra.
 * Os dados do servidor (nome exibido, cores, ícones) são obtidos dinamicamente da tabela `servidores`.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}

if (!$configPath) {
    echo json_encode(["erro" => "Arquivo config.php não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/../auth_api.php";
verificarAcessoApi();

// Servidores padrão de fallback caso o banco esteja indisponível
$servidoresDefault = [
    [
        "id" => "potatonerds",
        "nome" => "Potato Nerds",
        "badge" => "Modpack Tech",
        "cor" => "#7DB9DF",
        "icon" => "/assets/images/xomaps.png",
        "vips" => [
            [
                "id" => 1,
                "servidor" => "Potato Nerds",
                "nome" => "VIP Carvão",
                "preco" => 20.00,
                "duracao_dias" => 30,
                "destaque" => false,
                "vantagens" => [
                    "Tag [CARVÃO] exclusiva no chat e tablist",
                    "2 Homes (/sethome)",
                    "2 Chunks protegidas extras",
                    "2.000 Coins no comércio interno",
                    "Acesso ao Sistema de Cosméticos",
                    "Efeitos de partículas exclusivos",
                    "Kit Inicial: Ferramentas de Ferro + 64 Cenouras Douradas",
                    "Acesso à fila prioritária"
                ]
            ],
            [
                "id" => 2,
                "servidor" => "Potato Nerds",
                "nome" => "VIP Ferro",
                "preco" => 40.00,
                "duracao_dias" => 30,
                "destaque" => false,
                "vantagens" => [
                    "Tag [FERRO] exclusiva no chat e tablist",
                    "4 Homes (/sethome)",
                    "4 Chunks protegidas extras",
                    "4.000 Coins no comércio interno",
                    "Acesso ao Sistema de Cosméticos",
                    "Efeitos de partículas exclusivos",
                    "Kit Inicial: Ferramentas de Ferro + 64 Cenouras Douradas",
                    "Acesso à fila prioritária"
                ]
            ],
            [
                "id" => 3,
                "servidor" => "Potato Nerds",
                "nome" => "VIP Ouro",
                "preco" => 60.00,
                "duracao_dias" => 30,
                "destaque" => true,
                "vantagens" => [
                    "Tag [OURO] brilhante no chat e tablist",
                    "6 Homes (/sethome)",
                    "6 Chunks protegidas extras",
                    "6.000 Coins no comércio interno",
                    "Acesso ao Sistema de Cosméticos",
                    "Efeitos de partículas exclusivos",
                    "Kit Inicial: Ferramentas de Ferro + 64 Cenouras Douradas",
                    "Prioridade alta na fila de entrada"
                ]
            ],
            [
                "id" => 4,
                "servidor" => "Potato Nerds",
                "nome" => "VIP Diamante",
                "preco" => 80.00,
                "duracao_dias" => 30,
                "destaque" => false,
                "vantagens" => [
                    "Tag [DIAMANTE] destacada no chat e tablist",
                    "8 Homes (/sethome)",
                    "8 Chunks protegidas extras",
                    "8.000 Coins no comércio interno",
                    "Acesso ao Sistema de Cosméticos",
                    "Efeitos de partículas exclusivos",
                    "Kit Inicial: Ferramentas de Ferro + 64 Cenouras Douradas",
                    "Prioridade alta na fila de entrada"
                ]
            ],
            [
                "id" => 5,
                "servidor" => "Potato Nerds",
                "nome" => "VIP Netherite",
                "preco" => 100.00,
                "duracao_dias" => 30,
                "destaque" => true,
                "vantagens" => [
                    "Tag [NETHERITE] suprema no chat e tablist",
                    "10 Homes (/sethome)",
                    "10 Chunks protegidas extras",
                    "10.000 Coins no comércio interno",
                    "Acesso a todos os Cosméticos",
                    "Todos os Efeitos de partículas",
                    "Kit Inicial: Ferramentas de Ferro + 64 Cenouras Douradas",
                    "Prioridade máxima na fila de entrada"
                ]
            ]
        ]
    ],
    [
        "id" => "nerddead",
        "nome" => "Nerd Dead",
        "badge" => "Hardcore Survival",
        "cor" => "#E85D5D",
        "icon" => "/assets/images/nerddead.webp",
        "vips" => [
            [
                "id" => 6,
                "servidor" => "Nerd Dead",
                "nome" => "VIP Sobrevivente",
                "preco" => 30.00,
                "duracao_dias" => 30,
                "destaque" => false,
                "vantagens" => [
                    "A cada 24h receba uma Glock17 com 36 munições e bastão de baseball",
                    "A cada 30d receba uma Uzi com 100 munições e bastão de baseball",
                    "A cada 1 hora receba um kit comida e bebida",
                    "A cada 4 horas receba um kit medicamentos para curar seus ferimentos"
                ]
            ]
        ]
    ]
];

$servidoresDoBanco = [];
$vipsDoBanco = [];

// 1. Consulta os servidores ativos e os VIPs cadastrados no banco
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon, descricao FROM servidores WHERE enabled = 1 ORDER BY id ASC");
        $servidoresDoBanco = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);

        $stmtVips = $pdo->query("SELECT * FROM vips WHERE ativo = 1 ORDER BY preco ASC");
        $vipsDoBanco = $stmtVips->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $resSrv = $conn->query("SELECT id, servername, nome, themecolor, icon, descricao FROM servidores WHERE enabled = 1 ORDER BY id ASC");
        if ($resSrv) {
            while ($row = $resSrv->fetch_assoc()) $servidoresDoBanco[] = $row;
        }

        $resVips = $conn->query("SELECT * FROM vips WHERE ativo = 1 ORDER BY preco ASC");
        if ($resVips) {
            while ($row = $resVips->fetch_assoc()) $vipsDoBanco[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar VIPs e Servidores: " . $e->getMessage());
}

// 2. Se temos servidores e VIPs no banco, monta a resposta dinâmica usando o `servername`
if (!empty($servidoresDoBanco) && !empty($vipsDoBanco)) {
    $servidoresFormatados = [];

    foreach ($servidoresDoBanco as $srv) {
        $srvNomeLimpo = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $srv['servername']));
        $srvSlug = strtolower(trim($srv['nome']));

        $vipsDoServidor = [];

        foreach ($vipsDoBanco as $v) {
            $vipSrvRaw = trim($v['servidor'] ?? '');
            $vipSrvLimpo = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $vipSrvRaw));
            $vipServidorId = (int)($v['servidor_id'] ?? 0);

            // Correspondência direta por ID do servidor, com fallback por nome/slug
            $pertenceAoServidor = (
                ($vipServidorId > 0 && $vipServidorId === (int)$srv['id']) ||
                strcasecmp($vipSrvRaw, $srv['servername']) === 0 ||
                strcasecmp($vipSrvRaw, $srvSlug) === 0 ||
                ($vipSrvLimpo !== '' && $vipSrvLimpo === $srvNomeLimpo)
            );

            if ($pertenceAoServidor) {
                // Processa as vantagens (JSON ou quebras de linha)
                $vantagens = [];
                if (!empty($v['vantagens'])) {
                    $jsonDecoded = json_decode($v['vantagens'], true);
                    if (is_array($jsonDecoded)) {
                        $vantagens = $jsonDecoded;
                    } else {
                        $vantagens = array_values(array_filter(array_map('trim', explode("\n", $v['vantagens']))));
                    }
                }

                $vipsDoServidor[] = [
                    "id" => (int)$v['id'],
                    "servidor_id" => (int)$srv['id'],
                    "servidor" => $srv['servername'], // Puxa exatamente o servername da tabela servidores!
                    "nome" => $v['nome'],
                    "preco" => (float)$v['preco'],
                    "duracao_dias" => isset($v['duracao_dias']) ? (int)$v['duracao_dias'] : 30,
                    "destaque" => !empty($v['destaque']),
                    "vantagens" => $vantagens
                ];
            }
        }

        // Se o servidor possui pacotes cadastrados, inclui na loja
        if (!empty($vipsDoServidor)) {
            $servidoresFormatados[] = [
                "id" => $srvSlug ?: $srvNomeLimpo,
                "servidor_id" => (int)$srv['id'],
                "nome" => $srv['servername'], // Nome oficial do servidor
                "badge" => !empty($srv['descricao']) ? mb_strimwidth(strip_tags($srv['descricao']), 0, 45, '...') : 'Servidor Oficial',
                "cor" => $srv['themecolor'] ?: '#7DB9DF',
                "icon" => $srv['icon'] ?: 'fa-solid fa-server',
                "vips" => $vipsDoServidor
            ];
        }
    }

    $mpPublicKey = defined('MERCADO_PAGO_PUBLIC_KEY') ? trim(MERCADO_PAGO_PUBLIC_KEY) : '';

    if (!empty($servidoresFormatados)) {
        echo json_encode([
            "success" => true,
            "servidores" => $servidoresFormatados,
            "mercadopago_public_key" => $mpPublicKey
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$mpPublicKey = defined('MERCADO_PAGO_PUBLIC_KEY') ? trim(MERCADO_PAGO_PUBLIC_KEY) : '';

// Fallback caso o banco esteja vazio ou inacessível
echo json_encode([
    "success" => true,
    "servidores" => $servidoresDefault,
    "mercadopago_public_key" => $mpPublicKey
], JSON_UNESCAPED_UNICODE);
