<?php
/**
 * admin/includes/wiki_editor_toolbar.php
 * Barra de ferramentas visual e gerenciador de uploads de imagem para o editor Markdown da Wiki.
 */
?>
<div class="wiki-editor-toolbar mb-2 d-flex flex-wrap gap-1 p-2 bg-light border rounded align-items-center">
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('**', '**', 'texto em negrito')" title="Negrito (Ctrl+B)">
            <i class="fa-solid fa-bold"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('*', '*', 'texto em itálico')" title="Itálico (Ctrl+I)">
            <i class="fa-solid fa-italic"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirFormatacao('~~', '~~', 'texto tachado')" title="Tachado">
            <i class="fa-solid fa-strikethrough"></i>
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('## ', 'Título da Seção')" title="Título de Seção (H2)">
            <i class="fa-solid fa-heading"></i> 2
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('### ', 'Subtítulo')" title="Subtítulo (H3)">
            <i class="fa-solid fa-heading"></i> 3
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('- ', 'Item da lista')" title="Lista com Marcadores">
            <i class="fa-solid fa-list-ul"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLinha('1. ', 'Primeiro item')" title="Lista Numerada">
            <i class="fa-solid fa-list-ol"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirTabela()" title="Tabela">
            <i class="fa-solid fa-table"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirBlocoCodigo()" title="Bloco de Código">
            <i class="fa-solid fa-code"></i>
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-success editor-btn" onclick="inserirCallout('TIP', 'Escreva uma dica valiosa aqui...')" title="Caixa de Dica">
            <i class="fa-solid fa-lightbulb"></i> Dica
        </button>
        <button type="button" class="btn btn-outline-warning editor-btn" onclick="inserirCallout('WARNING', 'Escreva o aviso de atenção aqui...')" title="Caixa de Aviso">
            <i class="fa-solid fa-triangle-exclamation"></i> Aviso
        </button>
        <button type="button" class="btn btn-outline-danger editor-btn" onclick="inserirCallout('DANGER', 'Cuidado com itens banidos ou regras...')" title="Caixa de Perigo">
            <i class="fa-solid fa-shield-halved"></i> Perigo
        </button>
        <button type="button" class="btn btn-outline-primary editor-btn" onclick="inserirCallout('COMMAND', '/comando argumento1 argumento2')" title="Comando In-game">
            <i class="fa-solid fa-terminal"></i> Comando
        </button>
    </div>

    <div class="vr my-1 mx-1 d-none d-sm-block"></div>

    <div class="btn-group btn-group-sm ms-auto">
        <button type="button" class="btn btn-outline-secondary editor-btn" onclick="inserirLink()" title="Inserir Link">
            <i class="fa-solid fa-link"></i>
        </button>
        <button type="button" class="btn btn-primary editor-btn d-flex align-items-center gap-1" id="btnUploadImgWiki" onclick="document.getElementById('inputUploadImagemWiki').click()" title="Fazer Upload de Imagem">
            <i class="fa-solid fa-cloud-arrow-up"></i> <span>Imagem</span>
        </button>
    </div>
    <input type="file" id="inputUploadImagemWiki" accept="image/png, image/jpeg, image/webp, image/gif" style="display: none;" onchange="if(this.files.length) fazerUploadImagemWiki(this.files[0])">
</div>

<div id="uploadFeedbackWiki" class="small text-primary mb-2 d-none align-items-center gap-2">
    <div class="spinner-border spinner-border-sm" role="status"></div>
    <span>Enviando e otimizando imagem para o artigo...</span>
</div>

<script>
function getEditorTextarea() {
    return document.getElementById('conteudo');
}

function inserirFormatacao(delimitadorInicio, delimitadorFim, textoPadrao) {
    const textarea = getEditorTextarea();
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

function inserirLinha(prefixo, textoPadrao) {
    const textarea = getEditorTextarea();
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selection = textarea.value.substring(start, end);
    const needsNewline = (start > 0 && textarea.value[start - 1] !== '\n') ? '\n' : '';
    const textToInsert = needsNewline + prefixo + (selection || textoPadrao) + '\n';

    textarea.focus();
    document.execCommand('insertText', false, textToInsert);
}

function inserirTabela() {
    const tabela = `\n| Item / Recurso | Descrição | Nível |\n| --- | --- | --- |\n| Exemplo 1 | Descrição do item | Básico |\n| Exemplo 2 | Outra informação útil | Avançado |\n\n`;
    const textarea = getEditorTextarea();
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, tabela);
}

function inserirBlocoCodigo() {
    const bloco = `\n\`\`\`text\n# Cole seu código, JSON ou configuração aqui\n\`\`\`\n\n`;
    const textarea = getEditorTextarea();
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, bloco);
}

function inserirCallout(tipo, textoPadrao) {
    const callout = `\n> [!${tipo}]\n> ${textoPadrao}\n\n`;
    const textarea = getEditorTextarea();
    if (!textarea) return;
    textarea.focus();
    document.execCommand('insertText', false, callout);
}

function inserirLink() {
    const url = prompt('Digite a URL do link (ex: https://...):');
    if (!url) return;
    const textarea = getEditorTextarea();
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selection = textarea.value.substring(start, end) || 'Clique aqui';
    const linkMd = `[${selection}](${url})`;
    textarea.focus();
    document.execCommand('insertText', false, linkMd);
}

async function fazerUploadImagemWiki(file) {
    if (!file) return;

    const feedback = document.getElementById('uploadFeedbackWiki');
    const btn = document.getElementById('btnUploadImgWiki');

    if (feedback) feedback.classList.remove('d-none');
    if (btn) btn.disabled = true;

    const formData = new FormData();
    formData.append('imagem', file);

    try {
        const res = await fetch('/admin/api/wiki/imagem_upload.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success && data.url) {
            const textarea = getEditorTextarea();
            if (textarea) {
                const alt = data.filename || 'Imagem do Artigo';
                const markdownImg = `\n![${alt}](${data.url})\n\n`;
                textarea.focus();
                document.execCommand('insertText', false, markdownImg);
            }
        } else {
            alert('Erro no envio da imagem: ' + (data.erro || 'Falha ao processar arquivo'));
        }
    } catch (err) {
        console.error('Erro no upload:', err);
        alert('Erro ao enviar imagem. Verifique sua conexão.');
    } finally {
        if (feedback) feedback.classList.add('d-none');
        if (btn) btn.disabled = false;
        const input = document.getElementById('inputUploadImagemWiki');
        if (input) input.value = '';
    }
}

// Drag & Drop e Colar imagem direto no textarea
document.addEventListener('DOMContentLoaded', () => {
    const textarea = getEditorTextarea();
    if (!textarea) return;

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
                fazerUploadImagemWiki(file);
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
                        fazerUploadImagemWiki(file);
                        break;
                    }
                }
            }
        }
    });

    // Atalhos de teclado comuns no textarea
    textarea.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'b' || e.key === 'B') {
                e.preventDefault();
                inserirFormatacao('**', '**', 'texto em negrito');
            } else if (e.key === 'i' || e.key === 'I') {
                e.preventDefault();
                inserirFormatacao('*', '*', 'texto em itálico');
            }
        }
    });
});
</script>
