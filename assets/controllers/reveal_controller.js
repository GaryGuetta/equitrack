import { Controller } from '@hotwired/stimulus';
import { countUp, gsap, reduceMotion, ScrollTrigger } from '../motion.js';

/*
 * Animations d'apparition, posées sur <main> pour toutes les pages :
 *   data-reveal          apparaît en glissant quand il entre à l'écran (en cascade avec ses voisins)
 *                        et reçoit la classe is-inview, qui lance ses animations CSS
 *   data-count="12.5"    compte de 0 à la valeur (data-decimals pour les décimales)
 *   data-grow            barre qui s'allonge depuis la gauche
 *   data-draw            tracé SVG qui se dessine
 */
export default class extends Controller {
    connect() {
        const html = document.documentElement;
        if (reduceMotion()) {
            this.element.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-inview'));
            html.classList.add('motion-ready');
            return;
        }

        this.ctx = gsap.context(() => {
            const items = this.element.querySelectorAll('[data-reveal]');
            gsap.set(items, { autoAlpha: 0, y: 28 });
            html.classList.add('motion-ready');

            ScrollTrigger.batch(items, {
                start: 'top 92%',
                once: true,
                onEnter: (batch) => {
                    batch.forEach((el) => el.classList.add('is-inview'));
                    gsap.to(batch, { autoAlpha: 1, y: 0, duration: 0.9, stagger: 0.09, overwrite: true });
                },
            });

            this.element.querySelectorAll('[data-count]').forEach((el) => {
                ScrollTrigger.create({ trigger: el, start: 'top 95%', once: true, onEnter: () => countUp(el, { delay: 0.15 }) });
            });

            this.element.querySelectorAll('[data-grow]').forEach((el) => {
                gsap.from(el.children, {
                    scaleX: 0,
                    transformOrigin: 'left center',
                    duration: 1.1,
                    stagger: 0.12,
                    ease: 'power2.inOut',
                    scrollTrigger: { trigger: el, start: 'top 95%', once: true },
                });
            });

            this.element.querySelectorAll('[data-draw]').forEach((el) => {
                gsap.from(el, {
                    drawSVG: 0,
                    duration: 1.6,
                    ease: 'power1.inOut',
                    scrollTrigger: { trigger: el, start: 'top 85%', once: true },
                });
            });
        }, this.element);
    }

    disconnect() {
        this.ctx?.revert();
    }
}
