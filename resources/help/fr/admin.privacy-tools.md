---
title: "Outils de protection des données"
topic: admin.privacy-tools
version: 2
keywords:
    - RGPD
    - protection de la vie privée
    - sessions actives
    - révoquer une session
    - déconnexion forcée
    - révoquer un jeton API
    - portabilité des données
    - accès aux données personnelles
    - durées de conservation
    - export de données
audience:
    - admin
    - geschaeftsfuehrung
    - support
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Cette section regroupe sur une seule page les outils liés à la
protection des données pour votre organisation. Elle est protégée par
une autorisation (droit Protection des données) et porte sur
l'ensemble de l'organisation, et pas seulement sur votre propre compte.

Vue d'ensemble du statut :

- Membres actifs, sessions actives et jetons API en un coup d'œil.
- Catégories de données avec sensibilité, délais de conservation et
  procédure de suppression.

Sessions actives :

- Tableau des sessions ouvertes des membres de l'organisation avec
  adresse IP, navigateur/appareil et dernière activité.
- Chaque session peut être **révoquée** individuellement (déconnexion
  forcée).

Jetons API :

- Vue d'ensemble des jetons d'accès personnels (nom, utilisateur, créé
  le, dernière utilisation, expiration).
- Les jetons peuvent être **révoqués**. Les jetons révoqués perdent
  immédiatement leur validité.

Journaux :

- Derniers événements d'export du tenant (qui, quand, format/étendue).
- Derniers accès du support (télémaintenance/support) pour la
  traçabilité.

Export de données :

- Génère un rapport lisible par machine (JSON/CSV) contenant les
  métadonnées de l'organisation, les sessions, les jetons ainsi que les
  journaux d'export et de support – comme base pour les demandes
  d'accès et de portabilité (art. 20 RGPD) au niveau de l'organisation.

Risques : la révocation d'une session ou d'un jeton prend effet
immédiatement et peut interrompre des intégrations ou des connexions en
cours. L'export contient des données administratives à caractère
personnel – traitez-le de manière confidentielle et ne le transmettez
qu'aux personnes autorisées.
