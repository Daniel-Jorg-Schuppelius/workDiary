---
title: "Connecter la prise de rendez-vous (Calendly)"
topic: admin.calendly
version: 2
keywords:
    - réservation en ligne
    - page de réservation
    - prise de rendez-vous en ligne
    - planificateur
    - rendez-vous clients
    - annulation de rendez-vous
    - report de rendez-vous
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
sinon en attente de votre décision. Le rendez-vous n'est créé qu'ensuite.

**Annulations et reports** sont repris si la page de réservation les signale.
Un rendez-vous déjà repris n'est pas supprimé en silence, mais marqué annulé.

**Limites :** L'intégration lit les réservations ; elle ne crée pas de pages de
réservation et ne modifie pas les disponibilités. Celles-ci restent gérées là
où la page de réservation est administrée.

**Sans client correspondant :** Une réservation dont l'adresse ne peut être
attribuée sans ambiguïté à un client apparaît dans la **boîte d'intégration**
comme cas ciblant « Client » : attribuer (suggestions par nom), créer ou
rejeter. Vous pouvez aussi attribuer la demande à un client ou à un lead dans
la boîte des rendez-vous.

**Attribuer les hôtes :** Les hôtes Calendly sont reconnus par leur adresse
e-mail. Si l'adresse Calendly diffère, attribuez-la à un utilisateur sous
**Attribuer des utilisateurs Calendly** ; les rendez-vous vont alors à cet
utilisateur.

**Droits :** Connexion, webhook, synchronisation, attribution des hôtes et
liens de réservation relèvent de l'administration de l'organisation. Confirmer
et refuser les demandes de rendez-vous est permis à qui gère la planification,
sur cette page comme dans la boîte des rendez-vous.

**Souveraineté des données :** Les réservations et annulations de Calendly relèvent du domaine calendrier. Si un autre système gère le calendrier, WorkDiary ne crée ni ne modifie de demande de rendez-vous ; le cas attend dans la boîte de réception des intégrations pour être repris une fois.
