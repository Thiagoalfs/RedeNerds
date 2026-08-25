<?php
/**
 * vips_api.php
 * Retorna os servidores e seus respectivos pacotes VIP disponíveis para compra.
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

// Servidores padrão da Rede Nerds
$servidoresDefault = [
    [
        "id" => "potato",
        "nome" => "Potato Nerd",
        "badge" => "Modpack Tech",
        "cor" => "#7DB9DF",
        "icon" => "fa-solid fa-bolt",
        "vips" => [
            [
                "id" => 1,
                "servidor" => "potato",
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
                "servidor" => "potato",
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
                "servidor" => "potato",
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
                "servidor" => "potato",
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
                "servidor" => "potato",
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
        "nome" => "NerdDead",
        "badge" => "Hardcore Survival",
        "cor" => "#E85D5D",
        "icon" => "fa-solid fa-biohazard",
        "vips" => [
            [
                "id" => 3,
                "servidor" => "nerddead",
                "nome" => "Sobrevivente VIP",
                "preco" => 20.00,
                "duracao_dias" => 30,
                "destaque" => true,
                "vantagens" => [
                    "Tag [SOBREVIVENTE] vermelha no chat e tab",
                    "Kit de Primeiros Socorros semanal",
                    "Acesso a 3 safezones protegidas",
                    "Comando /back com menor cooldown",
                    "Prioridade na fila de entrada"
                ]
            ]
        ]
    ]
];

// Tenta buscar no banco de dados se a tabela `vips` possuir registros
$vipsDoBanco = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT * FROM vips WHERE ativo = 1 ORDER BY preco ASC");
        $vipsDoBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query("SELECT * FROM vips WHERE ativo = 1 ORDER BY preco ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $vipsDoBanco[] = $row;
            }
        }
    }
} catch (Exception $e) {
    // Se a tabela não existir ou erro de SQL, usa os padrões silenciosamente
    $vipsDoBanco = [];
}

// Se o banco tiver VIPs cadastrados, organiza por servidor
if (!empty($vipsDoBanco)) {
    $servidoresAgrupados = [];

    foreach ($vipsDoBanco as $v) {
        $srvKey = strtolower(trim($v['servidor']));
        if (!isset($servidoresAgrupados[$srvKey])) {
            // Tenta obter info do servidor padrão correspondente
            $srvInfo = [
                "id" => $srvKey,
                "nome" => ucfirst($v['servidor']),
                "badge" => "Servidor",
                "cor" => "#7DB9DF",
                "icon" => "fa-solid fa-server",
                "vips" => []
            ];

            foreach ($servidoresDefault as $sd) {
                if (strtolower($sd['id']) === $srvKey || strtolower($sd['nome']) === $srvKey) {
                    $srvInfo = $sd;
                    $srvInfo['vips'] = [];
                    break;
                }
            }

            $servidoresAgrupados[$srvKey] = $srvInfo;
        }

        // Processa vantagens (JSON ou quebra de linha)
        $vantagens = [];
        if (!empty($v['vantagens'])) {
            $jsonDecoded = json_decode($v['vantagens'], true);
            if (is_array($jsonDecoded)) {
                $vantagens = $jsonDecoded;
            } else {
                $vantagens = array_filter(array_map('trim', explode("\n", $v['vantagens'])));
            }
        }

        $servidoresAgrupados[$srvKey]['vips'][] = [
            "id" => (int)$v['id'],
            "servidor" => $v['servidor'],
            "nome" => $v['nome'],
            "preco" => (float)$v['preco'],
            "duracao_dias" => isset($v['duracao_dias']) ? (int)$v['duracao_dias'] : 30,
            "destaque" => !empty($v['destaque']),
            "vantagens" => $vantagens
        ];
    }

    echo json_encode([
        "success" => true,
        "servidores" => array_values($servidoresAgrupados)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Retorna dados padrão se o banco ainda estiver vazio
echo json_encode([
    "success" => true,
    "servidores" => $servidoresDefault
], JSON_UNESCAPED_UNICODE);
