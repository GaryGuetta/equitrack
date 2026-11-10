import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion, ScrollTrigger } from '../motion.js';

/*
 * Bandeau qui défile sans fin ; il accélère avec la vitesse de défilement de la page.
 */
export default class extends Controller {
    static targets = ['track'];

    connect() {
        if (reduceMotion()) {
            return;
        }
        // Le contenu est dupliqué pour boucler sans couture.
        this.trackTarget.append(...[...this.trackTarget.children].map((node) => {
            const copy = node.cloneNode(true);
            copy.setAttribute('aria-hidden', 'true');
            return copy;
        }));

        this.tween = gsap.to(this.trackTarget, { xPercent: -50, duration: 40, ease: 'none', repeat: -1 });
        // On part loin dans les répétitions pour pouvoir aussi défiler à l'envers.
        this.tween.totalTime(this.tween.duration() * 1000);
        this.trigger = ScrollTrigger.create({
            trigger: this.element,
            start: 'top bottom',
            end: 'bottom top',
            onUpdate: (self) => {
                const direction = self.direction < 0 ? -1 : 1;
                const boost = 1 + Math.min(6, Math.abs(self.getVelocity()) / 400);
                this.speed?.kill();
                this.speed = gsap.timeline()
                    .to(this.tween, { timeScale: boost * direction, duration: 0.2 })
                    .to(this.tween, { timeScale: direction, duration: 1.2 }, '+=0.25');
            },
        });
    }

    disconnect() {
        this.trigger?.kill();
        this.speed?.kill();
        this.tween?.kill();
    }
}
