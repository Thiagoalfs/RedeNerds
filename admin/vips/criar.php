<?php
require_once __DIR__ . "/../sessao.php";

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

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

try {
    $colunasVips = $pdo->query("SHOW COLUMNS FROM vips")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cor1', $colunasVips, true)) {
        $pdo->exec("ALTER TABLE vips ADD COLUMN cor1 VARCHAR(20) NULL DEFAULT '#ffffff'");
    }
    if (!in_array('cor2', $colunasVips, true)) {
        $pdo->exec("ALTER TABLE vips ADD COLUMN cor2 VARCHAR(20) NULL DEFAULT '#ffffff'");
    }
} catch (Exception $e) {
    // Ignora
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
$vantagens = ["Tag [NETHERITE] no chat e tablist"];
$cor1 = "#ffffff";
$cor2 = "#ffffff";

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
        $cor1                    = trim($_POST['cor1'] ?? '#ffffff');
        $cor2                    = trim($_POST['cor2'] ?? '#ffffff');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor1)) $cor1 = '#ffffff';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor2)) $cor2 = '#ffffff';

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
                    INSERT INTO vips (servidor_id, nome, preco, duracao_dias, destaque, vantagens, ativo, cor1, cor2)
                    VALUES (:servidor_id, :nome, :preco, :duracao_dias, :destaque, :vantagens, :ativo, :cor1, :cor2)
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
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2px 4px;
    color: #8c52ff;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1;
    gap: 2px;
    transition: transform 0.15s ease, color 0.15s ease;
}
.scratch-swap-btn:hover {
    color: #6d28d9;
    transform: scale(1.08);
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

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5 admin-form-group">
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
                                   placeholder="Ex: VIP Netherite" required>
                        </div>
                        <div class="col-md-3 admin-form-group">
                            <label class="d-flex align-items-center justify-content-between mb-1">
                                <span title="Cor aplicada nos termos entre colchetes [TAG] nos benefícios">Cor das Tags [ ]</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted" style="font-size: 0.72rem;" onclick="resetarCoresPadrao()">Cor padrão</button>
                            </label>
                            
                            <!-- Seletor Estilo Scratch -->
                            <div class="scratch-gradient-wrapper position-relative">
                                <div class="d-flex align-items-center gap-2 p-1 bg-white border rounded" style="min-height: 42px;">
                                    <!-- Swatch Cor 1 -->
                                    <div class="scratch-swatch-box" id="swatch-cor1-btn" onclick="abrirSeletorCor('cor1', event)" title="Cor inicial (clique para escolher)">
                                        <span class="scratch-swatch" id="preview-swatch-cor1" style="background-color: <?php echo htmlspecialchars($cor1, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                    </div>

                                    <!-- Swap Button (Scratch Style) -->
                                    <button type="button" class="scratch-swap-btn" onclick="trocarCoresGradiente(event)" title="Inverter cores (Swap)">
                                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                        <span>Swap</span>
                                    </button>

                                    <!-- Swatch Cor 2 -->
                                    <div class="scratch-swatch-box" id="swatch-cor2-btn" onclick="abrirSeletorCor('cor2', event)" title="Cor final (clique para escolher)">
                                        <span class="scratch-swatch" id="preview-swatch-cor2" style="background-color: <?php echo htmlspecialchars($cor2, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                    </div>

                                    <!-- Live Mini Gradient Bar -->
                                    <div class="flex-grow-1 px-1">
                                        <div id="scratch-mini-gradient-bar" class="rounded border" style="height: 18px; width: 100%;"></div>
                                    </div>
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
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 admin-form-group">
                            <label for="preco">Preço (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="preco" name="preco"
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

                    <!-- BENEFÍCIOS (BULLET POINTS) -->
                    <div class="admin-card p-3 mb-3 bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-primary me-1"></i> Benefícios do VIP (Bullet Points)</h6>
                                <p class="small text-muted mb-0">Cada item é exibido como um bullet point. Textos entre colchetes como <code>[NETHERITE]</code> ganham destaque de cor, e textos entre parênteses no final como <code>(Detalhes extras...)</code> exibem um botão de informação <code>(i)</code> interativo.</p>
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

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Cadastrar Pacote VIP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let activeCorSlot = 'cor1';

function atualizarMiniGradiente() {
    const c1 = document.getElementById('cor1')?.value || '#ffffff';
    const c2 = document.getElementById('cor2')?.value || '#ffffff';
    const bar = document.getElementById('scratch-mini-gradient-bar');
    if (bar) {
        bar.style.background = `linear-gradient(90deg, ${c1}, ${c2})`;
    }
    atualizarPreviewTags();
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
    if (input) input.value = hex;

    const swatch = document.getElementById('preview-swatch-' + activeCorSlot);
    if (swatch) swatch.style.backgroundColor = hex;

    atualizarMiniGradiente();
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
}

function resetarCoresPadrao() {
    const c1Input = document.getElementById('cor1');
    const c2Input = document.getElementById('cor2');
    if (c1Input) c1Input.value = '#ffffff';
    if (c2Input) c2Input.value = '#ffffff';

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
}

function atualizarPreviewTags() {
    const c1 = document.getElementById('cor1')?.value || '#ffffff';
    const c2 = document.getElementById('cor2')?.value || '#ffffff';
    const isCustom = (c1.toLowerCase() !== '#ffffff' || c2.toLowerCase() !== '#ffffff');

    const tagHighlights = document.querySelectorAll('.tag-preview-highlight');
    tagHighlights.forEach(el => {
        if (isCustom) {
            el.style.background = `linear-gradient(135deg, ${c1}, ${c2})`;
            el.style.webkitBackgroundClip = 'text';
            el.style.webkitTextFillColor = 'transparent';
            el.style.fontWeight = '700';
        } else {
            el.style.background = 'none';
            el.style.webkitBackgroundClip = 'unset';
            el.style.webkitTextFillColor = 'unset';
            el.style.fontWeight = '700';
        }
    });
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.scratch-gradient-wrapper')) {
        fecharSeletorCor();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    atualizarMiniGradiente();
});

function adicionarBeneficio() {
    const container = document.getElementById('vantagens-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 vantagem-row';
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>