---
title: "Gérer les intégrations"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

Cette aide s'applique à toutes les pages de gestion des intégrations –
notamment CalDAV, WebDAV, Todoist, Zammad, Kimai/Clockify, réception
d'e-mails, téléphonie, messagerie d'équipe, terminaux de pointage,
expédition et SSO. Toutes les connexions suivent les mêmes principes de
base.

**Pointeuses, kiosque et points de check-in :** Lors de l'enregistrement
d'une pointeuse, deux adresses s'affichent une seule fois : l'adresse d'ingestion
pour les terminaux matériels et l'adresse kiosque qui transforme le navigateur
d'une tablette en terminal. Les deux contiennent le même jeton ; en cas de perte,
faites pivoter le jeton ou désactivez le terminal. Les badges lus par la puce NFC
de la tablette (Chrome sur Android) doivent être enregistrés comme identifiant
hexadécimal sans séparateurs. Les points de check-in sont des QR codes ou des
autocollants NFC sur des sites et des véhicules : la vue d'impression fournit le
code, et la même adresse peut être écrite sur un autocollant avec une
application NFC. Un code peut être photographié – pour attester la présence sur
place, définissez un rayon. La position est alors seulement vérifiée, pas
enregistrée.

Les badges peuvent être remplacés par un **PIN du terminal** : l'administration
le définit par personne avec matricule ; seul un hachage est enregistré, et après
cinq échecs il est bloqué 15 minutes et peut être débloqué ici.

**Par organisation :** Les intégrations sont activées et configurées par
organisation. Activation, identifiants, état de santé et historique des
erreurs ne valent toujours que pour l'organisation actuelle – dans une
autre organisation, la même connexion peut se trouver dans un état
totalement différent.

**Identifiants :** Vous déposez les tokens, mots de passe et identifiants
d'appareil dans la configuration du plugin concerné. Les valeurs
sensibles sont stockées chiffrées et n'apparaissent plus en clair après
l'enregistrement – ni dans l'interface, ni dans le journal d'audit.

**Contrôle de santé et désactivation automatique :** Chaque connexion est
surveillée en continu pour détecter les erreurs de connexion. Si les
erreurs s'accumulent au-delà du seuil configurable, la connexion est
automatiquement désactivée afin qu'elle ne produise pas d'erreurs en
cascade. Les intégrations désactivées automatiquement restent visibles
dans la vue d'ensemble et sont marquées en conséquence – après
résolution de la cause (p. ex. renouvellement d'un token expiré), vous
pouvez les réactiver. Un plugin défectueux isolé n'entraîne jamais
l'application avec lui : les erreurs sont enregistrées de manière
isolée.

**Données entrantes – Inbox d'abord :** Les imports ne reprennent rien
aveuglément. Les enregistrements entrants atterrissent d'abord dans
l'Inbox des intégrations, sont rapprochés des données existantes et ne
sont repris qu'après une correspondance univoque ou votre décision
manuelle. Les cas ambigus et les conflits restent en attente comme
entrées ouvertes de l'Inbox jusqu'à ce que vous les résolviez ou les
rejetiez.

**Modifications sortantes – Outbox :** Les modifications à destination du
système tiers passent par une Outbox avec relance automatique. Si une
transmission échoue, elle est retentée ; les conflits détectés (p. ex.
lorsque le système tiers a été modifié entre-temps) retournent dans
l'Inbox pour clarification. Ainsi, aucune modification n'est perdue et
rien n'est écrit en double.

**Recommandation :** Après la mise en place d'une nouvelle connexion,
vérifiez le contrôle de santé, observez l'Inbox pendant quelques jours
pour repérer des conflits inattendus, et ne mettez en place des
processus automatisés qu'ensuite.

## Quelles intégrations existent

L'offre s'étoffe ; la liste ci-dessous nomme les intégrations disponibles par
usage, pour que tu n'aies pas à deviner où va quoi :

- **Comptabilité et facturation :** lexoffice, orgaMAX, sevDesk, easybill,
  BuchhaltungsButler, InvoicePlane et le point d'accès Peppol pour l'envoi de
  factures électroniques.
- **Téléphonie et messages :** sipgate et FRITZ!Box pour les appels entrants et
  sortants, seven.io pour les SMS aux destinataires critiques.
- **Expédition :** DHL, FedEx et UPS pour les étiquettes et le suivi.
- **Fichiers et sauvegardes :** Nextcloud, WebDAV, Dropbox, Google Drive,
  SharePoint et S3 comme cible de stockage ou de sauvegarde.
- **Agenda, contacts et courrier :** Microsoft Graph, Google Agenda, CalDAV,
  CardDAV et Calendly pour les rendez-vous réservés.
- **Projets et temps :** Todoist, OpenProject, GitHub, GitLab, Toggl, Clockify,
  Kimai, Zammad.
- **Commerce et gestion :** JTL-Wawi, Billbee, Etsy.

Une intégration absente de cette liste n'existe pas — en cas de doute, demande
plutôt que d'enregistrer des identifiants à un endroit non prévu pour cela.
