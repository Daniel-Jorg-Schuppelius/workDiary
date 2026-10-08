---
title: "Helpdesk & Service Desk"
topic: helpdesk.overview
version: 2
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

**Satisfaction & rapports :** Après la clôture, le client peut donner une
courte évaluation dans le portail — une par ticket. Les rapports montrent
le volume par file, les temps de réaction et de résolution, le respect
des SLA, les motifs d'attente, les taux de changement, l'encours de
problèmes et la demande du catalogue. Les classements d'agents
individuels sont volontairement exclus.
