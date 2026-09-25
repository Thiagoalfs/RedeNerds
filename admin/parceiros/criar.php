<?php
require_once __DIR__ . "/../sessao.php";
require_once __DIR__ . "/parceiro_upload.php";

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

$erro = null;
$nome = '';
$foto = '';
$youtube = '';
$tiktok = '';
$instagram = '';
$twitch = '';
$kick = '';
$ativo = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $erro = "Token CSRF inválido ou expirado. Recarregue a página e tente novamente.";
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        $tiktok = trim($_POST['tiktok'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $twitch = trim($_POST['twitch'] ?? '');
        $kick = trim($_POST['kick'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome)) {
            $erro = "O nome do parceiro é obrigatório.";
        } elseif (mb_strlen($nome) > 100) {
            $erro = "O nome do parceiro pode ter no máximo 100 caracteres.";
        } else {
            // Função para normalizar URLs se o usuário digitou sem https://
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

            // Processa upload ou URL de foto
            list($fotoSalvar, $erroFoto) = processarFotoParceiro(null, $nome);

            if ($erroFoto) {
                $erro = $erroFoto;
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO parceiros (nome, foto, youtube, tiktok, instagram, twitch, kick, ativo) 
                        VALUES (:nome, :foto, :youtube, :tiktok, :instagram, :twitch, :kick, :ativo)
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':foto' => $fotoSalvar,
                        ':youtube' => $youtube,
                        ':tiktok' => $tiktok,
                        ':instagram' => $instagram,
                        ':twitch' => $twitch,
                        ':kick' => $kick,
                        ':ativo' => $ativo
                    ]);

                    require_once __DIR__ . "/../../api/cache_helper.php";
                    invalidarCache('parceiros');

                    header("Location: index.php?msg=" . urlencode("Parceiro '{$nome}' cadastrado com sucesso!"));
                    exit;
                } catch (PDOException $e) {
                    $erro = "Erro ao salvar no banco de dados: " . $e->getMessage();
                }
            }
        }
    }
}

$paginaAtiva = 'parceiros';
$tituloPagina = 'Novo Parceiro';
require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h5 class="admin-card-title mb-0">
                    <i class="fa-solid fa-user-plus text-success me-2"></i> Adicionar Novo Parceiro
                </h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Voltar</a>
            </div>

            <div class="p-4">
                <?php if ($erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="criar.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="mb-3">
                        <label for="nome" class="form-label fw-bold">Nome do Criador / Parceiro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Danrique, Xonera Mil Grau, Matuzzo" required autofocus maxlength="100">
                        <div class="form-text small">Nome exibido no card oficial do parceiro no site.</div>
                    </div>

                    <!-- FOTO DO PARCEIRO -->
                    <div class="mb-3">
                        <label class="form-label fw-bold d-block mb-1">
                            <i class="fa-solid fa-image text-primary me-1"></i> Foto de Perfil / Avatar
                        </label>
                        <div class="form-text small mb-2">
                            Envie um arquivo de imagem (PNG, JPG, WEBP). O sistema converterá automaticamente para <strong>.webp</strong> otimizado.
                        </div>

                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-sm-auto text-center">
                                <img id="preview-foto" src="/assets/images/logo.webp" alt="Prévia" class="rounded-circle border shadow-sm" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.src='/assets/images/logo.webp'">
                            </div>
                            <div class="col">
                                <label for="foto_upload" class="form-label small fw-semibold mb-1">Fazer Upload de Arquivo</label>
                                <input type="file" class="form-control" id="foto_upload" name="foto_upload" accept="image/png,image/jpeg,image/webp,image/gif,image/avif">
                            </div>
                        </div>
                    </div>

                    <!-- REDES SOCIAIS -->
                    <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                        <i class="fa-solid fa-share-nodes text-primary me-2"></i> Redes Sociais & Canais Oficiais
                    </h6>
                    <p class="text-muted small mb-3">Preencha apenas as redes que o parceiro possui. Campos vazios não serão exibidos na página inicial.</p>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="youtube" class="form-label small fw-bold">
                                <i class="fa-brands fa-youtube text-danger me-1"></i> YouTube
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-brands fa-youtube text-danger"></i></span>
                                <input type="text" class="form-control font-monospace" id="youtube" name="youtube" value="<?php echo htmlspecialchars($youtube, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.youtube.com/@Canal">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="tiktok" class="form-label small fw-bold">
                                <i class="fa-brands fa-tiktok text-dark me-1"></i> TikTok
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-brands fa-tiktok"></i></span>
                                <input type="text" class="form-control font-monospace" id="tiktok" name="tiktok" value="<?php echo htmlspecialchars($tiktok, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.tiktok.com/@perfil">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="instagram" class="form-label small fw-bold" style="color: #E4405F;">
                                <i class="fa-brands fa-instagram me-1"></i> Instagram
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="color: #E4405F;"><i class="fa-brands fa-instagram"></i></span>
                                <input type="text" class="form-control font-monospace" id="instagram" name="instagram" value="<?php echo htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.instagram.com/perfil">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="twitch" class="form-label small fw-bold" style="color: #9146FF;">
                                <i class="fa-brands fa-twitch me-1"></i> Twitch
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="color: #9146FF;"><i class="fa-brands fa-twitch"></i></span>
                                <input type="text" class="form-control font-monospace" id="twitch" name="twitch" value="<?php echo htmlspecialchars($twitch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.twitch.tv/canal">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="kick" class="form-label small fw-bold text-success">
                                <i class="fa-brands fa-kick me-1"></i> Kick
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text text-success"><i class="fa-brands fa-kick"></i></span>
                                <input type="text" class="form-control font-monospace" id="kick" name="kick" value="<?php echo htmlspecialchars($kick, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://kick.com/canal">
                            </div>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="mt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?php echo $ativo ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="ativo">Parceiro Ativo (Exibir publicamente no site)</label>
                        </div>
                    </div>

                    <!-- BOTÕES DE SUBMIT -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="fa-solid fa-check me-1"></i> Salvar Parceiro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('foto_upload');
    const previewImg = document.getElementById('preview-foto');

    if (fileInput && previewImg) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    previewImg.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>