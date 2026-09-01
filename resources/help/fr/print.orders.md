---
title: "Ordres d'impression (imprimerie & copie)"
topic: print.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - claims.overview
    - documents.manage
---

Le profil sectoriel imprimerie/copie gère chaque ordre d'impression comme un
dossier spécialisé rattaché à un ordre de fabrication : réception des
données, contrôle du fichier (preflight), bon à tirer, production, contrôle
qualité et remise forment un ensemble reproductible.

**Fichier & preflight :** Le fichier de production réside dans la GED et est
lié à l'ordre par sa somme de contrôle SHA-256. Le preflight distingue les
erreurs (qui bloquent le bon à tirer) des avertissements ; une dérogation
manuelle exige un motif et est auditée. Une nouvelle version du fichier
remet automatiquement l'ordre en « contrôle des données ».

**Bon à tirer :** Le bon à tirer fige format, support, quantité, chromie,
échéance et façonnage avec le hachage du fichier dans un instantané de
production immuable.

**Production & CQ :** Les machines bloquées ou avec contrôle/étalonnage en
retard ne peuvent pas démarrer normalement. Bonne quantité et gâche passent
par l'ordre de fabrication vers le stock et le post-calcul. Le contrôle
qualité compare à l'état validé et documente libération, blocage ou reprise.

**Remise & rétention :** Le retrait exige une preuve de remise, l'expédition
utilise la logistique existante, la vente au comptoir reste sobre en
données. À l'échéance de rétention, seul le fichier client est supprimé —
ordre, instantané et somme de contrôle demeurent comme preuve commerciale.
