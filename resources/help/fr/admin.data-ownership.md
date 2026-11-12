---
title: "Souveraineté des données"
topic: admin.data-ownership
version: 3
keywords:
    - système maître
    - système de référence
    - source unique de vérité
    - maîtrise des données
    - autorité de facturation
    - Lexoffice
    - DATEV
    - éviter double facturation
    - conflit de synchronisation
    - conflit de souveraineté
    - reprendre une fois
audience:
    - admin
related:
    - admin.integration-inbox
    - admin.tenants
    - finance.transfers
---

Cette page définit, pour chaque organisation, quel système fait
**autorité** pour quel domaine de données – afin que deux systèmes
n'écrasent jamais mutuellement les mêmes données.

**La matrice :** Pour chaque domaine de données – tâches, tickets, stocks, calendrier, documents, clients et fournisseurs – s’applique exactement **un système de référence** : WorkDiary lui-même (par défaut) ou une intégration activée. Pour chaque domaine, seules les intégrations capables de l’écrire sont proposées. Les **stocks** ne se changent pas ici, mais dans la connexion du système de gestion des stocks (p. ex. JTL-Wawi → gestion des stocks) ; la matrice affiche ce réglage. La double gestion est structurellement exclue.

**Effet de la gestion :** Si WorkDiary gère, les imports des intégrations restent autorisés comme d’habitude. Si une intégration gère un domaine, elle seule peut y écrire. Si une autre intégration veut écrire – créer ou clôturer un ticket depuis Zammad, reprendre une réservation Calendly ou un rendez-vous du calendrier, comptabiliser un stock d’ouverture depuis JTL-Wawi, importer une tâche –, elle n’écrit rien. À la place, un cas « Souveraineté des données » apparaît dans la **boîte de réception des intégrations** : **Reprendre une fois** écrit l’état exactement une fois, **Écarter** l’abandonne et la même opération ne revient pas. La lecture et l’affichage restent toujours autorisés.

**Changement de gestion :** Chaque modification est auditée. Les conflits ouverts restent dans la boîte de réception lors du changement ; la matrice indique leur nombre par domaine. Si la nouvelle gestion autorise la voie d’écriture, la prochaine synchronisation ferme le cas d’elle-même ; sinon, reprenez-le ou écartez-le là.

**Files de tickets gérées en externe :** Si Zammad importe des tickets de service dans une file, Zammad gère cette file. De nouveaux tickets n’y naissent que par l’import ; WorkDiary n’accepte dans cette file ni création manuelle, ni enregistrement de courriels, ni réponses par courriel. Le statut et la communication client se gèrent dans le système de référence.

**Documents :** si une intégration est maître des documents, elle seule crée des documents et de nouvelles versions dans la GED via l’entrée de documents cloud. Les fichiers d’autres connexions cloud et les pièces récupérées d’autres programmes (par exemple d’easybill) attendent comme cas « Souveraineté des données » ; **Reprendre une fois** télécharge à nouveau le fichier et le crée. Vos propres téléversements, protocoles et pièces restent possibles dans WorkDiary – la GED est aussi le stockage des preuves de WorkDiary.

**Clients et fournisseurs :** cette ligne est aussi la souveraineté des données de base vis-à-vis de la comptabilité. Si vous choisissez un programme comptable (Lexoffice, sevDesk, easybill), il est maître des données de base : WorkDiary n’y transfère pas de contacts, et la synchronisation des contacts du programme reprend noms, données fiscales et adresses. Les autres programmes ne modifient alors plus les clients et fournisseurs, et la migration Toggl n’en crée aucun ; leurs états attendent dans la boîte d’intégration. « Comptabilité (tout programme qui écrit des clients) » laisse écrire chaque programme connecté. Les modifications locales restent possibles mais ne sont pas renvoyées, et la synchronisation suivante les écrase. Les numéros de client restent attribués par la série de numérotation, pas par cette ligne.

**Cas repris :** si l’intégration veut écrire plus tard un nouvel état de la même opération – une tâche modifiée ou une nouvelle révision de fichier, par exemple –, le cas repris se rouvre. Les cas rejetés restent rejetés.

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
