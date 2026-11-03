---
title: "Abonnements & licences"
topic: finance.resale
version: 12
keywords:
    - Aperçu d’import
    - Actions groupées
    - facturation des abonnements
    - services récurrents
    - revente de services
    - périodes de facturation
    - renouvellements
    - délai de résiliation
    - marge
    - facture récurrente
    - importer des abonnements
    - revendeur
    - Lexoffice
    - Telekom
    - Quality Hosting
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
    - finance.resale.accounts
    - finance.resale.reconcile
    - finance.resale.board
    - finance.resale.subscription
    - finance.resale.periods
    - finance.resale.plan
    - finance.resale.reports
---

Le **registre de revente** tient chaque prestation récurrente revendue comme
un abonnement : licences Microsoft 365, domaines, hébergement, boîtes mail,
sauvegardes ou autres — indépendamment du fournisseur, dans une seule liste.

**Titulaire :** chaque abonnement a exactement un titulaire. Un **client** est
facturé directement. Un **client final** appartient à un partenaire ; la
facture va au partenaire, qui la répercute. Le **parc propre** (licences
internes, domaines propres) n’est jamais facturé. Les abonnements sans
titulaire attendent une affectation et comptent dans « Sans titulaire ».
Dans la liste des abonnements, filtrez « Seulement sans titulaire »,
sélectionnez plusieurs abonnements et affectez-les avec « Affecter un
titulaire » en une étape à un client, un client final ou au parc propre ; un
abonnement seul passe par l’icône de sa ligne. Les domaines reçoivent aussi le
titulaire dans la gestion des domaines, et la boîte de réception y conduit les
abonnements sans nom de société.

**Durée et périodes :** à partir du début, de l’intervalle de facturation
(annuel ou mensuel) et de la fin, le registre planifie les périodes de
facturation attendues — jusqu’à 90 jours à l’avance pour voir le prochain
renouvellement. Un abonnement sans fin se renouvelle automatiquement. Un
reste en fin de durée plus court qu’un mois (annuel) ou cinq jours (mensuel)
est un résidu d’alignement, pas une période. Les périodes décidées
(facturée, partielle, abandonnée, contestée) survivent à toute
replanification ; les périodes ouvertes suivent les changements de quantité,
de prix et de fin.

**Prix :** achat et vente par unité et par intervalle, hors taxes. L’article
fournit le produit et le prix de vente pour les factures. Vente prévue par
période = quantité × prix de vente.

**Statut :** actif, résilié (fin connue, les périodes sont planifiées jusque-
là), remplacé (successeur chez un autre fournisseur) et terminé. Les
abonnements terminés et remplacés ne reçoivent plus de périodes.

**Suppression :** un abonnement avec des périodes décidées ne peut pas être
supprimé — passez-le à « terminé ». Droits : voir avec *Voir le registre de
revente*, gérer avec *Gérer le registre de revente*.

**Quantités et couverture :** le registre ne laisse jamais affecter plus
qu’il n’y en a. Vous affectez à une période au plus ce qu’elle a encore
d’ouvert — aussi lors du déplacement d’un lien. Seules les licences ni
cédées ni déjà facturées au détenteur sur la période peuvent être cédées,
réparties ou reprises ; une cession au propre détenteur est refusée et les
cas anciens apparaissent avec « Reprendre la cession ». Une ligne de modèle
récurrent prend au plus autant de licences qu’elle en facture (« Ligne de
modèle surréservée » signale les cas anciens). Si un modèle ne couvre qu’une
partie d’un abonnement, la proposition de facture contient le reste (« 4 sur
9 licences, 5 via modèle récurrent ») ; l’exécution automatique l’omet. Une
ligne de facture porte au plus autant de licences en stock qu’elle compte de
pièces.

**Liste générique :** outre les exports fournisseurs (Telekom, Quality
Hosting), l’import accepte toute liste CSV ou XLSX dont les colonnes sont
reconnaissables par leur nom — allemand ou anglais : identifiant, société,
produit, quantité, début, fin, intervalle, durée, prix d’achat, prix de
vente, fournisseur, commande. Société, produit et début sont obligatoires.
Sans identifiant, il est dérivé de société, produit et début, si bien qu’un
nouvel import met à jour les mêmes abonnements au lieu de les dupliquer. Le
fournisseur vient de la colonne ou du dialogue ; un modèle CSV se trouve
dans le dialogue d’import.

**Boîte de réception :** les abonnements importés dont le registre ne peut
pas encore rattacher la société à un titulaire arrivent dans la boîte de
réception. Par société, vous décidez une fois : client, client final d’un
partenaire ou parc propre — la suggestion vient de la comparaison des noms
avec les clients et clients finaux et des textes de facture : si la société
figure dans le texte de factures adressées à un partenaire, la boîte de
réception propose ce partenaire, et un clic ouvre l’affectation comme client
final. Les abonnements terminés restent aussi ici tant qu’une période échue
est ouverte — sans titulaire, personne ne trouverait leurs factures. « Attribuer un
titulaire » à côté de « Pas encore affecté » (compte de licences,
abonnement, périodes) ouvre la même affectation. La
décision est mémorisée ; l’import
suivant rattache aussitôt la même société. Les lignes que l’import n’a pas
pu traiter (date illisible, quantité sans nombre, identifiant en double)
restent comme constats sur l’import : le nombre dans le message, le détail
dépliable dans la liste.

**Facturation récurrente :** avec la facturation locale, le registre peut
créer lui-même les brouillons chaque jour. L’interrupteur se trouve sur la
page *Classification des produits* (droit *Gérer le registre de revente*) : actif
= le prochain passage crée, pour chaque destinataire avec des périodes
échues pas encore en brouillon, le même brouillon que le clic — lignes par
abonnement et période, périodes liées comme proposition et marquées.
L’*anticipation* en jours prend aussi les périodes avant leur début (0 =
périodes échues uniquement). Les destinataires facturés via
Lexoffice/DATEV ne sont pas concernés, les périodes sans prix de vente
sont ignorées, le parc propre n’est jamais facturé. Vous finalisez les
brouillons dans la liste des factures ; la synthèse hebdomadaire indique
combien de brouillons des sept derniers jours sont encore ouverts. Une
fois ou en essai : `php artisan resale:draft-local --organization=…
--dry-run` (`--force` ignore l’interrupteur).

**Pièces d’achat :** l’achat réel par abonnement et période provient de
trois sources : (1) factures et avoirs fournisseur en PDF (Quality Hosting,
mise en page allemande et anglaise) — chaque ligne nomme le contrat, le
client final et la durée, le montant va exactement à la période ; les lignes
d’avoir sans contrat appartiennent à la société. (2) Pièces reçues du miroir
des pièces au prorata : pour les factures groupées sans lignes (Telekom),
vous indiquez la part du fournisseur et le mois de prestation, le montant
est réparti sur toutes les périodes du mois, pondéré par leur achat prévu
mensuel. (3) Écritures de domaines de la gestion des domaines,
automatiquement. À l’import PDF, le registre vérifie le total : si la somme
des lignes diffère du total de la pièce (par exemple une page non lue),
l’import a quand même lieu et l’écart est signalé. La page d’achat filtre
par fournisseur, source, période et terme de recherche ; une affectation se
libère toujours en bloc par pièce.

**Classification des produits :** la liste présente tous les articles du
catalogue d’articles — les articles actifs du fichier articles et ceux des
logiciels comptables connectés comme Lexoffice, avec leur source. easybill,
sevDesk, Invoice Ninja, Akaunting et InvoicePlane livrent aussi leurs
articles : la récupération des pièces les lit avant les pièces, et les lignes
reflétées portent alors l’article de la source au lieu de son seul
identifiant. Le
registre reconnaît au nom lesquels sont des produits d’abonnement. Par
article, vous pouvez forcer : « produit d’abonnement » impose la
reconnaissance, « jamais une ligne d’abonnement » écarte les prestations
dont le texte contient un nom de produit (maintenance sur Exchange) des
propositions, listes de factures et « lignes sans abonnement ». La
classification agit de la même façon pour chaque source : elle décide
quelles lignes de facture le miroir traite comme lignes de licence, et le
contrôle des prix compare le prix de vente de l’article aux prix des
abonnements (un prix mensuel compte douze fois pour un abonnement annuel).

**Contrats :** un abonnement peut porter un contrat de la gestion des
contrats comme cadre d’échéances (champ « Contrat » du dialogue
d’abonnement ; uniquement des contrats clients dont le partenaire est le
destinataire de la facture de l’abonnement). Le dossier du contrat affiche
ses abonnements dans le panneau « Abonnements & licences ». Pas de second
catalogue d’échéances : le traitement quotidien inscrit une date par
abonnement dans le calendrier contractuel — le délai de résiliation à la fin
pour les abonnements résiliés, une alerte de renouvellement avant la
période suivante en cas de renouvellement automatique, chaque fois avancée
du préavis du contrat (30 jours à défaut), avec 14 jours de préalerte. Si
la fin change, la date suit ; les dates traitées restent traitées ; les
abonnements terminés ou sans contrat clôturent leur date ouverte.

**Domaines :** chaque domaine de la gestion des domaines devient
quotidiennement un abonnement « Domaine » avec intervalle annuel depuis
l’enregistrement, achat = prix de renouvellement et titulaire issu de la
gestion des domaines tant que le registre n’en a pas décidé un. Le prix de
vente par extension vient du catalogue de prix (fournisseur revente de
domaines, produit p. ex. « .de »), l’article du catalogue d’articles
(fichier articles ou logiciel comptable) pour l’extension ; les prix, articles et titulaires saisis à la main survivent à
chaque exécution. Les domaines disparus prennent fin au jour de référence ;
si la liste des domaines d’une exécution est vide, rien n’est terminé. Les
abonnements de domaines et leurs pièces d’achat ne se créent pas à la main —
ils n’arrivent que par la synchronisation.
Les domaines d’un compte de sous-utilisateur sont facturés par le portail de
domaines lui-même : il adresse au sous-utilisateur des factures mensuelles
que le journal des écritures affecte aux périodes. Ces domaines n’ont besoin
d’aucun modèle récurrent et n’entrent dans aucun brouillon de facture du
registre ; le classeur de travail et la page d’abonnement indiquent le
sous-utilisateur.

**Tuile du tableau de bord :** la tuile « Périodes d’abonnement ouvertes »
(groupe Finances, désactivée par défaut) montre les périodes ouvertes avec
montant ouvert, les propositions non confirmées et les abonnements sans
titulaire et mène à la page correspondante.

**Constats d’import :** chaque import (Telekom, Quality Hosting, liste de
prix, liste générique) journalise compteurs et constats par ligne. Les
dates doivent être des dates (les cellules de date Excel sont lues ;
« 3.2026 » ou « 2026 » seuls ne le sont pas), les quantités des nombres,
les identifiants uniques dans un fichier — sinon la ligne est ignorée et
la raison est indiquée.

**Conservation des fichiers d’import :** les fichiers d’import téléversés
(ils peuvent contenir des noms de clients finaux) restent 90 jours dans le
dossier de stockage puis sont supprimés par le planificateur ; la fiche
d’import avec ses compteurs reste. À la main : `resale:prune-imports`
(--days modifie le délai).

**Réparation des liens de facture :** si le miroir des pièces a été
reconstruit auparavant avec de nouveaux identifiants de lignes, les liens
confirmés pointent dans le vide (lien sans texte de ligne, période
considérée comme non couverte). La commande `lexoffice:repair-resale-links`
rattache ces liens via numéro de facture et ligne de licence ; ce qui n’est
pas univoque est seulement listé (--dry-run montre à l’avance ce qui se
passerait).

**Vue d’ensemble et onglets :** L’entrée « Abonnements & licences » est une
**liste de tâches en quatre groupes** — Facturer (prestation échue sans
facture, seulement après 21 jours de délai et jamais tant qu’un modèle
récurrent actif a encore son exécution pour la période devant lui ; plus les
constats sur les liens de facture, les répartitions au-delà du stock, les
licences à l’unité sans facture), Affecter (abonnements sans titulaire,
lignes de licence sans abonnement), Vérifier le plan (constats sur les
modèles récurrents, donc ce que la prochaine facture ferait de travers) et
État fournisseur (imports obsolètes, renouvellements sous 30 jours). Chaque
ligne montre nombre et montant et saute vers la page où l’on décide. À
côté, l’**état** : le dernier calcul de propositions, les propositions non
confirmées comme sondage, les périodes planifiées via modèle récurrent ou
encore dans le délai, par fournisseur le dernier import avec son âge. Les onglets
suivent le déroulement : Vue d’ensemble · Comptes de licences · Rapprochement ·
Périodes · Boîte de réception │ Abonnements · Stock de licences · Achats │
Rapports · Prix ; la classification des produits et la facturation sont dans
le menu ⋯.
Les tuiles au-dessus des listes mènent à ce qu’elles comptent : dans les
comptes de licences, le rapprochement, le stock de licences et un lot, elles
filtrent la liste ; dans le classeur de travail d’un destinataire, elles
filtrent le tableau de rapprochement sur les produits concernés, et un second
clic sur la tuile active y supprime le filtre.

**Classification des produits avec filtre :** La classification n’affiche
par défaut que les produits d’abonnement et les articles avec abonnements ou
lignes de modèle ; « Tous les articles », « Sans classification » et la
recherche par nom ou numéro affinent. Si un logiciel de comptabilité émet
les factures, chaque article indique dans combien de modèles récurrents
actifs il apparaît.

**Import avec aperçu :** Après le téléversement, le registre montre d’abord
ce que l’import changerait — par fichier nouveau, modifié (quantité, prix,
statut, début, fin avec l’ancienne valeur), inchangé et les abonnements
planifiables du fournisseur que le fichier ne cite plus (l’import ne les
termine pas). L’application n’a lieu qu’après « Appliquer » ;
« Abandonner » supprime les fichiers. Si le fournisseur change un prix,
l’import met à jour le prix d’achat de l’abonnement tant qu’il correspond au
dernier prix fournisseur — un prix saisi à la main est conservé — et écrit
une ligne de catalogue à la date du fichier (date dans le nom du fichier
comme « 2026-10-26_Export.xlsx », sinon le jour de l’import). Si le dernier
import d’un fournisseur date de plus de 30 jours, la vue d’ensemble le
signale et le résumé hebdomadaire le rappelle. Un fournisseur sans abonnement
en cours, par exemple après un changement, affiche « aucun abonnement en
cours » et n’est pas considéré comme obsolète.

**Achats depuis la boîte de factures :** Dans l’onglet Achats, « Associer
des fournisseurs » relie un fournisseur de la base au prestataire (le nom
donne une suggestion ; rien n’est associé automatiquement). Dès qu’une
facture entrante de ce fournisseur est affectée dans la boîte de factures,
le registre la reprend comme achat : Quality Hosting ligne par ligne via le
lecteur de factures (contrat et durée par ligne), prestataires de domaines
par nom de domaine sur l’abonnement du domaine (seulement si le journal
comptable du registrar ne fournit pas déjà les coûts), tous les autres au
prorata sur les périodes du mois de facture. Chaque ligne porte la facture
entrante comme origine ; une seconde exécution la reconnaît par son hachage.
Les lignes sans abonnement restent « sans abonnement » — le filtre « Sans
abonnement seulement » les affiche, « Affecter un abonnement » les rattache
au bon abonnement et à la période de la date de la pièce.
`resale:import-incoming --since=` rattrape rétroactivement les factures
affectées. Le compte de licences montre par produit l’achat prévu et réel
ainsi que la marge des douze derniers mois ; le résumé hebdomadaire signale
les abonnements dont le prix contractuel diffère du dernier prix de
catalogue ou de prestataire importé.

**Lien de facture sur les licences à l’unité :** Lors de la vente d’une
licence du stock, vous choisissez la ligne de facture du logiciel comptable
qui facture cette licence ; la liste montre les lignes du client choisi
autour de la date de vente, les lignes correspondantes (même article ou nom
de produit) viennent en premier et sont proposées. Sans ligne, la référence
de facture reste du texte libre. La liste des licences, la page du lot et le
dossier client affichent pour la ligne liée le numéro de pièce avec un saut
vers le logiciel comptable et l’image de la pièce. Une vente qui, après
30 jours, ne porte ni ligne ni référence apparaît comme cas à vérifier
« Licences à l’unité sans facture » dans la vue d’ensemble, dans le
rapprochement du destinataire et via le filtre « Sans facture » du stock de
licences ; ajoutez le lien via « Corriger la vente ». Dans le portail client,
les clients voient leurs licences vendues avec le numéro de facture, jamais
la clé.
