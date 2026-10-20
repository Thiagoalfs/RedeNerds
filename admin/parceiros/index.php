<?php
$paginaAtiva = 'parceiros';
$tituloPagina = 'Parceiros';
require_once __DIR__ . "/../includes/admin_header.php";

// Auto-criação da tabela se não existir (uma vez por sessão)
if (empty($_SESSION['parceiros_schema_ok'])) {
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
        $_SESSION['parceiros_schema_ok'] = true;
    } catch (Exception $e) {
        error_log("Erro ao preparar schema de parceiros: " . $e->getMessage());
    }
}

$busca = trim($_GET['busca'] ?? '');
$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "nome LIKE :busca";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$parceiros = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM parceiros $whereSql ORDER BY nome ASC");
    $stmt->execute($params);
    $parceiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de Parceiros: " . $e->getMessage());
    $parceiros = [];
}

$totalParceiros = count($parceiros);

function gerarHtmlRedesParceiro(array $p): string {
    $html = '<div class="d-flex align-items-center gap-2 flex-wrap js-redes-container">';
    $temRede = false;

    if (!empty($p['youtube'])) {
        $temRede = true;
        $html .= '<a href="' . htmlspecialchars($p['youtube'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-pill" title="YouTube: ' . htmlspecialchars($p['youtube'], ENT_QUOTES, 'UTF-8') . '"><i class="fa-brands fa-youtube me-1"></i> YouTube</a>';
    }
    if (!empty($p['tiktok'])) {
        $temRede = true;
        $html .= '<a href="' . htmlspecialchars($p['tiktok'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-dark p-1 px-2 rounded-pill" title="TikTok: ' . htmlspecialchars($p['tiktok'], ENT_QUOTES, 'UTF-8') . '"><i class="fa-brands fa-tiktok me-1"></i> TikTok</a>';
    }
    if (!empty($p['instagram'])) {
        $temRede = true;
        $html .= '<a href="' . htmlspecialchars($p['instagram'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2 rounded-pill" style="color: #E4405F; border-color: #E4405F;" title="Instagram: ' . htmlspecialchars($p['instagram'], ENT_QUOTES, 'UTF-8') . '"><i class="fa-brands fa-instagram me-1"></i> Instagram</a>';
    }
    if (!empty($p['twitch'])) {
        $temRede = true;
        $html .= '<a href="' . htmlspecialchars($p['twitch'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-primary p-1 px-2 rounded-pill" style="color: #9146FF; border-color: #9146FF;" title="Twitch: ' . htmlspecialchars($p['twitch'], ENT_QUOTES, 'UTF-8') . '"><i class="fa-brands fa-twitch me-1"></i> Twitch</a>';
    }
    if (!empty($p['kick'])) {
        $temRede = true;
        $html .= '<a href="' . htmlspecialchars($p['kick'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-outline-success p-1 px-2 rounded-pill" style="color: #53FC18; border-color: #53FC18;" title="Kick: ' . htmlspecialchars($p['kick'], ENT_QUOTES, 'UTF-8') . '"><i class="fa-brands fa-kick me-1"></i> Kick</a>';
    }

    if (!$temRede) {
        $html .= '<span class="text-muted small fst-italic">Nenhuma rede social configurada</span>';
    }

    $html .= '</div>';
    return $html;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Parceiros e Criadores de Conteúdo</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo pluralizar($totalParceiros, 'parceiro', 'parceiros'); ?></span> <?php echo $totalParceiros === 1 ? 'cadastrado' : 'cadastrados'; ?> (exibidos em ordem alfabética na página inicial)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/#parceiros-section" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver no Site</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarParceiro">
            <i class="fa-solid fa-plus me-1"></i> Novo Parceiro
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- FILTROS E BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por nome do parceiro..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <?php if ($busca !== ''): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PARCEIROS -->
<div class="admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-parceiros">
                <thead>
                    <tr>
                        <th style="width: 70px;" class="text-center">Foto</th>
                        <th>Nome do Parceiro</th>
                        <th>Redes Sociais Conectadas</th>
                        <th style="width: 120px;" class="text-center">Status</th>
                        <th class="text-end" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-parceiros">
                    <?php if (empty($parceiros)): ?>
                        <tr id="linha-sem-parceiros">
                            <td colspan="5" class="text-center py-4 text-muted">
                                Nenhum parceiro encontrado<?php echo $busca ? ' para os termos buscados' : ''; ?>. Clique em "+ Novo Parceiro" para cadastrar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parceiros as $p): 
                            $fotoUrl = !empty($p['foto']) ? $p['foto'] : '/assets/images/logo.webp';
                        ?>
                            <tr id="parceiro-row-<?php echo (int)$p['id']; ?>"
                                data-id="<?php echo (int)$p['id']; ?>"
                                data-nome="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-foto="<?php echo htmlspecialchars($p['foto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-youtube="<?php echo htmlspecialchars($p['youtube'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-tiktok="<?php echo htmlspecialchars($p['tiktok'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-instagram="<?php echo htmlspecialchars($p['instagram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-twitch="<?php echo htmlspecialchars($p['twitch'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-kick="<?php echo htmlspecialchars($p['kick'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ativo="<?php echo (int)$p['ativo']; ?>"
                                data-criado-em="<?php echo htmlspecialchars(date('d/m/Y', strtotime($p['criado_em'])), ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="text-center">
                                    <img src="<?php echo htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="rounded-circle border shadow-sm js-parceiro-foto"
                                         style="width: 42px; height: 42px; object-fit: cover;"
                                         onerror="this.src='/assets/images/logo.webp'">
                                </td>
                                <td>
                                    <strong class="text-dark fs-6 js-parceiro-nome"><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="text-muted small js-parceiro-data">Cadastrado em <?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></div>
                                </td>
                                <td>
                                    <?php echo gerarHtmlRedesParceiro($p); ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($p['ativo'])): ?>
                                        <span class="badge-status ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge-status inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-parceiro" title="Editar Parceiro">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-action="async-toggle" 
                                                data-url="/admin/api/parceiros/toggle.php" 
                                                data-id="<?php echo (int)$p['id']; ?>"
                                                title="<?php echo !empty($p['ativo']) ? 'Desativar na Home' : 'Ativar na Home'; ?>">
                                            <i class="fa-solid <?php echo !empty($p['ativo']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-action="async-delete" 
                                                data-url="/admin/api/parceiros/deletar.php" 
                                                data-id="<?php echo (int)$p['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-confirm="Tem certeza que deseja remover o parceiro <?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?>?"
                                                title="Deletar Parceiro">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CRIAR PARCEIRO -->
<div class="modal fade" id="modalCriarParceiro" tabindex="-1" aria-labelledby="modalCriarParceiroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formCriarParceiro" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarParceiroLabel">Adicionar Novo Parceiro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="mb-3">
                        <label for="criar-nome" class="form-label fw-bold">Nome do Criador / Parceiro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="criar-nome" name="nome" placeholder="Ex: Danrique, Xonera Mil Grau, Matuzzo" required maxlength="100">
                        <div class="form-text small">Nome exibido no card oficial do parceiro no site.</div>
                    </div>

                    <!-- CONTAINER INLINE: FOTO DE PERFIL LADO A LADO COM REDES SOCIAIS -->
                    <div class="row g-4 pt-2">
                        <!-- COLUNA 1: FOTO DE PERFIL -->
                        <div class="col-12 col-md-4">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                Foto de Perfil / Avatar
                            </h6>

                            <div class="d-flex flex-column align-items-center text-center">
                                <div class="mb-3">
                                    <img id="criar-preview-foto" src="/assets/images/logo.webp" alt="Prévia" class="rounded-circle border shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='/assets/images/logo.webp'">
                                </div>
                                <div class="w-100">
                                    <label for="criar-foto-upload" class="form-label small fw-semibold mb-1 text-start d-block">Fazer Upload de Arquivo</label>
                                    <input type="file" class="form-control form-control-sm" id="criar-foto-upload" name="foto_upload" accept="image/png,image/jpeg,image/webp,image/gif,image/avif">
                                </div>
                            </div>
                        </div>

                        <!-- COLUNA 2: REDES SOCIAIS -->
                        <div class="col-12 col-md-8">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                Redes Sociais & Canais Oficiais
                            </h6>

                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <label for="criar-youtube" class="form-label small fw-bold">
                                        YouTube
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-brands fa-youtube text-danger"></i></span>
                                        <input type="text" class="form-control font-monospace" id="criar-youtube" name="youtube" placeholder="https://youtube.com/@Canal">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="criar-tiktok" class="form-label small fw-bold">
                                        TikTok
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-brands fa-tiktok"></i></span>
                                        <input type="text" class="form-control font-monospace" id="criar-tiktok" name="tiktok" placeholder="https://tiktok.com/@perfil">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="criar-instagram" class="form-label small fw-bold">
                                        Instagram
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="color: #E4405F;"><i class="fa-brands fa-instagram"></i></span>
                                        <input type="text" class="form-control font-monospace" id="criar-instagram" name="instagram" placeholder="https://instagram.com/perfil">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="criar-twitch" class="form-label small fw-bold">
                                        Twitch
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="color: #9146FF;"><i class="fa-brands fa-twitch"></i></span>
                                        <input type="text" class="form-control font-monospace" id="criar-twitch" name="twitch" placeholder="https://twitch.tv/canal">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="criar-kick" class="form-label small fw-bold">
                                        Kick
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text text-success"><i class="fa-brands fa-kick"></i></span>
                                        <input type="text" class="form-control font-monospace" id="criar-kick" name="kick" placeholder="https://kick.com/canal">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="mt-4 pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="criar-ativo" name="ativo" value="1" checked>
                            <label class="form-check-label fw-bold" for="criar-ativo">Parceiro Ativo (Exibir publicamente no site)</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm btn-submit" id="btn-submit-criar">
                        <i class="fa-solid fa-check me-1"></i> Cadastrar Parceiro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR PARCEIRO -->
<div class="modal fade" id="modalEditarParceiro" tabindex="-1" aria-labelledby="modalEditarParceiroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form id="formEditarParceiro" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarParceiroLabel">
                        Editar Parceiro: <span id="modalEditarParceiroNomeHeader" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="mb-3">
                        <label for="edit-nome" class="form-label fw-bold">Nome do Criador / Parceiro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-nome" name="nome" required maxlength="100">
                    </div>

                    <!-- CONTAINER INLINE: FOTO DE PERFIL LADO A LADO COM REDES SOCIAIS -->
                    <div class="row g-4 pt-2">
                        <!-- COLUNA 1: FOTO DE PERFIL -->
                        <div class="col-12 col-md-4">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                Foto de Perfil / Avatar
                            </h6>

                            <div class="d-flex flex-column align-items-center text-center">
                                <div class="mb-3">
                                    <img id="edit-preview-foto" src="/assets/images/logo.webp" alt="Prévia" class="rounded-circle border shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='/assets/images/logo.webp'">
                                </div>
                                <div class="w-100">
                                    <label for="edit-foto-upload" class="form-label small fw-semibold mb-1 text-start d-block">Substituir por Upload</label>
                                    <input type="file" class="form-control form-control-sm" id="edit-foto-upload" name="foto_upload" accept="image/png,image/jpeg,image/webp,image/gif,image/avif">
                                    <div id="edit-remover-foto-wrapper" class="form-check mt-2 text-start" style="display: none;">
                                        <input class="form-check-input" type="checkbox" id="edit-remover-foto" name="remover_foto" value="1">
                                        <label class="form-check-label small text-danger" for="edit-remover-foto">Remover foto atual</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COLUNA 2: REDES SOCIAIS -->
                        <div class="col-12 col-md-8">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                Redes Sociais & Canais Oficiais
                            </h6>

                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <label for="edit-youtube" class="form-label small fw-bold">
                                        YouTube
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-brands fa-youtube text-danger"></i></span>
                                        <input type="text" class="form-control font-monospace" id="edit-youtube" name="youtube" placeholder="https://youtube.com/@Canal">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="edit-tiktok" class="form-label small fw-bold">
                                        TikTok
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-brands fa-tiktok"></i></span>
                                        <input type="text" class="form-control font-monospace" id="edit-tiktok" name="tiktok" placeholder="https://tiktok.com/@perfil">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="edit-instagram" class="form-label small fw-bold">
                                        Instagram
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="color: #E4405F;"><i class="fa-brands fa-instagram"></i></span>
                                        <input type="text" class="form-control font-monospace" id="edit-instagram" name="instagram" placeholder="https://instagram.com/perfil">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="edit-twitch" class="form-label small fw-bold">
                                        Twitch
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="color: #9146FF;"><i class="fa-brands fa-twitch"></i></span>
                                        <input type="text" class="form-control font-monospace" id="edit-twitch" name="twitch" placeholder="https://twitch.tv/canal">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="edit-kick" class="form-label small fw-bold">
                                        Kick
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text text-success"><i class="fa-brands fa-kick"></i></span>
                                        <input type="text" class="form-control font-monospace" id="edit-kick" name="kick" placeholder="https://kick.com/canal">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="mt-4 pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit-ativo" name="ativo" value="1">
                            <label class="form-check-label fw-bold" for="edit-ativo">Parceiro Ativo (Exibir publicamente no site)</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm btn-submit" id="btn-submit-editar">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalCriarEl = document.getElementById('modalCriarParceiro');
    const modalEditarEl = document.getElementById('modalEditarParceiro');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    // Helper para escapar HTML seguro
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Helper para formatar data BR (d/m/Y)
    function formatarDataBR(dataStr) {
        if (!dataStr) {
            const hoje = new Date();
            const d = String(hoje.getDate()).padStart(2, '0');
            const m = String(hoje.getMonth() + 1).padStart(2, '0');
            const y = hoje.getFullYear();
            return `${d}/${m}/${y}`;
        }
        const d = new Date(dataStr);
        if (isNaN(d.getTime())) return dataStr;
        const dia = String(d.getDate()).padStart(2, '0');
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const ano = d.getFullYear();
        return `${dia}/${mes}/${ano}`;
    }

    // Previews de imagem para upload
    function setupImagePreview(fileInputId, previewImgId) {
        const fileInput = document.getElementById(fileInputId);
        const previewImg = document.getElementById(previewImgId);
        if (fileInput && previewImg) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files && e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        previewImg.src = evt.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }
    setupImagePreview('criar-foto-upload', 'criar-preview-foto');
    setupImagePreview('edit-foto-upload', 'edit-preview-foto');

    // Ao fechar modal de criação, limpa o formulário
    if (modalCriarEl) {
        modalCriarEl.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('formCriarParceiro');
            if (form) form.reset();
            const preview = document.getElementById('criar-preview-foto');
            if (preview) preview.src = '/assets/images/logo.webp';
            const switchAtivo = document.getElementById('criar-ativo');
            if (switchAtivo) switchAtivo.checked = true;
        });
    }

    // Gera HTML dos botões de redes sociais para a linha da tabela
    function gerarHtmlRedes(p) {
        let html = '<div class="d-flex align-items-center gap-2 flex-wrap js-redes-container">';
        let temRede = false;

        if (p.youtube) {
            temRede = true;
            html += `<a href="${escapeHtml(p.youtube)}" target="_blank" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-pill" title="YouTube: ${escapeHtml(p.youtube)}"><i class="fa-brands fa-youtube me-1"></i> YouTube</a>`;
        }
        if (p.tiktok) {
            temRede = true;
            html += `<a href="${escapeHtml(p.tiktok)}" target="_blank" class="btn btn-sm btn-outline-dark p-1 px-2 rounded-pill" title="TikTok: ${escapeHtml(p.tiktok)}"><i class="fa-brands fa-tiktok me-1"></i> TikTok</a>`;
        }
        if (p.instagram) {
            temRede = true;
            html += `<a href="${escapeHtml(p.instagram)}" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2 rounded-pill" style="color: #E4405F; border-color: #E4405F;" title="Instagram: ${escapeHtml(p.instagram)}"><i class="fa-brands fa-instagram me-1"></i> Instagram</a>`;
        }
        if (p.twitch) {
            temRede = true;
            html += `<a href="${escapeHtml(p.twitch)}" target="_blank" class="btn btn-sm btn-outline-primary p-1 px-2 rounded-pill" style="color: #9146FF; border-color: #9146FF;" title="Twitch: ${escapeHtml(p.twitch)}"><i class="fa-brands fa-twitch me-1"></i> Twitch</a>`;
        }
        if (p.kick) {
            temRede = true;
            html += `<a href="${escapeHtml(p.kick)}" target="_blank" class="btn btn-sm btn-outline-success p-1 px-2 rounded-pill" style="color: #53FC18; border-color: #53FC18;" title="Kick: ${escapeHtml(p.kick)}"><i class="fa-brands fa-kick me-1"></i> Kick</a>`;
        }

        if (!temRede) {
            html += '<span class="text-muted small fst-italic">Nenhuma rede social configurada</span>';
        }

        html += '</div>';
        return html;
    }

    // Abre o modal de edição carregando os dados da linha
    function abrirModalEditarParceiro(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-id').value = d.id || '';
        document.getElementById('edit-nome').value = d.nome || '';
        document.getElementById('modalEditarParceiroNomeHeader').textContent = d.nome || '';
        document.getElementById('edit-youtube').value = d.youtube || '';
        document.getElementById('edit-tiktok').value = d.tiktok || '';
        document.getElementById('edit-instagram').value = d.instagram || '';
        document.getElementById('edit-twitch').value = d.twitch || '';
        document.getElementById('edit-kick').value = d.kick || '';
        document.getElementById('edit-ativo').checked = (d.ativo === '1' || d.ativo === 1);

        const previewImg = document.getElementById('edit-preview-foto');
        const removerWrapper = document.getElementById('edit-remover-foto-wrapper');
        const removerCheck = document.getElementById('edit-remover-foto');
        const fileInput = document.getElementById('edit-foto-upload');

        if (fileInput) fileInput.value = '';
        if (removerCheck) removerCheck.checked = false;

        const fotoUrl = d.foto && d.foto.trim() !== '' ? d.foto : '/assets/images/logo.webp';
        if (previewImg) previewImg.src = fotoUrl;

        if (removerWrapper) {
            removerWrapper.style.display = (d.foto && d.foto.trim() !== '') ? 'block' : 'none';
        }

        if (bsModalEditar) {
            bsModalEditar.show();
        }
    }

    // Delegação de clique no botão de editar
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-parceiro');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarParceiro(row);
    });

    // Atualiza contadores dinâmicos do cabeçalho
    function atualizarContador(delta = 0) {
        const counters = document.querySelectorAll('[data-item-counter]');
        const tbody = document.getElementById('lista-parceiros');
        const rows = tbody ? tbody.querySelectorAll('tr[data-id]') : [];
        const total = rows.length;
        const texto = total + ' ' + (total === 1 ? 'parceiro' : 'parceiros');
        counters.forEach(c => {
            c.textContent = texto;
        });
    }

    // Cria elemento <tr> completo para inserção na tabela
    function criarLinhaTabela(p) {
        const tr = document.createElement('tr');
        tr.id = `parceiro-row-${p.id}`;
        tr.dataset.id = p.id;
        tr.dataset.nome = p.nome || '';
        tr.dataset.foto = p.foto || '';
        tr.dataset.youtube = p.youtube || '';
        tr.dataset.tiktok = p.tiktok || '';
        tr.dataset.instagram = p.instagram || '';
        tr.dataset.twitch = p.twitch || '';
        tr.dataset.kick = p.kick || '';
        tr.dataset.ativo = p.ativo ? '1' : '0';
        tr.dataset.criadoEm = formatarDataBR(p.criado_em);

        const fotoSrc = p.foto || '/assets/images/logo.webp';
        const isAtivo = Boolean(Number(p.ativo));

        tr.innerHTML = `
            <td class="text-center">
                <img src="${escapeHtml(fotoSrc)}" 
                     alt="${escapeHtml(p.nome)}" 
                     class="rounded-circle border shadow-sm js-parceiro-foto"
                     style="width: 42px; height: 42px; object-fit: cover;"
                     onerror="this.src='/assets/images/logo.webp'">
            </td>
            <td>
                <strong class="text-dark fs-6 js-parceiro-nome">${escapeHtml(p.nome)}</strong>
                <div class="text-muted small js-parceiro-data">Cadastrado em ${tr.dataset.criadoEm}</div>
            </td>
            <td>
                ${gerarHtmlRedes(p)}
            </td>
            <td class="text-center">
                <span class="badge-status ${isAtivo ? 'ativo' : 'inativo'}">${isAtivo ? 'Ativo' : 'Inativo'}</span>
            </td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-parceiro" title="Editar Parceiro">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-action="async-toggle" 
                            data-url="/admin/api/parceiros/toggle.php" 
                            data-id="${p.id}"
                            title="${isAtivo ? 'Desativar na Home' : 'Ativar na Home'}">
                        <i class="fa-solid ${isAtivo ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/parceiros/deletar.php" 
                            data-id="${p.id}"
                            data-name="${escapeHtml(p.nome)}"
                            data-confirm="Tem certeza que deseja remover o parceiro ${escapeHtml(p.nome)}?"
                            title="Deletar Parceiro">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // Atualiza linha existente com novos dados retornados pela API
    function atualizarLinhaTabela(p) {
        const tr = document.getElementById(`parceiro-row-${p.id}`);
        if (!tr) return;

        tr.dataset.nome = p.nome || '';
        tr.dataset.foto = p.foto || '';
        tr.dataset.youtube = p.youtube || '';
        tr.dataset.tiktok = p.tiktok || '';
        tr.dataset.instagram = p.instagram || '';
        tr.dataset.twitch = p.twitch || '';
        tr.dataset.kick = p.kick || '';
        tr.dataset.ativo = p.ativo ? '1' : '0';

        const fotoImg = tr.querySelector('.js-parceiro-foto');
        if (fotoImg) {
            fotoImg.src = p.foto || '/assets/images/logo.webp';
            fotoImg.alt = p.nome || '';
        }

        const nomeEl = tr.querySelector('.js-parceiro-nome');
        if (nomeEl) nomeEl.textContent = p.nome || '';

        const redesCell = tr.cells[2];
        if (redesCell) redesCell.innerHTML = gerarHtmlRedes(p);

        const isAtivo = Boolean(Number(p.ativo));
        const badgeStatus = tr.querySelector('.badge-status');
        if (badgeStatus) {
            badgeStatus.className = `badge-status ${isAtivo ? 'ativo' : 'inativo'}`;
            badgeStatus.textContent = isAtivo ? 'Ativo' : 'Inativo';
        }

        const btnToggle = tr.querySelector('[data-action="async-toggle"]');
        if (btnToggle) {
            btnToggle.title = isAtivo ? 'Desativar na Home' : 'Ativar na Home';
            btnToggle.innerHTML = `<i class="fa-solid ${isAtivo ? 'fa-eye' : 'fa-eye-slash'}"></i>`;
        }

        const btnDelete = tr.querySelector('[data-action="async-delete"]');
        if (btnDelete) {
            btnDelete.dataset.name = p.nome || '';
            btnDelete.dataset.confirm = `Tem certeza que deseja remover o parceiro ${p.nome}?`;
        }

        // Se o nome foi alterado, reordena em ordem alfabética no DOM
        reordenarLinhaAlfabetica(tr);

        tr.classList.add('table-row-highlight');
        setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
    }

    // Insere linha mantendo a ordenação alfabética por nome
    function reordenarLinhaAlfabetica(tr) {
        const tbody = document.getElementById('lista-parceiros');
        if (!tbody) return;

        const nomeAtual = (tr.dataset.nome || '').toLowerCase();
        const linhas = Array.from(tbody.querySelectorAll('tr[data-id]')).filter(r => r !== tr);

        let inserido = false;
        for (const outraLinha of linhas) {
            const outroNome = (outraLinha.dataset.nome || '').toLowerCase();
            if (nomeAtual.localeCompare(outroNome, 'pt-BR') < 0) {
                tbody.insertBefore(tr, outraLinha);
                inserido = true;
                break;
            }
        }
        if (!inserido) {
            tbody.appendChild(tr);
        }
    }

    // SUBMIT: CRIAR PARCEIRO (AJAX)
    const formCriar = document.getElementById('formCriarParceiro');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cadastrando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/parceiros/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                const previewImg = document.getElementById('criar-preview-foto');
                if (previewImg) previewImg.src = '/assets/images/logo.webp';

                // Remove linha de tabela vazia se presente
                const semParceiros = document.getElementById('linha-sem-parceiros');
                if (semParceiros) semParceiros.remove();

                // Cria e insere nova linha
                if (res.parceiro) {
                    const novaLinha = criarLinhaTabela(res.parceiro);
                    reordenarLinhaAlfabetica(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContador();
                AdminToast.success(res.mensagem || 'Parceiro cadastrado com sucesso!');
            } catch (err) {
                console.error('Erro ao cadastrar parceiro:', err);
                AdminToast.error(err.message || 'Erro ao cadastrar parceiro.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR PARCEIRO (AJAX)
    const formEditar = document.getElementById('formEditarParceiro');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/parceiros/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.parceiro) {
                    atualizarLinhaTabela(res.parceiro);
                }

                AdminToast.success(res.mensagem || 'Parceiro atualizado com sucesso!');
            } catch (err) {
                console.error('Erro ao salvar parceiro:', err);
                AdminToast.error(err.message || 'Erro ao salvar parceiro.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // Tratamento de parâmetros de URL para abertura automática de modal (?action=novo ou ?edit=ID)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'novo' && bsModalCriar) {
        bsModalCriar.show();
    } else if (urlParams.has('edit')) {
        const editId = urlParams.get('edit');
        const row = document.getElementById(`parceiro-row-${editId}`);
        if (row) {
            abrirModalEditarParceiro(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>