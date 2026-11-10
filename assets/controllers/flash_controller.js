import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../motion.js';

/*
 * Message flash : glisse à l'arrivée, se referme seul après 6 s.
 */
export default class extends Controller {
    connect() {
        if (!reduceMotion()) {
            gsap.from(this.element, { y: -16, autoAlpha: 0, duration: 0.5, ease: 'back.out(1.7)' });
        }
        this.timer = setTimeout(() => this.close(), 6000);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        clearTimeout(this.timer);
        gsap.to(this.element, {
            height: 0, paddingTop: 0, paddingBottom: 0, marginTop: 0, autoAlpha: 0,
            duration: reduceMotion() ? 0 : 0.4,
            ease: 'power2.in',
            onComplete: () => this.element.remove(),
        });
    }
}
