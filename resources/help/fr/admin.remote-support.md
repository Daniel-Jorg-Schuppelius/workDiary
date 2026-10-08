---
title: "Télémaintenance"
topic: admin.remote-support
version: 3
keywords:
    - AnyDesk
    - TeamViewer
    - accès à distance
    - session à distance
    - assistance à distance
    - prise en main à distance
    - identifiant du poste
    - rapport de session
    - bureau à distance
    - support distant
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

La télémaintenance reprend les rapports de session d'AnyDesk et de
TeamViewer et les transforme en saisies de temps. Les sessions sont
rattachées à un appareil (actif, par ex. poste de travail, serveur,
ordinateur portable) via l'ID d'appareil (ID AnyDesk/TeamViewer). Avec
**Importer les sessions**, vous pouvez en outre importer les sessions
AnyDesk via l'import central.

La page **Télémaintenance – connexions non affectées** comporte deux
onglets ; le champ de recherche trouve l'ID d'appareil, l'alias,
l'appareil ou la note.

Onglet **Appareils non attribués** :

- On y trouve les ID qui apparaissent dans les rapports, mais ne sont
  encore rattachés à aucun appareil de l'organisation – avec le nombre
  de sessions, la durée et la période.
- Si une **Suggestion** existe (client ou appareil correspondant), vous
  la reprenez avec **Appliquer**.
- **Appareil existant** : choisir un appareil existant sous
  **Sélectionner un appareil**, puis **Affecter** ; les sessions
  enregistrées sont immédiatement comptabilisées en saisies de temps.
- **Nouvel appareil** : indiquer un nom, la **Catégorie**, le **Client**
  et, en option, le **Client final**, puis **Créer et affecter**.
- **Appareil multi-client** : cette case, présente dans les deux
  onglets, signale un appareil utilisé pour plusieurs clients. Ses
  sessions ne sont alors pas comptabilisées automatiquement, mais par
  client dans le second onglet.
- **Rejeter** : refuse toutes les connexions d'un ID ; elles ne sont pas
  comptabilisées.

Onglet **Attribuer les sessions** (appareils multi-clients) :

- Sélectionner les sessions, choisir le **Client**, en option le
  **Client final** et le **Projet**, puis **Comptabiliser les éléments
  sélectionnés** – le temps est ainsi imputé au bon client.
- **Comptabiliser la sélection en interne** impute les sessions sans
  client au projet de maintenance interne.
- **Rejeter les éléments sélectionnés** rejette des sessions
  individuelles.

Sécurité et risques :

- Les identifiants API des fournisseurs sont stockés dans les
  paramètres du plugin de l'organisation. Le système lit les rapports
  de session – il n'accorde aucun accès à distance direct.
- Les appareils multi-clients exigent une affectation soigneuse de
  chaque session afin d'éviter des erreurs d'imputation entre clients.
- **Les connexions et sessions rejetées ne sont pas comptabilisées** ;
  la page ne permet pas de les récupérer.

Autorisation : la page est réservée aux administrateurs.
