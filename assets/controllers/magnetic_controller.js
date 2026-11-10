import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../motion.js';

/*
 * Bouton « magnétique » : il se laisse attirer par le curseur puis revient en place avec un rebond.
 */
export default class extends Controller {
    connect() {
        if (reduceMotion() || !window.matchMedia('(hover: hover)').matches) {
            return;
        }
        this.x = gsap.quickTo(this.element, 'x', { duration: 0.4, ease: 'power3.out' });
        this.y = gsap.quickTo(this.element, 'y', { duration: 0.4, ease: 'power3.out' });

        this.onMove = (event) => {
            const rect = this.element.getBoundingClientRect();
            this.x((event.clientX - rect.left - rect.width / 2) * 0.3);
            this.y((event.clientY - rect.top - rect.height / 2) * 0.4);
        };
        this.onLeave = () => gsap.to(this.element, { x: 0, y: 0, duration: 0.9, ease: 'elastic.out(1, 0.35)' });
        this.element.addEventListener('pointermove', this.onMove);
        this.element.addEventListener('pointerleave', this.onLeave);
    }

    disconnect() {
        this.element.removeEventListener('pointermove', this.onMove);
        this.element.removeEventListener('pointerleave', this.onLeave);
    }
}
