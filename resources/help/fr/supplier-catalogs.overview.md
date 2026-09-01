---
title: "Catalogues fournisseurs"
topic: supplier-catalogs.overview
version: 1
audience: []
modules:
    - module.lager
related:
    - articles.master
    - procurement.orders
---

Les catalogues fournisseurs conservent les listes de prix de vos
fournisseurs dans le système — séparées de votre propre référentiel
d'articles, mais pouvant y être liées.

**Sources de catalogue :** Une ou plusieurs sources sont créées par
fournisseur. Les formats pris en charge sont DATANORM, BMEcat et CSV avec
un mappage de colonnes librement configurable (numéro d'article, libellé,
prix d'achat, devise, GTIN, référence fabricant, groupe de marchandises,
disponibilité, délai de livraison). Les fichiers arrivent par
téléversement ou par récupération distante automatique à intervalle
réglable ; un fichier shopinfo.xml téléversé prérenseigne le mappage, le
jeu de caractères et le séparateur. Le mappage est enregistré sur la
source et réutilisé lors des récupérations ultérieures.

**DATANORM en détail :** Les versions 4 et 5 sont prises en charge —
outre les fichiers d'articles (DATANORM.nnn), aussi les groupes de
remise (DATANORM.RAB), les groupes de marchandises (DATANORM.WRG) et les
fichiers de prix (DATPREIS.nnn). Les prix catalogue (indicateur 1)
deviennent des prix d'achat nets via le groupe de remise ; les fichiers
de modifications ne touchent pas l'existant (mode de traitement
sélectionnable dans le dialogue d'import). Pour les fichiers de prix
spécifiques au client, l'enregistrement de contrôle K est vérifié contre
le numéro de client enregistré sur la source. Le jeu de caractères est
généralement CP850. En sens inverse, la liste d'articles exporte votre
propre référentiel comme catalogue DATANORM ou fichier de prix DATPREIS
(aussi par accès catalogue B2B avec prix client).

**Import :** Chaque exécution récapitule combien d'articles de catalogue
ont été créés, mis à jour, modifiés en prix ou marqués comme abandonnés.
Outre le prix d'achat, les articles de catalogue gèrent aussi des prix
dégressifs.

**Liaison (sources d'approvisionnement) :** Les articles de catalogue
sont liés à vos propres articles (y compris les variantes) manuellement
ou par suggestion GTIN/EAN. Ce n'est que cette liaison qui établit la
source d'approvisionnement — le référentiel d'articles lui-même n'est pas
affecté par l'import. Les liaisons peuvent être défaites à tout moment.

**Alignement des prix avec validation :** Si un import modifie le prix
d'achat d'un article lié, une alerte de calcul est créée, qui doit être
examinée et acquittée. À partir des règles de marge, le système calcule
des propositions de prix de vente directement sur l'article de catalogue.
La reprise dans l'article n'est jamais automatique : en mode direct,
l'opérateur la reprend expressément ; en mode quatre yeux, une demande de
validation est créée à la place, qu'une seconde personne doit approuver
ou refuser.

**OCI-Punchout :** Les sources avec un accès boutique enregistré
permettent de basculer directement vers la boutique en ligne du
fournisseur. Le panier qui y est constitué revient via un retour signé et
limité dans le temps, et est affecté à l'entrepôt cible choisi — comme
base pour la suite de l'approvisionnement.

La lecture est possible avec des droits de lecture du stock ; la
création, l'import et la liaison exigent des droits d'écriture du stock.
