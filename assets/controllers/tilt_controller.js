import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../motion.js';

/*
 * Carte qui s'incline vers la souris, avec un reflet qui suit le curseur.
 */
export default class extends Controller {
    connect() {
        if (reduceMotion() || !window.matchMedia('(hover: hover)').matches) {
            return;
        }
        this.element.classList.add('has-tilt');
        this.rotateX = gsap.quickTo(this.element, 'rotationX', { duration: 0.5, ease: 'power3.out' });
        this.rotateY = gsap.quickTo(this.element, 'rotationY', { duration: 0.5, ease: 'power3.out' });
        gsap.set(this.element, { transformPerspective: 900 });

        this.onMove = (event) => {
            const rect = this.element.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;
            this.rotateY((x - 0.5) * 10);
            this.rotateX((0.5 - y) * 10);
            this.element.style.setProperty('--glare-x', `${x * 100}%`);
            this.element.style.setProperty('--glare-y', `${y * 100}%`);
        };
        this.onLeave = () => {
            this.rotateX(0);
            this.rotateY(0);
        };
        this.element.addEventListener('pointermove', this.onMove);
        this.element.addEventListener('pointerleave', this.onLeave);
    }

    disconnect() {
        this.element.removeEventListener('pointermove', this.onMove);
        this.element.removeEventListener('pointerleave', this.onLeave);
    }
}
