---
title: "Rapprochement licences"
topic: finance.reselling
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Le **rapprochement de revente de licences** vérifie que chaque période de
facturation des abonnements Microsoft 365 revendus est couverte par une
facture sortante dans Lexoffice et compare les prix de vente aux prix d’achat.

**Ce que vous téléversez :** l’export du Telekom Cloud Marketplace
(purchases.csv), l’export des contrats du portail partenaire Quality
Hosting (XLSX) et, en option, sa liste de prix. Les deux exports forment le
parc avant et après la migration ; les successions sont détectées et la durée
Telekom est coupée au début du contrat Quality Hosting.

**Ce que fait l’exécution :** elle découpe chaque abonnement en périodes
annuelles ou mensuelles, affecte chaque société marketplace à un contact
Lexoffice (fichier d’affectation, numéro client partenaire, fiche client,
recherche de nom sans ambiguïté — jamais de supposition) et cherche pour
chaque période une ligne de facture correspondante dans la fenêtre autour du
début de période.

**Statut par période :** Couverte, Sous le prix d’achat, Partielle, Manquante, Sans affectation. Les
sociétés sans affectation se résolvent à la prochaine exécution avec un
fichier d’affectation : une ligne par société, `Société;UUID du contact
Lexoffice` ou `Société;customer:<Sqid>`.

**Contrôle des prix :** par produit, vous voyez le prix d’achat des contrats,
le prix de liste actuel et le prix conseillé du fabricant, ainsi que les prix
de vente unitaires réellement facturés. Une alerte apparaît si votre prix est
sous le prix d’achat ou le prix conseillé, ou si un contrat en cours est plus
cher que la liste actuelle.

L’exécution lit Lexoffice en arrière-plan et prend quelques minutes avec de
nombreux clients. Elle n’écrit rien dans Lexoffice ni dans les données de
base — le rapport n’existe que sur l’exécution et se télécharge en CSV.
