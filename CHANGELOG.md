# Changelog — Plugin Fluidra Pool pour Jeedom

## v1.0.0 (2026-06-18)

### Première version
- Authentification via AWS Cognito (email/password + refresh token automatique)
- Découverte automatique des piscines et appareils connectés
- **Piscine** : statut, météo locale, localisation, qualité d'eau (pH, ORP, température, salinité)
- **Pompe** : état marche/arrêt, vitesse (basse 45% / moyenne 65% / haute 100%), mode automatique
- **Pompe à chaleur** : état, température consigne et actuelle, mode (chauffage/refroid/auto), preset (silence/smart/boost), état de fonctionnement (veille/chauffage/refroid/pas de débit)
- **Éclairage** : état marche/arrêt, luminosité
- **Électrolyseur** : état marche/arrêt
- Rafraîchissement automatique via cron Jeedom (intervalle configurable)
- Credentials stockés de façon sécurisée dans la configuration Jeedom (chiffrée), jamais dans git
