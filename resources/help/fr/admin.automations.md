---
title: "Automatisations"
topic: admin.automations
version: 4
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
  (par ex. « Note de frais soumise » ou « Facture entrante reçue »), l'**Action** (par ex.
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

Les actions avec une cible affichent en plus le champ **Cible** dans la boîte de dialogue : **Inscrire au cours** (déclencheurs **Collaborateur créé** ou **Équipement remis**) demande le cours. Pour les nouveaux collaborateurs, les conditions voient `role`, `roles` et `personnel_number` ; pour la remise d’équipement, entre autres `category_code`, `asset_class` et `manufacturer`. Le rôle n’est fixé qu’une fois la création enregistrée — la règle vérifie donc après l’enregistrement.

## Règles de réception (factures entrantes)

Le déclencheur « Facture entrante reçue » vérifie chaque nouveau document de
la boîte de réception des factures. Outre les colonnes de la facture entrante,
les conditions peuvent utiliser ces champs : `seller_name_key`, `buyer_name_key`, `party_name`, `party_name_key`, `sender_domain`, `mail_subject`, `attachment_names`, `file_name`, `country`, `kind_hint`. Les clés de nom sont en
minuscules et sans forme juridique, de sorte que « Muster GmbH » et « MUSTER »
comptent pareil ; `kind_hint` indique le type de pièce reconnu (par exemple
`reminder` pour une relance). Opérateurs : `=`, `!=`, `<`, `<=`, `>`, `>=`,
`in`, `not_in`, `contains`, `not_contains`, `starts_with`, `ends_with`,
`matches`. Exemple :
`{"all": [{"field": "sender_domain", "op": "=", "value": "exemple.fr"}]}`.
Actions possibles : « Classer la facture entrante comme « pas une facture » »,
« Attribuer la facture entrante » et « Rejeter et supprimer la facture
entrante ». La page des règles des factures entrantes affiche ces règles pour
la comptabilité ; on peut les activer, les désactiver et les supprimer, et
chaque action figure dans le journal d'audit.
