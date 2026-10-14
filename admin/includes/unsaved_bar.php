<?php
/**
 * admin/includes/unsaved_bar.php
 * Barra flutuante de "alterações não salvas" (estilo Discord), fixa no
 * rodapé do viewport. Só aparece quando o formulário principal da página
 * (marcado com a classe "js-unsaved-form") tem alterações pendentes.
 *
 * Incluir ao final das páginas de criar/editar, logo antes do admin_footer.
 */
?>
<div class="unsaved-bar" id="unsavedBar" role="status" aria-live="polite" aria-hidden="true">
    <div class="unsaved-bar-text">
        <span class="unsaved-bar-title">Alterações não salvas</span>
    </div>
    <div class="unsaved-bar-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="unsavedBarDiscard">Descartar alterações</button>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="unsavedBarSave">
            <i class="fa-solid fa-floppy-disk me-1"></i> Salvar
        </button>
    </div>
</div>

<script src="/admin/js/unsaved_bar.js?v=<?php echo file_exists(__DIR__ . '/../js/unsaved_bar.js') ? filemtime(__DIR__ . '/../js/unsaved_bar.js') : '1'; ?>"></script>
