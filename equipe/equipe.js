document.addEventListener("DOMContentLoaded", function () {
    carregarEquipe();
});

function adjustColorBrightness(hex, percent) {
    hex = hex.replace(/^#/, '');
    if (hex.length === 3) {
        hex = hex.split('').map(c => c + c).join('');
    }
    const num = parseInt(hex, 16);
    if (isNaN(num)) return '#27acff';

    let r = (num >> 16) + percent;
    let g = ((num >> 8) & 0x00FF) + percent;
    let b = (num & 0x00FF) + percent;

    r = Math.min(255, Math.max(0, r));
    g = Math.min(255, Math.max(0, g));
    b = Math.min(255, Math.max(0, b));

    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

function getCargoTheme(cargoTitle, cargoCor) {
    let hex = (cargoCor && typeof cargoCor === "string") ? cargoCor.trim() : "";
    if (!hex.startsWith("#")) {
        hex = hex ? `#${hex}` : "#27acff";
    }
    if (!/^#[0-9a-fA-F]{3,8}$/.test(hex)) {
        hex = "#27acff";
    }

    const lightHex = adjustColorBrightness(hex, 18);
    const darkHex = adjustColorBrightness(hex, -18);

    return {
        gradient: `linear-gradient(135deg, ${lightHex}, ${darkHex})`,
        color: hex
    };
}

async function carregarEquipe() {
    const containerEquipe = document.getElementById("equipe");
    if (!containerEquipe) return;

    try {
        const response = await fetch("/api/equipe_api.php");
        if (!response.ok) {
            throw new Error(`Erro ao carregar dados: ${response.status}`);
        }

        const cargos = await response.json();

        // Limpa o container
        containerEquipe.innerHTML = "";

        cargos.forEach(cargo => {
            const section = document.createElement("section");
            const theme = getCargoTheme(cargo.categoryTitle, cargo.cor);
            const FALLBACK_SKIN = "https://vzge.me/bust/FreehandCargo95.png";
            const members = Array.isArray(cargo.members) ? cargo.members : [];
            const isCarousel = members.length > 3;

            // Header
            const header = document.createElement("div");
            header.className = "equipe-header";

            const h3 = document.createElement("h3");
            h3.textContent = cargo.categoryTitle; // texto puro, nunca interpretado como HTML

            const headerColor = document.createElement("div");
            headerColor.className = "equipe-header-color";
            headerColor.style.background = `linear-gradient(90deg, ${theme.color}, rgba(0, 0, 0, 0))`;

            header.append(h3, headerColor);

            if (isCarousel) {
                const navButtons = document.createElement("div");
                navButtons.className = "carousel-nav-buttons";
                navButtons.setAttribute("aria-label", "Navegação do carrossel");
                navButtons.innerHTML = `
                    <button type="button" class="btn-carousel-scroll prev" aria-label="Membro anterior"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="btn-carousel-scroll next" aria-label="Próximo membro"><i class="fa-solid fa-chevron-right"></i></button>
                `;
                header.appendChild(navButtons);
            }

            // Wrapper de skins
            const wrapper = document.createElement("div");
            wrapper.className = `skins-wrapper ${isCarousel ? 'is-carousel' : ''}`;

            members.forEach(nick => {
                const skinUrl = `https://vzge.me/bust/${encodeURIComponent(nick)}.png`;

                const skinDiv = document.createElement("div");
                skinDiv.className = "skin";
                skinDiv.style.setProperty('--hover-color', theme.color);

                const img = document.createElement("img");
                img.src = skinUrl;
                img.alt = `Skin de ${nick}`;
                img.loading = "lazy";
                img.addEventListener("error", function () {
                    this.onerror = null;
                    this.src = FALLBACK_SKIN;
                }, { once: true });

                const nametagBox = document.createElement("div");
                nametagBox.className = "nametag-box";
                nametagBox.style.background = theme.gradient;

                const nametag = document.createElement("p");
                nametag.className = "nametag";
                nametag.textContent = nick; // texto puro

                nametagBox.appendChild(nametag);
                skinDiv.append(img, nametagBox);
                wrapper.appendChild(skinDiv);
            });

            section.append(header, wrapper);

            // Ativa o scroll suave pelos botões do carrossel
            if (isCarousel) {
                const prevBtn = section.querySelector(".btn-carousel-scroll.prev");
                const nextBtn = section.querySelector(".btn-carousel-scroll.next");

                if (prevBtn) {
                    prevBtn.addEventListener("click", () => {
                        wrapper.scrollBy({ left: -wrapper.clientWidth * 0.7, behavior: "smooth" });
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener("click", () => {
                        wrapper.scrollBy({ left: wrapper.clientWidth * 0.7, behavior: "smooth" });
                    });
                }
            }

            containerEquipe.appendChild(section);
        });

    } catch (error) {
        console.error("Erro ao carregar a equipe:", error);
        containerEquipe.innerHTML = "<p style='color: white; text-align: center;'>Erro ao carregar os membros da equipe.</p>";
    }
}
