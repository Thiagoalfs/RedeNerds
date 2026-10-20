<?php
/**
 * admin/includes/wiki_editor_toolbar.php
 * Barra de ferramentas visual e gerenciador de uploads de imagem para o editor Markdown da Wiki.
 */
$toolbarTarget = $toolbarTarget ?? 'conteudo';
$toolbarId = 'wiki-toolbar-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $toolbarTarget);
$fileInputId = 'inputUploadImagemWiki_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $toolbarTarget);
$feedbackId = 'uploadFeedbackWiki_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $toolbarTarget);
$btnUploadId = 'btnUploadImgWiki_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $toolbarTarget);
?>
<div id="<?php echo $toolbarId; ?>" class="wiki-editor-toolbar mb-2 d-flex flex-wrap gap-1 p-2 bg-light border rounded align-items-center" data-editor-target="<?php echo htmlspecialchars($toolbarTarget, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('**', '**', 'texto em negrito', this)" title="Negrito (Ctrl+B)">
            <i class="fa-solid fa-bold"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('*', '*', 'texto em itálico', this)" title="Itálico (Ctrl+I)">
            <i class="fa-solid fa-italic"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('~~', '~~', 'texto tachado', this)" title="Tachado">
            <i class="fa-solid fa-strikethrough"></i>
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('## ', 'Título da Seção', this)" title="Título de Seção (H2)">
            <i class="fa-solid fa-heading"></i> 2
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('### ', 'Subtítulo', this)" title="Subtítulo (H3)">
            <i class="fa-solid fa-heading"></i> 3
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('- ', 'Item da lista', this)" title="Lista com Marcadores">
            <i class="fa-solid fa-list-ul"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('1. ', 'Primeiro item', this)" title="Lista Numerada">
            <i class="fa-solid fa-list-ol"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirTabela(this)" title="Tabela">
            <i class="fa-solid fa-table"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirBlocoCodigo(this)" title="Bloco de Código">
            <i class="fa-solid fa-code"></i>
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-success editor-btn" onclick="inserirCallout('TIP', 'Escreva uma dica valiosa aqui...', this)" title="Caixa de Dica">
            <i class="fa-solid fa-lightbulb"></i> Dica
        </button>
        <button type="button" class="btn btn-outline-warning editor-btn" onclick="inserirCallout('WARNING', 'Escreva o aviso de atenção aqui...', this)" title="Caixa de Aviso">
            <i class="fa-solid fa-triangle-exclamation"></i> Aviso
        </button>
        <button type="button" class="btn btn-outline-danger editor-btn" onclick="inserirCallout('DANGER', 'Cuidado com itens banidos ou regras...', this)" title="Caixa de Perigo">
            <i class="fa-solid fa-shield-halved"></i> Perigo
        </button>
        <button type="button" class="btn btn-outline-primary editor-btn" onclick="inserirCallout('COMMAND', '/comando argumento1 argumento2', this)" title="Comando In-game">
            <i class="fa-solid fa-terminal"></i> Comando
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm ms-auto">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLink(this)" title="Inserir Link">
            <i class="fa-solid fa-link"></i>
        </button>
        <button type="button" class="btn btn-primary editor-btn d-flex align-items-center gap-1" id="<?php echo $btnUploadId; ?>" onclick="document.getElementById('<?php echo $fileInputId; ?>').click()" title="Fazer Upload de Imagem">
            <i class="fa-solid fa-cloud-arrow-up"></i> <span>Imagem</span>
        </button>
    </div>
    <input type="file" id="<?php echo $fileInputId; ?>" class="inputUploadImagemWiki" accept="image/png, image/jpeg, image/webp, image/gif" style="display: none;" onchange="if(this.files.length) fazerUploadImagemWiki(this.files[0], this)">
</div>

<div id="<?php echo $feedbackId; ?>" class="uploadFeedbackWiki small text-primary mb-2 d-none align-items-center gap-2">
    <div class="spinner-border spinner-border-sm" role="status"></div>
    <span>Enviando e otimizando imagem...</span>
</div>

<?php if (!defined('WIKI_EDITOR_TOOLBAR_JS_LOADED')): define('WIKI_EDITOR_TOOLBAR_JS_LOADED', true); ?>
<script>
function getEditorTextarea(ref = null) {
    if (typeof ref === 'string') {
        const el = document.getElementById(ref);
        if (el) return el;
    } else if (ref instanceof HTMLElement) {
        const toolbar = ref.closest('.wiki-editor-toolbar');
        if (toolbar && toolbar.dataset.editorTarget) {
            const el = document.getElementById(toolbar.dataset.editorTarget);
            if (el) return el;
        }
    }
    const openModal = document.querySelector('.modal.show');
    if (openModal) {
        const modalTextarea = openModal.querySelector('textarea[name="conteudo"]');
        if (modalTextarea) return modalTextarea;
    }
    return document.getElementById('conteudo') || document.querySelector('textarea[name="conteudo"]');
}

function inserirFormatacao(delimitadorInicio, delimitadorFim, textoPadrao, ref = null) {
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selection = textarea.value.substring(start, end);
    const textToInsert = selection ? (delimitadorInicio + selection + delimitadorFim) : (delimitadorInicio + textoPadrao + delimitadorFim);

    textarea.focus();
    document.execCommand('insertText', false, textToInsert);
    if (!selection) {
        textarea.setSelectionRange(start + delimitadorInicio.length, start + delimitadorInicio.length + textoPadrao.length);
    }
}

function inserirLinha(prefixo, textoPadrao, ref = null) {
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selection = textarea.value.substring(start, end);
    const needsNewline = (start > 0 && textarea.value[start - 1] !== '\n') ? '\n' : '';
    const textToInsert = needsNewline + prefixo + (selection || textoPadrao) + '\n';

    textarea.focus();
    document.execCommand('insertText', false, textToInsert);
}

function inserirTabela(ref = null) {
    const tabela = `\n| Item / Recurso | Descrição | Nível |\n| --- | --- | --- |\n| Exemplo 1 | Descrição do item | Básico |\n| Exemplo 2 | Outra informação útil | Avançado |\n\n`;
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, tabela);
}

function inserirBlocoCodigo(ref = null) {
    const bloco = `\n\`\`\`text\n# Cole seu código, JSON ou configuração aqui\n\`\`\`\n\n`;
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, bloco);
}

function inserirCallout(tipo, textoPadrao, ref = null) {
    const callout = `\n> [!${tipo}]\n> ${textoPadrao}\n\n`;
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, callout);
}

function inserirLink(ref = null) {
    const url = prompt('Digite a URL do link (ex: https://...):');
    if (!url) return;
    const textarea = getEditorTextarea(ref);
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selection = textarea.value.substring(start, end) || 'Clique aqui';
    const linkMd = `[${selection}](${url})`;
    textarea.focus();
    document.execCommand('insertText', false, linkMd);
}

async function fazerUploadImagemWiki(file, ref = null) {
    if (!file) return;

    let toolbar = null;
    if (ref instanceof HTMLElement) {
        toolbar = ref.closest('.wiki-editor-toolbar') || ref.parentElement;
    }
    const targetTextarea = getEditorTextarea(ref);
    const feedback = toolbar ? (toolbar.nextElementSibling?.classList.contains('uploadFeedbackWiki') ? toolbar.nextElementSibling : document.querySelector('.uploadFeedbackWiki')) : document.querySelector('.uploadFeedbackWiki');
    const btn = toolbar ? toolbar.querySelector('.btnUploadImgWiki, [id^="btnUploadImgWiki"]') : null;

    if (feedback) feedback.classList.remove('d-none');
    if (btn) btn.disabled = true;

    const formData = new FormData();
    formData.append('imagem', file);
    formData.append('csrf_token', '<?php echo isset($csrfToken) ? htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') : ''; ?>');

    try {
        const res = await fetch('/admin/api/wiki/imagem_upload.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success && data.url) {
            if (targetTextarea) {
                const alt = data.filename || 'Imagem';
                const markdownImg = `\n![${alt}](${data.url})\n\n`;
                targetTextarea.focus();
                document.execCommand('insertText', false, markdownImg);
            }
        } else {
            const msg = 'Erro no envio da imagem: ' + (data.erro || 'Falha ao processar arquivo');
            if (window.AdminToast) { window.AdminToast.error(msg); } else { alert(msg); }
        }
    } catch (err) {
        console.error('Erro no upload:', err);
        const msg = 'Erro ao enviar imagem. Verifique sua conexão.';
        if (window.AdminToast) { window.AdminToast.error(msg); } else { alert(msg); }
    } finally {
        if (feedback) feedback.classList.add('d-none');
        if (btn) btn.disabled = false;
        if (ref instanceof HTMLInputElement) ref.value = '';
    }
}

// Drag & Drop e Colar imagem direto nos textareas de wiki
document.addEventListener('DOMContentLoaded', () => {
    function setupWikiTextarea(textarea) {
        if (!textarea || textarea.dataset.wikiInitialized) return;
        textarea.dataset.wikiInitialized = 'true';

        // Drag & Drop
        textarea.addEventListener('dragover', (e) => {
            e.preventDefault();
            textarea.classList.add('border-primary');
        });

        textarea.addEventListener('dragleave', () => {
            textarea.classList.remove('border-primary');
        });

        textarea.addEventListener('drop', (e) => {
            e.preventDefault();
            textarea.classList.remove('border-primary');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                if (file.type.startsWith('image/')) {
                    fazerUploadImagemWiki(file, textarea);
                }
            }
        });

        // Colar Imagem (Ctrl + V)
        textarea.addEventListener('paste', (e) => {
            if (e.clipboardData && e.clipboardData.items) {
                for (let i = 0; i < e.clipboardData.items.length; i++) {
                    const item = e.clipboardData.items[i];
                    if (item.type.indexOf('image') !== -1) {
                        const file = item.getAsFile();
                        if (file) {
                            e.preventDefault();
                            fazerUploadImagemWiki(file, textarea);
                            break;
                        }
                    }
                }
            }
        });

        // Atalhos de teclado comuns
        textarea.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'b' || e.key === 'B') {
                    e.preventDefault();
                    inserirFormatacao('**', '**', 'texto em negrito', textarea);
                } else if (e.key === 'i' || e.key === 'I') {
                    e.preventDefault();
                    inserirFormatacao('*', '*', 'texto em itálico', textarea);
                }
            }
        });
    }

    document.querySelectorAll('textarea[name="conteudo"]').forEach(setupWikiTextarea);
});
</script>
<?php endif; ?>
