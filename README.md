# Plugin Jeedom — Fluidra Pool

Plugin pour contrôler votre piscine connectée **Fluidra Connect** depuis [Jeedom](https://www.jeedom.com).

Basé sur la reverse-engineering de l'API Fluidra réalisée par [foXaCe/Fluidra-pool](https://github.com/foXaCe/Fluidra-pool) (plugin Home Assistant).

---

## Compatibilité

| Appareil | Support |
|---|---|
| Pompe variable (Fluidra Connect) | ✅ Marche/Arrêt, Vitesse, Mode auto |
| Pompe à chaleur Z550iQ+ | ✅ Marche/Arrêt, Consigne, Mode, Preset, État |
| Pompe à chaleur LG | ✅ Marche/Arrêt, Consigne, Preset (7 modes) |
| Pompe à chaleur Z260iQ | ✅ Marche/Arrêt, Consigne (7–40 °C) |
| LumiPlus Connect (éclairage) | ✅ Marche/Arrêt, Luminosité |
| Électrolyseur (chlorinateur) | ✅ Marche/Arrêt |
| Sonde Blue Connect | ✅ pH, ORP, Température eau, Salinité |
| Données piscine (météo, statut) | ✅ Température air, Statut, Localisation |

> **Prérequis** : une piscine connectée au cloud **Fluidra Connect** (application iAquaLink / Fluidra Pool). L'intégration utilise le même cloud que l'application officielle.

---

## Prérequis système

- Jeedom **4.4** ou supérieur
- Python **3.8+** sur le serveur Jeedom
- Bibliothèque Python `requests` (installée automatiquement)
- Compte Fluidra Connect actif (email + mot de passe)

---

## Installation

### Depuis GitHub (recommandé)

1. Dans Jeedom, allez dans **Plugins → Gestion des plugins**
2. Cliquez sur l'icône **+** (installer depuis GitHub)
3. Renseignez :
   - **Utilisateur GitHub** : `benoitleq`
   - **Dépôt** : `fluidrapooljeedom`
   - **Branche** : `master`
4. Cliquez **Installer**

### Manuellement

1. Téléchargez le [ZIP de la dernière release](https://github.com/benoitleq/fluidrapooljeedom/archive/refs/heads/master.zip)
2. Décompressez dans `/var/www/html/plugins/fluidrapool/`
3. Dans Jeedom, activez le plugin

---

## Configuration

### 1. Paramètres globaux

Allez dans **Plugins → Protocole domotique → Fluidra Pool → Configuration**.

| Paramètre | Description |
|---|---|
| **Email** | Email de votre compte Fluidra Connect |
| **Mot de passe** | Mot de passe du compte (stocké chiffré, jamais dans git) |
| **Intervalle de rafraîchissement** | Délai entre chaque mise à jour en minutes (défaut : 5 min) |

Cliquez **Tester la connexion** pour vérifier que les identifiants sont corrects avant de sauvegarder.

> **Sécurité** : le mot de passe n'est jamais stocké dans un fichier texte accessible. Il est chiffré dans la base Jeedom et transmis au script Python via une variable d'environnement (non visible dans `ps aux`).

### 2. Découverte des appareils

Une fois les identifiants enregistrés :

1. Allez dans **Plugins → Protocole domotique → Fluidra Pool**
2. Cliquez **Découvrir les appareils**
3. Le plugin interroge le cloud Fluidra et crée automatiquement un équipement par appareil détecté

---

## Équipements créés automatiquement

Pour chaque piscine et chaque appareil connecté, un équipement Jeedom est créé.

### Piscine (données globales)

| Commande | Type | Description |
|---|---|---|
| Statut piscine | Info texte | État général : `using`, `maintenance`, `offline`, `winterized` |
| Température extérieure | Info numérique (°C) | Météo locale (via API météo Fluidra) |
| Localisation | Info texte | Ville et pays de la piscine |
| pH eau | Info numérique | pH de l'eau (si sonde connectée) |
| Potentiel rédox (ORP) | Info numérique (mV) | Mesure ORP de l'eau |
| Température eau | Info numérique (°C) | Température de l'eau |
| Salinité | Info numérique (g/L) | Concentration en sel |
| Rafraîchir | Action | Force la mise à jour immédiate |

### Pompe

| Commande | Type | Description |
|---|---|---|
| État pompe | Info binaire | 1 = en marche, 0 = arrêtée |
| Vitesse | Info texte | `45%` / `65%` / `100%` |
| Vitesse (%) | Info numérique | Pourcentage de vitesse actuel |
| Mode auto | Info binaire | 1 = mode automatique actif |
| Marche | Action | Démarre la pompe |
| Arrêt | Action | Arrête la pompe |
| Vitesse basse (45%) | Action | Passe en vitesse basse |
| Vitesse moyenne (65%) | Action | Passe en vitesse moyenne |
| Vitesse haute (100%) | Action | Passe en vitesse haute |
| Mode auto ON | Action | Active le mode automatique (programme) |
| Mode auto OFF | Action | Désactive le mode automatique |
| Rafraîchir | Action | Force la mise à jour |

### Pompe à chaleur (PAC)

| Commande | Type | Description |
|---|---|---|
| État PAC | Info binaire | 1 = en marche |
| Mode de fonctionnement | Info texte | `idle` / `heating` / `cooling` / `no_flow` |
| Température consigne | Info numérique (°C) | Température cible réglée |
| Température actuelle | Info numérique (°C) | Température mesurée (si disponible) |
| Mode (chauffe/refroid) | Info texte | `heating` / `cooling` / `auto` |
| Preset | Info texte | `silence` / `smart` / `boost` |
| Marche PAC | Action | Démarre la PAC |
| Arrêt PAC | Action | Arrête la PAC |
| Régler température | Action (slider 7–40 °C) | Règle la consigne de température |
| Mode chauffage | Action | Bascule en mode chauffage |
| Mode refroidissement | Action | Bascule en mode refroidissement |
| Mode automatique | Action | Bascule en mode auto (chauffe + refroid) |
| Preset silence | Action | Mode silencieux (puissance réduite) |
| Preset smart | Action | Mode intelligent (optimal) |
| Preset boost | Action | Mode boost (puissance maximale) |
| Rafraîchir | Action | Force la mise à jour |

### Éclairage (LumiPlus)

| Commande | Type | Description |
|---|---|---|
| État lumière | Info binaire | 1 = allumée |
| Luminosité | Info numérique (%) | Niveau de luminosité |
| Allumer | Action | Allume l'éclairage |
| Éteindre | Action | Éteint l'éclairage |
| Régler luminosité | Action (slider 0–100) | Règle l'intensité lumineuse |
| Rafraîchir | Action | Force la mise à jour |

### Électrolyseur

| Commande | Type | Description |
|---|---|---|
| État électrolyseur | Info binaire | 1 = en marche |
| Marche | Action | Démarre l'électrolyseur |
| Arrêt | Action | Arrête l'électrolyseur |
| Rafraîchir | Action | Force la mise à jour |

---

## Utilisation dans les scénarios Jeedom

### Exemple : démarrer la pompe à l'heure de pointe solaire

```
Déclencheur : Heure — 12h00
Condition   : Température extérieure > 25°C
Action      : Pompe [Vitesse haute]
Attendre    : 4 heures
Action      : Pompe [Arrêt]
```

### Exemple : chauffer la piscine si température trop basse

```
Déclencheur : Changement — Température eau
Condition   : Température eau < 26°C
Action      : PAC [Marche]
              PAC [Régler température] → 28
              PAC [Preset smart]
```

### Exemple : alerte qualité de l'eau

```
Déclencheur : Changement — pH eau
Condition   : pH eau < 7.0 OU pH eau > 7.6
Action      : Notification "⚠️ pH piscine hors plage : #{pH eau}"
```

---

## Architecture technique

```
Jeedom (PHP)                       Cloud Fluidra
     │                                    │
     │  fluidrapool.class.php             │
     │  └─ callApi()                      │
     │       │                            │
     │       ▼                            │
     │  resources/fluidra_api.py          │
     │       │                            │
     │       ├─ AWS Cognito ─────────────►│ Authentification
     │       │   (refresh token caché)    │
     │       │                            │
     │       ├─ GET /generic/users/me/pools►│ Liste des piscines
     │       ├─ GET /generic/devices ────►│ Appareils par piscine
     │       ├─ GET /generic/devices/     │
     │       │       {id}/components/{n} ►│ État d'un composant
     │       ├─ PUT /generic/devices/     │
     │       │       {id}/components/{n} ►│ Commande d'un composant
     │       └─ GET /generic/pools/       │
     │               {id}/assistant/...  ►│ Qualité d'eau
     │                                    │
     ▼
Jeedom DB (commandes mises à jour)
```

### Sécurité des credentials

| Donnée | Stockage | Visible dans git |
|---|---|---|
| Email | Config Jeedom (chiffrée) | ❌ Non |
| Mot de passe | Config Jeedom (chiffrée) | ❌ Non |
| Access token | Mémoire Python (TTL 1h) | ❌ Non |
| Refresh token | `/tmp/fluidrapool/token_cache.json` (chmod 600) | ❌ Non |

---

## Dépannage

### Le plugin ne trouve pas mes appareils

- Vérifiez vos identifiants avec le bouton **Tester la connexion**
- Assurez-vous que vos appareils sont visibles dans l'application **Fluidra Pool** ou **iAquaLink**
- Consultez les logs : **Analyse → Logs → fluidrapool**

### Erreur `Module 'requests' manquant`

Connectez-vous en SSH sur votre Jeedom et exécutez :
```bash
pip3 install requests
```
Ou relancez l'installation du plugin depuis Jeedom.

### La PAC ne répond pas aux commandes

Certains modèles utilisent le composant `13` pour marche/arrêt, d'autres le composant `21`. Le plugin essaie les deux automatiquement. Si votre modèle n'est pas reconnu, ouvrez une [issue GitHub](https://github.com/benoitleq/fluidrapooljeedom/issues) en indiquant le modèle exact.

### Rafraîchissement trop lent

Réduisez l'intervalle dans la configuration du plugin (minimum 1 minute). Notez que l'API Fluidra peut mettre quelques secondes à refléter les changements après une commande.

### Les données de qualité d'eau ne s'affichent pas

Les données pH/ORP/salinité nécessitent une **sonde Blue Connect** ou un électrolyseur compatible avec la télémétrie Fluidra. Ces commandes restent vides si votre installation ne dispose pas de ce matériel.

---

## Contribuer

Les contributions sont les bienvenues !

1. Forkez le dépôt
2. Créez une branche : `git checkout -b feature/mon-ajout`
3. Committez vos modifications
4. Poussez et ouvrez une **Pull Request**

Pour signaler un bug ou demander une fonctionnalité : [Issues GitHub](https://github.com/benoitleq/fluidrapooljeedom/issues)

---

## Crédits

- API Fluidra reverse-engineered par [foXaCe](https://github.com/foXaCe/Fluidra-pool) — projet Home Assistant
- Protocole AWS Cognito documenté via mitmproxy captures dans le projet source

---

## Licence

[AGPL-3.0](https://www.gnu.org/licenses/agpl-3.0.html)

> Ce plugin est un projet communautaire, sans affiliation avec Fluidra S.A. Utilisez-le à vos propres risques. L'API Fluidra est privée et non documentée officiellement ; elle peut changer sans préavis.
