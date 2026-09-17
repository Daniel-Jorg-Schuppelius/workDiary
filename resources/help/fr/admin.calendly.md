---
title: "Connecter la prise de rendez-vous (Calendly)"
topic: admin.calendly
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - appointments.inbox
---

L'intégration récupère dans la gestion des rendez-vous ceux que les clients
prennent sur une page de réservation.

**Connexion :** Elle s'établit une fois par organisation et vaut ensuite pour
toutes les pages du compte connecté. Les identifiants sont stockés chiffrés et
ne sont plus affichés en clair après l'enregistrement.

**Réservations entrantes :** Les nouveaux rendez-vous arrivent d'abord dans la
**boîte de réception des rendez-vous**, pas directement dans l'agenda. Ils y
sont rattachés à un client — automatiquement en cas de correspondance nette,
sinon en attente de ta décision. Le rendez-vous n'est créé qu'ensuite.

**Annulations et reports** sont repris si la page de réservation les signale.
Un rendez-vous déjà repris n'est pas supprimé en silence, mais marqué annulé.

**Limites :** L'intégration lit les réservations ; elle ne crée pas de pages de
réservation et ne modifie pas les disponibilités. Celles-ci restent gérées là
où la page de réservation est administrée.
