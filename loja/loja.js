/**
 * loja.js - Controlador Oficial da Loja (Rede Nerds)
 * Gerencia identificação do jogador, abas de servidor, checkout multi-método modular (PIX e Cartão de Crédito).
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
    mpPublicKey: '',
    mpInstance: null,
    activePaymentMethod: 'pix', // 'pix' | 'card' (arquitetura modular extensível)
    appliedCoupon: null, // { cupom, porcentagem, desconto, preco_original, preco_final }
    currentOrder: {
      txid: null,
      vipData: null,
      pollingInterval: null,
      countdownTimer: null,
      cardPaymentMethodId: '',
      cardIssuerId: '',
      cardInstallmentsData: []
    }
  };

  // 1. INICIALIZAÇÃO
  document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    setupCardFormHandlers();
    setupCouponHandlers();
    carregarCatalogoVips();

    if (STATE.nick) {
      liberarPainelLoja();
    } else {
      bloquearPainelLoja();
      abrirModalNick(false);
    }
  });

  // 2. CONFIGURAÇÃO DE EVENTOS GERAIS
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
      btnClosePix.addEventListener('click', () => fecharModalCheckout());
    }

    const btnCopyPix = document.getElementById('btn-copy-pix');
    if (btnCopyPix) {
      btnCopyPix.addEventListener('click', copiarCodigoPix);
    }

    const btnFinish = document.getElementById('btn-finish-purchase');
    if (btnFinish) {
      btnFinish.addEventListener('click', () => fecharModalCheckout());
    }

    const btnCloseError = document.getElementById('btn-close-error');
    if (btnCloseError) {
      btnCloseError.addEventListener('click', () => fecharModalCheckout());
    }

    const btnRetryPix = document.getElementById('btn-retry-pix');
    if (btnRetryPix) {
      btnRetryPix.addEventListener('click', () => {
        if (STATE.currentOrder.vipData) {
          abrirCheckoutModal(STATE.currentOrder.vipData);
        }
      });
    }

    // Seletor modular de métodos de pagamento
    const methodBtns = document.querySelectorAll('.method-nav-btn');
    methodBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const method = btn.dataset.method;
        if (method) {
          switchPaymentMethod(method);
        }
      });
    });
  }

  // 3. POPUP DE IDENTIFICAÇÃO (NICK)
  function abrirModalNick(podeFechar = true) {
    const dialog = document.getElementById('modal-nick-overlay');
    const btnClose = document.getElementById('btn-close-nick-modal');
    const inputNick = document.getElementById('input-player-nick');
    const errorMsg = document.getElementById('nick-error-feedback');

    if (!dialog) return;

    if (errorMsg) errorMsg.hidden = true;
    if (btnClose) btnClose.hidden = !podeFechar;

    if (inputNick) {
      inputNick.value = STATE.nick;
      atualizarPreviewAvatar(STATE.nick);
    }

    const accountBtns = document.querySelectorAll('.toggle-btn');
    accountBtns.forEach(btn => {
      btn.classList.toggle('active', (btn.dataset.type === STATE.tipoConta));
    });

    dialog.hidden = false;
    document.body.style.overflow = 'hidden';
    if (inputNick) inputNick.focus();
  }

  function fecharModalNick() {
    const dialog = document.getElementById('modal-nick-overlay');
    if (dialog) dialog.hidden = true;
    document.body.style.overflow = '';
  }

  function atualizarPreviewAvatar(nick) {
    const img = document.getElementById('nick-preview-img');
    const label = document.getElementById('nick-preview-label');
    const trimmed = (nick || '').trim();

    if (img) {
      img.src = trimmed
        ? `https://mc-heads.net/avatar/${encodeURIComponent(trimmed)}/128`
        : DEFAULT_AVATAR;
    }
    if (label) {
      label.textContent = trimmed || 'Steve';
    }
  }

  function confirmarNick() {
    const inputNick = document.getElementById('input-player-nick');
    const errorMsg = document.getElementById('nick-error-feedback');
    const rawNick = (inputNick ? inputNick.value : '').trim();

    if (!rawNick || rawNick.length < 3 || rawNick.length > 16) {
      if (errorMsg) {
        errorMsg.textContent = 'O nickname deve ter entre 3 e 16 caracteres.';
        errorMsg.hidden = false;
      }
      return;
    }

    if (!/^[a-zA-Z0-9_]+$/.test(rawNick)) {
      if (errorMsg) {
        errorMsg.textContent = 'O nickname deve conter apenas letras, números ou underline (_).';
        errorMsg.hidden = false;
      }
      return;
    }

    STATE.nick = rawNick;
    localStorage.setItem(STORAGE_KEY_NICK, STATE.nick);
    localStorage.setItem(STORAGE_KEY_TIPO, STATE.tipoConta);

    fecharModalNick();
    liberarPainelLoja();
  }

  // 4. CONTROLE DE ESTADO DA PÁGINA (BLOQUEADO / LIBERADO)
  function liberarPainelLoja() {
    const bar = document.getElementById('loja-profile-bar');
    const banner = document.getElementById('loja-locked-banner');
    const panel = document.getElementById('loja-panel');

    const avatar = document.getElementById('profile-avatar-img');
    const nickDisplay = document.getElementById('profile-nick-display');
    const badge = document.getElementById('profile-account-type-badge');

    if (avatar) avatar.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    if (nickDisplay) nickDisplay.textContent = STATE.nick;
    if (badge) {
      badge.textContent = STATE.tipoConta === 'original' ? 'Original' : 'Pirata';
      badge.className = `account-badge badge-${STATE.tipoConta}`;
    }

    if (bar) bar.hidden = false;
    if (banner) banner.hidden = true;
    if (panel) panel.hidden = false;
  }

  function bloquearPainelLoja() {
    const bar = document.getElementById('loja-profile-bar');
    const banner = document.getElementById('loja-locked-banner');
    const panel = document.getElementById('loja-panel');

    if (bar) bar.hidden = true;
    if (banner) banner.hidden = false;
    if (panel) panel.hidden = true;
  }

  // 5. CARREGAMENTO DO CATÁLOGO DE VIPS
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
      STATE.mpPublicKey = data.mercadopago_public_key || '';

      // Inicializa Mercado Pago SDK se disponível
      if (STATE.mpPublicKey && window.MercadoPago && !STATE.mpInstance) {
        try {
          STATE.mpInstance = new window.MercadoPago(STATE.mpPublicKey, { locale: 'pt-BR' });
        } catch (e) {
          console.warn('Aviso: Falha ao inicializar SDK Mercado Pago:', e);
        }
      }

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
              Adquirir
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
            abrirCheckoutModal(vipCompleto);
          }
        }
      });
    });
  }

  // 8. CONTROLE DO MODAL DE CHECKOUT MULTI-MÉTODO
  function abrirCheckoutModal(vipData) {
    STATE.currentOrder.vipData = vipData;
    STATE.currentOrder.txid = null;
    removerCupom(false); // Reset limpo do cupom

    const dialog = document.getElementById('modal-pix-overlay');
    const headerTitle = document.getElementById('pix-modal-header-title');
    const orderSummaryBox = document.getElementById('pix-order-summary-box');
    const summaryAvatar = document.getElementById('summary-avatar-img');
    const summaryNick = document.getElementById('summary-nick-display');
    const summaryServerVip = document.getElementById('summary-server-vip');
    const methodsNav = document.getElementById('checkout-methods-nav');
    const couponBox = document.getElementById('coupon-input-container');

    const stateSuccess = document.getElementById('pix-success-state');
    const stateError = document.getElementById('pix-error-state');

    if (!dialog) return;

    pararPollingPix();

    if (headerTitle) headerTitle.textContent = 'Finalizar Compra';
    if (orderSummaryBox) orderSummaryBox.hidden = false;
    if (couponBox) couponBox.hidden = false;
    if (methodsNav) methodsNav.hidden = false;
    if (summaryAvatar) summaryAvatar.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    if (summaryNick) summaryNick.textContent = STATE.nick;
    if (summaryServerVip) summaryServerVip.textContent = `${vipData.serverInfo.nome} • ${vipData.nome}`;
    
    atualizarPrecoSumario();

    if (stateSuccess) stateSuccess.hidden = true;
    if (stateError) stateError.hidden = true;

    // Reset do form de cartão
    const precoBase = obterPrecoAtualVip();
    resetCardForm(precoBase);

    // Abre o modal
    dialog.hidden = false;
    document.body.style.overflow = 'hidden';

    // Inicia no método ativo (PIX por padrão)
    switchPaymentMethod(STATE.activePaymentMethod || 'pix');
  }

  function obterPrecoAtualVip() {
    if (!STATE.currentOrder.vipData) return 0;
    if (STATE.appliedCoupon && STATE.appliedCoupon.preco_final) {
      return Number(STATE.appliedCoupon.preco_final);
    }
    return Number(STATE.currentOrder.vipData.preco);
  }

  function atualizarPrecoSumario() {
    const summaryPrice = document.getElementById('summary-price-display');
    if (!summaryPrice || !STATE.currentOrder.vipData) return;

    const precoOriginal = Number(STATE.currentOrder.vipData.preco);

    if (STATE.appliedCoupon && STATE.appliedCoupon.preco_final < precoOriginal) {
      const precoFinal = Number(STATE.appliedCoupon.preco_final);
      summaryPrice.innerHTML = `
        <span class="summary-amount-original">R$ ${precoOriginal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
        <span class="summary-amount-discounted">R$ ${precoFinal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
      `;
    } else {
      summaryPrice.innerHTML = `R$ ${precoOriginal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
    }

    // Atualiza label do botão de cartão
    const precoAtual = obterPrecoAtualVip();
    const btnCardLabel = document.getElementById('btn-card-label');
    if (btnCardLabel) {
      btnCardLabel.textContent = `Pagar R$ ${precoAtual.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
    }
  }

  // 8.1 TROCA MODULAR DE MÉTODO DE PAGAMENTO
  function switchPaymentMethod(methodName) {
    STATE.activePaymentMethod = methodName;

    // Atualiza botões da barra de navegação de métodos
    const methodBtns = document.querySelectorAll('.method-nav-btn');
    methodBtns.forEach(btn => {
      const isTarget = (btn.dataset.method === methodName);
      btn.classList.toggle('active', isTarget);
    });

    // Exibe o painel correspondente
    const panelPix = document.getElementById('panel-method-pix');
    const panelCard = document.getElementById('panel-method-card');

    if (panelPix) panelPix.hidden = (methodName !== 'pix');
    if (panelCard) panelCard.hidden = (methodName !== 'card');

    if (methodName === 'pix') {
      if (!STATE.currentOrder.txid && STATE.currentOrder.vipData) {
        gerarCobrancaPix(STATE.currentOrder.vipData);
      }
    } else if (methodName === 'card') {
      const inputCardNum = document.getElementById('card-number');
      if (inputCardNum && inputCardNum.value) {
        onCardNumberInput(inputCardNum.value);
      }
    }
  }

  // 8.2 GERENCIAMENTO DE CUPOM DE DESCONTO
  function setupCouponHandlers() {
    const btnApply = document.getElementById('btn-apply-coupon');
    const inputCode = document.getElementById('input-coupon-code');
    const btnRemove = document.getElementById('btn-remove-coupon');

    if (btnApply) {
      btnApply.addEventListener('click', () => {
        const code = inputCode ? inputCode.value.trim() : '';
        if (code) aplicarCupom(code);
      });
    }

    if (inputCode) {
      inputCode.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const code = inputCode.value.trim();
          if (code) aplicarCupom(code);
        }
      });
    }

    if (btnRemove) {
      btnRemove.addEventListener('click', () => removerCupom(true));
    }
  }

  async function aplicarCupom(cupomCodigo) {
    const inputRow = document.getElementById('coupon-input-row');
    const successPill = document.getElementById('coupon-success-pill');
    const tagDisplay = document.getElementById('applied-coupon-tag');
    const percentDisplay = document.getElementById('applied-discount-percent');
    const errorMsg = document.getElementById('coupon-error-msg');
    const errorLabel = document.getElementById('coupon-error-label');
    const btnApplyText = document.getElementById('btn-apply-coupon-text');
    const btnApplySpinner = document.getElementById('btn-apply-coupon-spinner');
    const btnApply = document.getElementById('btn-apply-coupon');

    if (!STATE.currentOrder.vipData) return;

    if (errorMsg) errorMsg.hidden = true;
    if (btnApply) btnApply.disabled = true;
    if (btnApplyText) btnApplyText.hidden = true;
    if (btnApplySpinner) btnApplySpinner.hidden = false;

    try {
      const res = await fetch('/api/loja/validar_cupom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          cupom: cupomCodigo,
          vip_id: STATE.currentOrder.vipData.id
        })
      });

      const data = await res.json();

      if (!res.ok || data.erro || !data.success) {
        throw new Error(data.erro || 'Cupom inválido ou expirado.');
      }

      // Cupom Válido!
      STATE.appliedCoupon = data;

      if (inputRow) inputRow.hidden = true;
      if (successPill) successPill.hidden = false;
      if (tagDisplay) tagDisplay.textContent = data.cupom;
      if (percentDisplay) percentDisplay.textContent = `${Number(data.porcentagem).toLocaleString('pt-BR')}%`;

      atualizarPrecoSumario();

      // Recalcula PIX ou Cartão
      if (STATE.activePaymentMethod === 'pix') {
        STATE.currentOrder.txid = null;
        gerarCobrancaPix(STATE.currentOrder.vipData);
      } else if (STATE.activePaymentMethod === 'card') {
        const inputCardNum = document.getElementById('card-number');
        if (inputCardNum && inputCardNum.value) {
          onCardNumberInput(inputCardNum.value);
        } else {
          resetInstallmentsSelect(data.preco_final);
        }
      }

    } catch (err) {
      console.warn('Erro ao aplicar cupom:', err);
      if (errorMsg && errorLabel) {
        errorLabel.textContent = err.message || 'Cupom inválido ou expirado.';
        errorMsg.hidden = false;
      }
    } finally {
      if (btnApply) btnApply.disabled = false;
      if (btnApplyText) btnApplyText.hidden = false;
      if (btnApplySpinner) btnApplySpinner.hidden = true;
    }
  }

  function removerCupom(recriarPagamento = true) {
    STATE.appliedCoupon = null;

    const inputRow = document.getElementById('coupon-input-row');
    const inputCode = document.getElementById('input-coupon-code');
    const successPill = document.getElementById('coupon-success-pill');
    const errorMsg = document.getElementById('coupon-error-msg');

    if (inputRow) inputRow.hidden = false;
    if (inputCode) inputCode.value = '';
    if (successPill) successPill.hidden = true;
    if (errorMsg) errorMsg.hidden = true;

    atualizarPrecoSumario();

    if (recriarPagamento && STATE.currentOrder.vipData) {
      if (STATE.activePaymentMethod === 'pix') {
        STATE.currentOrder.txid = null;
        gerarCobrancaPix(STATE.currentOrder.vipData);
      } else if (STATE.activePaymentMethod === 'card') {
        const inputCardNum = document.getElementById('card-number');
        if (inputCardNum && inputCardNum.value) {
          onCardNumberInput(inputCardNum.value);
        } else {
          resetInstallmentsSelect(STATE.currentOrder.vipData.preco);
        }
      }
    }
  }

  function fecharModalCheckout() {
    pararPollingPix();
    const dialog = document.getElementById('modal-pix-overlay');
    if (dialog) dialog.hidden = true;
    document.body.style.overflow = '';
  }

  // 9. FLUXO DE PAGAMENTO: PIX
  async function gerarCobrancaPix(vipData) {
    const stateLoading = document.getElementById('pix-loading-state');
    const stateReady = document.getElementById('pix-ready-state');
    const stateError = document.getElementById('pix-error-state');

    if (stateLoading) stateLoading.hidden = false;
    if (stateReady) stateReady.hidden = true;
    if (stateError) stateError.hidden = true;

    try {
      const payload = {
        nick: STATE.nick,
        tipo_conta: STATE.tipoConta,
        servidor: vipData.serverInfo.nome,
        vip_id: vipData.id,
        vip_nome: vipData.nome,
        valor: obterPrecoAtualVip(),
        cupom: STATE.appliedCoupon ? STATE.appliedCoupon.cupom : ''
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

  // 10. FLUXO DE PAGAMENTO: CARTÃO DE CRÉDITO
  function setupCardFormHandlers() {
    const inputCardNum = document.getElementById('card-number');
    const inputExpiry = document.getElementById('card-expiry');
    const inputCvv = document.getElementById('card-cvv');
    const inputCpf = document.getElementById('card-cpf');
    const selectInstallments = document.getElementById('card-installments');
    const formCard = document.getElementById('form-card-checkout');

    // Máscara do Cartão e listener de BIN para parcelamento dinâmico
    if (inputCardNum) {
      let binDebounce = null;
      inputCardNum.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '').substring(0, 16);
        val = val.replace(/(\d{4})(?=\d)/g, '$1 ');
        e.target.value = val;

        clearTimeout(binDebounce);
        binDebounce = setTimeout(() => onCardNumberInput(val), 250);
      });
    }

    // Máscara de Validade (MM/AA)
    if (inputExpiry) {
      inputExpiry.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '').substring(0, 4);
        if (val.length >= 3) {
          val = val.substring(0, 2) + '/' + val.substring(2);
        }
        e.target.value = val;
      });
    }

    // Máscara de CVV
    if (inputCvv) {
      inputCvv.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
      });
    }

    // Máscara de CPF (000.000.000-00)
    if (inputCpf) {
      inputCpf.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '').substring(0, 11);
        if (val.length > 9) {
          val = val.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        } else if (val.length > 6) {
          val = val.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        } else if (val.length > 3) {
          val = val.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        }
        e.target.value = val;
      });
    }

    // Atualiza texto do botão de pagamento ao trocar de parcela
    if (selectInstallments) {
      selectInstallments.addEventListener('change', () => {
        atualizarTextoBotaoCartao();
      });
    }

    // Submissão do Formulário de Cartão
    if (formCard) {
      formCard.addEventListener('submit', (e) => {
        e.preventDefault();
        processarPagamentoCartao();
      });
    }
  }

  // 10.1 IDENTIFICAÇÃO DE BANDEIRA E PARCELAMENTO POR BIN
  async function onCardNumberInput(cardNumberFormatted) {
    const cleanNumber = cardNumberFormatted.replace(/\D/g, '');
    const iconContainer = document.getElementById('card-brand-icon');

    if (cleanNumber.length < 6) {
      if (iconContainer) iconContainer.innerHTML = '<i class="fa-solid fa-credit-card"></i>';
      STATE.currentOrder.cardPaymentMethodId = '';
      STATE.currentOrder.cardIssuerId = '';
      resetInstallmentsSelect(obterPrecoAtualVip());
      return;
    }

    const bin = cleanNumber.substring(0, 6);

    if (!STATE.mpInstance && STATE.mpPublicKey && window.MercadoPago) {
      try {
        STATE.mpInstance = new window.MercadoPago(STATE.mpPublicKey, { locale: 'pt-BR' });
      } catch (e) {}
    }

    if (!STATE.mpInstance) return;

    try {
      // 1. Detecta bandeira
      const pmRes = await STATE.mpInstance.getPaymentMethods({ bin });
      if (pmRes && pmRes.results && pmRes.results.length > 0) {
        const pm = pmRes.results[0];
        STATE.currentOrder.cardPaymentMethodId = pm.id;
        if (iconContainer && pm.secure_thumbnail) {
          iconContainer.innerHTML = `<img src="${pm.secure_thumbnail}" alt="${pm.name}" class="brand-badge-img">`;
        }
      }

      // 2. Consulta parcelas com juros calculados pelo Mercado Pago
      const vipPreco = obterPrecoAtualVip();
      if (vipPreco > 0) {
        const instRes = await STATE.mpInstance.getInstallments({ amount: String(vipPreco), bin });
        if (instRes && instRes.length > 0) {
          const payerCosts = instRes[0].payer_costs || [];
          STATE.currentOrder.cardIssuerId = instRes[0].issuer?.id || '';
          STATE.currentOrder.cardInstallmentsData = payerCosts;
          renderInstallmentsSelect(payerCosts, vipPreco);
        }
      }
    } catch (e) {
      console.warn('Aviso: Falha na consulta de BIN do cartão:', e);
    }
  }

  function resetInstallmentsSelect(basePrice = 0) {
    const select = document.getElementById('card-installments');
    if (!select) return;
    const formatted = Number(basePrice).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    select.innerHTML = `<option value="1">1x de R$ ${formatted} (À vista sem juros)</option>`;
    atualizarTextoBotaoCartao();
  }

  function renderInstallmentsSelect(payerCosts, basePrice) {
    const select = document.getElementById('card-installments');
    if (!select) return;

    if (!payerCosts || payerCosts.length === 0) {
      resetInstallmentsSelect(basePrice);
      return;
    }

    const MAX_PARCELAS = 3;
    let optionsHtml = '';
    payerCosts.filter(cost => cost.installments <= MAX_PARCELAS).forEach(cost => {
      const n = cost.installments;
      const installmentVal = Number(cost.installment_amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
      const totalVal = Number(cost.total_amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
      const isSemJuros = (cost.installment_rate === 0);

      let label = `${n}x de R$ ${installmentVal}`;
      if (isSemJuros) {
        label += (n === 1) ? ' (À vista sem juros)' : ' (Sem juros)';
      } else {
        label += ` (Total: R$ ${totalVal})`;
      }

      optionsHtml += `<option value="${n}" data-total="${cost.total_amount}" data-installment-val="${cost.installment_amount}">${label}</option>`;
    });

    select.innerHTML = optionsHtml;
    atualizarTextoBotaoCartao();
  }

  function atualizarTextoBotaoCartao() {
    const select = document.getElementById('card-installments');
    const btnLabel = document.getElementById('btn-card-label');
    if (!select || !btnLabel) return;

    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption) {
      const total = selectedOption.dataset.total;
      if (total) {
        btnLabel.textContent = `Pagar R$ ${Number(total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        return;
      }
    }

    const basePrice = STATE.currentOrder.vipData ? Number(STATE.currentOrder.vipData.preco) : 0;
    btnLabel.textContent = `Pagar R$ ${basePrice.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
  }

  function resetCardForm(basePrice = 0) {
    const form = document.getElementById('form-card-checkout');
    if (form) form.reset();

    const iconContainer = document.getElementById('card-brand-icon');
    if (iconContainer) iconContainer.innerHTML = '<i class="fa-solid fa-credit-card"></i>';

    const errorBox = document.getElementById('card-error-box');
    if (errorBox) errorBox.hidden = true;

    setCardButtonLoading(false);
    resetInstallmentsSelect(basePrice);
  }

  function setCardButtonLoading(isLoading) {
    const btn = document.getElementById('btn-submit-card');
    const spinner = document.getElementById('btn-card-spinner');
    const icon = document.getElementById('btn-card-lock-icon');

    if (btn) btn.disabled = isLoading;
    if (spinner) spinner.hidden = !isLoading;
    if (icon) icon.hidden = isLoading;
  }

  // 10.2 PROCESSAMENTO SEGURO DO CARTÃO (TOKENIZAÇÃO + BACKEND)
  async function processarPagamentoCartao() {
    const errorBox = document.getElementById('card-error-box');
    const errorMsg = document.getElementById('card-error-msg');
    if (errorBox) errorBox.hidden = true;

    const inputCardNum = document.getElementById('card-number');
    const inputName = document.getElementById('card-holder-name');
    const inputExpiry = document.getElementById('card-expiry');
    const inputCvv = document.getElementById('card-cvv');
    const inputCpf = document.getElementById('card-cpf');
    const inputEmail = document.getElementById('card-email');
    const selectInstallments = document.getElementById('card-installments');

    const cardNum = (inputCardNum ? inputCardNum.value : '').replace(/\D/g, '');
    const cardholderName = (inputName ? inputName.value : '').trim();
    const expiry = (inputExpiry ? inputExpiry.value : '').trim();
    const cvv = (inputCvv ? inputCvv.value : '').trim();
    const cpf = (inputCpf ? inputCpf.value : '').replace(/\D/g, '');
    const email = (inputEmail ? inputEmail.value : '').trim();
    const installments = parseInt(selectInstallments ? selectInstallments.value : '1', 10) || 1;

    // Validações básicas no cliente
    if (cardNum.length < 13 || cardNum.length > 19) {
      exibirErroCartao('Informe um número de cartão de crédito válido.');
      return;
    }

    if (!cardholderName || cardholderName.length < 3) {
      exibirErroCartao('Informe o nome impresso no cartão.');
      return;
    }

    const expiryParts = expiry.split('/');
    if (expiryParts.length !== 2 || expiryParts[0].length !== 2 || expiryParts[1].length !== 2) {
      exibirErroCartao('Informe a validade no formato MM/AA.');
      return;
    }

    const expMonth = expiryParts[0];
    const expYear = '20' + expiryParts[1];

    if (cvv.length < 3 || cvv.length > 4) {
      exibirErroCartao('Informe o código de segurança (CVV) de 3 ou 4 dígitos.');
      return;
    }

    if (cpf.length !== 11) {
      exibirErroCartao('Informe um CPF válido com 11 dígitos.');
      return;
    }

    if (!email || !email.includes('@') || !email.includes('.')) {
      exibirErroCartao('Informe um e-mail válido para receber o comprovante.');
      return;
    }

    if (!STATE.currentOrder.vipData) {
      exibirErroCartao('Sessão de compra expirada. Selecione o pacote novamente.');
      return;
    }

    setCardButtonLoading(true);

    try {
      // 1. Gera Device ID Antifraude
      let deviceId = '';
      if (window.MP_DEVICE_SESSION_ID) {
        deviceId = window.MP_DEVICE_SESSION_ID;
      } else {
        const securityInput = document.querySelector('input[name="MP_DEVICE_SESSION_ID"]');
        if (securityInput) deviceId = securityInput.value;
      }

      // 2. Tokenização no SDK do Mercado Pago
      if (!STATE.mpInstance && STATE.mpPublicKey && window.MercadoPago) {
        STATE.mpInstance = new window.MercadoPago(STATE.mpPublicKey, { locale: 'pt-BR' });
      }

      let cardToken = '';

      if (STATE.mpInstance) {
        try {
          const tokenRes = await STATE.mpInstance.createCardToken({
            cardNumber: cardNum,
            cardholderName: cardholderName,
            cardExpirationMonth: expMonth,
            cardExpirationYear: expYear,
            securityCode: cvv,
            identificationType: 'CPF',
            identificationNumber: cpf
          });

          if (tokenRes && tokenRes.id) {
            cardToken = tokenRes.id;
          } else {
            throw new Error('Não foi possível validar o cartão com a operadora.');
          }
        } catch (tokenErr) {
          console.error('Erro na tokenização MP:', tokenErr);
          throw new Error('Dados do cartão inválidos ou recusados pela operadora.');
        }
      } else {
        // Fallback de demonstração
        cardToken = 'DEMO_TOKEN_' + Math.random().toString(36).substring(2);
      }

      // 3. Envio seguro ao Backend PHP
      const payload = {
        token: cardToken,
        card_number: cardNum,
        cardholder_name: cardholderName,
        email: email,
        cpf: cpf,
        installments: installments,
        payment_method_id: STATE.currentOrder.cardPaymentMethodId || 'credit_card',
        issuer_id: STATE.currentOrder.cardIssuerId || '',
        device_id: deviceId,
        nick: STATE.nick,
        tipo_conta: STATE.tipoConta,
        servidor: STATE.currentOrder.vipData.serverInfo.nome,
        vip_id: STATE.currentOrder.vipData.id,
        vip_nome: STATE.currentOrder.vipData.nome,
        cupom: STATE.appliedCoupon ? STATE.appliedCoupon.cupom : ''
      };

      const res = await fetch('/api/loja/criar_cartao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (!res.ok || data.erro || !data.success) {
        throw new Error(data.erro || 'Pagamento recusado pela operadora.');
      }

      // Pagamento aprovado com sucesso!
      setCardButtonLoading(false);
      exibirSucessoCheckout({
        ...data,
        metodo: 'Cartão de Crédito'
      });

    } catch (err) {
      console.error('Erro no pagamento com cartão:', err);
      setCardButtonLoading(false);
      exibirErroCartao(err.message || 'Não foi possível processar o pagamento com cartão.');
    }
  }

  function exibirErroCartao(msg) {
    const errorBox = document.getElementById('card-error-box');
    const errorMsg = document.getElementById('card-error-msg');
    if (errorMsg) errorMsg.textContent = msg;
    if (errorBox) errorBox.hidden = false;
  }

  // 11. POLLING E CONTAGEM REGRESSIVA PRECISA (TIMESTAMP PIX)
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
        exibirExpiradoPix(STATE.currentOrder.txid, 'Tempo de 15 minutos esgotado.');
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
        const isExpirado = (data.status === 'expirado' || data.status === 'cancelado' || data.expirado === true);

        if (isPago) {
          pararPollingPix();
          exibirSucessoCheckout({
            ...data,
            metodo: 'PIX'
          });
        } else if (isExpirado) {
          pararPollingPix();
          exibirExpiradoPix(txid, data.mensagem || 'A cobrança PIX expirou no sistema.');
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

  // 12. TELAS DE RESULTADO: EXPIRADO / ERRO
  async function exibirExpiradoPix(txid, motivo = 'O tempo limite para pagamento se esgotou.') {
    pararPollingPix();

    const inputCopiaCola = document.getElementById('input-pix-copiacola');
    const qrImg = document.getElementById('pix-qrcode-img');
    if (inputCopiaCola) inputCopiaCola.value = '';
    if (qrImg) qrImg.src = '';

    if (txid) {
      try {
        fetch(`/api/loja/cancelar_pedido.php?txid=${encodeURIComponent(txid)}&status=expirado`, {
          method: 'POST'
        }).catch(() => {});
      } catch (e) {}
    }

    const orderSummaryBox = document.getElementById('pix-order-summary-box');
    const methodsNav = document.getElementById('checkout-methods-nav');
    const panelPix = document.getElementById('panel-method-pix');
    const panelCard = document.getElementById('panel-method-card');
    const stateSuccess = document.getElementById('pix-success-state');
    const stateError = document.getElementById('pix-error-state');
    const headerTitle = document.getElementById('pix-modal-header-title');

    if (orderSummaryBox) orderSummaryBox.hidden = true;
    if (methodsNav) methodsNav.hidden = true;
    if (panelPix) panelPix.hidden = true;
    if (panelCard) panelCard.hidden = true;
    if (stateSuccess) stateSuccess.hidden = true;
    if (headerTitle) headerTitle.textContent = 'Pagamento Não Efetivado';

    if (stateError) {
      const title = document.getElementById('pix-error-title');
      const desc = document.getElementById('pix-error-desc');
      if (title) title.textContent = 'Pagamento Não Efetivado';
      if (desc) desc.textContent = motivo;
      stateError.hidden = false;
    }
  }

  // 13. TELA DE CONFIRMAÇÃO DE PAGAMENTO APROVADO
  function exibirSucessoCheckout(data) {
    const headerTitle = document.getElementById('pix-modal-header-title');
    const orderSummaryBox = document.getElementById('pix-order-summary-box');
    const methodsNav = document.getElementById('checkout-methods-nav');
    const panelPix = document.getElementById('panel-method-pix');
    const panelCard = document.getElementById('panel-method-card');
    const stateError = document.getElementById('pix-error-state');
    const stateSuccess = document.getElementById('pix-success-state');

    if (orderSummaryBox) orderSummaryBox.hidden = true;
    if (methodsNav) methodsNav.hidden = true;
    if (panelPix) panelPix.hidden = true;
    if (panelCard) panelCard.hidden = true;
    if (stateError) stateError.hidden = true;

    if (headerTitle) headerTitle.textContent = 'Confirmação de Pagamento';

    const rNick = document.getElementById('receipt-player-nick');
    const rVip = document.getElementById('receipt-vip-name');
    const rServer = document.getElementById('receipt-server-name');
    const rMethod = document.getElementById('receipt-method-name');
    const rTxid = document.getElementById('receipt-txid');

    const vipNome = data.vip_nome || STATE.currentOrder.vipData?.nome || 'VIP';
    const serverNome = data.servidor || STATE.currentOrder.vipData?.serverInfo?.nome || 'Servidor';
    const nick = data.nick || STATE.nick || 'Jogador';
    const txid = data.txid || STATE.currentOrder.txid || 'N/A';
    const metodo = data.metodo || (STATE.activePaymentMethod === 'card' ? 'Cartão de Crédito' : 'PIX');

    if (rNick) rNick.textContent = nick;
    if (rVip) rVip.textContent = vipNome;
    if (rServer) rServer.textContent = serverNome;
    if (rMethod) rMethod.textContent = metodo;
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

  // 14. UTILITÁRIOS
  function escapeHTML(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
