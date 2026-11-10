/*
 * Boîtier Equitrack en 3D, construit en code d'après la nomenclature (variante LTE-M) :
 * coque PETG vert forêt + joint TPU sauge, carte ESP32-S3 / SIM7080G, GPS Quectel L76K,
 * IMU LSM6DSOX, SHT41 sous membrane PTFE, sonde DS18B20, microSD, batterie 18650.
 *
 * Unité : 1 = 10 mm. Le boîtier fait 126 × 30 × 48 mm (x = longueur, y = épaisseur, z = largeur).
 */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

export const COLORS = {
    forest: 0x1e3a2f,
    sage: 0x8db89f,
    cream: 0xfbf8f1,
    pcb: 0x0f2a20,
    amber: 0xe2a54a,
};

const LENGTH = 12.6;
const WIDTH = 4.8;

function roundedRectShape(width, depth, radius) {
    const x = -width / 2;
    const y = -depth / 2;
    const shape = new THREE.Shape();
    shape.moveTo(x + radius, y);
    shape.lineTo(x + width - radius, y);
    shape.quadraticCurveTo(x + width, y, x + width, y + radius);
    shape.lineTo(x + width, y + depth - radius);
    shape.quadraticCurveTo(x + width, y + depth, x + width - radius, y + depth);
    shape.lineTo(x + radius, y + depth);
    shape.quadraticCurveTo(x, y + depth, x, y + depth - radius);
    shape.lineTo(x, y + radius);
    shape.quadraticCurveTo(x, y, x + radius, y);

    return shape;
}

/** Bloc arrondi « coussin », extrudé verticalement et centré. */
function pillow(width, height, depth, radius, bevel) {
    const geometry = new THREE.ExtrudeGeometry(roundedRectShape(width - 2 * bevel, depth - 2 * bevel, radius), {
        depth: Math.max(0.01, height - 2 * bevel),
        bevelEnabled: bevel > 0,
        bevelThickness: bevel,
        bevelSize: bevel,
        bevelSegments: 6,
        curveSegments: 18,
    });
    geometry.rotateX(-Math.PI / 2);
    geometry.center();

    return geometry;
}

/** Le fer à cheval du logo, avec ses six clous percés (mêmes cotes que le SVG du site). */
function horseshoeGeometry() {
    const shape = new THREE.Shape();
    shape.moveTo(-43, 50);
    shape.lineTo(-21, 50);
    shape.lineTo(-21, -7);
    shape.absarc(0, -7, 21, Math.PI, 2 * Math.PI, false);
    shape.lineTo(21, 50);
    shape.lineTo(43, 50);
    shape.lineTo(43, -7);
    shape.absarc(0, -7, 43, 2 * Math.PI, Math.PI, true);
    shape.lineTo(-43, 50);

    for (const [x, y] of [[-32, 33], [-32, 14], [32, 33], [32, 14], [-21, -32], [21, -32]]) {
        const hole = new THREE.Path();
        hole.absarc(x, y, 4.6, 0, Math.PI * 2, true);
        shape.holes.push(hole);
    }

    const geometry = new THREE.ExtrudeGeometry(shape, { depth: 4, bevelEnabled: true, bevelThickness: 1, bevelSize: 1, bevelSegments: 3, curveSegments: 32 });
    geometry.scale(0.02, 0.02, 0.02);
    geometry.rotateX(-Math.PI / 2);

    return geometry;
}

function material(color, options = {}) {
    return new THREE.MeshPhysicalMaterial({ color, roughness: 0.55, metalness: 0, ...options });
}

function mesh(geometry, mat, position = [0, 0, 0]) {
    const m = new THREE.Mesh(geometry, mat);
    m.position.set(...position);
    m.castShadow = true;
    m.receiveShadow = true;

    return m;
}

/**
 * @returns {{ root: THREE.Group, parts: object, focusMaterials: object, anchors: object }}
 */
export function buildDevice() {
    const root = new THREE.Group();
    const shell = material(COLORS.forest, { roughness: 0.5, clearcoat: 0.35, clearcoatRoughness: 0.4 });
    const tpu = material(COLORS.sage, { roughness: 0.8 });
    const metal = material(0xc9cdd0, { metalness: 1, roughness: 0.32 });
    const darkMetal = material(0x2b2f31, { metalness: 0.6, roughness: 0.45 });
    const black = material(0x111614, { roughness: 0.5 });

    // Matériaux qui s'illuminent quand on parle du composant.
    const focusMaterials = {
        gnss: material(0xcdb98f, { roughness: 0.6, emissive: new THREE.Color(COLORS.sage), emissiveIntensity: 0 }),
        imu: material(0x151a18, { roughness: 0.35, emissive: new THREE.Color(COLORS.sage), emissiveIntensity: 0 }),
        climate: material(0xf4f1ea, { roughness: 0.95, emissive: new THREE.Color(COLORS.amber), emissiveIntensity: 0 }),
        lte: material(0xc9cdd0, { metalness: 1, roughness: 0.3, emissive: new THREE.Color(COLORS.sage), emissiveIntensity: 0 }),
        battery: material(0x3f7d63, { roughness: 0.4, clearcoat: 0.8, emissive: new THREE.Color(COLORS.sage), emissiveIntensity: 0 }),
        sd: material(0x1c2a5a, { roughness: 0.4, emissive: new THREE.Color(COLORS.sage), emissiveIntensity: 0 }),
    };

    // ---------- Coque ----------
    const bottom = new THREE.Group();
    bottom.add(mesh(pillow(LENGTH, 1.9, WIDTH, 0.55, 0.32), shell, [0, -0.55, 0]));
    // Port USB-C (recharge)
    bottom.add(mesh(new RoundedBoxGeometry(0.14, 0.34, 0.95, 3, 0.06), black, [-LENGTH / 2 - 0.02, -0.35, 0]));
    // Presse-étoupe + câble de la sonde DS18B20, côté extérieur de la couverture
    const gland = mesh(new THREE.CylinderGeometry(0.34, 0.38, 0.4, 32), darkMetal, [LENGTH / 2 + 0.15, -0.45, 0]);
    gland.rotation.z = Math.PI / 2;
    bottom.add(gland);
    const cablePath = new THREE.CatmullRomCurve3([
        new THREE.Vector3(LENGTH / 2 + 0.3, -0.45, 0),
        new THREE.Vector3(LENGTH / 2 + 1.5, -0.6, 0.3),
        new THREE.Vector3(LENGTH / 2 + 2.6, -1.3, 1.4),
        new THREE.Vector3(LENGTH / 2 + 2.9, -2.1, 2.7),
    ]);
    bottom.add(mesh(new THREE.TubeGeometry(cablePath, 64, 0.09, 12), black));
    const probeMaterial = material(0xc9cdd0, { metalness: 1, roughness: 0.32, emissive: new THREE.Color(COLORS.amber), emissiveIntensity: 0 });
    const probe = mesh(new THREE.CapsuleGeometry(0.15, 0.8, 8, 16), probeMaterial, cablePath.getPoint(1).toArray());
    // La capsule est dans l'axe Y : on l'oriente le long de la fin du câble.
    probe.lookAt(cablePath.getPoint(1).clone().add(cablePath.getTangent(1)));
    probe.rotateX(Math.PI / 2);
    probe.position.add(cablePath.getTangent(1).multiplyScalar(0.45));
    bottom.add(probe);

    // Joint TPU : un cadre, pour laisser voir l'électronique en vue éclatée.
    const gasketShape = roundedRectShape(LENGTH + 0.12, WIDTH + 0.12, 0.62);
    gasketShape.holes.push(roundedRectShape(LENGTH - 0.5, WIDTH - 0.5, 0.4));
    const gasketGeometry = new THREE.ExtrudeGeometry(gasketShape, { depth: 0.16, bevelEnabled: false, curveSegments: 18 });
    gasketGeometry.rotateX(-Math.PI / 2);
    gasketGeometry.center();
    const gasket = new THREE.Group();
    gasket.add(mesh(gasketGeometry, tpu, [0, 0.4, 0]));

    const lid = new THREE.Group();
    lid.add(mesh(pillow(LENGTH, 1.1, WIDTH, 0.55, 0.32), shell, [0, 0.95, 0]));
    const logo = mesh(horseshoeGeometry(), material(COLORS.cream, { roughness: 0.3, metalness: 0.2, clearcoat: 1 }), [-3.3, 1.5, 0.1]);
    lid.add(logo);
    // Fenêtre du SHT41 fermée par une membrane PTFE
    const vent = mesh(pillow(1.5, 0.06, 1.0, 0.25, 0.02), focusMaterials.climate, [4.6, 1.51, 0]);
    lid.add(vent);
    // Témoin lumineux
    const led = mesh(new THREE.SphereGeometry(0.09, 16, 16), new THREE.MeshBasicMaterial({ color: COLORS.sage }), [-5.3, 1.5, 1.6]);
    lid.add(led);

    // ---------- Électronique ----------
    const board = new THREE.Group();
    board.add(mesh(new RoundedBoxGeometry(11.2, 0.16, 3.7, 2, 0.05), material(COLORS.pcb, { roughness: 0.6, clearcoat: 0.5 }), [0, 0.5, 0]));
    // Petits composants pour le réalisme (positions fixes, pas d'aléatoire)
    for (let i = 0; i < 26; i++) {
        const x = -5 + ((i * 3.7) % 10);
        const z = -1.5 + ((i * 1.3) % 3);
        const s = 0.12 + (i % 3) * 0.06;
        board.add(mesh(new THREE.BoxGeometry(s, 0.08, s * 0.6), i % 4 === 0 ? metal : black, [x, 0.62, z]));
    }

    const module = (name, geometry, mat, position, lift) => {
        const m = mesh(geometry, mat, position);
        m.userData.lift = lift;
        m.userData.base = m.position.y;
        m.name = name;
        board.add(m);
        return m;
    };

    module('esp32', new RoundedBoxGeometry(2.0, 0.3, 1.7, 2, 0.04), metal, [-4.3, 0.73, -0.5], 0.3);
    const modem = module('lte', new RoundedBoxGeometry(1.8, 0.3, 1.8, 2, 0.04), focusMaterials.lte, [-1.8, 0.73, 0.2], 0.55);
    const imuBoard = module('imuBoard', new RoundedBoxGeometry(1.2, 0.08, 1.0, 2, 0.02), material(0x2a5d4b), [0.6, 0.66, -0.6], 0.8);
    const imu = module('imu', new RoundedBoxGeometry(0.45, 0.14, 0.45, 2, 0.02), focusMaterials.imu, [0.6, 0.76, -0.6], 0.8);
    module('gnssBoard', new RoundedBoxGeometry(1.9, 0.1, 1.9, 2, 0.03), material(0x23314f), [2.8, 0.66, 0.3], 1.05);
    const patch = module('gnss', new RoundedBoxGeometry(1.5, 0.36, 1.5, 2, 0.05), focusMaterials.gnss, [2.8, 0.89, 0.3], 1.05);
    module('gnssTop', new THREE.BoxGeometry(1.0, 0.02, 1.0), metal, [2.8, 1.08, 0.3], 1.05);
    module('shtBoard', new RoundedBoxGeometry(1.0, 0.08, 0.9, 2, 0.02), material(0x2a5d4b), [4.6, 0.66, 0.4], 0.75);
    module('sht', new RoundedBoxGeometry(0.3, 0.1, 0.3, 2, 0.02), focusMaterials.climate, [4.6, 0.75, 0.4], 0.75);
    const sd = module('sd', new RoundedBoxGeometry(1.1, 0.07, 1.4, 2, 0.02), focusMaterials.sd, [-4.3, 0.61, 1.15], 0.45);

    const battery = new THREE.Group();
    const cell = mesh(new THREE.CylinderGeometry(0.9, 0.9, 6.5, 48), focusMaterials.battery, [0.3, -0.45, 0]);
    cell.rotation.z = Math.PI / 2;
    battery.add(cell);
    for (const side of [-1, 1]) {
        const cap = mesh(new THREE.CylinderGeometry(0.86, 0.86, 0.14, 48), metal, [0.3 + side * 3.3, -0.45, 0]);
        cap.rotation.z = Math.PI / 2;
        battery.add(cap);
    }
    // Jauge lumineuse qui « se remplit » pendant le chapitre énergie
    const charge = new THREE.Mesh(
        new THREE.CylinderGeometry(0.93, 0.93, 6.5, 48, 1, true),
        new THREE.MeshBasicMaterial({ color: COLORS.sage, transparent: true, opacity: 0, depthWrite: false, side: THREE.DoubleSide }),
    );
    charge.geometry.translate(0, 3.25, 0); // part de la borne négative
    charge.rotation.z = -Math.PI / 2;
    charge.position.set(0.3 - 3.25, -0.45, 0);
    battery.add(charge);

    root.add(bottom, gasket, lid, board, battery);

    return {
        root,
        parts: { bottom, gasket, lid, board, battery, led, probe, charge },
        focusMaterials,
        anchors: { gnss: patch, imu, climate: vent, lte: modem, battery: cell, sd, imuBoard },
    };
}

/** Ondes (GPS vers le ciel, LTE vers les antennes relais) : anneaux qui s'élargissent en s'effaçant. */
export function buildWaves(count, color, vertical) {
    const group = new THREE.Group();
    for (let i = 0; i < count; i++) {
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(0.92, 1, 96),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false }),
        );
        if (!vertical) {
            ring.rotation.x = -Math.PI / 2;
        }
        ring.userData.phase = i / count;
        group.add(ring);
    }

    return group;
}

/** Air chaud qui monte de la fenêtre du capteur. */
export function buildHeat(count = 90) {
    const positions = new Float32Array(count * 3);
    const seeds = new Float32Array(count);
    for (let i = 0; i < count; i++) {
        seeds[i] = (i * 0.618034) % 1;
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const points = new THREE.Points(geometry, new THREE.PointsMaterial({
        color: COLORS.amber,
        size: 0.14,
        transparent: true,
        opacity: 0,
        depthWrite: false,
    }));
    points.userData.seeds = seeds;

    return points;
}
