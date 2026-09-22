---
title: "Compléments Lexware : tarif, matrice des fonctions et remise"
topic: lexware.supplements
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - articles.lexoffice
---

Sous **Facturation → Compléments Lexware**, vous enregistrez le tarif Lexware Office souscrit (S, M, L, XL ou « Inconnu / contrat spécial ») avec sa source, la date de confirmation et — pour les accès d'essai — la date de fin et le tarif suivant confirmé. La page fonctionne sans connexion API.

**Matrice des fonctions :** Pour chaque fonction, vous voyez si elle est **incluse dans Lexware** dans votre tarif, si workDiary la propose comme **complément** ou si elle est **prévue** (extension). Avec un tarif inconnu, aucune certitude sur Lexware ; les fonctions locales restent utilisables selon leurs propres prérequis. Dans le premier paquet, workDiary complète pour S les factures standard/électroniques, les devis et les relances à partir de l'existant, et pour S/M/L les **factures récurrentes** via les plans de facturation.

**Activer un complément :** Seuls les compléments activés délibérément dans le profil tarifaire apparaissent comme « Disponible dans workDiary ». Les prérequis sont le module « Ventes & facturation », l'autorité de facturation **workDiary** (un client facturé en externe ne reçoit pas de série locale — le changement est un processus distinct avec date d'effet) et le droit de consulter les factures. Le tarif est une orientation, pas une autorisation ; un tarif supérieur ne retire rien.

**Liste de remise :** Sous « Liste de remise Lexware » figurent les documents émis des clients facturés localement sur la période d'en-tête choisie, avec statut de facture, d'envoi et de remise séparés. **Exporter** télécharge l'original figé de chaque document en PDF avec SHA-256 et une liste de correspondance (CSV) sous forme de paquet — un téléchargement pour vous, pas un prétendu format d'import Lexware. **Confirmer manuellement** valide la remise avec utilisateur, horodatage et remarque. « Exporté » ou « confirmé » ne signifie jamais « comptabilisé » ou « payé » ; annulation et avoir restent des documents distincts faisant référence à l'original.

**Remise automatique :** Le canal « automatique » nécessite une clé API propre (tarif XL) et un canal de remise validé ; d'ici là, l'export manuel reste la voie standard. Les remises ouvertes restent visibles lors d'un changement de tarif.

**Droits :** Toute personne ayant « Lister les factures » voit les pages ; seul « Configuration financière » modifie le profil tarifaire ; l'export et la confirmation nécessitent « Exporter les factures ».
