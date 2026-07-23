---
title: "Intégrité du code source"
topic: admin.integrity
version: 1
audience:
    - admin
related:
    - admin.security
---

La **surveillance de l’intégrité du code source** (fonctionnalité 095) détecte
les manipulations des fichiers de l’installation : chaque fichier source est
vérifié en SHA-256 contre une **référence**, `vendor/` via une empreinte par
paquet. L’empreinte racine de la référence fait partie du manifeste de release
signé — une référence manipulée échoue à la vérification de signature.

- **Panneau d’état** : résultat de la dernière vérification, source de la
  référence (release = signable, locale = détection de dérive à partir du
  gel) et empreinte racine.
- **Vérifier maintenant** lance une vérification en file d’attente ; le
  résultat apparaît dans la liste des constats et dans la chaîne de hachage
  `audit_logs`.
- **Figer la référence** crée une nouvelle référence locale — nécessaire
  après des modifications légitimes (correctif, `composer dump-autoload`),
  sinon chaque exécution signale un écart permanent.
- **Alertes** : les administrateurs de plateforme sont notifiés en cas de
  constat nouveau ou modifié ; la levée d’alerte suit la prochaine
  vérification saine.
- **Limites** : la vérification détecte, elle n’empêche pas. Un attaquant
  contrôlant entièrement le serveur peut aussi attaquer le vérificateur — la
  supervision externe (`integrity:verify --json`, code de sortie) et le
  durcissement OS (montages en lecture seule, AIDE) restent recommandés.
  `.env` et `storage/` ne font volontairement pas partie de la référence.

L’exécution quotidienne se désactive via `INTEGRITY_CHECK_ENABLED` et se
replanifie sur la page du planificateur.
