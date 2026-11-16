import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion, ScrollTrigger } from '../motion.js';

const GAITS = ['Pas', 'Pas', 'Repos', 'Pas', 'Trot', 'Pas', 'Galop', 'Trot'];
const ACTIVITY = { Repos: 6, Pas: 26, Trot: 62, Galop: 88 };

/*
 * Démo de la page d'accueil : un cheval se promène dans son pré, son trajet GPS se dessine,
 * les mesures défilent, et une alerte part quand il franchit la clôture (F2, F3, F4).
 */
export default class extends Controller {
    static targets = ['fence', 'track', 'horse', 'gait', 'temperature', 'activity', 'activityBar', 'distance', 'status', 'toast'];

    connect() {
        this.format = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        this.trackLength = this.trackTarget.getTotalLength();

        if (reduceMotion()) {
            this.walkTo(0.45);
            return;
        }

        this.ctx = gsap.context(() => this.play());
        this.trigger = ScrollTrigger.create({
            trigger: this.element,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (self) => (self.isActive ? this.loop?.resume() : this.loop?.pause()),
        });
    }

    disconnect() {
        this.trigger?.kill();
        this.ctx?.revert();
    }

    play() {
        this.escaped = false;
        let gaitIndex = 0;
        const walk = { progress: 0 };

        this.loop = gsap.timeline({ onComplete: () => this.restart() })
            .set(this.fenceTarget, { attr: { class: 'fence' } })
            .set(this.horseTarget, { attr: { class: 'horse' } })
            .set(this.toastTarget, { autoAlpha: 0, y: 16 })
            .add(() => this.setStatus(false))
            .fromTo(walk, { progress: 0 }, { progress: 1, duration: 16, ease: 'none', onUpdate: () => this.walkTo(walk.progress) }, 0)
            .to({}, { duration: 3.5 });

        // Les mesures « arrivent » toutes les 2 s, comme un lot du boîtier.
        for (let t = 0; t < 16; t += 2) {
            this.loop.add(() => this.updateMetrics(GAITS[gaitIndex++ % GAITS.length]), t);
        }
    }

    restart() {
        gsap.to([this.trackTarget, this.horseTarget, this.toastTarget], {
            autoAlpha: 0,
            duration: 0.6,
            onComplete: () => {
                gsap.set([this.trackTarget, this.horseTarget], { autoAlpha: 1 });
                this.walkTo(0);
                this.play();
            },
        });
    }

    /** Trait et point partent de la même longueur : le point reste toujours au bout du trajet. */
    walkTo(progress) {
        const length = this.trackLength * progress;
        const point = this.trackTarget.getPointAtLength(length);
        this.trackTarget.style.strokeDasharray = `${length} ${this.trackLength}`;
        this.horseTarget.setAttribute('transform', `translate(${point.x} ${point.y})`);

        if (!this.escaped && progress > 0 && !this.fenceTarget.isPointInFill(new DOMPoint(point.x, point.y))) {
            this.escape();
        }
    }

    escape() {
        this.escaped = true;
        this.fenceTarget.setAttribute('class', 'fence is-breached');
        this.horseTarget.setAttribute('class', 'horse is-out');
        this.setStatus(true);
        gsap.to(this.toastTarget, { autoAlpha: 1, y: 0, duration: 0.6, ease: 'back.out(1.6)' });
    }

    updateMetrics(gait) {
        const activity = ACTIVITY[gait] + gsap.utils.random(-4, 4, 1);
        const temperature = gsap.utils.random(26.8, 28.4, 0.1);
        const distance = (this.trackLength * this.loop.progress() * 4.2) / 1000;

        this.swapText(this.gaitTarget, gait);
        this.swapText(this.temperatureTarget, this.format.format(temperature));
        this.activityTarget.textContent = activity;
        this.distanceTarget.textContent = this.format.format(distance);
        gsap.to(this.activityBarTarget, { scaleX: activity / 100, duration: 0.8, ease: 'power2.out' });
    }

    swapText(element, text) {
        if (element.textContent === text) {
            return;
        }
        gsap.timeline()
            .to(element, { yPercent: -60, autoAlpha: 0, duration: 0.2, ease: 'power2.in' })
            .add(() => { element.textContent = text; })
            .fromTo(element, { yPercent: 60 }, { yPercent: 0, autoAlpha: 1, duration: 0.35 });
    }

    setStatus(outside) {
        this.statusTarget.className = outside ? 'badge badge-critical' : 'badge badge-ok';
        this.statusTarget.textContent = outside ? 'Hors de l\'enclos' : 'Dans l\'enclos';
    }
}
