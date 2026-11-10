import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../motion.js';

/*
 * Logo de la page d'accueil : les lettres montent, le fer à cheval se dessine, puis les clous se posent.
 */
export default class extends Controller {
    connect() {
        if (reduceMotion()) {
            return;
        }

        const parts = this.element.querySelectorAll('.wm-part');
        const outline = this.element.querySelector('.shoe-outline');
        const nails = this.element.querySelectorAll('.shoe-nails circle');

        this.timeline = gsap.timeline({ delay: 0.15 })
            .from(parts, { yPercent: 35, autoAlpha: 0, duration: 1.1, stagger: 0.12, ease: 'expo.out' })
            .from(outline, { drawSVG: 0, duration: 1.4, ease: 'power2.inOut' }, 0.25)
            .from(nails, { scale: 0, transformOrigin: '50% 50%', duration: 0.45, stagger: 0.08, ease: 'back.out(3)' }, '-=0.35')
            .fromTo(this.element.querySelector('.shoe'), { rotation: 0 }, {
                keyframes: { rotation: [7, -5, 2.5, 0] },
                transformOrigin: '50% 0%',
                duration: 1.3,
                ease: 'sine.inOut',
            }, '-=0.1');
    }

    disconnect() {
        this.timeline?.kill();
    }
}
