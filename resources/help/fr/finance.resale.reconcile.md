---
title: "Rapprochement par destinataire"
topic: finance.resale.reconcile
version: 1
keywords:
    - rapprochement
    - destinataire de facture
    - liste de travail
    - montant ouvert
    - jamais facturé
    - facture à un autre client
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

Le **rapprochement** met en regard, par destinataire de facture, les périodes
échues et les lignes de licence de ses factures. La liste indique par
destinataire le montant ouvert, les constats de modèles et s'il est terminé ;
le filtre par défaut masque les destinataires terminés, et « Traiter le
destinataire suivant » ouvre le premier classeur ouvert.

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

**Début de facturation du compte portail :** lorsqu'un sous-utilisateur
facture ses domaines via le portail du fournisseur, le portail ne facture que
depuis l'existence de ce sous-compte. Le compte de licences, le classeur, la
vue d'ensemble et la page d'abonnement du domaine signalent les années de
licence ouvertes qui ont commencé avant, avec le début du compte. « Reprendre
le début de facturation » les décide en une étape : **Ne plus facturer
(renonciation)** les clôture, avec le début du compte et votre motif.
**Facturer vous-même** les laisse ouvertes et les intègre à la proposition de
facture du registre, bien que le domaine soit sinon facturé via le portail.
Les années de licence commencées après le début du compte et celles
partiellement facturées restent inchangées ; chaque période peut ensuite être
rouverte ou abandonnée individuellement.

**Liens à vérifier :** Au-dessus du tableau figurent les liens qui ne peuvent
pas être corrects : une ligne couvre plus de mois de licence qu'elle n'en
contient (surréservée), ou sa période de prestation ne correspond pas à la
période (mal placée). Un lien mal placé peut être déplacé vers la période
proposée ou supprimé, un lien surréservé seulement supprimé.

**Réordonner le produit :** Si les liens d'un produit sont mélangés, par
exemple décalés d'un an, le bouton dans l'en-tête du produit supprime tous les
liens de facture de ce produit pour ce destinataire, et la proposition
redistribue les factures. Les liens confirmés et manuels sont perdus.
Renonciations, contestations et avoirs restent.

**Facture adressée à un autre client :** Si un autre client a une ligne libre
du même produit avec exactement la période de prestation de la période
ouverte, elle apparaît à la période, même pour une seule licence. De là, vous
pouvez changer le titulaire ou céder la période.

**Ligne excédentaire :** Si toutes les périodes d'un produit sont couvertes et
qu'une facture reste libre, elle apparaît dans le tableau comme « facturé deux fois » ou « facturé
après la fin ». Soit elle a été facturée deux fois (avoir), soit l'abonnement manque
dans le registre (créer l'abonnement), soit la ligne n'a pas sa place ici
(ignorer). Si vous créez l'abonnement à partir de la ligne et qu'un abonnement
du même produit s'est terminé peu avant, la boîte de dialogue le propose comme
prédécesseur.

**Trop, écart, manque — en euros :** la liste de rapprochement et l’aperçu
indiquent par destinataire ce qui a été trop facturé (ligne dans une année de
licence déjà couverte : « facturé deux fois » ; ligne après la dernière année
de licence du produit : « facturé après la fin ») et ce qui a été facturé
différemment. Ce qui manque figure comme montant ouvert. Les lignes « hors
durée » ou « sans abonnement » ne sont pas comptées : il manque généralement
un abonnement dans le registre.

**Lien divergent :** si vous affectez une ligne dont l’article n’est pas celui
de l’abonnement ou dont le montant s’écarte de plus de 5 % (au moins 1 €) du
montant prévu, la boîte de dialogue demande une justification. Le lien reste
possible ; la justification est enregistrée sur la période. Les liens
existants sans justification apparaissent parmi les constats comme « Autre
article » ou « Montant différent » et peuvent y être justifiés ou supprimés.

**Vue des licences et feuille de correction :** l’espace de travail affiche en
haut produit × année de licence chez tous les fournisseurs du client. La
feuille de correction en dessous indique les factures à créditer, les années
de licence à facturer, les écarts et le solde ; elle se télécharge en XLSX. Un
avoir qui se réfère à la facture retire ses lignes de la liste.
