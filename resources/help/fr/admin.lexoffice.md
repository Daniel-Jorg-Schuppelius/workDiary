---
title: "Conflits Lexoffice"
topic: admin.lexoffice
version: 2
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
---

Cette page permet de résoudre les conflits de synchronisation avec
Lexoffice, lorsqu'un enregistrement local et son pendant Lexoffice
divergent sur un ou plusieurs champs (contacts, articles, pièces,
factures). Pour chaque conflit, comparez les valeurs locales et
distantes, puis choisissez **« Reprendre en local »**, **« Reprendre
l'externe »** ou **« Rejeter »**. Attention : les deux premières
options écrasent des valeurs — vérifiez soigneusement la comparaison,
et rappelez-vous que la souveraineté de facturation reste au programme
externe.

La stratégie de conflit des paramètres Lexoffice s'applique aux contacts
et aux articles. Les conflits d'articles n'apparaissent pas dans cette boîte
de réception, mais dans la liste des conflits du stock (Stock → Conflits) :
vous y conservez l'état local, reprenez l'état Lexoffice ou rejetez le
conflit — avec le droit de gestion des articles.
