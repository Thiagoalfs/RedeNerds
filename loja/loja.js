/**
 * loja.js - Controlador Oficial da Loja (Rede Nerds)
 * Gerencia identificação do jogador, abas de servidor, checkout multi-método modular (PIX e Cartão de Crédito).
 */

(function () {
  'use strict';

  const STORAGE_KEY_NICK = 'redenerds_loja_nick';
  const DEFAULT_AVATAR = 'https://mc-heads.net/avatar/MHF_Steve/128';

  const STATE = {
    nick: localStorage.getItem(STORAGE_KEY_NICK) || '',
    tipoConta: 'original',
    servidores: [],
    selectedServer: null,
    currentCategory: 'vips', // 'vips' | 'chaves'
    mpPublicKey: '',
    mpInstance: null,
    activePaymentMethod: null, // 'pix' | 'card' | 'international'
    appliedCoupon: null, // { cupom, porcentagem, desconto, preco_original, preco_final }
    currentOrder: {
      txid: null,
      itemData: null,
      vipData: null, // fallback compatibility alias
      tipoProduto: 'vip', // 'vip' | 'chave'
      quantidade: 1,
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
    setupInternationalHandlers();
    setupCouponHandlers();
    carregarCatalogoVips();
    verificarLocalidadeEstrangeira();
    verificarRetornoUrlCheckout();

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

    // Stepper de Quantidade de Chaves no Modal
    const btnQtyMinus = document.getElementById('btn-qty-minus');
    const btnQtyPlus = document.getElementById('btn-qty-plus');
    if (btnQtyMinus) {
      btnQtyMinus.addEventListener('click', () => alterarQuantidade(-1));
    }
    if (btnQtyPlus) {
      btnQtyPlus.addEventListener('click', () => alterarQuantidade(1));
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
        if (STATE.currentOrder.itemData) {
          abrirCheckoutModal(STATE.currentOrder.itemData, STATE.currentOrder.tipoProduto);
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

    // Modal de Informação de Benefício (Mobile / Clique)
    const btnCloseBenefitInfo = document.getElementById('btn-close-benefit-info');
    if (btnCloseBenefitInfo) {
      btnCloseBenefitInfo.addEventListener('click', fecharModalBenefitInfo);
    }

    const btnOkBenefitInfo = document.getElementById('btn-ok-benefit-info');
    if (btnOkBenefitInfo) {
      btnOkBenefitInfo.addEventListener('click', fecharModalBenefitInfo);
    }

    const modalBenefitInfo = document.getElementById('modal-benefit-info');
    if (modalBenefitInfo) {
      modalBenefitInfo.addEventListener('click', (e) => {
        if (e.target === modalBenefitInfo) {
          fecharModalBenefitInfo();
        }
      });
    }

    document.addEventListener('click', (e) => {
      const categoryTabBtn = e.target.closest('.category-tab');
      if (categoryTabBtn) {
        e.preventDefault();
        const cat = categoryTabBtn.dataset.category;
        if (cat && cat !== STATE.currentCategory) {
          selecionarCategoria(cat);
        }
        return;
      }

      const openNickBtn = e.target.closest('#btn-open-nick-modal, #btn-trocar-nick');
      if (openNickBtn) {
        e.preventDefault();
        abrirModalNick(Boolean(STATE.nick));
        return;
      }

      const btn = e.target.closest('.benefit-info-btn');
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        const title = btn.dataset.title || 'Detalhes do Benefício';
        const info = btn.dataset.info || '';
        abrirModalBenefitInfo(title, info);
      }
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

    dialog.removeAttribute('hidden');
    dialog.hidden = false;
    document.body.style.overflow = 'hidden';
    if (inputNick) {
      setTimeout(() => {
        try {
          inputNick.focus();
          inputNick.select();
        } catch (_) {}
      }, 50);
    }
  }

  function fecharModalNick() {
    const dialog = document.getElementById('modal-nick-overlay');
    if (dialog) {
      dialog.setAttribute('hidden', '');
      dialog.hidden = true;
    }
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

    if (avatar) avatar.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    if (nickDisplay) nickDisplay.textContent = STATE.nick;

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
      renderCategoryNav();
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

    renderCategoryNav();
    renderServerSectionsWithDividers();
  }

  // 6.1 SELETOR DE CATEGORIAS (VIPS VS CHAVES)
  function renderCategoryNav() {
    const container = document.getElementById('loja-category-selector-container');
    const navBox = document.getElementById('loja-category-tabs');
    if (!navBox) return;

    const srv = STATE.servidores.find(s => s.id === STATE.selectedServer) || STATE.servidores[0];
    if (!srv) {
      navBox.innerHTML = '';
      if (container) container.hidden = true;
      return;
    }

    const hasVips = Array.isArray(srv.vips) && srv.vips.length > 0;
    const hasChaves = Array.isArray(srv.chaves) && srv.chaves.length > 0;

    // Se a categoria atual não tiver itens disponíveis no servidor ativo, seleciona a que tiver
    if (STATE.currentCategory === 'chaves' && !hasChaves && hasVips) {
      STATE.currentCategory = 'vips';
    } else if (STATE.currentCategory === 'vips' && !hasVips && hasChaves) {
      STATE.currentCategory = 'chaves';
    }

    // Se ambas as categorias tiverem itens disponíveis, renderiza os botões dinamicamente
    if (hasVips && hasChaves) {
      const isVipsActive = (STATE.currentCategory === 'vips');
      const isChavesActive = (STATE.currentCategory === 'chaves');

      let html = `
        <button type="button" class="category-tab ${isVipsActive ? 'active' : ''}" data-category="vips" role="tab" aria-selected="${isVipsActive ? 'true' : 'false'}">
          <i class="fa-solid fa-gem"></i> <span>Pacotes VIP</span>
        </button>
        <button type="button" class="category-tab ${isChavesActive ? 'active' : ''}" data-category="chaves" role="tab" aria-selected="${isChavesActive ? 'true' : 'false'}">
          <i class="fa-solid fa-key"></i> <span>Pacotes de Chaves</span>
        </button>
      `;
      navBox.innerHTML = html;

      navBox.querySelectorAll('.category-tab').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const cat = btn.dataset.category;
          if (cat && cat !== STATE.currentCategory) {
            selecionarCategoria(cat);
          }
        });
      });

      if (container) container.hidden = false;
    } else {
      // Oculta caso só haja uma ou nenhuma categoria cadastrada
      navBox.innerHTML = '';
      if (container) container.hidden = true;
    }
  }

  function selecionarCategoria(categoria) {
    STATE.currentCategory = categoria;

    renderCategoryNav();
    renderServerSectionsWithDividers();
  }

  // 7. RENDERIZAÇÃO DA SEÇÃO DO SERVIDOR E CATEGORIA ATIVA
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

    // Atualiza a visibilidade dinâmica das abas de categoria para o servidor ativo
    renderCategoryNav();

    const isChavesCat = (STATE.currentCategory === 'chaves');
    const itemsList = isChavesCat 
      ? (Array.isArray(srv.chaves) ? srv.chaves : [])
      : (Array.isArray(srv.vips) ? srv.vips : []);

    let cardsHtml = '';

    if (itemsList.length === 0) {
      const msgVazio = isChavesCat 
        ? 'Nenhum pacote de chaves cadastrado para este servidor no momento.'
        : 'Nenhum pacote VIP cadastrado para este servidor no momento.';
      cardsHtml = `
        <div class="state-feedback" style="grid-column: 1 / -1;">
          <p>${escapeHTML(msgVazio)}</p>
        </div>
      `;
    } else if (isChavesCat) {
      // RENDERIZAÇÃO DOS CARDS DE CHAVES
      cardsHtml = itemsList.map((chave, index) => {
        const isFeatured = !!chave.destaque;
        const precoFormatado = Number(chave.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const delay = (index * 0.05).toFixed(2);

        const cor1 = (chave.cor1 || '#ffffff').trim();
        const cor2 = (chave.cor2 || '#ffffff').trim();
        const hasCustomGradient = cor1 && cor2 && (cor1.toLowerCase() !== cor2.toLowerCase()) && (cor1 !== '#ffffff' || cor2 !== '#ffffff');
        const tagTextStyle = hasCustomGradient 
          ? `style="background: linear-gradient(135deg, ${escapeHTML(cor1)}, ${escapeHTML(cor2)}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800;"`
          : `style="color: ${escapeHTML(cor1)}; font-weight: 800;"`;

        const imagemUrl = (chave.imagem || '').trim() || '/assets/images/logo.webp';

        const vantagensHtml = (chave.vantagens || []).map((v) => {
          let item = (v || '').trim();
          let infoTexto = '';

          const matchInfo = item.match(/^(.*?)\s*\((.+?)\)$/);
          if (matchInfo) {
            item = matchInfo[1].trim();
            infoTexto = matchInfo[2].trim();
          }

          const iconeCls = obterIconeVantagem(item);
          let itemHtml = escapeHTML(item);
          itemHtml = itemHtml.replace(/\[(.*?)\]/g, `<span class="vip-tag-badge"><strong ${tagTextStyle}>$1</strong></span>`);

          let infoHtml = '';
          if (infoTexto) {
            const tooltipContent = renderBenefitInfoHtml(infoTexto);
            infoHtml = `
              <span class="benefit-info-trigger-wrap">
                <button type="button" class="benefit-info-btn" data-title="${escapeHTML(item)}" data-info="${escapeHTML(infoTexto)}" title="Ver detalhes" aria-label="Mais informações">
                  <i class="fa-solid fa-circle-info"></i>
                </button>
                <span class="benefit-info-tooltip">${tooltipContent}</span>
              </span>
            `;
          }

          return `<li><i class="${iconeCls}"></i> <span>${itemHtml}${infoHtml}</span></li>`;
        }).join('');

        return `
          <div class="vip-card key-card ${isFeatured ? 'is-featured' : ''}" style="animation-delay: ${delay}s;">
            <!-- IMAGEM EM DESTAQUE NO TOPO -->
            <div class="key-card-image-box">
              <img src="${escapeHTML(imagemUrl)}" alt="${escapeHTML(chave.nome)}" class="key-card-img" loading="lazy" onerror="this.onerror=null; this.src='/assets/images/logo.webp';">
            </div>

            <div class="vip-card-head">
              <span class="vip-server-label">${escapeHTML(srv.nome)}</span>
              ${isFeatured ? `<span class="featured-pill">Mais Escolhido</span>` : ''}
            </div>

            <h3 class="vip-title key-title">${escapeHTML(chave.nome)}</h3>

            <!-- PREÇO UNITÁRIO SEM DURAÇÃO -->
            <div class="vip-price-container key-price-container">
              <span class="price-currency">R$</span>
              <span class="price-val">${precoFormatado}</span>
              <span class="price-period">/ unidade</span>
            </div>

            <ul class="vip-benefits key-benefits">
              ${vantagensHtml}
            </ul>

            <button type="button" class="btn-purchase-card btn-purchase-key" data-item-id="${chave.id}" data-tipo="chave" data-server-id="${srv.id}">
              <span>Adquirir ${escapeHTML(chave.nome)}</span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        `;
      }).join('');
    } else {
      // RENDERIZAÇÃO DOS CARDS VIP (COM DURAÇÃO)
      cardsHtml = itemsList.map((vip, index) => {
        const isFeatured = !!vip.destaque;
        const duracao = vip.duracao_dias ? `${vip.duracao_dias} dias` : '30 dias';
        const precoFormatado = Number(vip.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const delay = (index * 0.05).toFixed(2);

        const cor1 = (vip.cor1 || '#ffffff').trim();
        const cor2 = (vip.cor2 || '#ffffff').trim();
        const hasCustomGradient = cor1 && cor2 && (cor1.toLowerCase() !== cor2.toLowerCase()) && (cor1 !== '#ffffff' || cor2 !== '#ffffff');
        const tagTextStyle = hasCustomGradient 
          ? `style="background: linear-gradient(135deg, ${escapeHTML(cor1)}, ${escapeHTML(cor2)}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800;"`
          : `style="color: ${escapeHTML(cor1)}; font-weight: 800;"`;

        const vantagensHtml = (vip.vantagens || []).map((v, vIndex) => {
          let item = (v || '').trim();
          let infoTexto = '';

          const matchInfo = item.match(/^(.*?)\s*\((.+?)\)$/);
          if (matchInfo) {
            item = matchInfo[1].trim();
            infoTexto = matchInfo[2].trim();
          }

          const iconeCls = obterIconeVantagem(item);
          const isHero = (vIndex === 0 || item.toLowerCase().includes('tag ['));

          let itemHtml = escapeHTML(item);
          itemHtml = itemHtml.replace(/\[(.*?)\]/g, `<span class="vip-tag-badge"><strong ${tagTextStyle}>$1</strong></span>`);

          let infoHtml = '';
          if (infoTexto) {
            const tooltipContent = renderBenefitInfoHtml(infoTexto);
            infoHtml = `
              <span class="benefit-info-trigger-wrap">
                <button type="button" class="benefit-info-btn" data-title="${escapeHTML(item)}" data-info="${escapeHTML(infoTexto)}" title="Ver detalhes" aria-label="Mais informações">
                  <i class="fa-solid fa-circle-info"></i>
                </button>
                <span class="benefit-info-tooltip">${tooltipContent}</span>
              </span>
            `;
          }

          const heroClass = isHero ? ' class="vip-benefit-hero"' : '';
          return `<li${heroClass}><i class="${iconeCls}"></i> <span>${itemHtml}${infoHtml}</span></li>`;
        }).join('');

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

            <button type="button" class="btn-purchase-card" data-item-id="${vip.id}" data-tipo="vip" data-server-id="${srv.id}">
              <span>Adquirir ${escapeHTML(vip.nome)}</span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        `;
      }).join('');
    }

    container.innerHTML = `
      <div class="loja-vips-grid ${isChavesCat ? 'loja-keys-grid' : ''}">
        ${cardsHtml}
      </div>
    `;

    container.querySelectorAll('.btn-purchase-card').forEach(btn => {
      btn.addEventListener('click', () => {
        const itemId = parseInt(btn.dataset.itemId, 10);
        const tipo = btn.dataset.tipo || 'vip';
        const srvId = btn.dataset.serverId;
        
        const currentSrv = STATE.servidores.find(s => s.id === srvId);
        if (!currentSrv) return;

        const item = (tipo === 'chave')
          ? (currentSrv.chaves || []).find(c => c.id === itemId)
          : (currentSrv.vips || []).find(v => v.id === itemId);

        if (item) {
          const itemCompleto = {
            ...item,
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
            abrirCheckoutModal(itemCompleto, tipo);
          }
        }
      });
    });
  }

  // 8. CONTROLE DO MODAL DE CHECKOUT MULTI-MÉTODO
  function abrirCheckoutModal(itemData, tipoProduto = 'vip') {
    STATE.currentOrder.itemData = itemData;
    STATE.currentOrder.vipData = itemData; // compatibilidade
    STATE.currentOrder.tipoProduto = tipoProduto;
    STATE.currentOrder.quantidade = 1;
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
    const quantityGroup = document.getElementById('checkout-quantity-group');
    const inputQty = document.getElementById('input-item-quantity');

    const stateSuccess = document.getElementById('pix-success-state');
    const stateError = document.getElementById('pix-error-state');

    if (!dialog) return;

    pararPollingPix();

    if (headerTitle) headerTitle.textContent = (tipoProduto === 'chave') ? 'Finalizar Compra de Chaves' : 'Finalizar Compra';
    if (orderSummaryBox) orderSummaryBox.hidden = false;
    if (couponBox) couponBox.hidden = false;
    if (methodsNav) methodsNav.hidden = false;
    if (summaryAvatar) summaryAvatar.src = `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64`;
    if (summaryNick) summaryNick.textContent = STATE.nick;
    
    // Controle do seletor de quantidade (apenas para chaves)
    if (quantityGroup) {
      quantityGroup.hidden = (tipoProduto !== 'chave');
    }
    if (inputQty) {
      inputQty.value = 1;
    }

    if (summaryServerVip) {
      const serverColor = (itemData.serverInfo && itemData.serverInfo.cor ? itemData.serverInfo.cor : '#38bdf8').trim();
      const cor1 = (itemData.cor1 || '#ffffff').trim();
      const cor2 = (itemData.cor2 || '#ffffff').trim();
      const hasCustomGradient = cor1 && cor2 && (cor1.toLowerCase() !== cor2.toLowerCase()) && (cor1 !== '#ffffff' || cor2 !== '#ffffff');

      const serverHtml = `<span style="color: ${escapeHTML(serverColor)}; font-weight: 700;">${escapeHTML(itemData.serverInfo ? itemData.serverInfo.nome : '')}</span>`;
      const itemTextStyle = hasCustomGradient
        ? `background: linear-gradient(135deg, ${escapeHTML(cor1)}, ${escapeHTML(cor2)}); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800;`
        : `color: ${escapeHTML(cor1)}; font-weight: 800;`;
      const itemHtml = `<span style="${itemTextStyle}">${escapeHTML(itemData.nome)}</span>`;

      summaryServerVip.innerHTML = `${serverHtml} <span style="color: rgba(255, 255, 255, 0.35); margin: 0 4px;">•</span> ${itemHtml}`;
    }
    
    atualizarPrecoSumario();

    if (stateSuccess) stateSuccess.hidden = true;
    if (stateError) stateError.hidden = true;

    // Reset do form de cartão
    const precoBase = obterPrecoAtualItem();
    resetCardForm(precoBase);

    // Abre o modal
    dialog.hidden = false;
    document.body.style.overflow = 'hidden';

    // Inicia sem método pré-selecionado (o usuário deve escolher uma opção)
    switchPaymentMethod(null);
  }

  function alterarQuantidade(delta) {
    if (STATE.currentOrder.tipoProduto !== 'chave') return;

    let novaQtd = (STATE.currentOrder.quantidade || 1) + delta;
    if (novaQtd < 1) novaQtd = 1;
    if (novaQtd > 100) novaQtd = 100;

    STATE.currentOrder.quantidade = novaQtd;
    const inputQty = document.getElementById('input-item-quantity');
    if (inputQty) inputQty.value = novaQtd;

    atualizarPrecoSumario();

    // Se cupom estiver aplicado, revalida com nova quantidade
    if (STATE.appliedCoupon && STATE.appliedCoupon.cupom) {
      aplicarCupom(STATE.appliedCoupon.cupom);
    } else {
      if (STATE.activePaymentMethod === 'pix') {
        STATE.currentOrder.txid = null;
        gerarCobrancaPix(STATE.currentOrder.itemData);
      } else if (STATE.activePaymentMethod === 'card') {
        const inputCardNum = document.getElementById('card-number');
        if (inputCardNum && inputCardNum.value) {
          onCardNumberInput(inputCardNum.value);
        } else {
          resetInstallmentsSelect(obterPrecoAtualItem());
        }
      }
    }
  }

  function obterPrecoAtualItem() {
    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!item) return 0;

    const precoUnitario = Number(item.preco);
    const qtd = (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1;
    const totalBruto = precoUnitario * qtd;

    if (STATE.appliedCoupon && STATE.appliedCoupon.porcentagem) {
      const pct = Number(STATE.appliedCoupon.porcentagem);
      const desc = Number((totalBruto * (pct / 100)).toFixed(2));
      return Math.max(0.01, Number((totalBruto - desc).toFixed(2)));
    }

    return totalBruto;
  }

  function obterPrecoAtualVip() {
    return obterPrecoAtualItem();
  }

  function atualizarPrecoSumario() {
    const summaryPrice = document.getElementById('summary-price-display');
    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!summaryPrice || !item) return;

    const precoUnitario = Number(item.preco);
    const qtd = (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1;
    const precoOriginalTotal = precoUnitario * qtd;
    const precoFinalTotal = obterPrecoAtualItem();

    if (STATE.appliedCoupon && precoFinalTotal < precoOriginalTotal) {
      summaryPrice.innerHTML = `
        <span class="summary-amount-original">R$ ${precoOriginalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
        <span class="summary-amount-discounted">R$ ${precoFinalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
      `;
    } else {
      summaryPrice.innerHTML = `R$ ${precoOriginalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    // Atualiza label do botão de cartão
    const btnCardLabel = document.getElementById('btn-card-label');
    if (btnCardLabel) {
      btnCardLabel.textContent = `Pagar R$ ${precoFinalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
  }

  // 8.1 TROCA MODULAR DE MÉTODO DE PAGAMENTO
  function switchPaymentMethod(methodName) {
    STATE.activePaymentMethod = methodName || null;

    // Atualiza botões da barra de navegação de métodos
    const methodBtns = document.querySelectorAll('.method-nav-btn');
    methodBtns.forEach(btn => {
      const isTarget = Boolean(methodName && btn.dataset.method === methodName);
      btn.classList.toggle('active', isTarget);
    });

    // Exibe o painel correspondente
    const panelPix = document.getElementById('panel-method-pix');
    const panelCard = document.getElementById('panel-method-card');
    const panelIntl = document.getElementById('panel-method-international');

    if (panelPix) panelPix.hidden = (methodName !== 'pix');
    if (panelCard) panelCard.hidden = (methodName !== 'card');
    if (panelIntl) panelIntl.hidden = (methodName !== 'international');

    if (methodName === 'pix') {
      const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
      if (!STATE.currentOrder.txid && item) {
        gerarCobrancaPix(item);
      }
    } else if (methodName === 'card') {
      const inputCardNum = document.getElementById('card-number');
      if (inputCardNum && inputCardNum.value) {
        onCardNumberInput(inputCardNum.value);
      }
    } else if (methodName === 'international') {
      resetInternationalForm();
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

    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!item) return;

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
          tipo_produto: STATE.currentOrder.tipoProduto || 'vip',
          quantidade: (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1,
          vip_id: item.id,
          chave_id: item.id
        })
      });

      let data = null;
      try {
        data = await res.json();
      } catch (jsonErr) {
        throw new Error(`Erro na resposta do servidor (HTTP ${res.status}).`);
      }

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
        gerarCobrancaPix(item);
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

    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (recriarPagamento && item) {
      if (STATE.activePaymentMethod === 'pix') {
        STATE.currentOrder.txid = null;
        gerarCobrancaPix(item);
      } else if (STATE.activePaymentMethod === 'card') {
        const inputCardNum = document.getElementById('card-number');
        if (inputCardNum && inputCardNum.value) {
          onCardNumberInput(inputCardNum.value);
        } else {
          resetInstallmentsSelect(obterPrecoAtualItem());
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
  async function gerarCobrancaPix(itemData) {
    const item = itemData || STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!item) return;

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
        servidor: item.serverInfo ? item.serverInfo.nome : '',
        tipo_produto: STATE.currentOrder.tipoProduto || 'vip',
        quantidade: (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1,
        vip_id: item.id,
        chave_id: item.id,
        vip_nome: item.nome,
        valor: obterPrecoAtualItem(),
        cupom: STATE.appliedCoupon ? STATE.appliedCoupon.cupom : ''
      };

      const res = await fetch('/api/loja/criar_pix.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      let data = null;
      try {
        data = await res.json();
      } catch (jsonErr) {
        throw new Error(`Erro na resposta do servidor (HTTP ${res.status}).`);
      }

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

    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!item) {
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
        throw new Error('Serviço de tokenização do gateway de pagamento indisponível no momento.');
      }

      // 3. Envio seguro ao Backend PHP (sem trafegar número bruto do cartão)
      const payload = {
        token: cardToken,
        cardholder_name: cardholderName,
        email: email,
        cpf: cpf,
        installments: installments,
        payment_method_id: STATE.currentOrder.cardPaymentMethodId || 'credit_card',
        issuer_id: STATE.currentOrder.cardIssuerId || '',
        device_id: deviceId,
        nick: STATE.nick,
        tipo_conta: STATE.tipoConta,
        servidor: item.serverInfo ? item.serverInfo.nome : '',
        tipo_produto: STATE.currentOrder.tipoProduto || 'vip',
        quantidade: (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1,
        vip_id: item.id,
        chave_id: item.id,
        vip_nome: item.nome,
        cupom: STATE.appliedCoupon ? STATE.appliedCoupon.cupom : ''
      };

      const res = await fetch('/api/loja/criar_cartao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      let data = null;
      try {
        data = await res.json();
      } catch (jsonErr) {
        throw new Error(`Erro na resposta do servidor (HTTP ${res.status}).`);
      }

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

  // 10.3 FLUXO DE PAGAMENTO: CHECKOUT PRO INTERNACIONAL
  function setupInternationalHandlers() {
    const formIntl = document.getElementById('form-international-checkout');
    if (formIntl) {
      formIntl.addEventListener('submit', (e) => {
        e.preventDefault();
        processarCheckoutInternacional();
      });
    }

    const btnCheckStatus = document.getElementById('btn-check-intl-status');
    if (btnCheckStatus) {
      btnCheckStatus.addEventListener('click', () => {
        if (STATE.currentOrder.txid) {
          verificarStatusManualmente(STATE.currentOrder.txid);
        }
      });
    }
  }

  function resetInternationalForm() {
    const form = document.getElementById('form-international-checkout');
    const waitingState = document.getElementById('intl-waiting-state');
    const errorBox = document.getElementById('intl-error-box');

    if (form) form.hidden = false;
    if (waitingState) waitingState.hidden = true;
    if (errorBox) errorBox.hidden = true;
  }

  async function processarCheckoutInternacional() {
    const inputEmail = document.getElementById('intl-email');
    const errorBox = document.getElementById('intl-error-box');
    const errorMsg = document.getElementById('intl-error-msg');
    const btnSubmit = document.getElementById('btn-submit-intl');
    const spinner = document.getElementById('btn-intl-spinner');
    const icon = document.getElementById('btn-intl-icon');
    const waitingState = document.getElementById('intl-waiting-state');
    const form = document.getElementById('form-international-checkout');

    if (errorBox) errorBox.hidden = true;

    const email = inputEmail ? inputEmail.value.trim() : '';
    if (!email || !email.includes('@') || !email.includes('.')) {
      if (errorBox && errorMsg) {
        errorMsg.textContent = 'Please enter a valid email address.';
        errorBox.hidden = false;
      }
      return;
    }

    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    if (!item) {
      if (errorBox && errorMsg) {
        errorMsg.textContent = 'No item selected.';
        errorBox.hidden = false;
      }
      return;
    }

    if (btnSubmit) btnSubmit.disabled = true;
    if (spinner) spinner.hidden = false;
    if (icon) icon.hidden = true;

    try {
      const payload = {
        nick: STATE.nick,
        tipo_conta: STATE.tipoConta,
        servidor: item.serverInfo ? item.serverInfo.nome : '',
        tipo_produto: STATE.currentOrder.tipoProduto || 'vip',
        quantidade: (STATE.currentOrder.tipoProduto === 'chave') ? (STATE.currentOrder.quantidade || 1) : 1,
        vip_id: item.id,
        chave_id: item.id,
        email: email,
        cupom: STATE.appliedCoupon ? STATE.appliedCoupon.cupom : ''
      };

      const res = await fetch('/api/loja/criar_checkout_internacional.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      let data = null;
      try {
        data = await res.json();
      } catch (jsonErr) {
        throw new Error(`Erro na resposta do servidor (HTTP ${res.status}).`);
      }

      if (!res.ok || data.erro || !data.init_point) {
        throw new Error(data.erro || 'Failed to create international payment preference.');
      }

      STATE.currentOrder.txid = data.txid;

      const btnReopen = document.getElementById('btn-reopen-checkout-pro');
      if (btnReopen) btnReopen.href = data.init_point;

      if (form) form.hidden = true;
      if (waitingState) waitingState.hidden = false;

      // Inicia contagem regressiva e polling (janela de 2 horas para Checkout Pro)
      iniciarCountdownPix(2 * 3600);
      iniciarPollingPix(data.txid, 2 * 60 * 60 * 1000);

      // Redirecionamento na mesma aba para o Checkout Pro
      window.location.href = data.init_point;

    } catch (err) {
      console.error('Erro no checkout internacional:', err);
      if (errorBox && errorMsg) {
        errorMsg.textContent = err.message || 'Error communicating with payment gateway.';
        errorBox.hidden = false;
      }
    } finally {
      if (btnSubmit) btnSubmit.disabled = false;
      if (spinner) spinner.hidden = true;
      if (icon) icon.hidden = false;
    }
  }

  async function verificarStatusManualmente(txid) {
    try {
      const res = await fetch(`/api/loja/checar_status.php?txid=${encodeURIComponent(txid)}`);
      if (!res.ok) return;
      const data = await res.json();
      if (data.status === 'pago' || data.status === 'approved' || data.aprovado === true) {
        pararPollingPix();
        exibirSucessoCheckout({
          ...data,
          metodo: 'Checkout Pro Internacional'
        });
      }
    } catch (e) {}
  }

  // 10.4 DETECÇÃO INTELIGENTE DE LOCALIDADE E RETORNO DE CHECKOUT PRO
  async function verificarLocalidadeEstrangeira() {
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
      const lang = (navigator.languages && navigator.languages.length) ? navigator.languages[0] : (navigator.language || '');

      const res = await fetch(`/api/loja/localidade.php?tz=${encodeURIComponent(tz)}&lang=${encodeURIComponent(lang)}`);
      if (!res.ok) return;

      const data = await res.json();
      if (data.sugestao === 'internacional') {
        const banner = document.getElementById('loja-locality-banner');
        if (banner) {
          banner.hidden = false;
          banner.style.display = 'flex';
        }

        const btnSwitch = document.getElementById('btn-switch-international');
        if (btnSwitch) {
          btnSwitch.addEventListener('click', () => {
            STATE.activePaymentMethod = 'international';
            if (STATE.currentOrder.vipData) {
              switchPaymentMethod('international');
            } else {
              const firstVip = document.querySelector('.btn-purchase-card');
              if (firstVip) firstVip.click();
            }
          });
        }
      }
    } catch (e) {}
  }

  function verificarRetornoUrlCheckout() {
    const urlParams = new URLSearchParams(window.location.search);
    const txid = urlParams.get('txid');

    if (txid && txid.startsWith('NERD-')) {
      STATE.currentOrder.txid = txid;
      window.history.replaceState({}, document.title, window.location.pathname);
      
      const dialog = document.getElementById('modal-pix-overlay');
      if (dialog) {
        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
      }
      switchPaymentMethod('international');
      const form = document.getElementById('form-international-checkout');
      const waitingState = document.getElementById('intl-waiting-state');
      if (form) form.hidden = true;
      if (waitingState) waitingState.hidden = false;

      iniciarPollingPix(txid, 2 * 60 * 60 * 1000);
    }
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
        // Mensagem de expiração calculada dinamicamente com base no tempo total configurado
        const textoDuracao = segundosTotais >= 3600
          ? `${Math.round(segundosTotais / 3600)} ${Math.round(segundosTotais / 3600) === 1 ? 'hora' : 'horas'}`
          : `${Math.round(segundosTotais / 60)} ${Math.round(segundosTotais / 60) === 1 ? 'minuto' : 'minutos'}`;
        exibirExpiradoPix(STATE.currentOrder.txid, `Tempo de ${textoDuracao} esgotado.`);
      }
    }

    atualizarTimer();
    STATE.currentOrder.countdownTimer = setInterval(atualizarTimer, 1000);
  }

  function iniciarPollingPix(txid, maxPollingMs = 30 * 60 * 1000) {
    pararPollingPix();

    const startTime = Date.now();
    const MAX_POLLING_MS = maxPollingMs;
    let intervalMs = 5000; // Inicia em 5 segundos

    const pollTask = async () => {
      // Se atingiu o tempo limite máximo de polling, interrompe o polling automático mantendo botão manual
      if (Date.now() - startTime > MAX_POLLING_MS) {
        pararPollingPix();
        return;
      }

      // Executa apenas se a aba estiver visível para economizar recursos
      if (document.visibilityState === 'visible') {
        try {
          const res = await fetch(`/api/loja/checar_status.php?txid=${encodeURIComponent(txid)}`);
          if (res.ok) {
            const data = await res.json();
            const isPago = (data.status === 'pago' || data.status === 'approved' || data.aprovado === true);
            const isExpirado = (data.status === 'expirado' || data.status === 'cancelado' || data.expirado === true);

            if (isPago) {
              pararPollingPix();
              exibirSucessoCheckout({
                ...data,
                metodo: data.metodo || (STATE.activePaymentMethod === 'international' ? 'Checkout Pro Internacional' : (STATE.activePaymentMethod === 'card' ? 'Cartão de Crédito' : 'PIX'))
              });
              return;
            } else if (isExpirado) {
              pararPollingPix();
              exibirExpiradoPix(txid, data.mensagem || 'A cobrança expirou no sistema.');
              return;
            }
          }
        } catch (e) {
          // Silencioso
        }
      }

      // Backoff progressivo até 10s
      intervalMs = Math.min(10000, intervalMs + 1000);
      STATE.currentOrder.pollingInterval = setTimeout(pollTask, intervalMs);
    };

    STATE.currentOrder.pollingInterval = setTimeout(pollTask, intervalMs);
  }

  function pararPollingPix() {
    if (STATE.currentOrder.pollingInterval) {
      clearTimeout(STATE.currentOrder.pollingInterval);
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
    const panelIntl = document.getElementById('panel-method-international');
    const stateSuccess = document.getElementById('pix-success-state');
    const stateError = document.getElementById('pix-error-state');
    const headerTitle = document.getElementById('pix-modal-header-title');

    if (orderSummaryBox) orderSummaryBox.hidden = true;
    if (methodsNav) methodsNav.hidden = true;
    if (panelPix) panelPix.hidden = true;
    if (panelCard) panelCard.hidden = true;
    if (panelIntl) panelIntl.hidden = true;
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
    const panelIntl = document.getElementById('panel-method-international');
    const stateError = document.getElementById('pix-error-state');
    const stateSuccess = document.getElementById('pix-success-state');

    if (orderSummaryBox) orderSummaryBox.hidden = true;
    if (methodsNav) methodsNav.hidden = true;
    if (panelPix) panelPix.hidden = true;
    if (panelCard) panelCard.hidden = true;
    if (panelIntl) panelIntl.hidden = true;
    if (stateError) stateError.hidden = true;

    if (headerTitle) headerTitle.textContent = 'Confirmação de Pagamento';

    const rNick = document.getElementById('receipt-player-nick');
    const rVip = document.getElementById('receipt-vip-name');
    const rServer = document.getElementById('receipt-server-name');
    const rMethod = document.getElementById('receipt-method-name');
    const rTxid = document.getElementById('receipt-txid');

    const item = STATE.currentOrder.itemData || STATE.currentOrder.vipData;
    const isChave = (STATE.currentOrder.tipoProduto === 'chave');
    const qtd = isChave ? (STATE.currentOrder.quantidade || 1) : 1;
    let vipNome = data.vip_nome || item?.nome || 'Item';
    if (isChave && qtd > 1) {
      vipNome = `${vipNome} (x${qtd})`;
    }
    const serverNome = data.servidor || item?.serverInfo?.nome || 'Servidor';
    const nick = data.nick || STATE.nick || 'Jogador';
    const txid = data.txid || STATE.currentOrder.txid || 'N/A';
    const metodo = data.metodo || (STATE.activePaymentMethod === 'card' ? 'Cartão de Crédito' : (STATE.activePaymentMethod === 'international' ? 'Checkout Pro Internacional' : 'PIX'));

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

  // 13. MODAL DE INFORMAÇÃO DE BENEFÍCIO
  function abrirModalBenefitInfo(title, info) {
    const dialog = document.getElementById('modal-benefit-info');
    const titleEl = document.getElementById('benefit-info-title');
    const textEl = document.getElementById('benefit-info-text');

    if (!dialog) return;

    if (titleEl) titleEl.textContent = title;
    if (textEl) {
      textEl.innerHTML = renderBenefitInfoHtml(info);
    }

    dialog.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function fecharModalBenefitInfo() {
    const dialog = document.getElementById('modal-benefit-info');
    if (dialog) dialog.hidden = true;
    document.body.style.overflow = '';
  }

  // 14. UTILITÁRIOS
  function isImageUrl(url) {
    if (!url || typeof url !== 'string') return false;
    const cleanUrl = url.split('?')[0].split('#')[0].toLowerCase();
    return /\.(png|jpe?g|webp|gif|svg|bmp|avif)$/i.test(cleanUrl);
  }

  function obterIconeVantagem(texto) {
    const t = (texto || '').toLowerCase();
    if (t.includes('tag') || t.includes('cosmético') || t.includes('efeito') || t.includes('partícula') || t.includes('cor ')) {
      return 'fa-solid fa-tag';
    }
    if (t.includes('home') || t.includes('/') || t.includes('cooldown') || t.includes('comando') || t.includes('fly')) {
      return 'fa-solid fa-terminal';
    }
    if (t.includes('kit') || t.includes('caixa') || t.includes('item') || t.includes('ferramenta') || t.includes('chave') || t.includes('sanduíche') || t.includes('candy') || t.includes('ball') || t.includes('armadura')) {
      return 'fa-solid fa-box-open';
    }
    if (t.includes('chunk') || t.includes('proteg') || t.includes('terreno')) {
      return 'fa-solid fa-shield-halved';
    }
    if (t.includes('coin') || t.includes('dinheiro') || t.includes('comércio') || t.includes('econ')) {
      return 'fa-solid fa-coins';
    }
    if (t.includes('fila') || t.includes('priorit') || t.includes('prioridade') || t.includes('vaga')) {
      return 'fa-solid fa-star';
    }
    return 'fa-solid fa-check';
  }

  function renderBenefitInfoHtml(rawText) {
    if (!rawText) return '';
    let text = String(rawText).trim();

    // 1. Suporte a sintaxe markdown de imagem ![alt](url)
    text = text.replace(/!\[(.*?)\]\((https?:\/\/[^\s\)]+)\)/gi, (match, alt, url) => {
      return `__IMG_TAG__${url}__ALT__${alt || 'Imagem do benefício'}__END__`;
    });

    // 2. Identifica URLs no texto
    const urlRegex = /(https?:\/\/[^\s<>"'()]+)/gi;
    const parts = [];
    let lastIndex = 0;
    let match;

    while ((match = urlRegex.exec(text)) !== null) {
      const pre = text.substring(lastIndex, match.index);
      if (pre) parts.push({ type: 'text', value: pre });

      const url = match[0];
      if (url.startsWith('__IMG_TAG__') || isImageUrl(url)) {
        let finalUrl = url;
        let alt = 'Imagem do benefício';
        if (url.startsWith('__IMG_TAG__')) {
          const parsed = url.replace('__IMG_TAG__', '').split('__ALT__');
          finalUrl = parsed[0];
          alt = (parsed[1] || '').replace('__END__', '') || 'Imagem do benefício';
        }
        parts.push({ type: 'image', url: finalUrl, alt: alt });
      } else {
        parts.push({ type: 'link', url: url });
      }
      lastIndex = urlRegex.lastIndex;
    }

    const post = text.substring(lastIndex);
    if (post) parts.push({ type: 'text', value: post });

    if (parts.length === 0) {
      return `<span class="benefit-info-text-part">${escapeHTML(text)}</span>`;
    }

    return parts.map(p => {
      if (p.type === 'image') {
        const safeUrl = escapeHTML(p.url);
        const safeAlt = escapeHTML(p.alt);
        return `
          <div class="benefit-info-img-wrapper">
            <a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="benefit-info-img-link" title="Clique para abrir imagem em tela cheia">
              <img src="${safeUrl}" alt="${safeAlt}" class="benefit-info-img" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='<a href=\\'${safeUrl}\\' target=\\'_blank\\' rel=\\'noopener noreferrer\\' class=\\'benefit-info-link\\'>Ver imagem <i class=\\'fa-solid fa-arrow-up-right-from-square ms-1\\'></i></a>';">
            </a>
            <span class="benefit-info-img-hint"><i class="fa-solid fa-magnifying-glass-plus me-1"></i>Clique na imagem para expandir</span>
          </div>
        `;
      } else if (p.type === 'link') {
        const safeUrl = escapeHTML(p.url);
        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="benefit-info-link">${safeUrl} <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a>`;
      } else {
        let val = p.value;
        if (val.includes('__IMG_TAG__')) {
          val = val.replace(/__IMG_TAG__(.*?)__ALT__(.*?)__END__/g, (m, u, a) => {
            const safeUrl = escapeHTML(u);
            const safeAlt = escapeHTML(a || 'Imagem');
            return `<div class="benefit-info-img-wrapper"><a href="${safeUrl}" target="_blank" rel="noopener noreferrer"><img src="${safeUrl}" alt="${safeAlt}" class="benefit-info-img" loading="lazy"></a></div>`;
          });
          return val;
        }
        return `<span class="benefit-info-text-part">${escapeHTML(val)}</span>`;
      }
    }).join('');
  }

  function escapeHTML(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
