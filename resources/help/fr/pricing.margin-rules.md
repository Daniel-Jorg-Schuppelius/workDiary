---
title: "Règles de prix & de marge"
topic: pricing.margin-rules
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - supplier-catalogs.overview
    - articles.master
---

Les règles de marge dérivent des propositions de prix de vente à partir
des prix d'achat. Elles garantissent que les prix issus des catalogues
fournisseurs n'ont pas à être calculés à la main — et qu'aucune reprise
ne contourne le calcul.

**Contenu d'une règle :** Une règle calcule soit avec une majoration en
pourcentage sur le prix d'achat, soit avec une marge cible en pourcentage
du prix de vente ; si les deux sont définies, la marge cible prime. En
option s'y ajoutent : une marge minimale (la proposition est signalée si
elle passait en dessous), un prix de vente minimal et un schéma d'arrondi
pour des prix finaux commercialement ronds. Les règles peuvent être
désactivées sans être supprimées.

**Périmètre & ordre d'application :** Une règle vaut globalement, pour un
fournisseur, pour un groupe de marchandises ou pour la combinaison des
deux. Si plusieurs règles actives correspondent, la plus spécifique
gagne : fournisseur plus groupe de marchandises avant l'un des deux
critères seul, avant la règle globale. En cas d'égalité, la priorité de
la règle décide, puis la plus récente. Vous pouvez ainsi entretenir une
majoration standard à l'échelle de l'entreprise et la surcharger de
manière ciblée pour certains fournisseurs ou groupes de marchandises.

**Effet sur les reprises de catalogue :** Les propositions apparaissent
directement sur les articles de catalogue liés des catalogues
fournisseurs. Elles n'arrivent jamais automatiquement dans le prix de
vente de l'article : en mode direct, l'opérateur les reprend
expressément ; en mode quatre yeux, une demande de validation est créée à
la place. Seule une personne autre que le demandeur peut approuver une
demande ; les refus peuvent être motivés. Le mode de validation (direct
ou quatre yeux) se règle sur cette page par organisation ; les demandes
ouvertes et tranchées y sont consultables.

Les opérations déjà clôturées et les prix historiques ne sont pas
affectés par les modifications de règles — une règle modifiée n'agit qu'à
la prochaine reprise de prix. La lecture exige des droits de lecture du
stock, la gestion des règles et des demandes des droits de configuration
du stock.
