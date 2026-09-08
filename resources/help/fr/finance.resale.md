---
title: "Abonnements & licences"
topic: finance.resale
version: 1
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
retirées.
