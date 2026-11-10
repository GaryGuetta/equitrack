/*
 * Défilement fluide (Lenis), synchronisé avec GSAP/ScrollTrigger.
 * Une seule instance pour toute la session : Turbo ne recharge pas la page entre deux visites.
 */
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';
import { gsap, reduceMotion, ScrollTrigger } from './motion.js';

if (!reduceMotion()) {
    const lenis = new Lenis({
        lerp: 0.1,
        anchors: { offset: -80 },
        // Les cartes Leaflet gardent la molette pour zoomer.
        prevent: (node) => node.closest?.('.leaflet-container, [data-lenis-prevent]') !== null,
    });

    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    // Nouvelle page Turbo : on repart du haut, sans glissement.
    document.addEventListener('turbo:load', () => {
        if (!location.hash) {
            lenis.scrollTo(0, { immediate: true, force: true });
        }
        lenis.resize();
    });

    window.lenis = lenis;
}
