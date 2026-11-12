---
title: "Transfert de données"
topic: admin.data-transfer
version: 3
keywords:
    - export de données
    - exporter des données
    - importer des données
    - export CSV
    - export Excel
    - télécharger un export
    - historique des exports
    - exporter les clients
    - reprise de données
audience:
    - admin
    - buchhaltung
related:
    - admin.import
    - admin.backups
    - admin.handbook
---

Le transfert de données regroupe l'import et l'export sous trois onglets :
**Import**, **Export** et **Historique**, toutes les exécutions étant
limitées à l'organisation courante. L'import CSV passe par l'assistant
d'import ; l'export consiste à choisir une entité et un format, avec des
filtres optionnels (statut, texte, période, utilisateur), puis le fichier
est disponible au téléchargement. L'historique liste les exécutions
d'import et d'export avec statut et indicateurs ; les exports peuvent être
téléchargés ou supprimés, la suppression effaçant aussi le fichier. Chaque
export est consigné dans le journal d'audit ; pour les sauvegardes
complètes du système, voir **Sauvegardes**.

## Colonnes soumises à un droit propre

Les taux de facturation et de coût des personnes ne sont exportés qu'avec le droit
sur les données du personnel et de paie, ceux des projets qu'avec le droit sur la
facturation des projets. Sans ce droit, les colonnes manquent dans le fichier ; un
fichier qui les contient ne peut être téléchargé que par une personne disposant de
ce droit. À l'import, workDiary ignore ces colonnes sans le droit et les cite dans le
rapport de contrôle ; les autres colonnes sont importées.
