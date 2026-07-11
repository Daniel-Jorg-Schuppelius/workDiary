---
title: "Tâches d'exploitation & fenêtres de maintenance"
topic: admin.operations
version: 1
audience:
    - admin
related:
    - admin.backups
    - admin.diagnostics
---

WorkDiary soutient l'exploitation courante avec deux outils : le
**centre de tâches** pour les contrôles d'exploitation récurrents et la
planification de **fenêtres de maintenance**.

**Contrôles d'exploitation par scan :** Un scan récurrent (par défaut
toutes les heures) vérifie les points pertinents pour l'exploitation –
notamment les certificats arrivant à expiration, les sauvegardes
manquantes ainsi que les dates d'expiration des licences et des
identifiants. Les points détectés apparaissent comme tâches priorisées
dans le centre de tâches, triées par gravité (critique avant
avertissement avant information) et filtrables par statut, type et
gravité.

**Traiter les tâches :** Vous pouvez **terminer** chaque tâche, la
**reporter** (mise en veille pour un nombre de jours réglable), la
**déléguer** (à une personne de votre organisation) ou l'**ignorer**
(avec justification obligatoire). Les tâches terminées peuvent être
rouvertes. Tous les changements de statut sont journalisés de manière
infalsifiable. La visibilité est liée à l'organisation ; les tâches
à l'échelle de l'installation se trouvent dans l'organisation de
l'exploitant et sont marquées en conséquence.

**Planifier des fenêtres de maintenance :** Vous annoncez les fenêtres de
maintenance avec un début, une fin et un délai d'annonce optionnel – à
partir du moment de l'annonce, les utilisateurs voient un message avec
votre texte d'information. Une fenêtre vaut au choix pour **tout le
système** ou uniquement pour l'**organisation** actuelle.

**Déroulement d'une fenêtre :** Après la planification, une fenêtre passe
par les étapes annonce, démarrage, prolongation si nécessaire et fin.
Pendant la fenêtre en cours, vous pouvez activer en option le **mode
lecture seule** (les utilisateurs voient les données mais ne modifient
rien) et **bloquer la réception de données** (les livraisons externes
sont suspendues). Si quelque chose tourne mal, un **rollback** documente
l'interruption avec des notes ; les fenêtres planifiées peuvent aussi
être annulées purement et simplement. Chaque action est auditée.

**Recommandation :** Planifiez les fenêtres de maintenance avec
suffisamment d'avance pour que l'annonce produise son effet, et traitez
régulièrement le centre de tâches – les tâches critiques en premier. Le
report est destiné à des ajournements délibérés, pas à devenir un état
permanent.
