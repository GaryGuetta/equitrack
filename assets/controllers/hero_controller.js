import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion, SplitText } from '../motion.js';

/*
 * Entrée de la page d'accueil : le slogan s'écrit mot à mot, puis le texte et les boutons montent.
 */
export default class extends Controller {
    static targets = ['slogan', 'fade'];

    connect() {
        if (reduceMotion()) {
            return;
        }

        this.ctx = gsap.context(() => {
            const split = SplitText.create(this.sloganTarget, { type: 'words', mask: 'words' });
            gsap.timeline({ delay: 1.2 })
                .from(split.words, { yPercent: 110, duration: 0.9, stagger: 0.06, ease: 'expo.out' })
                .from(this.fadeTargets, { autoAlpha: 0, y: 20, stagger: 0.12 }, '-=0.5');
        }, this.element);
    }

    disconnect() {
        this.ctx?.revert();
    }
}
