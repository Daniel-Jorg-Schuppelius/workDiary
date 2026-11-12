---
title: "Helpdesk & Service Desk"
topic: helpdesk.overview
version: 5
keywords:
    - système de tickets
    - créer un ticket
    - incident
    - demande de support
    - demande de service
    - file d'attente
    - SLA
    - délai de réponse
    - note interne
    - routage des tickets
    - satisfaction client
    - ITSM
audience: []
related:
    - open-issues
    - customer-portal.overview
---

Le helpdesk regroupe les incidents et les demandes de service sous forme
de tickets — chacun avec un numéro, un titre, une priorité, un statut, un
client, un lien optionnel vers un équipement et une personne responsable.

**Files (queues) :** Les tickets sont gérés dans des files (domaines de
responsabilité), chacune avec une équipe responsable et un contrat SLA
optionnel. Exactement une file est la file par défaut pour les nouveaux
tickets entrants ; un changement s'effectue de manière contrôlée. Une
file ne peut être supprimée que si plus aucun ticket ne lui est affecté —
rien n'est réaffecté en silence.

**Priorités & SLA :** Du contrat SLA découlent des délais de réaction et
de résolution par priorité. Les délais en cours sont visibles sur le
ticket ; si un délai est dépassé sans que la première réaction ou la
résolution soit intervenue à temps, cela est consigné comme violation et
alimente l'analyse SLA.

**Public vs interne :** Les réponses au client et les notes internes sont
deux actions distinctes avec des droits différents. Une réponse publique
est visible par le client et peut être envoyée par e-mail aux
destinataires ; une note interne reste exclusivement dans l'équipe. La
séparation est ancrée techniquement — une publication accidentelle de
remarques internes est exclue.

**Réception :** Les tickets naissent manuellement, par e-mail (les
réponses à un ticket existant sont automatiquement rattachées au
dossier), via le portail client, à partir de points ouverts, de plans de
maintenance ou via l'interface. La source reste consignée sur le ticket.

**Routage :** Des règles répartissent automatiquement les tickets
entrants — par exemple vers une file, avec une priorité ou une
responsabilité — et sont appliquées dans un ordre défini. Un mode test
vérifie une règle contre un ticket d'exemple et journalise le résultat
sans rien modifier.

**Approbations :** Les étapes d'approbation ouvertes des demandes de
service, des changes et des négociations contractuelles sont regroupées
sous « Approbations » — par dossier uniquement l'étape ouverte suivante et
seulement pour les responsables : une personne, un rôle ou, pour les
négociations contractuelles, le rôle que l'organisation associe au type
d'étape (par défaut : commercial → Comptabilité, technique → Chef d'équipe,
RH → Gestion du personnel). Approuver, refuser, poser une question et
déléguer ont le même effet que sur le dossier ; un refus clôt le tour,
l'auto-approbation reste bloquée.

**Services métier et carte des services :** Un ticket peut porter un service
métier (par exemple « e-mail » ou « serveur de comptabilité ») et une offre de
service du catalogue ; les tickets peuvent ensuite être filtrés, routés et
évalués dans les rapports selon ces critères. La carte des services montre
comment les services métier dépendent les uns des autres : pour chaque service,
de quoi il dépend, qui en a besoin et qui serait touché en cas de panne — avec
les tickets ouverts de ces services. Les dépendances se gèrent sur le service
métier (sélection multiple) ; un cycle est refusé. Sur le ticket, la carte
« Impact » indique quels autres services métier une panne du service signalé
toucherait et combien de tickets y sont ouverts — l'incident peut ainsi être
priorisé avant l'arrivée des signalements consécutifs.

**Satisfaction & rapports :** Après la clôture, le client peut donner une
courte évaluation dans le portail — une par ticket. Les rapports montrent
le volume par file, les temps de réaction et de résolution, le respect
des SLA, les motifs d'attente, les taux de changement, l'encours de
problèmes et la demande du catalogue. Les classements d'agents
individuels sont volontairement exclus.

**Changer le statut :** avec **Changer le statut** sur le ticket, vous
choisissez le nouveau statut ; seules les transitions autorisées sont
proposées. Lors de la résolution et de la clôture, vous saisissez le **Code
de clôture** et le **Résumé de la résolution**. Un état d’attente nécessite un
motif, une date de relance et une personne responsable ; à l’échéance,
WorkDiary le lui rappelle. Le contrat SLA détermine si l’horloge SLA
s’arrête. Vous ne rouvrez un ticket résolu qu’avec un motif ; les temps de
réaction et de résolution précédents sont conservés.

**Paramètres de la file :** pour chaque file, vous définissez les types de
tickets pris en charge, les heures d’ouverture, la région des jours fériés,
le nom de l’expéditeur et l’adresse de réponse. Les nouveaux tickets sans
affectation vont dans la file par défaut ; si elle n’accepte pas le type de
ticket, dans la première file qui l’accepte. Le SLA par défaut de la file
s’applique si ni l’actif du ticket, ni le projet, ni le client n’ont leur
propre contrat.

**File gérée en externe :** si un système connecté tel que Zammad gère les
tickets d’une file, les changements de statut et les réponses au client sont
verrouillés dans WorkDiary et le ticket affiche un avis. L’attribution, les
notes internes, les temps et la facturation restent possibles. Dès que vous
rétablissez la cible des tickets de l’intégration, WorkDiary gère de nouveau
la file.

**Confidentialité :** un ticket confidentiel n’est visible que par le
responsable, les observateurs, l’équipe de la file et les administrateurs.
Vous définissez le niveau à la création ou dans la classification.

**Commander une prestation :** sous **Commander une prestation**, les
collaborateurs commandent pour eux-mêmes des entrées du catalogue de
services. Les informations du formulaire de commande figurent sur le ticket
et dans la boîte de dialogue de décision de l’approbation ; le ticket indique
aussi quel dossier réalise la commande.

**État de l’envoi :** chaque réponse envoyée par e-mail indique si l’envoi
est en attente, effectué ou en échec.

**Ticket à partir d’un point ouvert :** sur le point ouvert, **Ouvrir un
ticket** crée un ticket avec le titre, la description et la référence du
point. Un seul ticket au plus est créé par point ; le point y renvoie.

**Portail client :** lorsque le client confirme la résolution ou rouvre le
ticket avec un motif, cela se déroule comme un changement de statut interne —
avec historique et temps de réaction et de résolution inchangés. Pour les
files gérées en externe, la réponse, l’acceptation et la réouverture sont
également verrouillées dans le portail ; celui-ci n’y affiche que l’état.

**Changement de file et boîte :** lorsqu’un ticket change de file, le SLA par
défaut de la nouvelle file s’applique si son contrat provenait jusque-là de
la file ou de l’organisation, ou s’il manquait ; un contrat de l’actif, du
projet ou du client et un contrat défini explicitement sont conservés. Sous **Boîte de
réception**, vous attribuez une boîte à la file — une par file.

**Tickets confidentiels dans les listes :** les listes de sélection et les
renvois n’affichent un ticket confidentiel qu’aux personnes autorisées. Dans
les détails des rapports, il reste compté mais n’apparaît pour les autres que
comme « Ticket confidentiel ».
