---
title: "Périodes de facturation"
topic: finance.resale.periods
version: 1
keywords:
    - périodes
    - calculer les propositions
    - brouillon de facture
    - actions groupées
    - renonciation
    - contesté
    - facturation rétroactive
    - paiement anticipé
    - date butoir
    - traitement récurrent
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.plan
    - finance.resale.board
---

La **page des périodes** liste les périodes de facturation échues de tous les
abonnements : vous y décidez par période (confirmer, affecter, renoncer,
contester), lancez le calcul des propositions, créez des brouillons de
facture et ouvrez la proposition de modèles. Les tuiles de statut et le filtre
« Problèmes seulement » restreignent la liste.

**Périodes :** la page des périodes montre les périodes échues de tous les
abonnements avec des tuiles de statut (ouverte, facturée, partielle,
abandonnée, contestée). « Calculer les propositions » rapproche les périodes
ouvertes des lignes de licences des factures miroir et crée des propositions ;
vous les confirmez, rattachez une ligne à la main (uniquement des factures du
même destinataire, uniquement des mois de licence libres) ou renoncez avec un
motif (« geste commercial »).
Confirmer exige une proposition ouverte ; renoncer ou contester exige une
période échue non encore décidée — l’action unitaire et l’action groupée
vérifient la même chose.
Dans la boîte de rattachement, les factures dans la fenêtre de la période
viennent d’abord, puis toutes les autres du destinataire ; le champ de recherche
trouve chacune par son numéro, et une facture déjà utilisée indique où elle est
rattachée. Les listes des abonnements et des périodes cherchent aussi par numéro
de facture. Les périodes décidées ne sont plus touchées par
la planification ; « rouvrir » les rouvre. Si une facture rattachée est
annulée plus tard dans Lexoffice, le prochain calcul met le lien à zéro mois,
note l’annulation et rouvre la période pour que la facture de remplacement
puisse être rattachée. Les abonnements de domaine sont couverts par les lignes
qui nomment le domaine (par exemple « domaine .de avec gestion
(lastkraft-logistik.de) »), quelle que soit la classification de l’article. Les
domaines qu’un sous-revendeur règle via le portail du fournisseur sont
couverts par le journal comptable du module domaines : une pièce par
sous-utilisateur et par mois, numérotée « numéro client-MM-AAAA » (saisissez
le numéro client sur la page du sous-utilisateur) ; le destinataire est le
client du sous-utilisateur.

**Propositions sûres et incertaines :** Le calcul de propositions (chaque
heure et après chaque import) confirme lui-même les correspondances sûres —
une pièce dont la date de facture se situe au plus 14 jours avant à 45 jours
après le début de période et qui couvre entièrement la période avec le bon
nombre de licences ; la confirmation figure dans le journal comme
« confirmée automatiquement ». Tout le reste reste une proposition et porte
« incertaine » avec la raison dans l’info-bulle. Les propositions valent sans
confirmation : la page des périodes affiche par défaut « Problèmes
seulement » (ouvert, partiel, contesté) ; les propositions non confirmées
se consultent comme sondage via le filtre et l’état de la vue d’ensemble. Un clic sur le
numéro de pièce ouvre la pièce : lignes, période de prestation, texte de
facture, consommation par ligne et comparaison avec la période (date dans la
fenêtre, prestation, article) — vous voyez ainsi avant de confirmer si la
facture appartient à la période.

**Actions groupées :** Sur la page des périodes, sélectionnez des lignes
via les cases et confirmez toutes les propositions d’un coup, renoncez avec
un motif pour toutes (« Renoncer / contester … ») ou faites créer un
brouillon par destinataire de facture de la sélection. Les périodes
décidées et à venir sont ignorées et comptées ; chaque période reçoit la
même entrée de journal que pour l’action individuelle. Il n’y a pas de
présélection.

**Brouillon de facture :** à partir de toutes les périodes ouvertes d’un
destinataire de facture, un clic crée un brouillon — avec la facturation
Lexoffice comme brouillon dans Lexoffice (rien n’est finalisé ; vous
vérifiez et émettez là-bas), avec la facturation locale comme brouillon de
facture local avec lignes et liens proposés. Une ligne par abonnement et
période, client final dans la description, quantité en mois pour les
articles mensuels. Les périodes mémorisent le brouillon : un second clic
n’en crée pas un deuxième mais nomme le brouillon en attente avec numéro et
date ; ce n’est que lorsque le brouillon est devenu facture ou que la
période est décidée qu’elles sont de nouveau libres ; jusque-là, elles affichent « En brouillon ».
Le dialogue ne liste
que les destinataires avec périodes ouvertes et prix de vente et indique en
dessous ce qui est déjà dans un brouillon. La création requiert le droit
*Créer des brouillons de facture à partir des périodes*.
Deux clics simultanés (double clic, seconde fenêtre du navigateur) ne créent
eux aussi qu’un seul brouillon — le second signale qu’un brouillon est en
cours de création.

**Facturation rétroactive et planification anticipée :** La proposition de
facture regroupe les périodes ouvertes consécutives d'un abonnement en une
seule ligne — toujours pour les articles mensuels, pour les abonnements
annuels à une licence (« 3 an » pour trois années ouvertes) ; les périodes
partiellement couvertes et les lacunes ouvrent une nouvelle ligne. Les années
passées peuvent ainsi être facturées dans un seul brouillon. Pour les
paiements anticipés, la boîte de dialogue propose une date butoir (échues
seulement, +1, +2, +3 ans) : la planification crée les périodes jusqu'à cette
date, et le traitement quotidien conserve les périodes portant un cachet de
brouillon. En cas de facturation externe, le traitement récurrent peut créer
chaque mois, le premier, un transfert par destinataire vers son logiciel de
comptabilité (interrupteur et délai dans la boîte de dialogue
« Facturation ») ; les abonnements avec un modèle récurrent actif en sont
exclus.
