---
title: "Rapports et prix"
topic: finance.resale.reports
version: 1
keywords:
    - rapport de marge
    - marge
    - renouvellements
    - abonnements sans facture
    - contrôle des prix
    - prix conseillé
    - exporter la proposition de facture
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.periods
---

Les **rapports** du registre portent sur les périodes échues : rapport de
marge, proposition de facture, renouvellements et abonnements sans facture —
chacun en CSV, XLSX ou PDF ; la page « Prix » contrôle les prix de vente par
rapport à l'achat, au catalogue et au prix conseillé.

**Rapport de marge :** par produit et par destinataire de facture figurent
les périodes échues de la plage avec la vente prévue (quantité × prix de
vente), le facturé (montants HT des liens de facture, propositions
incluses), l’achat prévu (prix fournisseur × quantité) et l’achat réel issu
des pièces d’achat. Marge = facturé − achat ; l’achat réel compte dès que
chaque période de la ligne en a un, sinon l’achat prévu. Les montants ne
sont jamais additionnés entre devises — avec plusieurs devises, il y a une
ligne par devise et un avertissement. Export en CSV, XLSX ou PDF ; la
proposition de facture (périodes ouvertes avec mois de licence ouverts et
montant) en CSV ou XLSX.

**Renouvellements et abonnements sans facture :** le rapport
« Renouvellements » montre quels abonnements se renouvellent ou prennent fin
sur la période (tuiles 30, 60, 90 jours) : le renouvellement est le début de
la prochaine période planifiée, pour les abonnements résiliés c’est la fin
qui compte. « Sans facture » liste les abonnements dont la plus ancienne
période échue ouverte remonte à plus de N jours (60 par défaut), avec
périodes ouvertes et montant ouvert. Les deux en CSV ou XLSX.

**Contrôle des prix :** par produit, l’achat selon le contrat, le prix
catalogue et le prix conseillé de la dernière liste de prix importée face
aux prix de vente des abonnements (minimum, médiane, maximum). Indications :
« vente sous l’achat », « vente sous le prix conseillé », « contrat plus
cher que le catalogue », « pas de prix de vente ».
