---
title: "Clôture de journée"
topic: time-entries.day-close
version: 3
keywords:
    - clôturer la journée
    - fin de journée
    - ajouter une pause
    - bilan journalier
    - solde du jour
    - trous dans la saisie
    - pause obligatoire
    - pointage ouvert
    - demander une correction
    - compléter les heures
audience: []
related:
    - time-entries.start
    - attendance.manage
    - time-accounts.flex
---

La **Clôture journalière** regroupe sur la page **Aujourd’hui** (menu
**Activité quotidienne** → **Saisie** → **Aujourd’hui**) tout ce qui
concerne une journée de travail : **Pointages**, pauses, **Saisies de
temps**, les contrôles sous **Lacunes & avertissements** et le **Bilan**
(notamment **Présence (brut)**, **Pause obligatoire**, **Solde du jour** et
**Solde du mois en cours**).

Voici comment procéder :

1. **Vérifier** : ouvrez la page en fin de journée ; **Jour précédent** et
   **Jour suivant** mènent aux autres jours. Les lacunes et incohérences
   apparaissent dans la section **Lacunes & avertissements**.
2. **Compléter** : saisissez les temps manquants dans la barre de saisie
   en haut (choisir un projet, indiquer **Durée** ou **De / à**,
   **Saisir**). Affectez à un projet les blocs de présence pas encore
   imputés sous **Saisie rapide** avec **Enregistrer** ; là, `Ctrl` +
   `Entrée` enregistre le bloc et passe au suivant. Les pointages
   eux-mêmes ne peuvent être modifiés que via une demande de correction.
3. **Clôturer** : s’il ne reste plus d’avertissements ⛔, clôturez la
   journée avec **Clôturer la journée**. **Enregistrer** conserve l’état
   sans clôturer la journée.

Les avertissements ⛔ bloquent la clôture : pointeuse encore ouverte,
présence non imputée (plus de 5 minutes) ou pause obligatoire non
respectée. Les remarques ⚠ ne bloquent pas, par exemple un solde
journalier au-delà de ±2 heures, plus de 10 heures de travail net, une
interruption de présence sans pause ou des imputations facturables sans
commentaire.

Après la clôture, la journée est verrouillée pour vous. Si vous avez
besoin d’une modification, demandez une validation via **Demander une
correction** (justification d’au moins 20 caractères). Les personnes
disposant du droit **Approuver les corrections de clôture journalière**
(par défaut les chefs d’équipe) ou les administrateurs décident avec
**Approuver** ou **Rejeter**. Après validation, seules les imputations
sont modifiables, les pointages restent verrouillés. Toute personne
disposant du droit **Rouvrir une clôture journalière** peut aussi
déverrouiller une journée clôturée sans demande via **Rouvrir la
journée** ; la justification est consignée dans le journal d’audit.

Les journées d’un mois déjà validé sont entièrement verrouillées ; toute
modification passe alors par la validation mensuelle.
