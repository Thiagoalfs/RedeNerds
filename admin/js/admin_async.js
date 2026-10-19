/**
 * admin_async.js - Gerenciador de Operações Assíncronas do Admin (Rede Nerds)
 * Fornece Toasts flutuantes, requisições AJAX unificadas com CSRF,
 * ações instantâneas de toggle/delete e controle de modais sem reload.
 */

(function (window, document) {
  'use strict';

  // 1. OBTENÇÃO SEGURA DO CSRF TOKEN
  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const input = document.querySelector('input[name="csrf_token"]');
    if (input && input.value) return input.value;
    return '';
  }

  // 2. SISTEMA DE TOASTS FLUTUANTES
  const AdminToast = {
    container: null,

    ensureStyles() {
      if (document.getElementById('admin-toast-fallback-styles')) return;
      const style = document.createElement('style');
      style.id = 'admin-toast-fallback-styles';
      style.textContent = `
        .admin-toast-container {
          position: fixed !important;
          top: 76px !important;
          right: 24px !important;
          z-index: 999999 !important;
          display: flex !important;
          flex-direction: column !important;
          gap: 12px !important;
          max-width: 380px !important;
          pointer-events: none !important;
        }
        .admin-toast {
          display: flex !important;
          align-items: center !important;
          gap: 12px !important;
          padding: 14px 18px !important;
          border-radius: 10px !important;
          background-color: #1e293b !important;
          color: #f8fafc !important;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35), 0 2px 6px rgba(0, 0, 0, 0.15) !important;
          border-left: 4px solid #2563eb !important;
          font-size: 0.9rem !important;
          font-weight: 500 !important;
          pointer-events: auto !important;
          animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
          transition: all 0.25s ease !important;
          font-family: 'Poppins', -apple-system, sans-serif !important;
        }
        .admin-toast.toast-fade-out {
          opacity: 0 !important;
          transform: translateX(40px) !important;
        }
        .admin-toast .toast-icon { font-size: 1.25rem !important; flex-shrink: 0 !important; }
        .admin-toast .toast-content { flex: 1 !important; line-height: 1.4 !important; }
        .admin-toast .toast-close {
          background: none !important;
          border: none !important;
          color: #94a3b8 !important;
          font-size: 1.3rem !important;
          line-height: 1 !important;
          padding: 0 !important;
          cursor: pointer !important;
          opacity: 0.7 !important;
        }
        .admin-toast .toast-close:hover { opacity: 1 !important; }
        .admin-toast.toast-success { border-left-color: #10b981 !important; }
        .admin-toast.toast-success .toast-icon { color: #10b981 !important; }
        .admin-toast.toast-error, .admin-toast.toast-danger { border-left-color: #ef4444 !important; }
        .admin-toast.toast-error .toast-icon, .admin-toast.toast-danger .toast-icon { color: #ef4444 !important; }
        .admin-toast.toast-warning { border-left-color: #f59e0b !important; }
        .admin-toast.toast-warning .toast-icon { color: #f59e0b !important; }
        .admin-toast.toast-info { border-left-color: #06b6d4 !important; }
        .admin-toast.toast-info .toast-icon { color: #06b6d4 !important; }
        @keyframes toastSlideIn {
          from { opacity: 0; transform: translateY(-16px) scale(0.95); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .table-row-highlight { animation: rowHighlight 1s ease; }
        @keyframes rowHighlight {
          0% { background-color: rgba(37, 99, 235, 0.15); }
          100% { background-color: transparent; }
        }
      `;
      document.head.appendChild(style);
    },

    init() {
      this.ensureStyles();
      if (!this.container) {
        let el = document.getElementById('admin-toast-container');
        if (!el) {
          el = document.createElement('div');
          el.id = 'admin-toast-container';
          el.className = 'admin-toast-container';
          document.body.appendChild(el);
        }
        this.container = el;
      }
    },

    show(message, type = 'success', duration = 3500) {
      this.init();

      const toast = document.createElement('div');
      toast.className = `admin-toast toast-${type}`;

      let iconClass = 'fa-solid fa-circle-check';
      if (type === 'error' || type === 'danger') iconClass = 'fa-solid fa-circle-xmark';
      if (type === 'info') iconClass = 'fa-solid fa-circle-info';
      if (type === 'warning') iconClass = 'fa-solid fa-triangle-exclamation';

      const icon = document.createElement('i');
      icon.className = `${iconClass} toast-icon`;

      const content = document.createElement('div');
      content.className = 'toast-content';
      content.textContent = message; // texto puro, nunca interpretado como HTML

      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'toast-close';
      closeBtn.setAttribute('aria-label', 'Fechar');
      closeBtn.innerHTML = '&times;'; // fixo, sem input externo — seguro

      toast.append(icon, content, closeBtn);

      const removeToast = () => {
        toast.classList.add('toast-fade-out');
        setTimeout(() => toast.remove(), 250);
      };
      closeBtn.addEventListener('click', removeToast);

      this.container.appendChild(toast);

      if (duration > 0) {
        setTimeout(removeToast, duration);
      }
    },

    success(msg, duration) { this.show(msg, 'success', duration); },
    error(msg, duration) { this.show(msg, 'error', duration || 4500); },
    info(msg, duration) { this.show(msg, 'info', duration); },
    warning(msg, duration) { this.show(msg, 'warning', duration); }
  };

  // 3. HELPER DE REQUISIÇÕES AJAX COM CSRF
  const AdminApi = {
    async post(url, data = {}) {
      let body;
      const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': getCsrfToken()
      };

      if (data instanceof FormData) {
        if (!data.has('csrf_token')) {
          data.append('csrf_token', getCsrfToken());
        }
        body = data;
      } else {
        const formData = new URLSearchParams();
        formData.append('csrf_token', getCsrfToken());
        for (const [key, value] of Object.entries(data)) {
          if (value !== undefined && value !== null) {
            formData.append(key, value);
          }
        }
        headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        body = formData.toString();
      }

      const res = await fetch(url, {
        method: 'POST',
        headers,
        body
      });

      let json = null;
      try {
        json = await res.json();
      } catch (_) {
        throw new Error(`Erro na resposta do servidor (HTTP ${res.status}).`);
      }

      if (!res.ok || json.erro || json.success === false) {
        throw new Error(json.erro || json.mensagem || 'Falha ao processar solicitação.');
      }

      return json;
    }
  };

  // 4. HANDLERS DE AÇÕES DINÂMICAS NAS TABELAS
  function setupAsyncTableActions() {
    // 4.1. TOGGLE ATIVO / INATIVO
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action="async-toggle"]');
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const url = btn.dataset.url;
      const id = btn.dataset.id;
      if (!url || !id) return;

      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      try {
        const res = await AdminApi.post(url, { id, format: 'json' });
        const novoStatus = res.ativo !== undefined ? Boolean(res.ativo) : 
                           (res.enabled !== undefined ? Boolean(res.enabled) : 
                           (res.novo_status !== undefined ? Boolean(res.novo_status) :
                           (res.publicado !== undefined ? Boolean(res.publicado) : true)));

        // Atualiza o badge de status na mesma linha da tabela
        const row = btn.closest('tr');
        let isVisibilityType = false;

        if (row) {
          const badge = row.querySelector('.badge-status, [data-status-badge]');
          if (badge) {
            isVisibilityType = badge.classList.contains('visivel') || badge.classList.contains('oculto') || badge.textContent.trim().toLowerCase().includes('vis');
            const ehPublicado = badge.dataset.statusPublicado !== undefined;

            if (ehPublicado) {
                badge.className = novoStatus ? 'badge-status ativo' : 'badge-status inativo';
                badge.innerHTML = novoStatus
                    ? '<i class="fa-solid fa-check me-1"></i> Publicado'
                    : '<i class="fa-solid fa-eye-slash me-1"></i> Rascunho';
            } else if (isVisibilityType) {
              badge.className = novoStatus ? 'badge-status visivel' : 'badge-status oculto';
              badge.textContent = novoStatus ? 'Visível' : 'Oculto';

              const tabelaAtivos = document.querySelector('[id$="-ativos"] tbody');
              const tabelaInativos = document.querySelector('[id$="-inativos"] tbody');
              if (tabelaAtivos && tabelaInativos) {
                if (novoStatus) {
                  moverLinhaEntreTabelas(row, row.parentElement, tabelaAtivos);
                } else {
                  moverLinhaEntreTabelas(row, row.parentElement, tabelaInativos);
                }
              }
            } else {
              // Verifica se estamos em uma página com divisão de tabelas (ativos e inativos)
              const tabelaAtivos = document.querySelector('[id$="-ativos"] tbody');
              const tabelaInativos = document.querySelector('[id$="-inativos"] tbody');
              const hasSplitTables = tabelaAtivos && tabelaInativos;
              const hasServidorOculto = row.textContent.includes('Servidor Oculto');

              if (hasSplitTables) {
                if (!novoStatus) {
                  badge.className = 'badge-status inativo';
                  badge.textContent = 'Inativo';
                  moverLinhaEntreTabelas(row, row.parentElement, tabelaInativos);
                } else {
                  if (hasServidorOculto) {
                    badge.className = 'badge-status pendente';
                    badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Servidor Oculto';
                  } else {
                    badge.className = 'badge-status ativo';
                    badge.textContent = 'Ativo';
                    moverLinhaEntreTabelas(row, row.parentElement, tabelaAtivos);
                  }
                }
              } else {
                badge.className = novoStatus ? 'badge-status ativo' : 'badge-status inativo';
                badge.textContent = novoStatus ? 'Ativo' : 'Inativo';
              }
            }
          }
          row.classList.add('table-row-highlight');
          setTimeout(() => row.classList.remove('table-row-highlight'), 1000);
        }

        // Atualiza ícone e título do botão
        const badgeEl = row ? row.querySelector('[data-status-badge]') : null;
        const ehPublicado = badgeEl && badgeEl.dataset.statusPublicado !== undefined;
        if (novoStatus) {
          btn.title = ehPublicado ? 'Despublicar artigo' : (isVisibilityType ? 'Ocultar servidor' : 'Desativar da loja');
          btn.innerHTML = '<i class="fa-solid ' + (ehPublicado ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        } else {
          btn.title = ehPublicado ? 'Publicar artigo' : (isVisibilityType ? 'Exibir servidor' : 'Ativar na loja');
          btn.innerHTML = '<i class="fa-solid ' + (ehPublicado ? 'fa-eye' : 'fa-eye-slash') + '"></i>';
        }

        const msgSucesso = res.mensagem || (novoStatus
            ? (ehPublicado ? 'Artigo publicado!' : (isVisibilityType ? 'Servidor visível!' : 'Status alterado para Ativo!'))
            : (ehPublicado ? 'Artigo despublicado!' : (isVisibilityType ? 'Servidor oculto!' : 'Status alterado para Inativo!')));
        AdminToast.success(msgSucesso);
      } catch (err) {
        console.error('Erro no toggle:', err);
        btn.innerHTML = originalHtml;
        AdminToast.error(err.message || 'Erro ao alterar status.');
      } finally {
        btn.disabled = false;
      }
    });

    // 4.2. DELETAR ITEM ASSÍNCRONO
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action="async-delete"]');
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const url = btn.dataset.url;
      const id = btn.dataset.id;
      const itemName = btn.dataset.name || 'este item';
      const confirmMsg = btn.dataset.confirm || `Tem certeza que deseja excluir "${itemName}"?`;

      if (!window.confirm(confirmMsg)) return;

      const row = btn.closest('tr');
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      try {
        const res = await AdminApi.post(url, { id, format: 'json' });
        
        if (row) {
          row.style.transition = 'all 0.3s ease';
          row.style.opacity = '0';
          row.style.transform = 'scale(0.96)';
          setTimeout(() => {
            const parentTable = row.parentElement;
            row.remove();
            if (parentTable) {
              const restantes = parentTable.querySelectorAll('tr:not(.empty-row)');
              if (restantes.length === 0) {
                const trEmpty = document.createElement('tr');
                trEmpty.className = 'empty-row';
                trEmpty.innerHTML = `<td colspan="8" class="text-center py-4 text-muted">Nenhum item nesta seção.</td>`;
                parentTable.appendChild(trEmpty);
              }
            }
            atualizarContadorTabela();
            document.dispatchEvent(new CustomEvent('admin:row-deleted', { detail: { id, url, row } }));
          }, 300);
        }

        AdminToast.success(res.mensagem || `"${itemName}" excluído com sucesso!`);
      } catch (err) {
        console.error('Erro ao deletar:', err);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        AdminToast.error(err.message || 'Erro ao excluir item.');
      }
    });
  }

  function moverLinhaEntreTabelas(row, tabelaOrigem, tabelaDestino) {
    if (!row || !tabelaDestino || row.parentElement === tabelaDestino) return;

    // Remove empty row da tabela de destino se houver
    const emptyDest = tabelaDestino.querySelector('.empty-row');
    if (emptyDest) emptyDest.remove();

    // Anima saída e insere no destino
    row.style.transition = 'all 0.25s ease';
    row.style.opacity = '0.3';
    setTimeout(() => {
      tabelaDestino.appendChild(row);
      row.style.opacity = '1';
      
      // Verifica se a tabela de origem ficou vazia
      if (tabelaOrigem) {
        const restantesOrigem = tabelaOrigem.querySelectorAll('tr:not(.empty-row)');
        if (restantesOrigem.length === 0) {
          const colCount = row.children.length || 7;
          const trEmpty = document.createElement('tr');
          trEmpty.className = 'empty-row';
          trEmpty.innerHTML = `<td colspan="${colCount}" class="text-center py-4 text-muted">Nenhum item nesta seção.</td>`;
          tabelaOrigem.appendChild(trEmpty);
        }
      }

      atualizarContadorTabela();
    }, 250);
  }

  function atualizarContadorTabela() {
    function pluralizar(count, singular, plural) {
      return `${count} ${count === 1 ? singular : plural}`;
    }

    function obterNomes(el) {
      const txt = (el ? el.textContent : '').toLowerCase();
      if (txt.includes('servidor')) return ['servidor', 'servidores'];
      if (txt.includes('pacote')) return ['pacote', 'pacotes'];
      if (txt.includes('cupom') || txt.includes('cupon')) return ['cupom', 'cupons'];
      if (txt.includes('membro')) return ['membro', 'membros'];
      if (txt.includes('cargo')) return ['cargo', 'cargos'];
      if (txt.includes('artigo')) return ['artigo', 'artigos'];
      if (txt.includes('novidade')) return ['novidade', 'novidades'];
      if (txt.includes('parceiro')) return ['parceiro', 'parceiros'];
      if (txt.includes('pedido')) return ['pedido', 'pedidos'];
      return ['item', 'itens'];
    }

    // Contador global (se existir)
    const allRows = document.querySelectorAll('.table-admin tbody tr:not(.empty-row)');
    const counterBadge = document.querySelector('[data-item-counter]');
    if (counterBadge) {
      const [sing, plur] = obterNomes(counterBadge);
      counterBadge.textContent = pluralizar(allRows.length, sing, plur);

      const parentP = counterBadge.closest('p');
      if (parentP) {
        if (allRows.length === 1) {
          parentP.innerHTML = parentP.innerHTML
            .replace(/\bcadastrados\b/g, 'cadastrado')
            .replace(/\bregistrados\b/g, 'registrado')
            .replace(/\bcadastradas\b/g, 'cadastrada');
        } else {
          parentP.innerHTML = parentP.innerHTML
            .replace(/\bcadastrado\b/g, 'cadastrados')
            .replace(/\bregistrado\b/g, 'registrados')
            .replace(/\bcadastrada\b/g, 'cadastradas');
        }
      }
    }

    // Contadores divididos (Ativos / Inativos)
    const tabAtivos = document.querySelector('[id$="-ativos"] tbody');
    const countAtivos = tabAtivos ? tabAtivos.querySelectorAll('tr:not(.empty-row)').length : 0;

    const tabInativos = document.querySelector('[id$="-inativos"] tbody');
    const countInativos = tabInativos ? tabInativos.querySelectorAll('tr:not(.empty-row)').length : 0;

    const counterAtivos = document.querySelector('[data-item-counter-ativos]');
    if (counterAtivos) {
      const [sing, plur] = obterNomes(counterAtivos);
      counterAtivos.textContent = pluralizar(countAtivos, sing, plur);
    }

    const counterInativos = document.querySelector('[data-item-counter-inativos]');
    if (counterInativos) {
      const [sing, plur] = obterNomes(counterInativos);
      counterInativos.textContent = pluralizar(countInativos, sing, plur);
    }

    // Contadores do cabeçalho resumo (se existirem)
    const summaryAtivos = document.querySelector('[data-item-counter-ativos-summary]');
    if (summaryAtivos) {
      const isServ = summaryAtivos.textContent.includes('visível') || summaryAtivos.textContent.includes('visíveis');
      summaryAtivos.textContent = isServ 
        ? pluralizar(countAtivos, 'visível', 'visíveis')
        : pluralizar(countAtivos, 'ativo', 'ativos');
    }

    const summaryInativos = document.querySelector('[data-item-counter-inativos-summary]');
    if (summaryInativos) {
      const isServ = summaryInativos.textContent.includes('oculto') || summaryInativos.textContent.includes('ocultos');
      summaryInativos.textContent = isServ
        ? pluralizar(countInativos, 'oculto', 'ocultos')
        : pluralizar(countInativos, 'inativo', 'inativos');
    }
  }

  // Inicialização automática
  document.addEventListener('DOMContentLoaded', () => {
    setupAsyncTableActions();
  });

  // Exportação global
  window.AdminToast = AdminToast;
  window.AdminApi = AdminApi;

})(window, document);
