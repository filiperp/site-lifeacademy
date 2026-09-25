import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

/*
 * Entrada suave das seções ao rolar.
 * IntersectionObserver em vez de listener de scroll para não pagar layout a
 * cada frame; quem pediu menos movimento vê tudo já visível, via CSS.
 */
const reveal = () => {
    const targets = document.querySelectorAll('.reveal');

    if (!targets.length) return;

    if (!('IntersectionObserver' in window)) {
        targets.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.05 },
    );

    targets.forEach((el) => observer.observe(el));
};

document.addEventListener('DOMContentLoaded', reveal);
