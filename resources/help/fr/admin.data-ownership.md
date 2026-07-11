---
title: "Souveraineté des données"
topic: admin.data-ownership
version: 1
audience:
    - admin
related:
    - admin.tenants
    - finance.transfers
---

Cette page définit, pour chaque organisation, quel système fait
**autorité** pour quel domaine de données – afin que deux systèmes
n'écrasent jamais mutuellement les mêmes données.

**La matrice :** Pour chaque domaine de données (p. ex. tâches, tickets,
stocks, calendriers, documents, clients), il existe exactement **un
système maître** : soit WorkDiary lui-même (« natif », le standard),
soit une intégration activée. Une double maîtrise est structurellement
exclue.

**Effet de la maîtrise :** Si WorkDiary fait autorité, les imports issus
des intégrations restent autorisés comme d'habitude via l'Inbox. Si une
intégration fait autorité sur un domaine, elle seule peut encore y
écrire – les tentatives d'écriture d'autres intégrations atterrissent
dans l'Inbox en tant que conflit au lieu de modifier des données. Chaque
changement de maîtrise est audité.

**Souveraineté de facturation :** Le même principe s'applique à la
facturation : exactement un programme fait autorité sur les factures –
WorkDiary, Lexoffice ou DATEV. Vous définissez le canal de facturation
comme **standard par organisation** et pouvez le surcharger **par
client**. La cascade suivante s'applique : le paramètre client prime sur
le standard de l'organisation ; à défaut des deux, WorkDiary facture
localement.

**Conséquences d'une souveraineté externe :** Si un programme externe
gère la facturation d'un client, la **création locale de factures est
verrouillée pour ce client**. Les temps et matériaux facturables sont
transmis à la place au programme maître sous forme de **justificatif de
transfert** : les transferts naissent d'abord comme brouillon, sont
confirmés, et ce n'est qu'au transfert effectif que les postes sources
sont consommés comme facturés – rien ne peut ainsi être facturé deux
fois. L'attribution officielle des numéros de facture reste entièrement
du ressort du programme maître.

**Changement en cours d'exploitation :** Un changement de canal de
facturation ne s'applique qu'aux opérations futures ; les pièces déjà
créées restent inchangées. Avant le basculement, clarifiez quels postes
ouverts doivent encore être clôturés via l'ancien canal.

**Recommandation :** Gardez la matrice volontairement simple – ne
transférez la maîtrise à une intégration que là où le système tiers est
réellement la source de données de référence.
