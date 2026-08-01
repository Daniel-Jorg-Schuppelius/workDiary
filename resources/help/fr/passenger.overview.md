---
title: "Transport de personnes (taxi/VTC)"
topic: passenger.overview
version: 1
audience: []
related:
    - claims.overview
---

Le profil sectoriel taxi/VTC gère chaque transport de personnes dans un
dossier de course dédié : acceptation avec mode d'exploitation figé,
dispatching avec contrôles obligatoires (permis de transport de personnes,
licence, justificatifs du véhicule), départ avec tarif ou prix forfaitaire
figé, et clôture avec la valeur du taximètre, la décision fiscale et le mode
de paiement.

**Courses :** Les nouvelles courses se créent via « Nouvelle course ». Les
VTC et le transport à la demande groupé exigent une réception de commande
documentée au siège ; seul le taxi connaît des destinations ouvertes. Le
dispatching vérifie conducteur, profil du véhicule et licence — les
obstacles s'affichent comme erreurs de validation.

**Données de base :** Les tarifs sont versionnés (période de validité, prix
de base, prix au km et à la minute, suppléments, corridor de prix
forfaitaire). Les licences et profils de véhicules avec échéances
d'étalonnage, BOKraft et contrôle technique se trouvent à côté ; des
justificatifs expirés bloquent le dispatching.

**Décompte de service :** Le chiffre d'affaires du taximètre et les modes de
paiement (espèces, carte, bon, facture, intermédiaire) restent séparés ; les
pourboires ne comptent pas contre le total du taximètre. Les écarts restent
ouverts jusqu'à leur clarification motivée.

WorkDiary ne remplace ni le taximètre/compteur kilométrique ni la TSE — ces
systèmes restent souverains ; leurs valeurs sont documentées et rapprochées.
