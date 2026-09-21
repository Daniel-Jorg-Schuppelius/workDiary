---
title: "Standards d'apprentissage : SCORM, cmi5 et LTI"
topic: learning.standards
version: 1
audience: []
related:
    - learning.overview
    - training.overview
    - admin.integrations
---

Outre ses propres unités, la plateforme comprend les formats d'échange
courants : vous pouvez importer des cours achetés et lancer vos cours dans
d'autres systèmes.

**SCORM 1.2 et 2004** — Un paquet SCORM est un ZIP accompagné d'un manifeste.
Au dépôt, il est vérifié puis extrait ; les fichiers exécutables et les chemins
sortant du paquet sont refusés. Le contenu s'exécute sur un **hôte dédié**,
afin que du code étranger ne s'exécute pas dans l'origine de l'application. La
progression et l'achèvement proviennent des messages du paquet.

**cmi5 et xAPI** — Les cours cmi5 transmettent leur activité sous forme de
déclarations au registre d'apprentissage intégré. Une session n'accepte les
déclarations que pendant une fenêtre limitée ; ensuite elle est close.

**LTI 1.3** — La plateforme fonctionne dans les deux sens : elle peut intégrer
des outils externes comme unité d'apprentissage **et** être lancée depuis un
autre système de gestion de l'apprentissage. Le lancement utilise des jetons
signés ; les clés sont renouvelées régulièrement et les anciennes restent
valables pour vérifier les sessions en cours.

**Limites :** Dans les trois cas, c'est la règle d'achèvement du cours qui
s'applique, pas celle du paquet. Un paquet peut signaler un achèvement — sa
prise en compte dépend des réglages de publication du cours.
