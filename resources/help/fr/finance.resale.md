---
title: "Abonnements & licences"
topic: finance.resale
version: 2
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

**Classification des produits :** le registre reconnaît au nom quels
articles Lexoffice sont des produits d’abonnement. Par article, vous pouvez
forcer : « produit d’abonnement » impose la reconnaissance, « jamais une
ligne d’abonnement » écarte les prestations dont le texte contient un nom de
produit (maintenance sur Exchange) des propositions, listes de factures et
« lignes sans abonnement ».

**Domaines :** chaque domaine de la gestion des domaines devient
quotidiennement un abonnement « Domaine » avec intervalle annuel depuis
l’enregistrement, achat = prix de renouvellement et titulaire issu de la
gestion des domaines tant que le registre n’en a pas décidé un. Le prix de
vente par extension vient du catalogue de prix (fournisseur revente de
domaines, produit p. ex. « .de »), l’article de l’article Lexoffice de
l’extension ; les prix, articles et titulaires saisis à la main survivent à
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
