/*
 * GSAP et ses plugins, enregistrés une seule fois pour tous les contrôleurs.
 * Toutes les animations respectent « Réduire les animations » du système.
 */
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin';

gsap.registerPlugin(ScrollTrigger, SplitText, DrawSVGPlugin, MotionPathPlugin);
gsap.defaults({ ease: 'power3.out', duration: 0.8 });

export const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const numberFormat = (decimals) => new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
});

/** Fait défiler un nombre de 0 à sa valeur, au format français. */
export function countUp(element, { delay = 0 } = {}) {
    const target = Number(element.dataset.count);
    const decimals = Number(element.dataset.decimals ?? 0);
    const format = numberFormat(decimals);
    const state = { value: 0 };

    return gsap.to(state, {
        value: target,
        delay,
        duration: 1.4,
        ease: 'power2.out',
        onUpdate: () => { element.textContent = format.format(state.value); },
    });
}

export { gsap, ScrollTrigger, SplitText };
