<?php
require_once __DIR__ . "/../sessao.php";


$servidores = [];
$servidoresMap = [];
try {
    $stmtSrv = $pdo->query("SELECT id, servername, nome FROM servidores ORDER BY servername ASC");
    $servidores = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
    foreach ($servidores as $s) {
        $servidoresMap[$s['id']] = $s;
        $servidoresMap[$s['servername']] = $s;
        if (!empty($s['nome'])) {
            $servidoresMap[$s['nome']] = $s;
        }
    }
} catch (PDOException $e) {
    $servidores = [];
}

$mensagem_erro = "";

// Garante as colunas de cor apenas uma vez por sessão (DDL completo em migrations/2026_10_15_vips_tag_string.sql)
if (empty($_SESSION['vips_schema_ok'])) {
    try {
        $colunasVips = $pdo->query("SHOW COLUMNS FROM vips")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('cor1', $colunasVips, true)) {
            $pdo->exec("ALTER TABLE vips ADD COLUMN cor1 VARCHAR(20) NULL DEFAULT '#ffffff'");
        }
        if (!in_array('cor2', $colunasVips, true)) {
            $pdo->exec("ALTER TABLE vips ADD COLUMN cor2 VARCHAR(20) NULL DEFAULT '#ffffff'");
        }
        if (!in_array('cor_tipo', $colunasVips, true)) {
            $pdo->exec("ALTER TABLE vips ADD COLUMN cor_tipo VARCHAR(20) NOT NULL DEFAULT 'colorwheel'");
        }
        if (!in_array('cor_string', $colunasVips, true)) {
            $pdo->exec("ALTER TABLE vips ADD COLUMN cor_string TEXT NULL");
        }
        $_SESSION['vips_schema_ok'] = true;
    } catch (Exception $e) {
        // Ignora: a ausência das colunas será tratada no INSERT/UPDATE
    }
}

$servidor_id_selecionado = 0;
$servidor_param = trim($_GET['servidor'] ?? ($_GET['servidor_id'] ?? ''));
if ($servidor_param !== '') {
    if (is_numeric($servidor_param)) {
        $servidor_id_selecionado = (int)$servidor_param;
    } elseif (isset($servidoresMap[$servidor_param])) {
        $servidor_id_selecionado = (int)$servidoresMap[$servidor_param]['id'];
    }
}

$nome = "";
$preco = "20.00";
$duracao_dias = 30;
$destaque = false;
$ativo = true;
$vantagens = ["TAG [tag] na tablist"];
$cor1 = "#ffffff";
$cor2 = "#ffffff";
$cor_tipo = "colorwheel";
$cor_string = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $mensagem_erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $servidor_id_selecionado = (int)($_POST['servidor_id'] ?? ($_POST['servidor'] ?? 0));
        $servidorObj             = $servidoresMap[$servidor_id_selecionado] ?? null;
        $nome                    = trim($_POST['nome'] ?? '');
        $precoRaw                = str_replace(',', '.', trim($_POST['preco'] ?? '0'));
        $preco                   = (float)$precoRaw;
        $duracao_dias            = (int)($_POST['duracao_dias'] ?? 30);
        $destaque                = isset($_POST['destaque']) ? 1 : 0;
        $ativo                   = isset($_POST['ativo']) ? 1 : 0;
        $cor_tipo                = trim($_POST['cor_tipo'] ?? 'colorwheel');
        if (!in_array($cor_tipo, ['colorwheel', 'string'], true)) {
            $cor_tipo = 'colorwheel';
        }
        $cor_string              = trim($_POST['cor_string'] ?? '');
        $cor1                    = trim($_POST['cor1'] ?? '#ffffff');
        $cor2                    = trim($_POST['cor2'] ?? '#ffffff');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor1)) $cor1 = '#ffffff';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor2)) $cor2 = '#ffffff';

        if ($cor_tipo === 'string' && $cor_string !== '') {
            if (preg_match_all('/(?:&#|#)([0-9A-Fa-f]{6})/', $cor_string, $matchesHex)) {
                if (!empty($matchesHex[1])) {
                    $cor1 = '#' . strtoupper($matchesHex[1][0]);
                    $cor2 = '#' . strtoupper(end($matchesHex[1]));
                }
            }
        }

        $vantagensPost = $_POST['vantagens'] ?? [];
        $vantagens = array_values(array_filter(array_map('trim', $vantagensPost), fn($v) => $v !== ''));

        if (!$servidorObj || $servidor_id_selecionado <= 0) {
            $mensagem_erro = "Selecione um servidor válido para este pacote VIP.";
        } elseif ($nome === '' || strlen($nome) < 2) {
            $mensagem_erro = "Informe um nome válido para o pacote VIP (ex: VIP Ouro).";
        } elseif ($preco <= 0) {
            $mensagem_erro = "O preço do VIP deve ser maior que zero.";
        } elseif (empty($vantagens)) {
            $mensagem_erro = "Adicione ao menos um benefício (bullet point) para o pacote VIP.";
        } else {
            $vantagensJson = json_encode($vantagens, JSON_UNESCAPED_UNICODE);

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO vips (servidor_id, nome, preco, duracao_dias, destaque, vantagens, ativo, cor1, cor2, cor_tipo, cor_string)
                    VALUES (:servidor_id, :nome, :preco, :duracao_dias, :destaque, :vantagens, :ativo, :cor1, :cor2, :cor_tipo, :cor_string)
                ");
                $stmt->execute([
                    ':servidor_id'  => $servidor_id_selecionado,
                    ':nome'         => $nome,
                    ':preco'        => $preco,
                    ':duracao_dias' => $duracao_dias > 0 ? $duracao_dias : 30,
                    ':destaque'     => $destaque,
                    ':vantagens'    => $vantagensJson,
                    ':ativo'        => $ativo,
                    ':cor1'         => $cor1,
                    ':cor2'         => $cor2,
                    ':cor_tipo'     => $cor_tipo,
                    ':cor_string'   => $cor_string,
                ]);

                header("Location: index.php?msg=" . urlencode("Pacote VIP '{$nome}' cadastrado com sucesso!"));
                exit;
            } catch (PDOException $e) {
                $mensagem_erro = "Erro ao cadastrar pacote VIP: " . $e->getMessage();
            }
        }
    }
}

$paginaAtiva = 'vips';
$tituloPagina = 'Novo Pacote VIP';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<style>
.scratch-swatch-box {
    width: 32px;
    height: 32px;
    padding: 2px;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    transition: all 0.15s ease;
}
.scratch-swatch-box:hover {
    border-color: #cbd5e1;
}
.scratch-swatch-box.active {
    border-color: #8c52ff;
    box-shadow: 0 0 0 2px rgba(140, 82, 255, 0.35);
}
.scratch-swatch {
    width: 100%;
    height: 100%;
    border-radius: 4px;
    display: block;
    border: 1px solid rgba(0,0,0,0.15);
}
.scratch-swap-btn {
    background: #334155;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    width: 28px;
    height: 28px;
    min-width: 28px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.78rem;
    padding: 0;
    transition: transform 0.15s ease, background-color 0.15s ease;
}
.scratch-swap-btn:hover {
    background: #475569;
    color: #ffffff;
    transform: scale(1.08);
}
.scratch-swap-btn i {
    color: #ffffff !important;
    line-height: 1;
}
.scratch-popover {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    width: 230px;
}
.scratch-preset-btn {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.2);
    cursor: pointer;
    padding: 0;
    transition: transform 0.1s ease;
}
.scratch-preset-btn:hover {
    transform: scale(1.2);
}
</style>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="fa-solid fa-gem text-success"></i> Cadastrar Novo Pacote VIP</h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">← Voltar para Pacotes VIP</a>
            </div>
            <div class="p-4">
                <?php if ($mensagem_erro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" class="js-unsaved-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 admin-form-group">
                            <label for="servidor_id">Servidor *</label>
                            <select class="admin-form-control" id="servidor_id" name="servidor_id" required autofocus>
                                <option value="">Selecione o servidor...</option>
                                <?php foreach ($servidores as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ($servidor_id_selecionado === (int)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <label for="nome">Nome do Pacote VIP *</label>
                            <input type="text" class="admin-form-control" id="nome" name="nome"
                                   value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Ex: VIP Netherite" required oninput="atualizarPreviewTag()">
                        </div>
                        <div class="col-md-4 admin-form-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 0.88rem;">
                                    Cor da Tag
                                </label>

                                <!-- SWITCH COLORWHEEL / STRING -->
                                <div class="d-flex align-items-center gap-1" title="Alternar entre Colorwheel (Gradiente) e String (formato Minecraft)">
                                    <span class="small mode-toggle-label <?php echo ($cor_tipo !== 'string') ? 'text-primary fw-bold' : 'text-muted'; ?>" id="label-mode-colorwheel" style="font-size: 0.72rem; cursor: pointer;" onclick="definirModoCor('colorwheel')">Colorwheel</span>
                                    <div class="form-check form-switch m-0 d-inline-flex align-items-center" style="min-height: auto; padding-left: 2rem;">
                                        <input class="form-check-input m-0" type="checkbox" role="switch" id="cor_tipo_switch" style="cursor: pointer;" <?php echo ($cor_tipo === 'string') ? 'checked' : ''; ?> onchange="onSwitchModoCor(this.checked)">
                                    </div>
                                    <span class="small mode-toggle-label <?php echo ($cor_tipo === 'string') ? 'text-primary fw-bold' : 'text-muted'; ?>" id="label-mode-string" style="font-size: 0.72rem; cursor: pointer;" onclick="definirModoCor('string')">String</span>
                                </div>
                            </div>

                            <!-- Hidden input capturado pelo form e unsaved_bar -->
                            <input type="hidden" id="cor_tipo" name="cor_tipo" value="<?php echo htmlspecialchars($cor_tipo, ENT_QUOTES, 'UTF-8'); ?>">

                            <!-- PAINEL 1: COLORWHEEL -->
                            <div id="panel-colorwheel" style="<?php echo ($cor_tipo === 'string') ? 'display: none;' : ''; ?>">
                                <div class="scratch-gradient-wrapper position-relative">
                                    <div class="d-flex align-items-center gap-2 p-1 bg-white border rounded" style="min-height: 42px;">
                                        <!-- Swatch Cor 1 -->
                                        <div class="scratch-swatch-box" id="swatch-cor1-btn" onclick="abrirSeletorCor('cor1', event)" title="Cor inicial (clique para escolher)">
                                            <span class="scratch-swatch" id="preview-swatch-cor1" style="background-color: <?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                        </div>

                                        <!-- Swap Button -->
                                        <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradiente(event)" title="Inverter cores">
                                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                        </button>

                                        <!-- Swatch Cor 2 -->
                                        <div class="scratch-swatch-box" id="swatch-cor2-btn" onclick="abrirSeletorCor('cor2', event)" title="Cor final (clique para escolher)">
                                            <span class="scratch-swatch" id="preview-swatch-cor2" style="background-color: <?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                        </div>

                                        <!-- Live Mini Gradient Bar -->
                                        <div class="flex-grow-1 px-1">
                                            <div id="scratch-mini-gradient-bar" class="rounded border" style="height: 18px; width: 100%;"></div>
                                        </div>

                                        <!-- Reset button -->
                                        <button type="button" class="btn btn-link btn-sm p-1 text-muted" title="Resetar cores para padrão (#ffffff)" onclick="resetarCoresPadrao()" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    </div>

                                    <input type="hidden" id="cor1" name="cor1" value="<?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" id="cor2" name="cor2" value="<?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>">

                                    <!-- Floating Popover Picker -->
                                    <div id="scratch-popover" class="scratch-popover shadow-lg" style="display: none;" onclick="event.stopPropagation()">
                                        <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                                            <span class="small fw-bold text-dark" id="scratch-popover-title">Selecionar Cor</span>
                                            <button type="button" class="btn-close btn-sm" onclick="fecharSeletorCor()"></button>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <input type="color" id="scratch-native-picker" class="form-control form-control-color w-100 mb-2" style="height: 42px; cursor: pointer;" oninput="onNativeColorChange(this.value)">
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <button type="button" class="scratch-preset-btn" style="background:#ffffff;" title="Branco / Padrão" onclick="setPresetColor('#ffffff')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#9945FF;" title="Roxo" onclick="setPresetColor('#9945FF')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#14F195;" title="Verde Esmeralda" onclick="setPresetColor('#14F195')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#FF8C00;" title="Laranja Ouro" onclick="setPresetColor('#FF8C00')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#F1C40F;" title="Amarelo" onclick="setPresetColor('#F1C40F')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#00D2FF;" title="Ciano Diamante" onclick="setPresetColor('#00D2FF')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#FF416C;" title="Rosa Rubi" onclick="setPresetColor('#FF416C')"></button>
                                                <button type="button" class="scratch-preset-btn" style="background:#B971DA;" title="Roxo Claro" onclick="setPresetColor('#B971DA')"></button>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-top">
                                            <label class="small text-muted mb-1 d-block fw-semibold" style="font-size: 0.72rem;">Código HEX:</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text font-monospace">#</span>
                                                <input type="text" id="scratch-hex-input" class="form-control font-monospace" maxlength="7" placeholder="FFFFFF" oninput="onHexInputChange(this.value)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PAINEL 2: STRING (MINECRAFT) -->
                            <div id="panel-string" style="<?php echo ($cor_tipo === 'string') ? '' : 'display: none;'; ?>">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted font-monospace" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-code"></i>
                                    </span>
                                    <input type="text" class="form-control font-monospace" id="cor_string" name="cor_string" 
                                           value="<?php echo htmlspecialchars($cor_string, ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="#HEX&amp;lLetra&#38;#HEX&amp;l..."
                                           autocomplete="off" style="font-size: 0.8rem;" oninput="onStringInputChange()">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Limpar string" onclick="limparCorString()">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- PREVIEW AO VIVO DA TAG -->
                            <div class="vip-tag-live-card mt-2 p-2 rounded border d-flex align-items-center justify-content-between" style="background: #0f172a; min-height: 38px; border-color: #1e293b !important;">
                                <span class="badge bg-dark text-muted border border-secondary" style="font-size: 0.65rem; letter-spacing: 0.5px;">PREVIEW</span>
                                <div id="vip-tag-preview-content" class="text-center flex-grow-1 px-2 fw-bold font-monospace" style="font-size: 0.95rem; letter-spacing: 0.5px;">
                                    <!-- Atualizado dinamicamente via JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="preco">Preço (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold" style="padding: 0.65rem 0.85rem; font-size: 0.88rem; border-color: var(--border-color);">R$</span>
                                <input type="number" step="0.01" min="0.01" class="admin-form-control" id="preco" name="preco"
                                       value="<?php echo htmlspecialchars((string)$preco, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6 admin-form-group">
                            <label for="duracao_dias">Duração (em dias)</label>
                            <input type="number" min="1" max="3650" class="admin-form-control" id="duracao_dias" name="duracao_dias"
                                   value="<?php echo (int)$duracao_dias; ?>" placeholder="30">
                        </div>
                    </div>

                    <!-- BENEFÍCIOS -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h6 class="fw-bold mb-0 d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-list-check text-primary me-1"></i> Benefícios do VIP
                                    <i class="fa-solid fa-circle-info text-muted ms-1" style="cursor: pointer; font-size: 0.85rem;" title="Textos entre parênteses no final como (Detalhes extras...) exibem um botão de informação (i) interativo."></i>
                                </h6>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarBeneficio()">
                                <i class="fa-solid fa-plus me-1"></i> Adicionar Benefício
                            </button>
                        </div>
                        <div id="vantagens-container" class="mt-3">
                            <?php foreach ($vantagens as $v): ?>
                                <div class="input-group mb-2 vantagem-row">
                                    <span class="input-group-text bg-white text-success border-end-0"><i class="fa-solid fa-check"></i></span>
                                    <input type="text" class="form-control border-start-0" name="vantagens[]" 
                                           value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>" 
                                           placeholder="Ex: Tag [NETHERITE] suprema (Exibida no chat e tablist)">
                                    <button type="button" class="btn btn-outline-danger" onclick="removerBeneficio(this)" title="Remover benefício">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch p-3 bg-light border rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="destaque" name="destaque" value="1" <?php echo $destaque ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="destaque">
                                    <i class="fa-solid fa-star text-warning me-1"></i> Destacar na Loja (Mais Popular / Mais Vendido)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch p-3 bg-light border rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="ativo">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i> Pacote VIP Ativo e Disponível para Compra
                                </label>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
let activeCorSlot = 'cor1';

// Parser para tags no formato Minecraft (hex e formatações)
function parseMinecraftString(raw) {
    if (!raw || typeof raw !== 'string') return '';
    const legacyColors = {
        '0': '#000000', '1': '#0000AA', '2': '#00AA00', '3': '#00AAAA',
        '4': '#AA0000', '5': '#AA00AA', '6': '#FFAA00', '7': '#AAAAAA',
        '8': '#555555', '9': '#5555FF', 'a': '#55FF55', 'b': '#55FFFF',
        'c': '#FF5555', 'd': '#FF55FF', 'e': '#FFFF55', 'f': '#FFFFFF'
    };
    let currentColor = '';
    let isBold = false, isItalic = false, isUnderline = false, isStrikethrough = false;
    let i = 0;
    let result = '';

    while (i < raw.length) {
        // &#RRGGBB
        if (raw.substr(i, 2) === '&#' && i + 8 <= raw.length && /^[0-9A-Fa-f]{6}$/.test(raw.substr(i + 2, 6))) {
            currentColor = '#' + raw.substr(i + 2, 6);
            isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
            i += 8;
            continue;
        }
        // #RRGGBB
        if (raw[i] === '#' && i + 7 <= raw.length && /^[0-9A-Fa-f]{6}$/.test(raw.substr(i + 1, 6))) {
            currentColor = '#' + raw.substr(i + 1, 6);
            isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
            i += 7;
            continue;
        }
        // &x&r&r&g&g&b&b (Bungee Hex)
        if ((raw[i] === '&' || raw[i] === '§') && (raw[i+1] === 'x' || raw[i+1] === 'X')) {
            let bungeeHex = '';
            let k = i + 2;
            for (let step = 0; step < 6; step++) {
                if (k + 1 < raw.length && (raw[k] === '&' || raw[k] === '§') && /^[0-9A-Fa-f]$/.test(raw[k+1])) {
                    bungeeHex += raw[k+1];
                    k += 2;
                } else {
                    break;
                }
            }
            if (bungeeHex.length === 6) {
                currentColor = '#' + bungeeHex;
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i = k;
                continue;
            }
        }
        // Formatações e cores padrão Minecraft (&0-&f, &l, &o, &n, &m, &r)
        if ((raw[i] === '&' || raw[i] === '§') && i + 1 < raw.length) {
            const code = raw[i + 1].toLowerCase();
            if (legacyColors[code] !== undefined) {
                currentColor = legacyColors[code];
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i += 2;
                continue;
            } else if (code === 'l') {
                isBold = true;
                i += 2;
                continue;
            } else if (code === 'o') {
                isItalic = true;
                i += 2;
                continue;
            } else if (code === 'n') {
                isUnderline = true;
                i += 2;
                continue;
            } else if (code === 'm') {
                isStrikethrough = true;
                i += 2;
                continue;
            } else if (code === 'r') {
                currentColor = '';
                isBold = false; isItalic = false; isUnderline = false; isStrikethrough = false;
                i += 2;
                continue;
            }
        }

        const char = raw[i];
        let style = [];
        if (currentColor) style.push('color:' + currentColor);
        if (isBold) style.push('font-weight:800');
        if (isItalic) style.push('font-style:italic');
        if (isUnderline && isStrikethrough) {
            style.push('text-decoration:underline line-through');
        } else if (isUnderline) {
            style.push('text-decoration:underline');
        } else if (isStrikethrough) {
            style.push('text-decoration:line-through');
        }

        const safeChar = char === '&' ? '&amp;' : (char === '<' ? '&lt;' : (char === '>' ? '&gt;' : char));
        if (style.length > 0) {
            result += `<span style="${style.join(';')}">${safeChar}</span>`;
        } else {
            result += safeChar;
        }
        i++;
    }
    return result;
}

function atualizarPreviewTag() {
    const previewEl = document.getElementById('vip-tag-preview-content');
    if (!previewEl) return;

    const modo = document.getElementById('cor_tipo')?.value || 'colorwheel';

    if (modo === 'string') {
        const str = (document.getElementById('cor_string')?.value || '').trim();
        if (!str) {
            previewEl.innerHTML = '<span class="text-muted" style="font-weight: 400; font-size: 0.76rem; font-style: italic;">Cole ou digite a string da tag...</span>';
            return;
        }
        const parsed = parseMinecraftString(str);
        if (!parsed) {
            previewEl.innerHTML = '<span class="text-warning" style="font-size: 0.76rem;">Formato inválido</span>';
            return;
        }
        const hasBrackets = str.includes('[') || str.includes(']');
        previewEl.innerHTML = hasBrackets ? parsed : `<span style="color: #94a3b8; font-weight: normal;">[</span>${parsed}<span style="color: #94a3b8; font-weight: normal;">]</span>`;
    } else {
        const c1 = document.getElementById('cor1')?.value || '#ffffff';
        const c2 = document.getElementById('cor2')?.value || '#ffffff';
        const nomeVip = (document.getElementById('nome')?.value || 'VIP').replace(/^vip\s*/i, '').trim() || 'TAG';
        const tagName = nomeVip.toUpperCase();
        const hasCustomGrad = c1 && c2 && (c1.toLowerCase() !== c2.toLowerCase()) && (c1 !== '#ffffff' || c2 !== '#ffffff');

        const tagStyle = hasCustomGrad
            ? `background: linear-gradient(135deg, ${c1}, ${c2}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800;`
            : `color: ${c1}; font-weight: 800;`;

        previewEl.innerHTML = `<span style="color: #94a3b8; font-weight: normal;">[</span><span style="${tagStyle}">${tagName}</span><span style="color: #94a3b8; font-weight: normal;">]</span>`;
    }
}

function aplicarModoCor(modo, dispararEvento) {
    if (dispararEvento === undefined) dispararEvento = true;
    const inputModo = document.getElementById('cor_tipo');
    const switchEl = document.getElementById('cor_tipo_switch');
    const panelColorwheel = document.getElementById('panel-colorwheel');
    const panelString = document.getElementById('panel-string');
    const labelColorwheel = document.getElementById('label-mode-colorwheel');
    const labelString = document.getElementById('label-mode-string');

    if (inputModo) inputModo.value = modo;
    if (switchEl) switchEl.checked = (modo === 'string');

    if (modo === 'string') {
        if (panelColorwheel) panelColorwheel.style.display = 'none';
        if (panelString) panelString.style.display = 'block';
        if (labelColorwheel) {
            labelColorwheel.classList.remove('text-primary', 'fw-bold');
            labelColorwheel.classList.add('text-muted');
        }
        if (labelString) {
            labelString.classList.remove('text-muted');
            labelString.classList.add('text-primary', 'fw-bold');
        }
        fecharSeletorCor();
    } else {
        if (panelColorwheel) panelColorwheel.style.display = 'block';
        if (panelString) panelString.style.display = 'none';
        if (labelColorwheel) {
            labelColorwheel.classList.remove('text-muted');
            labelColorwheel.classList.add('text-primary', 'fw-bold');
        }
        if (labelString) {
            labelString.classList.remove('text-primary', 'fw-bold');
            labelString.classList.add('text-muted');
        }
    }

    atualizarPreviewTag();

    if (dispararEvento && inputModo) {
        inputModo.dispatchEvent(new Event('input', { bubbles: true }));
        inputModo.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function onSwitchModoCor(isChecked) {
    aplicarModoCor(isChecked ? 'string' : 'colorwheel', true);
}

function definirModoCor(modo) {
    aplicarModoCor(modo, true);
}

function onStringInputChange() {
    atualizarPreviewTag();
}

function limparCorString() {
    const input = document.getElementById('cor_string');
    if (input) {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        atualizarPreviewTag();
        input.focus();
    }
}

function preencherExemploString() {
    const input = document.getElementById('cor_string');
    if (input) {
        input.value = '#FFE082&lC&#FFD76B&lO&#FFCE54&lM&#FFC53D&lA&#FFBC26&lN&#FFB30F&lD&#FFAA00&lA&#FFA000&lN&#FF9700&lT&#FF8E00&lE&#FF8E00';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        atualizarPreviewTag();
    }
}

function atualizarMiniGradiente() {
    const c1 = document.getElementById('cor1')?.value || '#ffffff';
    const c2 = document.getElementById('cor2')?.value || '#ffffff';
    const bar = document.getElementById('scratch-mini-gradient-bar');
    if (bar) {
        bar.style.background = `linear-gradient(90deg, ${c1}, ${c2})`;
    }
}

function abrirSeletorCor(slot, event) {
    if (event) event.stopPropagation();
    activeCorSlot = slot;
    
    document.getElementById('swatch-cor1-btn')?.classList.toggle('active', slot === 'cor1');
    document.getElementById('swatch-cor2-btn')?.classList.toggle('active', slot === 'cor2');

    const inputVal = document.getElementById(slot)?.value || '#ffffff';
    const title = document.getElementById('scratch-popover-title');
    if (title) title.textContent = slot === 'cor1' ? 'Cor Inicial (Gradiente)' : 'Cor Final (Gradiente)';

    const nativePicker = document.getElementById('scratch-native-picker');
    if (nativePicker) nativePicker.value = inputVal;

    const hexInput = document.getElementById('scratch-hex-input');
    if (hexInput) hexInput.value = inputVal.replace('#', '').toUpperCase();

    const popover = document.getElementById('scratch-popover');
    if (popover) popover.style.display = 'block';
}

function fecharSeletorCor() {
    const popover = document.getElementById('scratch-popover');
    if (popover) popover.style.display = 'none';
    document.getElementById('swatch-cor1-btn')?.classList.remove('active');
    document.getElementById('swatch-cor2-btn')?.classList.remove('active');
}

function aplicarCor(hex) {
    if (!hex.startsWith('#')) hex = '#' + hex;
    const input = document.getElementById(activeCorSlot);
    if (input) {
        input.value = hex;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const swatch = document.getElementById('preview-swatch-' + activeCorSlot);
    if (swatch) swatch.style.backgroundColor = hex;

    atualizarMiniGradiente();
    atualizarPreviewTag();
}

function onNativeColorChange(hex) {
    const hexInput = document.getElementById('scratch-hex-input');
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCor(hex);
}

function onHexInputChange(val) {
    const clean = val.trim().replace('#', '');
    if (/^[0-9A-Fa-f]{6}$/.test(clean)) {
        const hex = '#' + clean.toUpperCase();
        const nativePicker = document.getElementById('scratch-native-picker');
        if (nativePicker) nativePicker.value = hex;
        aplicarCor(hex);
    }
}

function setPresetColor(hex) {
    const nativePicker = document.getElementById('scratch-native-picker');
    if (nativePicker) nativePicker.value = hex;
    const hexInput = document.getElementById('scratch-hex-input');
    if (hexInput) hexInput.value = hex.replace('#', '').toUpperCase();
    aplicarCor(hex);
}

function trocarCoresGradiente(e) {
    if (e) e.stopPropagation();
    const c1Input = document.getElementById('cor1');
    const c2Input = document.getElementById('cor2');
    if (!c1Input || !c2Input) return;

    const temp = c1Input.value;
    c1Input.value = c2Input.value;
    c2Input.value = temp;

    c1Input.dispatchEvent(new Event('input', { bubbles: true }));
    c1Input.dispatchEvent(new Event('change', { bubbles: true }));
    c2Input.dispatchEvent(new Event('input', { bubbles: true }));
    c2Input.dispatchEvent(new Event('change', { bubbles: true }));

    const sw1 = document.getElementById('preview-swatch-cor1');
    const sw2 = document.getElementById('preview-swatch-cor2');
    if (sw1) sw1.style.backgroundColor = c1Input.value;
    if (sw2) sw2.style.backgroundColor = c2Input.value;

    if (document.getElementById('scratch-popover')?.style.display === 'block') {
        const currentVal = document.getElementById(activeCorSlot)?.value || '#ffffff';
        const nativePicker = document.getElementById('scratch-native-picker');
        if (nativePicker) nativePicker.value = currentVal;
        const hexInput = document.getElementById('scratch-hex-input');
        if (hexInput) hexInput.value = currentVal.replace('#', '').toUpperCase();
    }

    atualizarMiniGradiente();
    atualizarPreviewTag();
}

function resetarCoresPadrao() {
    const c1Input = document.getElementById('cor1');
    const c2Input = document.getElementById('cor2');
    if (c1Input) {
        c1Input.value = '#ffffff';
        c1Input.dispatchEvent(new Event('input', { bubbles: true }));
        c1Input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (c2Input) {
        c2Input.value = '#ffffff';
        c2Input.dispatchEvent(new Event('input', { bubbles: true }));
        c2Input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const sw1 = document.getElementById('preview-swatch-cor1');
    const sw2 = document.getElementById('preview-swatch-cor2');
    if (sw1) sw1.style.backgroundColor = '#ffffff';
    if (sw2) sw2.style.backgroundColor = '#ffffff';

    if (document.getElementById('scratch-popover')?.style.display === 'block') {
        const nativePicker = document.getElementById('scratch-native-picker');
        if (nativePicker) nativePicker.value = '#ffffff';
        const hexInput = document.getElementById('scratch-hex-input');
        if (hexInput) hexInput.value = 'FFFFFF';
    }

    atualizarMiniGradiente();
    atualizarPreviewTag();
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.scratch-gradient-wrapper')) {
        fecharSeletorCor();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    atualizarMiniGradiente();
    atualizarPreviewTag();

    // Integração com o botão "Descartar" do unsaved_bar
    const form = document.querySelector('form.js-unsaved-form');
    if (form) {
        form.addEventListener('unsaved:discarded', () => {
            const restoredModo = document.getElementById('cor_tipo')?.value || 'colorwheel';
            aplicarModoCor(restoredModo, false);

            const c1 = document.getElementById('cor1')?.value || '#ffffff';
            const c2 = document.getElementById('cor2')?.value || '#ffffff';
            const sw1 = document.getElementById('preview-swatch-cor1');
            const sw2 = document.getElementById('preview-swatch-cor2');
            if (sw1) sw1.style.backgroundColor = c1;
            if (sw2) sw2.style.backgroundColor = c2;

            atualizarMiniGradiente();
            atualizarPreviewTag();
        });
    }
});

function adicionarBeneficio() {
    const container = document.getElementById('vantagens-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 vantagem-row';
    div.setAttribute('data-js-added', '1');
    div.innerHTML = `
        <span class="input-group-text bg-white text-success border-end-0"><i class="fa-solid fa-check"></i></span>
        <input type="text" class="form-control border-start-0" name="vantagens[]" placeholder="Ex: Acesso ao Sistema de Cosméticos">
        <button type="button" class="btn btn-outline-danger" onclick="removerBeneficio(this)" title="Remover benefício">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    container.appendChild(div);
    const input = div.querySelector('input');
    if (input) input.focus();
}

function removerBeneficio(btn) {
    const rows = document.querySelectorAll('.vantagem-row');
    if (rows.length > 1) {
        btn.closest('.vantagem-row').remove();
    } else {
        btn.closest('.vantagem-row').querySelector('input').value = '';
    }
}
</script>

<?php require_once __DIR__ . "/../includes/unsaved_bar.php"; ?>
<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>