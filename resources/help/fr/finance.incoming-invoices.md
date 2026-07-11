---
title: "Réception des factures électroniques"
topic: finance.incoming-invoices
version: 1
audience: []
related:
    - invoices.manage
    - finance.datev-bookings
---

Ce domaine réceptionne les factures électroniques entrantes, les vérifie
et les conduit à travers un processus de validation documenté — sans
toucher à la souveraineté de facturation du programme de comptabilité ou
de facturation maître.

**Réception :** Les factures électroniques arrivent par téléversement de
fichier ou via la réception d'e-mails — au format XRechnung (XML) ou
ZUGFeRD/Factur-X (PDF avec XML intégré). Tous les canaux passent par
exactement le même traitement. La pièce est classée dans la GED comme
document de type facture ; l'original inchangé reste la seule source, la
page de détail le relit à chaque consultation. Aucune facture locale
n'est créée à cette occasion.

**Doublons :** Un contenu de fichier identique n'est enregistré qu'une
seule fois par organisation — y compris entre canaux (un téléversement
après une réception préalable par e-mail reste un doublon).

**Validation & cohérence :** Chaque réception est validée contre le
schéma XML et, si configuré, contre les règles de contrôle KoSIT
(EN 16931) ; la disponibilité de ces contrôles est indiquée de manière
transparente. De plus, le contrôle des écarts avertit visiblement —
jamais en silence — en cas de numéro de facture déjà enregistré pour le
même émetteur, de totaux contradictoires (net + taxe ≠ brut) et de
mention de taxe sans identifiant fiscal de l'émetteur.

**Suggestions :** Pour l'affectation, le système propose des fournisseurs
(via le numéro de TVA intracommunautaire ou la similarité du nom), des
commandes (via la référence de commande) et des projets (via la référence
projet/acheteur) — en tant que candidats motivés. La reprise reste du
ressort du vérificateur ; les données de base ne sont jamais créées ni
modifiées automatiquement.

**Workflow de vérification :** Une réception est validée, assortie d'une
demande de précision ou refusée (refus uniquement avec justification). Ce
n'est qu'après la validation métier que l'autorisation de paiement est
possible. Chaque décision est auditée avec la personne et le moment.

**Transfert à la comptabilité :** Seules les réceptions validées ou
autorisées au paiement sont transférées. Le transfert est idempotent — un
second appel ne change rien et ne crée aucun justificatif en double.

**Téléchargement XML :** Le XML de la facture peut à tout moment être
extrait de manière déterministe de l'original (pour ZUGFeRD, depuis la
pièce jointe du PDF). Chaque récupération est journalisée avec une somme
de contrôle comme justificatif.
