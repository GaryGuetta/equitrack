import { Controller } from '@hotwired/stimulus';
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { buildDevice, buildHeat, buildWaves, COLORS } from '../device3d/device_model.js';
import { gsap, reduceMotion, ScrollTrigger } from '../motion.js';

const FOCUS_KEYS = ['gnss', 'imu', 'sd', 'climate', 'lte', 'battery'];

/*
 * Le boîtier en 3D, piloté par le défilement : il tourne, s'ouvre en vue éclatée,
 * puis chaque chapitre éclaire un capteur et montre ce qu'il fait (ondes GPS, pas du cheval,
 * air chaud, ondes LTE-M, charge de la batterie).
 */
export default class extends Controller {
    static targets = ['stage', 'canvas', 'chapter', 'dot', 'hotspot', 'hotspotLabel', 'progress'];

    connect() {
        try {
            this.setupScene();
        } catch (error) {
            // Pas de WebGL : les chapitres restent lisibles sous forme de liste.
            this.element.classList.add('no-webgl');
            return;
        }

        this.state = {
            yaw: -0.75, pitch: 0.42, camDistance: 29, explode: 0, lidAway: 0, lookY: 0,
            gnss: 0, imu: 0, sd: 0, climate: 0, lte: 0, battery: 0,
        };
        this.pointer = { x: 0, y: 0, tx: 0, ty: 0 };
        this.activeChapter = -1;
        this.clock = new THREE.Clock();

        this.buildTimeline();
        this.setChapter(0);
        this.observeSize();

        this.onPointer = (event) => {
            const rect = this.stageTarget.getBoundingClientRect();
            this.pointer.tx = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
            this.pointer.ty = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
        };
        this.stageTarget.addEventListener('pointermove', this.onPointer);

        // On ne calcule les images que quand la section est à l'écran.
        this.visibility = ScrollTrigger.create({
            trigger: this.element,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (self) => (self.isActive ? this.start() : this.stop()),
        });
    }

    disconnect() {
        this.stop();
        this.timeline?.scrollTrigger?.kill();
        this.timeline?.kill();
        this.visibility?.kill();
        this.resizeObserver?.disconnect();
        this.stageTarget.removeEventListener('pointermove', this.onPointer);
        this.scene?.traverse((object) => {
            object.geometry?.dispose();
            [object.material].flat().forEach((m) => m?.dispose());
        });
        this.environment?.dispose();
        this.renderer?.dispose();
    }

    // ---------------------------------------------------------------- scène

    setupScene() {
        this.renderer = new THREE.WebGLRenderer({ canvas: this.canvasTarget, antialias: true, alpha: true, powerPreference: 'high-performance' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.05;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        this.scene = new THREE.Scene();
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        this.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
        this.scene.environment = this.environment;
        // Reflets discrets : la coque doit rester vert forêt, pas virer au sauge.
        this.scene.environmentIntensity = 0.45;
        pmrem.dispose();

        this.camera = new THREE.PerspectiveCamera(30, 1, 0.1, 300);

        const key = new THREE.DirectionalLight(0xffffff, 1.6);
        key.position.set(6, 14, 8);
        key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        key.shadow.radius = 6;
        key.shadow.camera.left = -12;
        key.shadow.camera.right = 12;
        key.shadow.camera.top = 12;
        key.shadow.camera.bottom = -12;
        key.shadow.bias = -0.0004;
        const rim = new THREE.DirectionalLight(new THREE.Color(COLORS.sage), 0.9);
        rim.position.set(-10, 4, -8);
        this.scene.add(key, rim, new THREE.HemisphereLight(0xfbf8f1, 0x1e3a2f, 0.35));

        const ground = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), new THREE.ShadowMaterial({ opacity: 0.16 }));
        ground.rotation.x = -Math.PI / 2;
        ground.position.y = -4.8;
        ground.receiveShadow = true;
        this.scene.add(ground);

        this.device = buildDevice();
        this.scene.add(this.device.root);

        this.gnssWaves = buildWaves(4, COLORS.sage, false);
        this.lteWaves = buildWaves(4, COLORS.forest, true);
        this.heat = buildHeat();
        this.scene.add(this.gnssWaves, this.lteWaves, this.heat);
    }

    observeSize() {
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.stageTarget);
        this.resize();
    }

    resize() {
        const { width, height } = this.stageTarget.getBoundingClientRect();
        if (width === 0 || height === 0) {
            return;
        }
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        // Sur grand écran, le boîtier se décale à droite pour laisser la place au texte ;
        // sur écran étroit, on recule pour qu'il tienne en largeur.
        const shift = width > 960 ? width * 0.19 : 0;
        this.zoom = Math.max(1, 1.5 / this.camera.aspect);
        this.camera.setViewOffset(width, height, -shift, width > 960 ? 0 : height * 0.12, width, height);
        this.camera.updateProjectionMatrix();
        this.render();
    }

    start() {
        if (this.frame) {
            return;
        }
        const loop = () => {
            this.render();
            this.frame = requestAnimationFrame(loop);
        };
        this.frame = requestAnimationFrame(loop);
    }

    stop() {
        cancelAnimationFrame(this.frame);
        this.frame = null;
    }

    // ---------------------------------------------------------------- défilement

    buildTimeline() {
        const s = this.state;
        const chapters = this.chapterTargets.length;
        const focus = (key, at, extra = {}) => this.timeline
            .to(s, { [key]: 1, duration: 0.35, ease: 'power2.out', ...extra }, at)
            .to(s, { [key]: 0, duration: 0.3, ease: 'power2.in' }, at + 0.7);

        this.timeline = gsap.timeline({
            defaults: { ease: 'power2.inOut', duration: 0.8 },
            // Appelé à chaque image du lissage (scrub) : le texte suit exactement l'image.
            onUpdate: () => {
                this.progressTarget.style.transform = `scaleX(${this.timeline.progress()})`;
                this.setChapter(Math.min(chapters - 1, Math.floor(this.timeline.time())));
            },
            scrollTrigger: {
                trigger: this.element,
                start: 'top top',
                end: `+=${chapters * 90}%`,
                pin: this.stageTarget,
                scrub: 1,
            },
        });

        this.timeline
            // 0 · le boîtier fermé tourne
            .to(s, { yaw: 0.55, duration: 1 }, 0)
            // 1 · vue éclatée
            .to(s, { explode: 1, yaw: -0.35, pitch: 0.55, camDistance: 33, lookY: 0.2, duration: 0.9 }, 1)
            // 2 · GPS : le couvercle s'efface vers l'arrière, vue plongeante sur l'antenne patch
            .to(s, { yaw: -0.05, pitch: 0.95, camDistance: 31, lidAway: 1 }, 2);
        focus('gnss', 2.1);
        // 3 · mouvements : l'IMU et la microSD
        this.timeline.to(s, { yaw: 0.4, pitch: 0.62, camDistance: 31 }, 3);
        focus('imu', 3.1);
        focus('sd', 3.1);
        // 4 · climat : la fenêtre du SHT41 et la sonde extérieure
        this.timeline.to(s, { yaw: 0.85, pitch: 0.5 }, 4);
        focus('climate', 4.1);
        // 5 · LTE-M
        this.timeline.to(s, { yaw: -0.65, pitch: 0.58 }, 5);
        focus('lte', 5.1);
        // 6 · énergie : on écarte un peu plus pour bien voir la batterie
        this.timeline.to(s, { yaw: 0.25, pitch: 0.3, explode: 1.3 }, 6);
        focus('battery', 6.1);
        // 7 · on referme et on fait un tour complet
        this.timeline
            .to(s, { explode: 0, lidAway: 0, pitch: 0.42, camDistance: 27, lookY: 0, duration: 0.6 }, 7)
            .to(s, { yaw: `+=${Math.PI * 2}`, duration: 1, ease: 'power1.inOut' }, 7)
            .set({}, {}, chapters);
    }

    setChapter(index) {
        if (index === this.activeChapter) {
            return;
        }
        this.activeChapter = index;
        this.chapterTargets.forEach((chapter, i) => chapter.classList.toggle('is-active', i === index));
        this.dotTargets.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === index);
            dot.toggleAttribute('aria-current', i === index);
        });
        const chapter = this.chapterTargets[index];
        this.hotspotKey = chapter?.dataset.anchor ?? null;
        this.hotspotLabelTarget.textContent = chapter?.dataset.hotspot ?? '';
    }

    /** Clic sur un intitulé : on fait défiler jusqu'au milieu de ce chapitre. */
    goTo({ params: { index } }) {
        const trigger = this.timeline?.scrollTrigger;
        if (!trigger) {
            return;
        }
        const chapters = this.chapterTargets.length;
        // Le chapitre i occupe l'intervalle [i, i + 1) de la timeline ; on vise juste après son début,
        // quand ses animations sont jouées.
        const target = trigger.start + (trigger.end - trigger.start) * ((index + 0.6) / chapters);
        const distance = Math.abs(target - window.scrollY);
        const duration = reduceMotion() ? 0 : Math.min(2.4, 0.8 + distance / 2500);

        if (window.lenis) {
            window.lenis.scrollTo(target, { duration, easing: (t) => 1 - (1 - t) ** 4 });
        } else {
            window.scrollTo({ top: target, behavior: duration ? 'smooth' : 'auto' });
        }
    }

    // ---------------------------------------------------------------- rendu

    render() {
        if (!this.renderer || !this.state) {
            return;
        }
        const t = this.clock.getElapsedTime();
        const s = this.state;
        const calm = reduceMotion() ? 0 : 1;
        const { root, parts, focusMaterials, anchors } = this.device;

        // Basculement doux selon la souris
        this.pointer.x += (this.pointer.tx - this.pointer.x) * 0.05;
        this.pointer.y += (this.pointer.ty - this.pointer.y) * 0.05;

        // Le boîtier flotte ; pendant le chapitre IMU, il « trotte ».
        root.rotation.y = s.yaw + Math.sin(t * 0.4) * 0.06 * calm + this.pointer.x * 0.18;
        root.rotation.x = this.pointer.y * 0.08;
        root.rotation.z = Math.sin(t * 9.5) * 0.05 * s.imu * calm;
        root.position.y = Math.sin(t * 1.1) * 0.08 * calm + Math.abs(Math.sin(t * 4.75)) * 0.25 * s.imu * calm;

        // Vue éclatée
        const e = s.explode;
        // Couvercle soulevé, puis rabattu vers l'arrière comme un capot pour dégager la vue sur la carte.
        const away = s.lidAway;
        parts.lid.position.set(0, 3.3 * e + 1.2 * away, -6.2 * away);
        parts.lid.rotation.set(0.55 * away, 0, -0.06 * e * (1 - away));
        parts.gasket.position.y = 1.9 * e;
        parts.board.position.y = 0.95 * e;
        parts.board.children.forEach((child) => {
            if (child.userData.lift !== undefined) {
                child.position.y = child.userData.base + child.userData.lift * Math.min(e, 1) * 0.6;
            }
        });
        parts.battery.position.y = 0.25 * e;
        parts.bottom.position.y = -2.7 * e;

        // Caméra en orbite autour du boîtier
        const distance = s.camDistance * (this.zoom ?? 1);
        this.camera.position.set(0, Math.sin(s.pitch) * distance, Math.cos(s.pitch) * distance);
        this.camera.lookAt(0, s.lookY, 0);

        // Composants mis en lumière
        const pulse = 0.75 + 0.25 * Math.sin(t * 4);
        for (const key of FOCUS_KEYS) {
            focusMaterials[key].emissiveIntensity = s[key] * 1.1 * pulse;
        }
        parts.probe.material.emissiveIntensity = s.climate * 0.9 * pulse;
        parts.led.scale.setScalar(1 + 0.4 * Math.max(0, Math.sin(t * 3)));

        root.updateMatrixWorld(true);
        this.animateWaves(this.gnssWaves, anchors.gnss, s.gnss, t, 0.5, 3.6, new THREE.Vector3(0, 0.4, 0));
        this.lteWaves.quaternion.copy(this.camera.quaternion);
        this.animateWaves(this.lteWaves, anchors.lte, s.lte, t, 0.7, 5, new THREE.Vector3(0, 0.2, 0));
        this.animateHeat(anchors.climate, s.climate, t);

        // Jauge de batterie qui se remplit en boucle
        const level = (t * 0.3) % 1;
        parts.charge.scale.y = Math.max(0.001, 1 - (1 - level) ** 3);
        parts.charge.material.opacity = s.battery * 0.5;

        this.renderer.render(this.scene, this.camera);
        this.placeHotspot();
    }

    animateWaves(group, anchor, intensity, t, speed, maxScale, offset) {
        group.visible = intensity > 0.01;
        if (!group.visible) {
            return;
        }
        anchor.getWorldPosition(group.position).add(offset);
        group.children.forEach((ring) => {
            const phase = (t * speed + ring.userData.phase) % 1;
            ring.scale.setScalar(0.5 + phase * maxScale);
            ring.material.opacity = (1 - phase) * 0.7 * intensity;
        });
    }

    animateHeat(anchor, intensity, t) {
        this.heat.visible = intensity > 0.01;
        if (!this.heat.visible) {
            return;
        }
        const origin = anchor.getWorldPosition(new THREE.Vector3());
        const positions = this.heat.geometry.attributes.position;
        const seeds = this.heat.userData.seeds;
        for (let i = 0; i < seeds.length; i++) {
            const seed = seeds[i];
            const phase = (seed + t * 0.35) % 1;
            positions.setXYZ(
                i,
                origin.x + Math.sin(seed * 40 + t) * 0.55 * (0.4 + phase),
                origin.y + 0.1 + phase * 3.2,
                origin.z + Math.cos(seed * 31) * 0.4 * (0.4 + phase),
            );
        }
        positions.needsUpdate = true;
        this.heat.material.opacity = intensity * 0.85;
    }

    /** Étiquette HTML qui suit le composant éclairé à l'écran. */
    placeHotspot() {
        const key = this.hotspotKey;
        const anchor = key ? this.device.anchors[key] : null;
        const strength = key ? this.state[key] : 0;
        if (!anchor || strength < 0.25) {
            this.hotspotTarget.classList.remove('is-visible');
            return;
        }
        const point = anchor.getWorldPosition(new THREE.Vector3()).project(this.camera);
        const { width, height } = this.stageTarget.getBoundingClientRect();
        this.hotspotTarget.style.transform = `translate(${(point.x * 0.5 + 0.5) * width}px, ${(-point.y * 0.5 + 0.5) * height}px)`;
        this.hotspotTarget.classList.add('is-visible');
    }
}
