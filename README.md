# Equitrack — application web

*Parce que son bien-être ne prend jamais de pause.*

Plateforme du projet Equitrack (PFE) : elle reçoit les mesures du boîtier fixé dans la couverture,
les stocke, déclenche les alertes et les affiche au propriétaire. Couvre les fonctions F2 à F9 du cahier des charges.

| Fonction | Où |
|---|---|
| F2 Localiser, trajet, distance | Fiche cheval : carte, distance du jour |
| F3 Allures, roulades, couché/debout | Fiche cheval : répartition des allures, courbe d'activité |
| F4 Sortie d'enclos | Enclos tracé sur la carte, `AlertEvaluator` |
| F5 Transmettre | `POST /api/v1/readings` (lots de mesures) |
| F6 Stocker et afficher | Doctrine + tableau de bord, fiche cheval, export CSV |
| F7 Conseil de couverture | `BlanketAdvisor` + météo Open-Meteo |
| F8 Activité anormale | Comparaison à l'habitude du cheval sur 14 jours (`ActivityBaseline`) |
| F9 Température / humidité | Seuils dans `AlertEvaluator` |

## Stack

Symfony 8.1, PHP 8.4, Doctrine ORM (SQLite en dev), Twig, AssetMapper + Stimulus + Turbo
(pas de Node ni de build), Leaflet (cartes OpenStreetMap), Chart.js. Tests PHPUnit.

## Installer et lancer

Prérequis : PHP 8.4 (extensions intl, pdo_sqlite, mbstring, curl, openssl), Composer, [Symfony CLI](https://symfony.com/download).

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n     # données de démonstration
symfony serve -d --no-tls                     # http://127.0.0.1:8000
```

Compte de démonstration : **demo@equitrack.fr** / **equitrack-demo** (3 chevaux, 14 jours d'historique simulé).

Passer à MySQL ou PostgreSQL : changer `DATABASE_URL` dans un fichier `.env.local`, puis relancer les migrations.

## API du boîtier

Chaque boîtier s'authentifie avec un jeton, généré depuis la fiche du cheval (« Associer un boîtier »).
Le jeton n'est affiché qu'une fois ; seule son empreinte SHA-256 est stockée.

```http
POST /api/v1/readings
Authorization: Bearer <jeton>
Content-Type: application/json

{
  "readings": [
    {
      "recordedAt": "2026-11-10T14:30:00+01:00",
      "latitude": 44.0560, "longitude": 1.4285,
      "temperature": 27.8,        // °C sous la couverture (SHT41)
      "humidity": 61,             // % HR sous la couverture
      "outsideTemperature": 9.5,  // °C extérieur (DS18B20)
      "activity": 24,             // indice 0–100 calculé par le boîtier
      "gait": "pas",              // couche | repos | pas | trot | galop
      "rolls": 0,                 // roulades sur l'intervalle
      "heartRate": null,          // pouls estimé (F10, optionnel)
      "battery": 77               // %
    }
  ]
}
```

Réponses : `201` avec `{"accepted": n, "alerts": [...]}`, `401` jeton invalide, `409` boîtier non associé,
`422` données invalides (détail des erreurs dans la réponse). 1 à 100 mesures par lot.

Avec les données de démo, les jetons `demo-token-tornade` et `demo-token-caramel` sont actifs.

> Le boîtier LTE-M peut aussi envoyer en UDP/CoAP pour économiser le forfait (voir la nomenclature) :
> il faudra alors une petite passerelle qui convertit vers cette API.

## Simuler un boîtier (démo, soutenance)

```bash
php bin/console app:simulate EQT-TORNADE                         # un lot normal de 10 min
php bin/console app:simulate EQT-TORNADE --scenario=sortie       # sortie d'enclos
php bin/console app:simulate EQT-TORNADE --scenario=roulades     # roulades répétées (colique ?)
php bin/console app:simulate EQT-CARAMEL --scenario=transpiration
php bin/console app:simulate EQT-CARAMEL --scenario=agitation
php bin/console app:simulate EQT-TORNADE --live                  # un lot toutes les 10 s
```

## Alertes par e-mail

Les alertes « Attention » et « Urgent » sont envoyées par e-mail au propriétaire via Messenger (file asynchrone).
En dev, `MAILER_DSN=null://null` : rien ne part, les e-mails sont visibles dans le profiler.
Pour un vrai envoi, renseigner `MAILER_DSN` dans `.env.local` et lancer le worker :

```bash
php bin/console messenger:consume async
```

## Seuils à calibrer

Tous les seuils sont des hypothèses de départ, regroupés en constantes commentées :

- `src/Monitoring/AlertEvaluator.php` : chaud (35 °C), froid (10 °C), humidité (85 %), roulades (3 en 30 min),
  couché prolongé (90 min), agitation (3 écarts types), apprentissage (5 jours minimum).
- `src/Advice/BlanketAdvisor.php` : grille température × tonte, seuils du microclimat sous la couverture.

À recalibrer avec les mesures des essais ; la grille de couverture doit être validée par un professionnel (critère F7).

## Tests

```bash
php bin/phpunit
```

## Organisation du code

```
src/
  Advice/        conseil de couverture (F7)
  Api/           DTO validés de l'API boîtier
  Command/       app:simulate
  Controller/    pages web et API
  Entity/        User, Horse, Device, Reading, Alert
  Geo/           distances, appartenance à l'enclos
  Monitoring/    ingestion, alertes, synthèses, séries pour graphiques
  Simulation/    générateur de mesures réalistes
  Weather/       prévisions Open-Meteo
assets/controllers/  carte, éditeur d'enclos, graphiques (Stimulus)
```
