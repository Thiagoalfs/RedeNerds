/**
 * loja.js - Controlador Oficial da Loja (Rede Nerds)
 * Gerencia identificação do jogador, abas de servidor, checkout PIX e modal de confirmação.
 */

(function () {
  'use strict';

  const STORAGE_KEY_NICK = 'redenerds_loja_nick';
  const STORAGE_KEY_TIPO = 'redenerds_loja_tipo_conta';
  const DEFAULT_AVATAR = 'https://mc-heads.net/avatar/MHF_Steve/128';

  const STATE = {
    nick: localStorage.getItem(STORAGE_KEY_NICK) || '',
    tipoConta: localStorage.getItem(STORAGE_KEY_TIPO) || 'original',
    servidores: [],
    selectedServer: null,
    currentOrder: {
      txid: null,
      vipData: null,
      pollingInterval: null,
      countdownTimer: null
    }
  };

  // 1. INICIALIZAÇÃO
  document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    carregarCatalogoVips();

    if (STATE.nick) {
      liberarPainelLoja();
    } else {
      bloquearPainelLoja();
      abrirModalNick(false);
    }
  });

  // 2. CONFIGURAÇÃO DE EVENTOS
  function setupEventListeners() {
    const btnOpenNick = document.getElementById('btn-open-nick-modal');
    if (btnOpenNick) {
      btnOpenNick.addEventListener('click', () => abrirModalNick(true));
    }

    const btnTrocarNick = document.getElementById('btn-trocar-nick');
    if (btnTrocarNick) {
      btnTrocarNick.addEventListener('click', () => abrirModalNick(true));
    }

    const btnCloseNick = document.getElementById('btn-close-nick-modal');
    if (btnCloseNick) {
      btnCloseNick.addEventListener('click', () => fecharModalNick());
    }

    const inputNick = document.getElementById('input-player-nick');
    if (inputNick) {
      let debounceTimer = null;
      inputNick.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        const val = e.target.value.trim();
        debounceTimer = setTimeout(() => atualizarPreviewAvatar(val), 200);
      });
    }

    const accountBtns = document.querySelectorAll('.toggle-btn');
    accountBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        accountBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        STATE.tipoConta = btn.dataset.type || 'original';
      });
    });

    const formNick = document.getElementById('form-nick-step');
    if (formNick) {
      formNick.addEventListener('submit', (e) => {
        e.preventDefault();
        confirmarNick();
      });
    }

    const btnRetryVips = document.getElementById('btn-retry-vips');
    if (btnRetryVips) {
      btnRetryVips.addEventListener('click', carregarCatalogoVips);
    }

    const btnClosePix = document.getElementById('btn-close-pix-modal');
    if (btnClosePix) {
      btnClosePix.addEventListener('click', () => fecharModalPix());
    }

    const btnCopyPix = document.getElementById('btn-copy-pix');
    if (btnCopyPix) {
      btnCopyPix.addEventListener('click', copiarCodigoPix);
    }

    const btnFinish = document.getElementById('btn-finish-purchase');
    if (btnFinish) {
      btnFinish.addEventListener('click', () => fecharModalPix());
    }

    const btnRetryPix = document.getElementById('btn-retry-pix');
    if (btnRetryPix) {
      btnRetryPix.addEventListener('click', () => {
        if (STATE.currentOrder.vipData) {
          iniciarCheckoutPix(STATE.currentOrder.vipData);
        }
      });
    }
  }

  // 3. POPUP DE IDENTIFICAÇÃO (NICK)
  function abrirModalNick(podeFechar = true) {
    const dialog = document.getElementById('modal-nick-overlay');
    const btnClose = document.getElementById('btn-close-nick-modal');
    const inputNick = document.getElementById('input-player-nick');
    const feedback = document.getElementById('nick-error-feedback');

    if (!dialog) return;

    if (feedback) feedback.hidden = true;
    if (inputNick) {
      inputNick.value = STATE.nick || '';
      atualizarPreviewAvatar(STATE.nick);
    }

    document.querySelectorAll('.toggle-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.type === STATE.tipoConta);
    });

    if (btnClose) {
      btnClose.hidden = !podeFechar || !STATE.nick;
    }

    dialog.hidden = false;
    document.body.style.overflow = 'hidden';

    if (inputNick) {
      setTimeout(() => inputNick.focus(), 100);
    }
  }

  function fecharModalNick() {
    const dialog = document.getElementById('modal-nick-overlay');
    if (dialog) dialog.hidden = true;
    document.body.style.overflow = '';
  }

  function atualizarPreviewAvatar(nick) {
    const img = document.getElementById('nick-preview-img');
    const label = document.getElementById('nick-preview-label');

    if (!img || !label) return;

    const clean = String(nick || '').trim();
    if (clean.length >= 3 && /^[a-zA-Z0-9_]+$/.test(clean)) {
      img.src = `https://mc-heads.net/avatar/${encodeURIComponent(clean)}/128`;
      label.textContent = clean;
    } else {
      img.src = DEFAULT_AVATAR;
      label.textContent = clean || 'Steve';
    }
  }

  function confirmarNick() {
    const inputNick = document.getElementById('input-player-nick');
    const feedback = document.getElementById('nick-error-feedback');
    const nick = (inputNick ? inputNick.value : '').trim();

    if (!nick || nick.length < 3 || nick.length > 16 || !/^[a-zA-Z0-9_]+$/.test(nick)) {
      if (feedback) {
        feedback.textContent = 'Digite um nickname válido (3 a 16 caracteres, sem espaços ou símbolos).';
        feedback.hidden = false;
      }
      if (inputNick) inputNick.focus();
      return;
    }

    if (feedback) feedback.hidden = true;

    STATE.nick = nick;
    localStorage.setItem(STORAGE_KEY_NICK, STATE.nick);
    localStorage.setItem(STORAGE_KEY_TIPO, STATE.tipoConta);

    fecharModalNick();
    liberarPainelLoja();
  }

  // 4. LIBERAÇÃO & BLOQUEIO DO PAINEL
  function liberarPainelLoja() {
    const profileBar = document.getElementById('loja-profile-bar');
    const lockedBanner = document.getElementById('loja-locked-banner');
    const panel = document.getElementById('loja-panel');

    const avatarImg = document.getElementById('profile-avatar-img');
    const nickDisplay = document.getElementById('profile-nick-display');
    const badgeType = document.getElementById('profile-account-type-badge');

    if (avatarImg) {
      avatarImg.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    }
    if (nickDisplay) {
      nickDisplay.textContent = STATE.nick;
    }
    if (badgeType) {
      const isOriginal = (STATE.tipoConta === 'original');
      badgeType.className = isOriginal ? 'account-badge' : 'account-badge pirata';
      badgeType.textContent = isOriginal ? 'Original' : 'Pirata';
    }

    if (lockedBanner) lockedBanner.hidden = true;
    if (profileBar) profileBar.hidden = false;
    if (panel) panel.hidden = false;
  }

  function bloquearPainelLoja() {
    const profileBar = document.getElementById('loja-profile-bar');
    const lockedBanner = document.getElementById('loja-locked-banner');
    const panel = document.getElementById('loja-panel');

    if (lockedBanner) lockedBanner.hidden = false;
    if (profileBar) profileBar.hidden = true;
    if (panel) panel.hidden = true;
  }

  // 5. CARREGAMENTO DOS VIPS VIA API
  async function carregarCatalogoVips() {
    const loadingBox = document.getElementById('loja-loading');
    const errorBox = document.getElementById('loja-error');
    const container = document.getElementById('loja-servers-container');

    if (loadingBox) loadingBox.hidden = false;
    if (errorBox) errorBox.hidden = true;
    if (container) container.hidden = true;

    try {
      const res = await fetch('/api/loja/vips_api.php');
      if (!res.ok) throw new Error(`Falha na requisição (${res.status})`);

      const data = await res.json();
      if (!data.success || !Array.isArray(data.servidores)) {
        throw new Error(data.erro || 'Formato de resposta inválido.');
      }

      STATE.servidores = data.servidores;

      renderQuickNav();
      renderServerSectionsWithDividers();

      if (loadingBox) loadingBox.hidden = true;
      if (container) container.hidden = false;

    } catch (err) {
      console.error('Erro ao carregar catálogo VIP:', err);
      if (loadingBox) loadingBox.hidden = true;
      if (errorBox) {
        const msg = document.getElementById('loja-error-msg');
        if (msg) msg.textContent = err.message || 'Erro ao carregar pacotes da loja.';
        errorBox.hidden = false;
      }
    }
  }

  // 6. SELETOR DE ABAS POR SERVIDOR
  function renderQuickNav() {
    const navBox = document.getElementById('loja-server-quicknav');
    if (!navBox) return;

    if (!STATE.selectedServer && STATE.servidores.length > 0) {
      const params = new URLSearchParams(window.location.search);
      const urlSrv = (params.get('servidor') || params.get('server') || '').toLowerCase().trim();
      const match = STATE.servidores.find(s => s.id.toLowerCase() === urlSrv || s.nome.toLowerCase() === urlSrv);
      STATE.selectedServer = match ? match.id : STATE.servidores[0].id;
    }

    let html = '';
    STATE.servidores.forEach(srv => {
      const isSelected = srv.id === STATE.selectedServer;
      const srvColor = srv.cor || '#38BDF8';

      html += `
        <button type="button" class="server-tab ${isSelected ? 'active' : ''}" data-server-id="${escapeHTML(srv.id)}" style="--server-color: ${escapeHTML(srvColor)};" role="tab" aria-selected="${isSelected ? 'true' : 'false'}">
          <span class="server-color-dot" style="background-color: ${escapeHTML(srvColor)};"></span>
          <span>${escapeHTML(srv.nome)}</span>
        </button>
      `;
    });

    navBox.innerHTML = html;

    navBox.querySelectorAll('.server-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        const srvId = btn.dataset.serverId;
        if (srvId && srvId !== STATE.selectedServer) {
          selecionarServidor(srvId);
        }
      });
    });
  }

  function selecionarServidor(serverId) {
    STATE.selectedServer = serverId;

    const buttons = document.querySelectorAll('.server-tab');
    buttons.forEach(btn => {
      const isCurrent = btn.dataset.serverId === serverId;
      btn.classList.toggle('active', isCurrent);
      btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
    });

    try {
      const url = new URL(window.location);
      url.searchParams.set('servidor', serverId);
      window.history.replaceState({}, '', url);
    } catch (e) {}

    renderServerSectionsWithDividers();
  }

  // 7. RENDERIZAÇÃO DA SEÇÃO DO SERVIDOR ATIVO
  function renderServerSectionsWithDividers() {
    const container = document.getElementById('loja-servers-container');
    if (!container) return;

    if (STATE.servidores.length === 0) {
      container.innerHTML = `
        <div class="state-feedback">
          <p>Nenhum pacote disponível no momento.</p>
        </div>
      `;
      return;
    }

    const srv = STATE.servidores.find(s => s.id === STATE.selectedServer) || STATE.servidores[0];
    if (!srv) return;

    const vipsList = Array.isArray(srv.vips) ? srv.vips : [];

    let vipsCardsHtml = '';

    if (vipsList.length === 0) {
      vipsCardsHtml = `
        <div class="state-feedback" style="grid-column: 1 / -1;">
          <p>Nenhum VIP cadastrado para este servidor no momento.</p>
        </div>
      `;
    } else {
      vipsCardsHtml = vipsList.map((vip, index) => {
        const isFeatured = !!vip.destaque;
        const duracao = vip.duracao_dias ? `${vip.duracao_dias} dias` : '30 dias';
        const precoFormatado = Number(vip.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const delay = (index * 0.05).toFixed(2);

        const vantagensHtml = (vip.vantagens || []).map(v => `
          <li><i class="fa-solid fa-check"></i> <span>${escapeHTML(v)}</span></li>
        `).join('');

        return `
          <div class="vip-card ${isFeatured ? 'is-featured' : ''}" style="animation-delay: ${delay}s;">
            <div class="vip-card-head">
              <span class="vip-server-label">${escapeHTML(srv.nome)}</span>
              ${isFeatured ? `<span class="featured-pill">Mais Escolhido</span>` : ''}
            </div>

            <h3 class="vip-title">${escapeHTML(vip.nome)}</h3>

            <div class="vip-price-container">
              <span class="price-currency">R$</span>
              <span class="price-val">${precoFormatado}</span>
              <span class="price-period">/ ${duracao}</span>
            </div>

            <ul class="vip-benefits">
              ${vantagensHtml}
            </ul>

            <button type="button" class="btn-purchase-card" data-vip-id="${vip.id}" data-server-id="${srv.id}">
              Adquirir com PIX
            </button>
          </div>
        `;
      }).join('');
    }

    container.innerHTML = `
      <div class="loja-vips-grid">
        ${vipsCardsHtml}
      </div>
    `;

    container.querySelectorAll('.btn-purchase-card').forEach(btn => {
      btn.addEventListener('click', () => {
        const vipId = parseInt(btn.dataset.vipId, 10);
        const srvId = btn.dataset.serverId;
        
        const currentSrv = STATE.servidores.find(s => s.id === srvId);
        const vip = currentSrv ? (currentSrv.vips || []).find(v => v.id === vipId) : null;

        if (currentSrv && vip) {
          const vipCompleto = {
            ...vip,
            serverInfo: {
              id: currentSrv.id,
              nome: currentSrv.nome,
              cor: currentSrv.cor || '#7DB9DF',
              icon: currentSrv.icon || 'fa-solid fa-server'
            }
          };

          if (!STATE.nick) {
            abrirModalNick(true);
          } else {
            iniciarCheckoutPix(vipCompleto);
          }
        }
      });
    });
  }

  // 8. FLUXO DE CHECKOUT PIX
  async function iniciarCheckoutPix(vipData) {
    STATE.currentOrder.vipData = vipData;

    const dialog = document.getElementById('modal-pix-overlay');
    const headerTitle = document.getElementById('pix-modal-header-title');
    const orderSummaryBox = document.getElementById('pix-order-summary-box');
    const summaryAvatar = document.getElementById('summary-avatar-img');
    const summaryNick = document.getElementById('summary-nick-display');
    const summaryServerVip = document.getElementById('summary-server-vip');
    const summaryPrice = document.getElementById('summary-price-display');

    const stateLoading = document.getElementById('pix-loading-state');
    const stateReady = document.getElementById('pix-ready-state');
    const stateSuccess = document.getElementById('pix-success-state');
    const stateError = document.getElementById('pix-error-state');

    if (!dialog) return;

    pararPollingPix();

    if (headerTitle) headerTitle.textContent = 'Pagamento via PIX';
    if (orderSummaryBox) orderSummaryBox.hidden = false;
    if (summaryAvatar) summaryAvatar.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    if (summaryNick) summaryNick.textContent = STATE.nick;
    if (summaryServerVip) summaryServerVip.textContent = `${vipData.serverInfo.nome} • ${vipData.nome}`;
    if (summaryPrice) summaryPrice.textContent = `R$ ${Number(vipData.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

    if (stateLoading) stateLoading.hidden = false;
    if (stateReady) stateReady.hidden = true;
    if (stateSuccess) stateSuccess.hidden = true;
    if (stateError) stateError.hidden = true;

    dialog.hidden = false;
    document.body.style.overflow = 'hidden';

    try {
      const payload = {
        nick: STATE.nick,
        tipo_conta: STATE.tipoConta,
        servidor: vipData.serverInfo.nome,
        vip_id: vipData.id,
        vip_nome: vipData.nome,
        valor: vipData.preco
      };

      const res = await fetch('/api/loja/criar_pix.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (!res.ok || data.erro || !data.txid) {
        throw new Error(data.erro || 'Falha ao criar transação PIX.');
      }

      STATE.currentOrder.txid = data.txid;

      const qrImg = document.getElementById('pix-qrcode-img');
      const inputCopiaCola = document.getElementById('input-pix-copiacola');

      const pixCode = String(data.pix_copia_cola || data.qr_code || '').trim();
      const pixBase64 = String(data.pix_qr_base64 || data.qr_code_base64 || '').trim();

      if (qrImg) {
        if (pixBase64) {
          if (pixBase64.startsWith('data:image')) {
            qrImg.src = pixBase64;
          } else if (pixBase64.startsWith('PHN2Zy') || pixBase64.startsWith('<svg')) {
            qrImg.src = `data:image/svg+xml;base64,${pixBase64.replace(/^data:image\/svg\+xml;base64,/, '')}`;
          } else {
            qrImg.src = `data:image/png;base64,${pixBase64}`;
          }
        } else if (pixCode) {
          qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(pixCode)}`;
        }
      }

      if (inputCopiaCola) {
        inputCopiaCola.value = pixCode;
      }

      if (stateLoading) stateLoading.hidden = true;
      if (stateReady) stateReady.hidden = false;

      iniciarCountdownPix(15 * 60);
      iniciarPollingPix(data.txid);

    } catch (err) {
      console.error('Erro PIX:', err);
      if (stateLoading) stateLoading.hidden = true;
      if (stateError) {
        const title = document.getElementById('pix-error-title');
        const desc = document.getElementById('pix-error-desc');
        if (title) title.textContent = 'Erro na Cobrança';
        if (desc) desc.textContent = err.message || 'Não foi possível gerar a chave PIX.';
        stateError.hidden = false;
      }
    }
  }

  function fecharModalPix() {
    pararPollingPix();
    const dialog = document.getElementById('modal-pix-overlay');
    if (dialog) dialog.hidden = true;
    document.body.style.overflow = '';
  }

  // 9. POLLING E CONTAGEM REGRESSIVA PRECISA (TIMESTAMP)
  function iniciarCountdownPix(segundosTotais = 900) {
    const countdownEl = document.getElementById('pix-countdown');
    if (!countdownEl) return;

    if (STATE.currentOrder.countdownTimer) {
      clearInterval(STATE.currentOrder.countdownTimer);
      STATE.currentOrder.countdownTimer = null;
    }

    const endTime = Date.now() + (segundosTotais * 1000);

    function atualizarTimer() {
      const now = Date.now();
      const diffSegundos = Math.max(0, Math.ceil((endTime - now) / 1000));

      const m = Math.floor(diffSegundos / 60);
      const s = diffSegundos % 60;
      countdownEl.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

      if (diffSegundos <= 0) {
        pararPollingPix();
        const stateReady = document.getElementById('pix-ready-state');
        const stateError = document.getElementById('pix-error-state');
        if (stateReady) stateReady.hidden = true;
        if (stateError) {
          const title = document.getElementById('pix-error-title');
          const desc = document.getElementById('pix-error-desc');
          if (title) title.textContent = 'PIX Expirado';
          if (desc) desc.textContent = 'O tempo limite de 15 minutos para pagamento se esgotou.';
          stateError.hidden = false;
        }
      }
    }

    atualizarTimer();
    STATE.currentOrder.countdownTimer = setInterval(atualizarTimer, 1000);
  }

  function iniciarPollingPix(txid) {
    if (STATE.currentOrder.pollingInterval) {
      clearInterval(STATE.currentOrder.pollingInterval);
      STATE.currentOrder.pollingInterval = null;
    }

    STATE.currentOrder.pollingInterval = setInterval(async () => {
      try {
        const res = await fetch(`/api/loja/checar_status.php?txid=${encodeURIComponent(txid)}`);
        if (!res.ok) return;

        const data = await res.json();
        const isPago = (data.status === 'pago' || data.status === 'approved' || data.aprovado === true);

        if (isPago) {
          pararPollingPix();
          exibirSucessoPix(data);
        }
      } catch (e) {
        // Silencioso
      }
    }, 3500);
  }

  function pararPollingPix() {
    if (STATE.currentOrder.pollingInterval) {
      clearInterval(STATE.currentOrder.pollingInterval);
      STATE.currentOrder.pollingInterval = null;
    }
    if (STATE.currentOrder.countdownTimer) {
      clearInterval(STATE.currentOrder.countdownTimer);
      STATE.currentOrder.countdownTimer = null;
    }
  }

  // 10. TELA DE CONFIRMAÇÃO DE PAGAMENTO
  function exibirSucessoPix(data) {
    const headerTitle = document.getElementById('pix-modal-header-title');
    const orderSummaryBox = document.getElementById('pix-order-summary-box');
    const stateLoading = document.getElementById('pix-loading-state');
    const stateReady = document.getElementById('pix-ready-state');
    const stateError = document.getElementById('pix-error-state');
    const stateSuccess = document.getElementById('pix-success-state');

    if (stateLoading) stateLoading.hidden = true;
    if (stateReady) stateReady.hidden = true;
    if (stateError) stateError.hidden = true;
    if (orderSummaryBox) orderSummaryBox.hidden = true;

    if (headerTitle) headerTitle.textContent = 'Confirmação de Pagamento';

    const rNick = document.getElementById('receipt-player-nick');
    const rVip = document.getElementById('receipt-vip-name');
    const rServer = document.getElementById('receipt-server-name');
    const rTxid = document.getElementById('receipt-txid');

    const vipNome = data.vip_nome || STATE.currentOrder.vipData?.nome || 'VIP';
    const serverNome = data.servidor || STATE.currentOrder.vipData?.serverInfo?.nome || 'Servidor';
    const nick = data.nick || STATE.nick || 'Jogador';
    const txid = data.txid || STATE.currentOrder.txid || 'N/A';

    if (rNick) rNick.textContent = nick;
    if (rVip) rVip.textContent = vipNome;
    if (rServer) rServer.textContent = serverNome;
    if (rTxid) rTxid.textContent = txid;

    if (stateSuccess) stateSuccess.hidden = false;
  }

  function copiarCodigoPix() {
    const input = document.getElementById('input-pix-copiacola');
    const btn = document.getElementById('btn-copy-pix');
    const btnText = document.getElementById('copy-btn-text');

    if (!input || !input.value) return;

    navigator.clipboard.writeText(input.value).then(() => {
      if (btnText) btnText.textContent = 'Copiado!';
      setTimeout(() => {
        if (btnText) btnText.textContent = 'Copiar';
      }, 2000);
    }).catch(() => {
      input.select();
      document.execCommand('copy');
      if (btnText) btnText.textContent = 'Copiado!';
      setTimeout(() => {
        if (btnText) btnText.textContent = 'Copiar';
      }, 2000);
    });
  }

  // 11. UTILITÁRIOS
  function escapeHTML(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
