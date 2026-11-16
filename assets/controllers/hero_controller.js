import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion, SplitText } from '../motion.js';

/*
 * Entrée de la page d'accueil : les courbes du pré se tracent, le slogan s'écrit mot à mot,
 * puis le texte, les boutons et les instantanés « en direct » arrivent. Ces derniers suivent
 * légèrement la souris, chacun à sa profondeur.
 */
export default class extends Controller {
    static targets = ['slogan', 'fade', 'contour', 'float'];

    connect() {
        if (reduceMotion()) {
            return;
        }

        this.ctx = gsap.context(() => {
            const split = SplitText.create(this.sloganTarget, { type: 'words', mask: 'words' });
            gsap.fromTo(this.contourTargets, { drawSVG: '50% 50%' }, { drawSVG: '0% 100%', duration: 2.4, stagger: 0.12, ease: 'power2.inOut' });
            gsap.timeline({ delay: 1.2 })
                .from(split.words, { yPercent: 110, duration: 0.9, stagger: 0.06, ease: 'expo.out' })
                .from(this.fadeTargets, { autoAlpha: 0, y: 20, stagger: 0.12 }, '-=0.5')
                .from(this.floatTargets, { autoAlpha: 0, scale: 0.85, y: 30, stagger: 0.15, duration: 0.9, ease: 'back.out(1.6)' }, '-=0.6');
        }, this.element);

        this.movers = this.floatTargets.map((el) => ({
            depth: Number(el.dataset.depth),
            x: gsap.quickTo(el, 'x', { duration: 1.2, ease: 'power3.out' }),
            y: gsap.quickTo(el, 'y', { duration: 1.2, ease: 'power3.out' }),
        }));
    }

    parallax(event) {
        if (!this.movers) {
            return;
        }
        const box = this.element.getBoundingClientRect();
        const dx = (event.clientX - box.left) / box.width - 0.5;
        const dy = (event.clientY - box.top) / box.height - 0.5;
        for (const mover of this.movers) {
            mover.x(dx * mover.depth);
            mover.y(dy * mover.depth);
        }
    }

    disconnect() {
        this.ctx?.revert();
    }
}
