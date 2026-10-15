/**
 * unsaved_bar.js - Barra flutuante de alterações não salvas (Rede Nerds)
 * Mostra uma barra fixa no rodapé (estilo Discord) sempre que o formulário
 * principal da página (form.js-unsaved-form) possui alterações pendentes.
 *
 * - Descartar: volta todos os campos aos valores carregados do banco
 * - Salvar: dispara o submit nativo do formulário (com validação HTML5)
 * - Aviso de beforeunload enquanto houver alterações não salvas
 */
(function (window, document) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form.js-unsaved-form');
    var bar = document.getElementById('unsavedBar');
    if (!form || !bar) return;

    var btnSave = document.getElementById('unsavedBarSave');
    var btnDiscard = document.getElementById('unsavedBarDiscard');
    if (!btnSave || !btnDiscard) return;

    var savedState = '';
    var visible = false;
    var submitting = false;
    var initialStates = [];
    var previewStates = [];

    // Captura o estado de todos os campos (indexado, para lidar com linhas
    // dinâmicas adicionadas/removidas via JS, ex: vantagens e features)
    function capture() {
      var fields = form.querySelectorAll('input, select, textarea');
      var state = [];
      for (var i = 0; i < fields.length; i++) {
        var el = fields[i];
        var type = (el.type || '').toLowerCase();
        if (type === 'file') {
          state.push(i + ':' + (el.files && el.files.length ? el.files[0].name + '@' + el.files[0].size : ''));
        } else if (type === 'checkbox' || type === 'radio') {
          state.push(i + ':' + (el.checked ? '1' : '0'));
        } else {
          state.push(i + ':' + el.value);
        }
      }
      return state.join('|');
    }

    // Guarda o valor inicial de cada campo para o descarte.
    // Necessário porque form.reset() NÃO restaura inputs hidden
    // (ex: seletores de cor dos VIPs) nem remove linhas criadas via JS.
    function snapshotFields() {
      initialStates = [];
      var fields = form.querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        var el = fields[i];
        var type = (el.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
          initialStates.push({ el: el, type: type, value: el.checked });
        } else {
          initialStates.push({ el: el, type: type, value: el.value });
        }
      }
    }

    // Guarda o HTML inicial das caixas de preview de imagem (data-preview-box).
    // As imagens escolhidas ficam apenas no navegador até o submit; ao
    // descartar, o preview volta ao estado original (imagem do banco/placeholder).
    function snapshotPreviews() {
      previewStates = [];
      var boxes = form.querySelectorAll('[data-preview-box]');
      for (var i = 0; i < boxes.length; i++) {
        previewStates.push({ el: boxes[i], html: boxes[i].innerHTML });
      }
    }

    function restorePreviews() {
      for (var i = 0; i < previewStates.length; i++) {
        previewStates[i].el.innerHTML = previewStates[i].html;
      }
    }

    function refresh() {
      var dirty = capture() !== savedState;
      if (dirty !== visible) {
        visible = dirty;
        bar.classList.toggle('visible', dirty);
        bar.setAttribute('aria-hidden', dirty ? 'false' : 'true');
        document.body.classList.toggle('unsaved-active', dirty);
      }
    }

    // Baseline = valores como vieram do banco
    savedState = capture();
    snapshotFields();
    snapshotPreviews();

    // input/change cobrem digitação, selects, checkboxes e uploads;
    // click cobre controles visuais que alteram valores via JS
    // (ex: seletor de cores dos VIPs e linhas dinâmicas de benefícios)
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('click', refresh);

    // Descartar: remove linhas criadas via JS, reverte previews de imagem
    // e restaura os valores iniciais — nada foi enviado ao servidor ainda.
    btnDiscard.addEventListener('click', function () {
      var added = form.querySelectorAll('[data-js-added]');
      for (var i = 0; i < added.length; i++) {
        if (added[i].parentNode) added[i].parentNode.removeChild(added[i]);
      }
      restorePreviews();
      for (var j = 0; j < initialStates.length; j++) {
        var s = initialStates[j];
        if (!s.el.isConnected) continue;
        if (s.type === 'checkbox' || s.type === 'radio') {
          s.el.checked = s.value;
        } else if (s.type === 'file') {
          try { s.el.value = ''; } catch (e) { /* navegador pode bloquear */ }
        } else {
          s.el.value = s.value;
        }
      }
      savedState = capture();
      refresh();
      // Notifica a página (ex: equipe/manage.php) para re-renderizar estados
      // client-side que dependem dos campos (ex: ordem dos cargos)
      form.dispatchEvent(new CustomEvent('unsaved:discarded', { bubbles: true }));
    });

    // Salvar: se o formulário definir data-save-endpoint, o envio é feito via
    // API (ex: reordenação de cargos) em vez de submit tradicional do formulário
    btnSave.addEventListener('click', function () {
      var endpoint = form.dataset.saveEndpoint;
      if (endpoint) {
        if (!window.AdminApi || !window.AdminToast) {
          window.AdminToast.error('Recursos assíncronos indisponíveis. Recarregue a página.');
          return;
        }

        // Envia todos os campos do formulário (ex: ordem_ids[], membro_cargos[])
        var data = new FormData();
        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function(el) {
          if (!el.name || el.disabled) return;
          var type = (el.type || '').toLowerCase();
          if (type === 'checkbox' || type === 'radio') {
            if (el.checked) data.append(el.name, el.value);
          } else if (type !== 'file' && type !== 'submit' && type !== 'button') {
            data.append(el.name, el.value);
          }
        });

        window.AdminApi.post(endpoint, data)
          .then(function(res) {
            if (res.success) {
              savedState = capture();
              snapshotFields();
              refresh();
              window.AdminToast.success(res.mensagem || 'Alterações salvas com sucesso!');
            } else {
              window.AdminToast.error(res.erro || 'Falha ao salvar.');
            }
          })
          .catch(function(err) {
            window.AdminToast.error(err && err.message ? err.message : 'Erro ao salvar.');
          });
        return;
      }

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });

    form.addEventListener('submit', function () {
      submitting = true;
    });

    // Confirmação ao sair da página com alterações pendentes
    window.addEventListener('beforeunload', function (e) {
      if (submitting || !visible) return;
      e.preventDefault();
      e.returnValue = '';
      return '';
    });
  });
})(window, document);
