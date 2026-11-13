---
title: "Modifications hors ligne"
topic: offline.changes
version: 3
keywords:
    - travailler hors ligne
    - sans connexion
    - pas de réseau
    - file d'attente
    - synchronisation
    - modifications en attente
    - entrées refusées
    - mode hors ligne
    - non synchronisé
audience: []
related:
    - attendance.manage
    - forms.fill
---

Les actions saisies sans connexion (pointages, commentaires de commande, formulaires) sont mises en file d'attente sur cet appareil et transférées automatiquement dès que la connexion revient.

**En attente :** entrées pas encore transférées. Elles partent au prochain contact en ligne ; « Supprimer » les retire avant l'envoi.

**Rejetées :** entrées refusées par le serveur (champ obligatoire manquant, pointage déjà ouvert…) — avec motif. « Réappliquer » remet l'entrée en file.

**Important :** la liste n'existe que sur cet appareil et est vidée à la déconnexion. Fichiers et signatures ne peuvent pas être saisis hors ligne.

## Ce qui fonctionne sans connexion

Outre les pointages, les commentaires de commande et les formulaires, vous saisissez aussi hors ligne du **temps de projet** via la barre de saisie d’« Aujourd’hui » (durée ou de/à — pas le minuteur), du **matériel** via « Matériel » sur « Aujourd’hui » et des **corrections de vos pointages** via l’icône crayon de la liste des pointages sur « Aujourd’hui ». Ces dialogues font partie de la page : ils s’ouvrent donc sans réseau si « Aujourd’hui » a été chargé auparavant.

## Trancher les conflits

Un conflit signifie que la saisie n’a **pas** été reprise, parce que quelque chose a changé depuis la saisie ou qu’une information obligatoire manque — par exemple un pointage modifié entre-temps, une journée clôturée ou une saisie sans projet. Vous décidez :

- **Conserver l’autre version** ou **Supprimer** — votre version est abandonnée.
- **Envoyer ma version** — uniquement pour un pointage modifié : votre correction s’applique au nouvel état.
- **Modifier** — ajuster les champs (date, durée ou motif) et les envoyer comme nouvelle entrée.
- **Stock insuffisant** — s’il ne manque que du stock pour un matériel, « Modifier » propose la case « Autoriser le stock négatif » avec le droit « Autoriser les stocks négatifs ». Si l’organisation exige quatre yeux, l’envoi crée une demande dans la boîte des validations. Sans ce droit, le conflit reste ; adaptez la quantité ou supprimez-le.

Chaque décision est consignée dans le journal d’audit. La page affiche aussi les conflits ouverts nés sur un autre appareil ; leur contenu s’y trouve, ici ils peuvent seulement être abandonnés.
