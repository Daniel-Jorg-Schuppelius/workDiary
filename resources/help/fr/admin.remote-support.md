---
title: "Télémaintenance"
topic: admin.remote-support
version: 4
keywords:
    - AnyDesk
    - TeamViewer
    - RustDesk
    - Splashtop
    - pcvisit
    - jeton d'API
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

La télémaintenance reprend les rapports de session d'AnyDesk,
TeamViewer, RustDesk, Splashtop et pcvisit et les transforme en saisies
de temps. Les sessions sont rattachées à un appareil (actif, par ex.
poste de travail, serveur, ordinateur portable) via l'ID d'appareil du
fournisseur. Avec **Importer les sessions**, vous pouvez en outre
importer des exports de sessions (AnyDesk, RustDesk Server Pro) via
l'import central ; la ligne d'en-tête révèle le fournisseur, une colonne
**Fournisseur** a la priorité.

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

Configurer les fournisseurs (paramètres du plugin de l'organisation, un
interrupteur par fournisseur) :

- **AnyDesk** : ID de licence et mot de passe API de la licence ; l'ID
  d'appareil est l'ID AnyDesk de l'ordinateur.
- **TeamViewer** : jeton de script avec autorisation pour les rapports
  de connexion ; l'ID d'appareil est l'ID TeamViewer.
- **RustDesk** : le serveur RustDesk gratuit n'enregistre pas les
  connexions. Les sessions ne proviennent que d'un serveur API que les
  clients ont saisi sous « Serveur API ». Pour **Server Pro**, saisissez
  l'adresse de la console web et un jeton d'API (Paramètres → Tokens →
  Créer, autorisation « Audit Log ») ; pour le **serveur API open
  source** (rustdesk-api), l'adresse avec le port ainsi que le nom
  d'utilisateur et le mot de passe d'un administrateur, le fuseau horaire
  du serveur seulement s'il ne tourne pas dans le fuseau de
  l'organisation. Si le serveur est sur votre propre réseau, activez
  **Autoriser les adresses de réseau privé**. L'ID d'appareil est l'ID
  RustDesk de l'ordinateur (sans espaces). Les connexions encore en cours
  sont reprises après leur fin.
- **Splashtop** : jeton d'API de la console web (Paramètres du compte →
  Web API Tokens ; propriétaires d'équipe et super-administrateurs
  uniquement, l'Open API fait partie de Splashtop Enterprise). Choisissez
  la région de votre compte ; l'ID d'équipe est déterminé à partir du
  jeton. Les types de session se choisissent séparés par des virgules
  (par défaut : sans surveillance et SOS). L'ID d'appareil est l'UUID
  d'ordinateur Splashtop ; la boîte de réception affiche le nom de
  l'ordinateur à côté. Les autres formules utilisent l'export CSV de la
  page des journaux via l'import central.
- **pcvisit** : jeton d'API utilisateur de la pcvisit Web App (paramètres
  du compte) et un jeton d'intégrateur que pcvisit attribue à l'éditeur du
  logiciel (paramètre de l'exploitant ; à saisir par organisation
  seulement avec votre propre accord). Les sessions terminées sont
  reprises avec la durée enregistrée dans pcvisit ; l'ID d'appareil est
  l'ID d'ordinateur pcvisit, le client final figure dans la note.

Si un fournisseur tombe en panne, les autres continuent ; le message
apparaît dans l'état du plugin et dans le résultat de l'import.

Sécurité et risques :

- Les identifiants API des fournisseurs sont stockés dans les
  paramètres du plugin de l'organisation. Le système lit les rapports
  de session – il n'accorde aucun accès à distance direct.
- Les appareils multi-clients exigent une affectation soigneuse de
  chaque session afin d'éviter des erreurs d'imputation entre clients.
- **Les connexions et sessions rejetées ne sont pas comptabilisées** ;
  la page ne permet pas de les récupérer.

Autorisation : la page est réservée aux administrateurs.
