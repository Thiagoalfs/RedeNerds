document.addEventListener("DOMContentLoaded", () => {
    const buttons = document.querySelectorAll("[data-copy-ip]");

    buttons.forEach(btn => {
        btn.addEventListener("click", async () => {
            const ip = btn.getAttribute("data-copy-ip");
            if (!ip) return;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(ip);
                } else {
                    // Fallback para contextos não-seguros
                    const textarea = document.createElement("textarea");
                    textarea.value = ip;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "absolute";
                    textarea.style.left = "-9999px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand("copy");
                    document.body.removeChild(textarea);
                }

                showFeedback(btn, "Copiado!");
            } catch (err) {
                console.error("Erro ao copiar IP:", err);
                showFeedback(btn, "Erro ao copiar");
            }
        });
    });

    function showFeedback(btn, message) {
        const original = btn.innerHTML;
        btn.innerHTML = `<i class="fa-solid fa-check"></i> ${message}`;
        btn.classList.add("copied");
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove("copied");
            btn.disabled = false;
        }, 2000);
    }
});
