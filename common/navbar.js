// common/navbar.js
// Lógica do menu hambúrguer — funciona em todos os .html que importam este arquivo.

(function () {
    'use strict';

    const nav = document.querySelector('nav');
    const toggle = document.getElementById('navbar-toggle');
    const navbar = document.getElementById('navbar');

    if (!nav || !toggle || !navbar) return;

    const openIcon = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
    const closeIcon = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

    function setOpen(isOpen) {
        nav.classList.toggle('navbar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
        toggle.innerHTML = isOpen ? closeIcon : openIcon;
    }

    // Toggle ao clicar no botão
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = nav.classList.contains('navbar-open');
        setOpen(!isOpen);
    });

    // Fecha ao clicar em qualquer link da navbar
    navbar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setOpen(false);
        });
    });

    // Fecha ao clicar fora do nav
    document.addEventListener('click', function (e) {
        if (!nav.classList.contains('navbar-open')) return;
        if (!nav.contains(e.target)) setOpen(false);
    });

    // Fecha com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('navbar-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Garante estado correto ao redimensionar pra desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && nav.classList.contains('navbar-open')) {
            setOpen(false);
        }
    });
})();
