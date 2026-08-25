/**
 * loja.js - Sistema de Loja Oficial Rede Nerds
 * Gerencia a navegação, seleção de jogador, servidores, pacotes VIP e checkout PIX em tempo real.
 */

(function () {
    const STATE = {
        nick: "",
        tipoConta: "original",
        servidores: [],
        selectedServidorId: null,
        selectedVip: null,
        step: 1,
        txid: null,
        pollingTimer: null,
        expireTimer: null
    };

    const DEFAULT_HEAD = "https://mc-heads.net/avatar/MHF_Steve/128";

    function initLoja() {
        injectModalHTML();
        setupGlobalTriggers();
    }

    function injectModalHTML() {
        if (document.getElementById("loja-modal-overlay")) return;

        const overlay = document.createElement("div");
        overlay.id = "loja-modal-overlay";
        overlay.innerHTML = `
            <div id="loja-modal" role="dialog" aria-modal="true" aria-labelledby="loja-modal-title">
                <!-- Header -->
                <div class="loja-header">
                    <h3 id="loja-modal-title" class="loja-header-title">
                        <i class="fa-solid fa-gem"></i> Loja Oficial - Rede Nerds
                    </h3>
                    <button type="button" class="loja-close-btn" id="loja-btn-close" aria-label="Fechar loja">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Breadcrumbs de Etapas -->
                <div class="loja-steps-indicator">
                    <span class="loja-step-item active" id="loja-step-ind-1">
                        <i class="fa-solid fa-user"></i> 1. Jogador
                    </span>
                    <span class="loja-step-sep"><i class="fa-solid fa-chevron-right"></i></span>
                    <span class="loja-step-item" id="loja-step-ind-2">
                        <i class="fa-solid fa-layer-group"></i> 2. Servidor & VIP
                    </span>
                    <span class="loja-step-sep"><i class="fa-solid fa-chevron-right"></i></span>
                    <span class="loja-step-item" id="loja-step-ind-3">
                        <i class="fa-solid fa-qrcode"></i> 3. Pagamento PIX
                    </span>
                </div>

                <!-- Corpo do Modal -->
                <div class="loja-body" id="loja-body-content">
                    <!-- Conteúdo gerado dinamicamente via JS -->
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Eventos do Modal
        document.getElementById("loja-btn-close").addEventListener("click", fecharLoja);
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) fecharLoja();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && overlay.classList.contains("active")) {
                fecharLoja();
            }
        });
    }

    function setupGlobalTriggers() {
        document.addEventListener("click", (e) => {
            const btn = e.target.closest("#btn-abrir-loja, a[href='#loja'], .btn-abrir-loja");
            if (btn) {
                e.preventDefault();
                abrirLoja();
            }
        });
    }

    function abrirLoja() {
        const overlay = document.getElementById("loja-modal-overlay");
        if (!overlay) return;

        overlay.classList.add("active");
        document.body.style.overflow = "hidden";

        // Se já tinha dados salvos, mantém; senão inicia na etapa 1
        if (STATE.step === 1 || !STATE.nick) {
            renderStep1();
        } else if (STATE.step === 2) {
            renderStep2();
        }
    }

    function fecharLoja() {
        const overlay = document.getElementById("loja-modal-overlay");
        if (overlay) overlay.classList.remove("active");
        document.body.style.overflow = "";

        pararPolling();
    }

    function updateStepIndicator(currentStep) {
        STATE.step = currentStep;
        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById(`loja-step-ind-${i}`);
            if (!ind) continue;
            ind.classList.remove("active", "done");
            if (i === currentStep) {
                ind.classList.add("active");
            } else if (i < currentStep) {
                ind.classList.add("done");
            }
        }
    }

    // ==========================================
    // ETAPA 1: Identificação do Jogador
    // ==========================================
    function renderStep1() {
        updateStepIndicator(1);
        const body = document.getElementById("loja-body-content");
        if (!body) return;

        const avatarUrl = STATE.nick ? `https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/128` : DEFAULT_HEAD;

        body.innerHTML = `
            <div class="loja-player-card">
                <div class="loja-avatar-wrap">
                    <img id="loja-avatar-preview" class="loja-avatar-img" src="${avatarUrl}" alt="Avatar">
                </div>

                <div class="loja-input-group">
                    <label class="loja-label" for="loja-input-nick">
                        <i class="fa-solid fa-gamepad"></i> Nickname do Minecraft:
                    </label>
                    <input
                        type="text"
                        id="loja-input-nick"
                        class="loja-input-text"
                        placeholder="Ex: SeuNick"
                        maxlength="16"
                        value="${escapeHTML(STATE.nick)}"
                        autocomplete="off"
                        autofocus
                    >
                </div>

                <div class="loja-input-group">
                    <label class="loja-label">
                        <i class="fa-solid fa-shield-halved"></i> Tipo de Conta:
                    </label>
                    <div class="loja-account-types">
                        <button type="button" class="loja-account-btn ${STATE.tipoConta === 'original' ? 'selected' : ''}" data-type="original">
                            <i class="fa-solid ${STATE.tipoConta === 'original' ? 'fa-circle-check' : 'fa-circle'}"></i>
                            🟢 Conta Original
                        </button>
                        <button type="button" class="loja-account-btn ${STATE.tipoConta === 'pirata' ? 'selected' : ''}" data-type="pirata">
                            <i class="fa-solid ${STATE.tipoConta === 'pirata' ? 'fa-circle-check' : 'fa-circle'}"></i>
                            ⚪ Conta Pirata
                        </button>
                    </div>
                </div>

                <p id="loja-step1-error" style="color: #E85D5D; font-size: 0.88rem; margin: 0; display: none;"></p>

                <button type="button" id="loja-btn-step1-next" class="loja-btn-primary">
                    Continuar para os Servidores <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        `;

        const nickInput = document.getElementById("loja-input-nick");
        const avatarImg = document.getElementById("loja-avatar-preview");
        const errBox = document.getElementById("loja-step1-error");
        let debounceTimer = null;

        // Atualização em tempo real da skin
        nickInput.addEventListener("input", (e) => {
            const val = e.target.value.trim();
            clearTimeout(debounceTimer);
            if (val.length >= 3) {
                debounceTimer = setTimeout(() => {
                    avatarImg.src = `https://mc-heads.net/avatar/${encodeURIComponent(val)}/128`;
                }, 400);
            } else {
                avatarImg.src = DEFAULT_HEAD;
            }
        });

        // Alternar tipo de conta
        body.querySelectorAll(".loja-account-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                body.querySelectorAll(".loja-account-btn").forEach(b => {
                    b.classList.remove("selected");
                    b.querySelector("i").className = "fa-solid fa-circle";
                });
                btn.classList.add("selected");
                btn.querySelector("i").className = "fa-solid fa-circle-check";
                STATE.tipoConta = btn.dataset.type;
            });
        });

        // Avançar para Etapa 2
        document.getElementById("loja-btn-step1-next").addEventListener("click", () => {
            const nick = nickInput.value.trim();
            if (!nick || nick.length < 3 || nick.length > 16 || !/^[a-zA-Z0-9_]+$/.test(nick)) {
                errBox.textContent = "Digite um nick válido (3 a 16 caracteres alfanuméricos ou underline).";
                errBox.style.display = "block";
                nickInput.focus();
                return;
            }

            errBox.style.display = "none";
            STATE.nick = nick;
            carregarServidoresEVips();
        });
    }

    // ==========================================
    // ETAPA 2: Escolha de Servidor & VIP
    // ==========================================
    async function carregarServidoresEVips() {
        const body = document.getElementById("loja-body-content");
        body.innerHTML = `<div style="text-align:center; padding: 40px;"><div class="loja-spinner" style="margin: 0 auto 12px; width: 28px; height: 28px;"></div><p>Carregando servidores e pacotes...</p></div>`;

        try {
            const res = await fetch("/api/loja/vips_api.php");
            const data = await res.json();

            if (!data.success || !Array.isArray(data.servidores) || data.servidores.length === 0) {
                throw new Error("Nenhum pacote VIP disponível no momento.");
            }

            STATE.servidores = data.servidores;
            if (!STATE.selectedServidorId || !STATE.servidores.some(s => s.id === STATE.selectedServidorId)) {
                STATE.selectedServidorId = STATE.servidores[0].id;
            }

            renderStep2();
        } catch (err) {
            body.innerHTML = `
                <div style="text-align:center; padding: 30px;">
                    <p style="color: #E85D5D;">Não foi possível carregar a lista de VIPs.</p>
                    <button type="button" class="loja-btn-primary" style="max-width: 200px; margin: 10px auto;" onclick="renderStep1()">Voltar</button>
                </div>
            `;
        }
    }

    function renderStep2() {
        updateStepIndicator(2);
        const body = document.getElementById("loja-body-content");
        if (!body) return;

        const currentServer = STATE.servidores.find(s => s.id === STATE.selectedServidorId) || STATE.servidores[0];
        const tipoContaTxt = STATE.tipoConta === "original" ? "Original" : "Pirata";

        body.innerHTML = `
            <!-- Badge do Jogador Selecionado -->
            <div class="loja-player-badge">
                <div class="loja-player-badge-left">
                    <img class="loja-player-badge-head" src="https://mc-heads.net/avatar/${encodeURIComponent(STATE.nick)}/64" alt="${escapeHTML(STATE.nick)}">
                    <div>
                        <strong>${escapeHTML(STATE.nick)}</strong>
                        <span style="color:#9ca3af; font-size: 0.8rem; margin-left: 6px;">(${tipoContaTxt})</span>
                    </div>
                </div>
                <button type="button" id="loja-btn-trocar-nick" class="loja-player-badge-trocar">
                    <i class="fa-solid fa-pen"></i> Trocar
                </button>
            </div>

            <!-- Abas dos Servidores -->
            <div class="loja-servers-tabs">
                ${STATE.servidores.map(srv => `
                    <button type="button" class="loja-server-tab ${srv.id === currentServer.id ? 'active' : ''}" data-server="${srv.id}" style="--card-color: ${srv.cor || '#7DB9DF'};">
                        <i class="${srv.icon || 'fa-solid fa-server'}"></i>
                        ${escapeHTML(srv.nome)}
                    </button>
                `).join("")}
            </div>

            <!-- Grid de VIPs do Servidor Escolhido -->
            <div class="loja-vips-grid">
                ${(currentServer.vips || []).map(vip => `
                    <div class="loja-vip-card ${vip.destaque ? 'destaque' : ''}" style="--card-color: ${currentServer.cor || '#7DB9DF'};">
                        ${vip.destaque ? `<span class="loja-vip-badge-destaque">Mais Popular</span>` : ''}
                        <div class="loja-vip-header">
                            <h4 class="loja-vip-nome">${escapeHTML(vip.nome)}</h4>
                            <span class="loja-vip-duracao">${vip.duracao_dias ? `${vip.duracao_dias} dias de acesso` : 'Acesso Vitalício'}</span>
                            <div class="loja-vip-preco">R$ ${vip.preco.toFixed(2).replace('.', ',')}</div>
                        </div>

                        <ul class="loja-vip-vantagens">
                            ${(vip.vantagens || []).map(van => `
                                <li><i class="fa-solid fa-check"></i> <span>${escapeHTML(van)}</span></li>
                            `).join("")}
                        </ul>

                        <button type="button" class="loja-vip-buy-btn" data-vip-id="${vip.id}" data-server-id="${currentServer.id}">
                            <i class="fa-brands fa-pix"></i> Comprar com PIX
                        </button>
                    </div>
                `).join("")}
            </div>
        `;

        // Botão Trocar Nick
        document.getElementById("loja-btn-trocar-nick").addEventListener("click", renderStep1);

        // Alternar aba de servidor
        body.querySelectorAll(".loja-server-tab").forEach(tab => {
            tab.addEventListener("click", () => {
                STATE.selectedServidorId = tab.dataset.server;
                renderStep2();
            });
        });

        // Botões de Comprar VIP
        body.querySelectorAll(".loja-vip-buy-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const srv = STATE.servidores.find(s => s.id === btn.dataset.serverId);
                const vip = (srv?.vips || []).find(v => v.id === parseInt(btn.dataset.vipId, 10));
                if (srv && vip) {
                    STATE.selectedVip = { ...vip, servidorNome: srv.nome, servidorCor: srv.cor };
                    iniciarCheckoutPix();
                }
            });
        });
    }

    // ==========================================
    // ETAPA 3: Checkout PIX
    // ==========================================
    async function iniciarCheckoutPix() {
        updateStepIndicator(3);
        const body = document.getElementById("loja-body-content");
        body.innerHTML = `<div style="text-align:center; padding: 40px;"><div class="loja-spinner" style="margin: 0 auto 12px; width: 28px; height: 28px;"></div><p>Gerando código PIX dinâmico...</p></div>`;

        try {
            const payload = {
                nick: STATE.nick,
                tipo_conta: STATE.tipoConta,
                servidor: STATE.selectedVip.servidorNome,
                vip_id: STATE.selectedVip.id,
                vip_nome: STATE.selectedVip.nome,
                valor: STATE.selectedVip.preco
            };

            const res = await fetch("/api/loja/criar_pix.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!data.success || !data.txid) {
                throw new Error(data.erro || "Falha ao gerar pagamento PIX.");
            }

            STATE.txid = data.txid;
            renderCheckoutScreen(data);
            iniciarPollingPagamento(data.txid);
            iniciarContadorExpiracao(data.expira_em);
        } catch (err) {
            body.innerHTML = `
                <div style="text-align:center; padding: 30px;">
                    <p style="color: #E85D5D;">${escapeHTML(err.message)}</p>
                    <button type="button" class="loja-btn-primary" style="max-width: 200px; margin: 10px auto;" id="loja-btn-checkout-retry">Tentar Novamente</button>
                </div>
            `;
            document.getElementById("loja-btn-checkout-retry").addEventListener("click", renderStep2);
        }
    }

    function renderCheckoutScreen(data) {
        const body = document.getElementById("loja-body-content");
        if (!body) return;

        const qrSrc = data.pix_qr_base64.startsWith("data:") 
            ? data.pix_qr_base64 
            : (data.pix_qr_base64.startsWith("PHN2Zy") ? `data:image/svg+xml;base64,${data.pix_qr_base64}` : `data:image/png;base64,${data.pix_qr_base64}`);

        body.innerHTML = `
            <div class="loja-checkout-wrap">
                <!-- Resumo do Pedido -->
                <div class="loja-checkout-summary">
                    <div>
                        <div style="font-weight: 700; color: white;">${escapeHTML(data.vip_nome)}</div>
                        <div style="font-size: 0.85rem; color: #9ca3af;">${escapeHTML(data.servidor)} • ${escapeHTML(data.nick)}</div>
                    </div>
                    <div style="font-size: 1.3rem; font-weight: 800; color: var(--loja-accent-green);">
                        R$ ${data.valor.toFixed(2).replace('.', ',')}
                    </div>
                </div>

                <!-- Frame do QR Code -->
                <div class="loja-checkout-qr-frame">
                    <img class="loja-checkout-qr-img" src="${qrSrc}" alt="QR Code PIX">
                </div>

                <!-- Código Copia e Cola -->
                <div class="loja-copia-cola-box">
                    <input type="text" class="loja-copia-cola-input" id="loja-pix-copia" value="${escapeHTML(data.pix_copia_cola)}" readonly>
                    <button type="button" class="loja-btn-copiar" id="loja-btn-copiar">
                        <i class="fa-regular fa-copy"></i> Copiar Código PIX
                    </button>
                </div>

                <!-- Status de Verificação em Tempo Real -->
                <div class="loja-polling-status">
                    <div class="loja-spinner"></div>
                    <span>Aguardando pagamento... O sistema confirma automaticamente.</span>
                </div>

                <div class="loja-timer" id="loja-countdown-txt">Válido por 15:00 minutos</div>

                <button type="button" class="loja-player-badge-trocar" id="loja-btn-cancelar-checkout">
                    <i class="fa-solid fa-arrow-left"></i> Escolher outro VIP ou Servidor
                </button>
            </div>
        `;

        // Botão Copiar
        const btnCopiar = document.getElementById("loja-btn-copiar");
        const inputCopia = document.getElementById("loja-pix-copia");

        btnCopiar.addEventListener("click", () => {
            inputCopia.select();
            navigator.clipboard.writeText(inputCopia.value).then(() => {
                btnCopiar.classList.add("copied");
                btnCopiar.innerHTML = `<i class="fa-solid fa-check"></i> Copiado!`;
                setTimeout(() => {
                    btnCopiar.classList.remove("copied");
                    btnCopiar.innerHTML = `<i class="fa-regular fa-copy"></i> Copiar Código PIX`;
                }, 2500);
            });
        });

        // Botão Voltar / Cancelar
        document.getElementById("loja-btn-cancelar-checkout").addEventListener("click", () => {
            pararPolling();
            renderStep2();
        });
    }

    // ==========================================
    // POLLING & CHECAGEM DE STATUS
    // ==========================================
    function iniciarPollingPagamento(txid) {
        pararPolling();

        STATE.pollingTimer = setInterval(async () => {
            try {
                const res = await fetch(`/api/loja/checar_status.php?txid=${encodeURIComponent(txid)}`);
                const data = await res.json();

                if (data && data.status === "pago") {
                    pararPolling();
                    renderSuccessScreen(data);
                }
            } catch (err) {
                console.error("Erro na checagem do PIX:", err);
            }
        }, 3000);
    }

    function pararPolling() {
        if (STATE.pollingTimer) {
            clearInterval(STATE.pollingTimer);
            STATE.pollingTimer = null;
        }
        if (STATE.expireTimer) {
            clearInterval(STATE.expireTimer);
            STATE.expireTimer = null;
        }
    }

    function iniciarContadorExpiracao(isoDate) {
        const expireMs = new Date(isoDate).getTime();
        const countTxt = document.getElementById("loja-countdown-txt");

        STATE.expireTimer = setInterval(() => {
            const now = Date.now();
            const diff = Math.max(0, Math.floor((expireMs - now) / 1000));
            const min = String(Math.floor(diff / 60)).padStart(2, "0");
            const sec = String(diff % 60).padStart(2, "0");

            if (countTxt) {
                countTxt.textContent = `Válido por ${min}:${sec} minutos`;
            }

            if (diff <= 0) {
                pararPolling();
                if (countTxt) countTxt.textContent = "Pagamento expirado. Gere uma nova cobrança.";
            }
        }, 1000);
    }

    // ==========================================
    // ETAPA 4: Sucesso
    // ==========================================
    function renderSuccessScreen(data) {
        const body = document.getElementById("loja-body-content");
        if (!body) return;

        body.innerHTML = `
            <div class="loja-success-wrap">
                <div class="loja-success-icon">
                    <i class="fa-solid fa-check"></i>
                </div>

                <h3 class="loja-success-title">Pagamento Aprovado! 🎉</h3>
                <p style="color: #9ca3af; margin: 0;">Seu VIP foi ativado com sucesso em nossa rede.</p>

                <div class="loja-success-info">
                    <div style="margin-bottom: 8px;"><strong>Jogador:</strong> ${escapeHTML(data.nick || STATE.nick)}</div>
                    <div style="margin-bottom: 8px;"><strong>Servidor:</strong> ${escapeHTML(data.servidor || STATE.selectedVip.servidorNome)}</div>
                    <div style="margin-bottom: 8px;"><strong>Pacote:</strong> ${escapeHTML(data.vip_nome || STATE.selectedVip.nome)}</div>
                    <div><strong>Transação:</strong> <code>${escapeHTML(data.txid || STATE.txid)}</code></div>
                </div>

                <p style="font-size: 0.88rem; color: #a1a7b4; line-height: 1.4;">
                    Entre no servidor <strong>${escapeHTML(data.servidor || STATE.selectedVip.servidorNome)}</strong> com o nick <strong>${escapeHTML(data.nick || STATE.nick)}</strong> para desfrutar de todas as suas vantagens!
                </p>

                <button type="button" class="loja-btn-primary" id="loja-btn-concluir">
                    Concluir e Fechar
                </button>
            </div>
        `;

        document.getElementById("loja-btn-concluir").addEventListener("click", fecharLoja);
    }

    function escapeHTML(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Inicialização ao carregar o DOM
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initLoja);
    } else {
        initLoja();
    }
})();
