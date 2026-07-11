---
title: "Saisie des temps basée sur la localisation"
topic: location.overview
version: 1
audience: []
related:
    - time-entries.start
    - attendance.manage
---

La saisie des temps basée sur la localisation propose automatiquement des
imputations de temps lorsqu'un appareil entre dans un site client
enregistré, puis en ressort. Elle complète la saisie manuelle — rien
n'est jamais comptabilisé automatiquement, mais seulement après une
confirmation explicite.

**Geofences par site client :** Pour chaque site client pertinent, un
périmètre est défini par un centre et un rayon. Ce n'est qu'à l'intérieur
de ces zones que des séjours sont créés ; les déplacements en dehors
restent sans signification métier.

**Sources de données :** Les positions proviennent au choix des
applications OwnTracks ou Traccar via un accès d'appareil personnel,
directement du navigateur, ou a posteriori par l'import d'un fichier
d'historique de localisation Google. Chaque appareil est enregistré de
manière délibérée, et la collecte suppose le consentement documenté de la
personne concernée.

**Du signal à la proposition :** Les points entrants sont condensés en
séjours : l'entrée et la sortie d'un geofence forment une visite avec un
début et une fin. Les visites terminées apparaissent comme propositions
dans une boîte de vérification personnelle — avec le client, le cas
échéant le projet, et la période enregistrée.

**Vérifier plutôt qu'automatiser :** Seule la confirmation d'une
proposition crée une véritable saisie de temps ; les propositions
inadaptées peuvent être rejetées. Entre le signal de localisation et
l'écriture se trouve ainsi toujours une décision consciente de la
personne concernée elle-même.

**Protection des données :** Seuls les événements d'entrée et de sortie
aux sites clients enregistrés sont exploités — il n'y a aucune
surveillance permanente de la localisation. Chaque personne voit
exclusivement sa propre trace de déplacement et ses propres
propositions ; même les administrateurs n'y ont pas accès. Les points de
localisation bruts sont stockés chiffrés et supprimés automatiquement à
l'expiration d'un délai de conservation (90 jours par défaut). Les
saisies de temps confirmées et les analyses qui en découlent n'en sont
pas affectées — seule la trace brute disparaît, pas le temps de travail.
