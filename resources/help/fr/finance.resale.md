---
title: "Abonnements & licences"
topic: finance.resale
version: 5
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
---

Le **registre de revente** tient chaque prestation récurrente revendue comme
un abonnement : licences Microsoft 365, domaines, hébergement, boîtes mail,
sauvegardes ou autres — indépendamment du fournisseur, dans une seule liste.

**Titulaire :** chaque abonnement a exactement un titulaire. Un **client** est
facturé directement. Un **client final** appartient à un partenaire ; la
facture va au partenaire, qui la répercute. Le **parc propre** (licences
internes, domaines propres) n’est jamais facturé. Les abonnements sans
titulaire attendent une affectation et comptent dans « Sans titulaire ».

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

**Rapprochement par destinataire de facture :** lorsque des périodes restent
ouvertes sans que l’on sache s’il manque une facture ou seulement le
rattachement, utilisez le rapprochement (bouton dans la liste des
abonnements, sur la page des périodes et sur le client). Par destinataire —
le client avec ses clients finaux — il confronte les périodes échues de
tous les abonnements aux lignes de licence de ses factures, en mois de
licence par produit : *attendu* d’après les périodes, *facturé* d’après les
lignes. Le constat dit quoi faire : « seulement non rattaché » (les lignes
libres suffisent — rattacher), « jamais facturé » (plus de périodes que de
lignes — facture de rattrapage via le brouillon ou renonciation) ou « sans
période » (plus de lignes que de périodes — abonnement manquant dans le
registre ou double facturation). Par période ouverte, les lignes du même
produit sont listées avec leur distance au début de période : les libres
avec rattachement, les consommées avec leur détenteur pour contrôle. La date de référence est la **période
de prestation** de la facture, sinon la date de facture ; licences et mois
sont affichés séparément (« 5 × 12 mois » = cinq licences pour un an). Plus
trois pièges invisibles par abonnement : **facture à un autre client** (le
compte fournisseur n’est pas le client, ou le client final est facturé
directement au lieu du partenaire ; reconnu à un élément de nom commun) —
la solution est « Détenteur → client » : l’abonnement passe à ce client et
la proposition s’applique aussitôt. Les factures **annulées** près du début
de période expliquent une période vide. Les abonnements de la **boîte de
réception** dont la société apparaît dans les textes de facture attendent
leur détenteur. Une ligne libre qui ne touche plus aucune période de son
produit signale un contrat absent du registre (l’export fournisseur ne le
connaît pas) : la ligne produit indique « Ligne sans abonnement à partir
du … » et « Créer l’abonnement depuis la ligne » ouvre le dialogue avec
article, quantité, début et prix issus de la facture. Le rattachement peut
viser une période d’un autre abonnement du même destinataire — jamais une
période d’un autre client.

**Liste générique :** outre les exports fournisseurs (Telekom, Quality
Hosting), l’import accepte toute liste CSV ou XLSX dont les colonnes sont
reconnaissables par leur nom — allemand ou anglais : identifiant, société,
produit, quantité, début, fin, intervalle, durée, prix d’achat, prix de
vente, fournisseur, commande. Société, produit et début sont obligatoires.
Sans identifiant, il est dérivé de société, produit et début, si bien qu’un
nouvel import met à jour les mêmes abonnements au lieu de les dupliquer. Le
fournisseur vient de la colonne ou du dialogue ; un modèle CSV se trouve
dans le dialogue d’import.

**Céder des licences :** quand deux sociétés partagent les mêmes locaux et
que la seconde utilise une partie des licences d’un contrat, cédez ces
licences au niveau du contrat (« Céder des licences » : détenteur,
quantité, période, prix de vente). Un abonnement distinct pour l’autre
détenteur avec ses propres périodes apparaît ; le contrat planifie ses
périodes avec le reste. Chaque détenteur se voit rattacher ses propres
factures. Si un successeur remplace le contrat (import), la cession y
continue. Un contrat avec cessions ne se supprime qu’une fois les cessions
retirées. Un changement de détenteur dans le temps — une société se scinde
et la nouvelle reprend les contrats — est aussi une cession : toutes les
licences de l’ancienne période à l’ancien détenteur ; le rapprochement le
propose sur une facture de l’autre client sous « Céder la période à … »,
prérempli.

**Boîte de réception :** les abonnements importés dont le registre ne peut
pas encore rattacher la société à un titulaire arrivent dans la boîte de
réception. Par société, vous décidez une fois : client, client final d’un
partenaire ou parc propre — la suggestion vient de la comparaison des noms
avec les clients et clients finaux. La décision est mémorisée ; l’import
suivant rattache aussitôt la même société. Les lignes que l’import n’a pas
pu traiter (date illisible, quantité sans nombre, identifiant en double)
restent comme constats sur l’import : le nombre dans le message, le détail
dépliable dans la liste.

**Périodes :** la page des périodes montre les périodes échues de tous les
abonnements avec des tuiles de statut (ouverte, facturée, partielle,
abandonnée, contestée). « Calculer les propositions » rapproche les périodes
ouvertes des lignes de licences des factures miroir et crée des
propositions ; vous les confirmez, rattachez une ligne à la main (uniquement
des factures du même destinataire, uniquement des mois de licence libres)
ou renoncez avec un motif (« geste commercial »). Les périodes décidées ne
sont plus touchées par la planification ; « rouvrir » les rouvre. Si une
facture rattachée est annulée plus tard dans Lexoffice, le prochain calcul
met le lien à zéro mois, note l’annulation et rouvre la période pour que la
facture de remplacement puisse être rattachée.

**Brouillon de facture :** à partir de toutes les périodes ouvertes d’un
destinataire de facture, un clic crée un brouillon — avec la facturation
Lexoffice comme brouillon dans Lexoffice (rien n’est finalisé ; vous
vérifiez et émettez là-bas), avec la facturation locale comme brouillon de
facture local avec lignes et liens proposés. Une ligne par abonnement et
période, client final dans la description, quantité en mois pour les
articles mensuels. Les périodes mémorisent le brouillon : un second clic
n’en crée pas un deuxième mais nomme le brouillon en attente avec numéro et
date ; ce n’est que lorsque le brouillon est devenu facture ou que la
période est décidée qu’elles sont de nouveau libres. Le dialogue ne liste
que les destinataires avec périodes ouvertes et prix de vente et indique en
dessous ce qui est déjà dans un brouillon. La création requiert le droit
*Créer des brouillons de facture à partir des périodes*.

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

**Contrôle des prix :** par produit, l’achat selon le contrat, le prix
catalogue et le prix conseillé de la dernière liste de prix importée face
aux prix de vente des abonnements (minimum, médiane, maximum). Indications :
« vente sous l’achat », « vente sous le prix conseillé », « contrat plus
cher que le catalogue », « pas de prix de vente ».

**Classification des produits :** la liste présente tous les articles du
catalogue d’articles — les articles actifs du fichier articles et ceux des
logiciels comptables connectés comme Lexoffice, avec leur source. Le
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

**Renouvellements et abonnements sans facture :** le rapport
« Renouvellements » montre quels abonnements se renouvellent ou prennent fin
sur la période (tuiles 30, 60, 90 jours) : le renouvellement est le début de
la prochaine période planifiée, pour les abonnements résiliés c’est la fin
qui compte. « Sans facture » liste les abonnements dont la plus ancienne
période échue ouverte remonte à plus de N jours (60 par défaut), avec
périodes ouvertes et montant ouvert. Les deux en CSV ou XLSX.

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

**Compte de licences :** L'onglet *Comptes de licences* montre par compte
fournisseur (numéro client chez le fournisseur, prédécesseurs d'un changement
de fournisseur inclus) combien de licences par produit sont en cours, qui
les détient et quelle année de licence est facturée — en grille produit ×
détenteur × année avec numéro de facture ou « manque ». Si plusieurs sociétés
détiennent des licences du même compte, tenez la **répartition** par produit
(« Märkische Bunker 5 × Exchange à partir du 18.10.2025 ») : les autres
détenteurs remplissent d'abord le contrat à la plus grande quantité, puis par
début ; le titulaire du contrat garde le reste. Les cessions par contrat en
découlent, suivent chaque modification et passent au successeur lors d'un
changement de fournisseur ; les cessions manuelles restent possibles à titre
d'exception. La planification reprend aussi les contrats remplacés et
terminés des trois dernières années pour qu'une répartition ultérieure change
encore leur cible. Les **points à vérifier sur les liens** montrent les lignes
couvrant plus de mois de licence qu'elles n'en ont (surréservées) et les liens
dont la période de prestation ne correspond pas à la période — avec
« Déplacer vers … » sur la bonne période du même abonnement ou « Détacher » ;
le dialogue de lien et l'affectation rapide préviennent avant avec ⚠.

**Modèles récurrents (plan) :** Si un programme de comptabilité émet les
factures, les factures récurrentes y naissent comme modèles récurrents. Le
registre les reflète (Lexoffice : chaque jour et après la synchronisation des
pièces, en lecture seule) et montre dans le compte de licences, par modèle,
statut, rythme, prochaine exécution et les lignes de licence avec les
abonnements qu'elles facturent. L'affectation abonnement ↔ ligne de modèle est
proposée par le traitement (même destinataire, article, quantité ; avec
plusieurs clients finaux seulement s'ils sont nommés) : vous confirmez,
affectez à la main ou retirez. Les périodes d'un abonnement avec modèle actif
sont exclues de la proposition de facture. Les **contrôles stock ↔ plan**
signalent avant la facture : abonnement sans modèle, modèle sans abonnement
(« Créer un abonnement depuis le modèle » reprend article, quantité, rythme et
prix), quantité différente, modèle qui continue après la fin de l'abonnement,
modèle en pause ou terminé alors que l'abonnement court, prochaine exécution à
plus de sept jours du début de période, dernière exécution échouée — dans la
tuile, le résumé hebdomadaire et la file de travail. Chaque facture reflétée
porte la référence à son modèle.

**Vue d’ensemble et onglets :** L’entrée « Abonnements & licences » n’affiche
plus la liste des abonnements mais une **liste de tâches** : périodes
échues sans facture (avec montant ouvert), propositions non confirmées,
constats sur les liens de facture, constats sur les modèles récurrents,
répartitions au-delà du stock, abonnements sans titulaire, renouvellements
ou fins sous 30 jours et exports fournisseur obsolètes — chaque ligne avec un
nombre et un saut vers la page où l’on décide. À côté, l’**état** : le
dernier calcul de propositions (avec « Calculer les propositions ») et, par
fournisseur, le dernier import avec son âge. Les onglets suivent le
déroulement : Vue d’ensemble · Comptes de licences · Rapprochement ·
Périodes · Boîte de réception │ Abonnements · Stock de licences · Achats │
Rapports · Prix ; la classification des produits et la facturation sont
dans le menu ⋯. La liste des abonnements s’ouvre via « Abonnements ».

**Propositions sûres et incertaines :** Le calcul de propositions (chaque
heure et après chaque import) confirme lui-même les correspondances sûres —
une pièce dont la date de facture se situe au plus 14 jours avant à 45 jours
après le début de période et qui couvre entièrement la période avec le bon
nombre de licences ; la confirmation figure dans le journal comme
« confirmée automatiquement ». Tout le reste reste une proposition et porte
« incertaine » avec la raison dans l’info-bulle. La page des périodes
affiche donc par défaut « Propositions non confirmées » ; le filtre
« Problèmes seulement » et les tuiles de statut restent.

**Actions groupées :** Sur la page des périodes, sélectionnez des lignes
via les cases et confirmez toutes les propositions d’un coup, renoncez avec
un motif pour toutes (« Renoncer / contester … ») ou faites créer un
brouillon par destinataire de facture de la sélection. Les périodes
décidées et à venir sont ignorées et comptées ; chaque période reçoit la
même entrée de journal que pour l’action individuelle. Il n’y a pas de
présélection.

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
signale et le résumé hebdomadaire le rappelle.

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
