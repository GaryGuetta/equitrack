/*
 * Maquette 3D du prototype Equitrack, d'après la synthèse des composants retenus :
 * - carte tout-en-un (processeur, modem LTE-M, GPS, lecteur microSD, chargeur, USB-C),
 *   batterie lithium clipsée dessous, carte SIM et microSD dans leurs logements ;
 * - antennes GPS (patch actif) et LTE-M (souple) collées sous le couvercle ;
 * - petite carte centrale inertielle posée sur entretoises ;
 * - une sonde température + humidité sur câble, sous la couverture (l'extérieur vient de la météo).
 *
 * Unité : 1 = 10 mm. Boîtier ≈ 126 × 35 × 46 mm (x = longueur, y = épaisseur, z = largeur).
 */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

export const COLORS = {
    forest: 0x1e3a2f,
    sage: 0x8db89f,
    cream: 0xfbf8f1,
    amber: 0xe2a54a,
};

const LENGTH = 12.6;
const WIDTH = 4.6;
const BOARD_Y = 0.5;

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

function glowing(color, glow, options = {}) {
    return material(color, { emissive: new THREE.Color(glow), emissiveIntensity: 0, ...options });
}

function mesh(geometry, mat, position = [0, 0, 0]) {
    const m = new THREE.Mesh(geometry, mat);
    m.position.set(...position);
    m.castShadow = true;
    m.receiveShadow = true;

    return m;
}

/** Câble souple qui sort d'un presse-étoupe et se termine par une sonde. */
function probeOnCable(points, cableMaterial) {
    const curve = new THREE.CatmullRomCurve3(points.map((p) => new THREE.Vector3(...p)));
    const cable = mesh(new THREE.TubeGeometry(curve, 72, 0.085, 12), cableMaterial);
    const end = curve.getPoint(1);
    const direction = curve.getTangent(1);
    // Repère orienté le long de la fin du câble : y local = direction du câble.
    const tip = new THREE.Group();
    tip.position.copy(end);
    tip.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), direction);

    return { cable, tip };
}

/**
 * @returns {{ root: THREE.Group, parts: object, focusMaterials: object, anchors: object }}
 */
export function buildDevice() {
    const root = new THREE.Group();
    const shell = material(COLORS.forest, { roughness: 0.5, clearcoat: 0.35, clearcoatRoughness: 0.4 });
    const tpu = material(COLORS.sage, { roughness: 0.8 });
    const metal = material(0xc9cdd0, { metalness: 1, roughness: 0.32 });
    const gold = material(0xd4a84a, { metalness: 1, roughness: 0.3 });
    const darkMetal = material(0x2b2f31, { metalness: 0.6, roughness: 0.45 });
    const black = material(0x111614, { roughness: 0.5 });
    const plastic = material(0x1b1f1d, { roughness: 0.7 });

    // Un matériau par fonction : il s'illumine quand on en parle.
    const focusMaterials = {
        gnss: glowing(0xcdb98f, COLORS.sage, { roughness: 0.6 }),
        imu: glowing(0x151a18, COLORS.sage, { roughness: 0.35 }),
        sd: glowing(0x1c2a5a, COLORS.sage, { roughness: 0.4 }),
        climate: glowing(0x9aa5a0, COLORS.amber, { roughness: 0.9 }),
        lte: glowing(0xc9cdd0, COLORS.sage, { metalness: 1, roughness: 0.3 }),
        lteAntenna: glowing(0xd98a2b, COLORS.amber, { roughness: 0.4, clearcoat: 1, transparent: true, opacity: 0.92 }),
        sim: glowing(0xf2f0ea, COLORS.sage, { roughness: 0.5 }),
        battery: glowing(0x3f7d63, COLORS.sage, { roughness: 0.4, clearcoat: 0.8 }),
    };

    // ---------- Fond du boîtier : bac creux, port USB-C et sonde ----------
    const bottom = new THREE.Group();
    bottom.add(mesh(pillow(LENGTH, 0.9, WIDTH, 0.55, 0.3), shell, [0, -1.55, 0])); // fond
    // Parois : anneau arrondi extrudé, même contour extérieur que le fond.
    const wallShape = roundedRectShape(LENGTH, WIDTH, 0.85);
    wallShape.holes.push(roundedRectShape(LENGTH - 0.5, WIDTH - 0.5, 0.6));
    const wallGeometry = new THREE.ExtrudeGeometry(wallShape, { depth: 1.66, bevelEnabled: true, bevelThickness: 0.04, bevelSize: 0.03, bevelSegments: 2, curveSegments: 18 });
    wallGeometry.rotateX(-Math.PI / 2);
    bottom.add(mesh(wallGeometry, shell, [0, -1.3, 0]));
    // Fûts de vis dans les coins, pour fermer le couvercle
    for (const [x, z] of [[-1, -1], [-1, 1], [1, -1], [1, 1]]) {
        const at = [x * (LENGTH / 2 - 0.55), z * (WIDTH / 2 - 0.55)];
        bottom.add(mesh(new THREE.CylinderGeometry(0.24, 0.28, 1.3, 24), shell, [at[0], -0.45, at[1]]));
        bottom.add(mesh(new THREE.CylinderGeometry(0.09, 0.09, 0.02, 16), black, [at[0], 0.21, at[1]]));
    }
    bottom.add(mesh(new RoundedBoxGeometry(0.14, 0.34, 0.95, 3, 0.06), black, [-LENGTH / 2 - 0.02, -0.25, 0]));

    const gland = mesh(new THREE.CylinderGeometry(0.3, 0.34, 0.4, 32), darkMetal, [LENGTH / 2 + 0.15, -0.5, 0]);
    gland.rotation.z = Math.PI / 2;
    bottom.add(gland);

    // Sonde température + humidité : corps noir et filtre fritté, déportée contre le cheval.
    const climate = probeOnCable([[LENGTH / 2 + 0.3, -0.5, 0], [LENGTH / 2 + 1.2, -0.8, -0.2], [LENGTH / 2 + 1.9, -1.4, 0.4], [LENGTH / 2 + 2.3, -2.0, 1.2]], black);
    climate.tip.add(mesh(new THREE.CylinderGeometry(0.26, 0.26, 0.9, 32), plastic, [0, 0.45, 0]));
    const filter = mesh(new THREE.CylinderGeometry(0.24, 0.24, 0.5, 32), focusMaterials.climate, [0, 1.15, 0]);
    climate.tip.add(filter);
    for (let i = 0; i < 4; i++) {
        climate.tip.add(mesh(new THREE.TorusGeometry(0.245, 0.018, 8, 32), darkMetal, [0, 0.98 + i * 0.11, 0]).rotateX(Math.PI / 2));
    }
    bottom.add(climate.cable, climate.tip);

    // ---------- Joint TPU (cadre) ----------
    const gasketShape = roundedRectShape(LENGTH + 0.12, WIDTH + 0.12, 0.62);
    gasketShape.holes.push(roundedRectShape(LENGTH - 0.5, WIDTH - 0.5, 0.4));
    const gasketGeometry = new THREE.ExtrudeGeometry(gasketShape, { depth: 0.16, bevelEnabled: false, curveSegments: 18 });
    gasketGeometry.rotateX(-Math.PI / 2);
    gasketGeometry.center();
    const gasket = new THREE.Group();
    gasket.add(mesh(gasketGeometry, tpu, [0, 0.4, 0]));

    // ---------- Couvercle : logo dessus, antennes collées dessous ----------
    const lid = new THREE.Group();
    lid.add(mesh(pillow(LENGTH, 1.1, WIDTH, 0.55, 0.32), shell, [0, 0.95, 0]));
    lid.add(mesh(horseshoeGeometry(), material(COLORS.cream, { roughness: 0.3, metalness: 0.2, clearcoat: 1 }), [-3.3, 1.5, 0.1]));
    const led = mesh(new THREE.SphereGeometry(0.09, 16, 16), new THREE.MeshBasicMaterial({ color: COLORS.sage }), [-5.3, 1.5, 1.5]);
    lid.add(led);
    // Antenne GPS active : céramique avec son plot argenté, face au ciel.
    const patch = mesh(new RoundedBoxGeometry(2.5, 0.4, 2.5, 2, 0.06), focusMaterials.gnss, [3.4, 0.2, 0]);
    lid.add(patch);
    lid.add(mesh(new THREE.BoxGeometry(1.6, 0.02, 1.6), metal, [3.4, -0.01, 0]));
    // Antenne LTE-M souple, collée à plat.
    const lteAntenna = mesh(new RoundedBoxGeometry(4.2, 0.04, 1.1, 2, 0.02), focusMaterials.lteAntenna, [-2.4, 0.38, 0.6]);
    lid.add(lteAntenna);

    // ---------- Carte électronique ----------
    const board = new THREE.Group();
    board.add(mesh(new RoundedBoxGeometry(11.1, 0.16, 3.3, 2, 0.05), material(0x0d0f0e, { roughness: 0.55, clearcoat: 0.6 }), [0, BOARD_Y, 0]));
    // Petits composants pour le réalisme (positions fixes)
    for (let i = 0; i < 24; i++) {
        const x = -4.8 + ((i * 3.7) % 9.6);
        const z = -1.35 + ((i * 1.3) % 2.7);
        const s = 0.12 + (i % 3) * 0.06;
        board.add(mesh(new THREE.BoxGeometry(s, 0.08, s * 0.6), i % 4 === 0 ? metal : black, [x, BOARD_Y + 0.12, z]));
    }

    const module = (geometry, mat, position, lift) => {
        const m = mesh(geometry, mat, position);
        m.userData.lift = lift;
        m.userData.base = m.position.y;
        board.add(m);
        return m;
    };
    const top = BOARD_Y + 0.08;

    module(new RoundedBoxGeometry(0.75, 0.3, 0.9, 2, 0.05), metal, [-5.2, top + 0.15, 0], 0); // prise USB-C
    module(new RoundedBoxGeometry(2.0, 0.28, 1.7, 2, 0.04), metal, [-3.5, top + 0.14, 0.2], 0.3); // processeur
    // Logements SIM et microSD, avec leurs cartes à moitié insérées
    module(new RoundedBoxGeometry(1.3, 0.12, 1.25, 2, 0.02), metal, [-1.3, top + 0.06, -0.85], 0.2);
    const sim = module(new RoundedBoxGeometry(1.23, 0.05, 0.88, 2, 0.02), focusMaterials.sim, [-1.75, top + 0.15, -0.85], 0.55);
    module(new THREE.BoxGeometry(0.5, 0.01, 0.4), gold, [-1.95, top + 0.18, -0.85], 0.55);
    module(new RoundedBoxGeometry(1.3, 0.12, 1.25, 2, 0.02), metal, [-1.3, top + 0.06, 0.85], 0.2);
    const sd = module(new RoundedBoxGeometry(1.5, 0.07, 1.1, 2, 0.02), focusMaterials.sd, [-1.85, top + 0.16, 0.85], 0.55);
    // Modem LTE-M avec GPS intégré, et ses deux connecteurs d'antenne
    const modem = module(new RoundedBoxGeometry(1.8, 0.28, 1.8, 2, 0.04), focusMaterials.lte, [0.9, top + 0.14, 0], 0.4);
    for (const z of [-1.25, 1.25]) {
        module(new THREE.CylinderGeometry(0.1, 0.1, 0.12, 16), gold, [2.1, top + 0.06, z], 0.4);
    }
    // Carte centrale inertielle sur entretoises, avec ses deux connecteurs à clipser
    module(new RoundedBoxGeometry(1.78, 0.08, 2.54, 2, 0.03), material(0x16213a, { roughness: 0.5, clearcoat: 0.4 }), [3.9, top + 0.3, 0], 0.9);
    for (const [x, z] of [[3.2, -1.05], [4.6, -1.05], [3.2, 1.05], [4.6, 1.05]]) {
        module(new THREE.CylinderGeometry(0.07, 0.07, 0.26, 12), gold, [x, top + 0.13, z], 0.45);
    }
    const imu = module(new RoundedBoxGeometry(0.45, 0.12, 0.45, 2, 0.02), focusMaterials.imu, [3.9, top + 0.4, 0], 0.9);
    for (const z of [-1.0, 1.0]) {
        module(new RoundedBoxGeometry(0.5, 0.18, 0.35, 2, 0.03), material(0xf2f0ea, { roughness: 0.6 }), [3.9, top + 0.43, z], 0.9);
    }

    // Support de batterie sous la carte : deux clips et leurs contacts
    for (const side of [-1, 1]) {
        board.add(mesh(new RoundedBoxGeometry(0.4, 1.0, 2.0, 2, 0.06), plastic, [0.6 + side * 3.6, BOARD_Y - 0.55, 0]));
        board.add(mesh(new THREE.BoxGeometry(0.04, 0.6, 0.6), metal, [0.6 + side * 3.37, BOARD_Y - 0.6, 0]));
    }

    // ---------- Batterie, clipsée sous la carte ----------
    const battery = new THREE.Group();
    const cell = mesh(new THREE.CylinderGeometry(0.92, 0.92, 6.5, 48), focusMaterials.battery, [0.6, -0.45, 0]);
    cell.rotation.z = Math.PI / 2;
    battery.add(cell);
    for (const side of [-1, 1]) {
        const cap = mesh(new THREE.CylinderGeometry(0.88, 0.88, 0.14, 48), metal, [0.6 + side * 3.3, -0.45, 0]);
        cap.rotation.z = Math.PI / 2;
        battery.add(cap);
    }
    // Jauge lumineuse qui « se remplit » pendant le chapitre énergie
    const charge = new THREE.Mesh(
        new THREE.CylinderGeometry(0.95, 0.95, 6.5, 48, 1, true),
        new THREE.MeshBasicMaterial({ color: COLORS.sage, transparent: true, opacity: 0, depthWrite: false, side: THREE.DoubleSide }),
    );
    charge.geometry.translate(0, 3.25, 0); // part de la borne négative
    charge.rotation.z = -Math.PI / 2;
    charge.position.set(0.6 - 3.25, -0.45, 0);
    battery.add(charge);

    root.add(bottom, gasket, lid, board, battery);

    return {
        root,
        parts: { bottom, gasket, lid, board, battery, led, charge },
        focusMaterials,
        anchors: { gnss: patch, imu, sd, climate: filter, lte: modem, lteAntenna, sim, battery: cell },
    };
}

/** Ondes (GPS, LTE-M) : anneaux face à la caméra qui s'élargissent en s'effaçant. */
export function buildWaves(count, color) {
    const group = new THREE.Group();
    for (let i = 0; i < count; i++) {
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(0.92, 1, 96),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false }),
        );
        ring.userData.phase = i / count;
        group.add(ring);
    }

    return group;
}

/** Air tiède qui monte autour de la sonde climat. */
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
