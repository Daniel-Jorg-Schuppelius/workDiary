---
title: "Automatisations"
topic: admin.automations
version: 3
keywords:
    - workflow
    - règles
    - règle si alors
    - déclencheur
    - moteur de règles
    - action automatique
    - automatiser un processus
    - créer une règle
    - flux de travail
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Les automatisations sont des flux basés sur des règles suivant le
schéma **événement → condition → action**. Lorsqu'un événement
déclencheur défini survient et que les conditions configurées
correspondent, l'action associée est exécutée. Les règles s'appliquent
par organisation et sont strictement limitées à votre propre
périmètre. Chaque évaluation est consignée dans le journal d'audit.

La vue d'ensemble liste toutes les règles avec **Prio**, **Trigger**,
**Action(s)** et **Active**, triées par défaut par priorité. Les
actions suivantes sont disponibles :

- **Créer une nouvelle règle (JSON)** : ouvre la boîte de dialogue
  **Nouvelle règle d’automatisation** avec un nom, le **Déclencheur**
  (par ex. « Note de frais soumise »), l'**Action** (par ex.
  « Approuver les frais »), la **Priorité** et les **Conditions
  (JSON)**. L'action doit correspondre au déclencheur ; une condition
  vide s'applique toujours. **Créer une règle** enregistre la règle.
- **Désactiver**/**Activer** : les règles désactivées sont conservées,
  mais ne déclenchent plus aucune action.
- Vue détaillée (clic sur le nom) : affiche le déclencheur, les
  conditions et les actions ainsi que le **Journal d’audit (50
  derniers)** avec **Moment**, **Sujet**, **Décision** et **Log**.
- **Supprimer** : supprime définitivement la règle.

La **Priorité** détermine l'ordre lorsque plusieurs règles dépendent du
même déclencheur (valeur la plus basse d'abord, 100 par défaut). Seule
la première règle correspondante est exécutée ; une règle se déclenche
au plus une fois par enregistrement. Tout JSON invalide dans les
conditions est rejeté.

Autorisation : les automatisations sont gérées par les administrateurs
de l'organisation.

Remarque : pour de simples notifications, les **Règles de
notification** sont souvent le choix le plus simple ; pour les systèmes
externes, voir les **Webhooks**.
