---
title: "Boîte de rapprochement"
topic: admin.integration-inbox
version: 3
keywords:
    - boîte des intégrations
    - conflits import
    - correspondance
    - associer des enregistrements
    - enregistrements non associés
    - conflit de champ
    - conflit de synchronisation
    - numéro inconnu
    - appareils inconnus
    - demande sur facture
audience: []
related:
    - admin.integrations
    - admin.import
    - contacts.manage
    - finance.open-times
---

La boîte de rapprochement rassemble **les imports reçus qui n'ont pas pu être
rapprochés automatiquement** – issus des systèmes connectés, de l'import CSV
et de la réception d'e-mails. Rien n'est créé à l'aveugle : vous décidez pour
chaque entrée.

**Trois cas :**

- **Non rapproché** – aucun enregistrement existant ne correspond.
- **Ambigu** – plusieurs enregistrements sont possibles.
- **Conflit de champ** – l'enregistrement est connu, mais l'état local et
  l'état distant se contredisent. Les deux états sont affichés côte à côte.

**Numéro déjà attribué :** un cas particulier de conflit de champ. Un
logiciel de comptabilité a attribué à un client ou fournisseur un numéro
qu'un autre enregistrement porte déjà dans WorkDiary. Le cas nomme le
détenteur. Modifiez-y le numéro, puis choisissez **Reprendre le distant** ;
la synchronisation suivante le reprend aussi d'elle-même.

**Décider :** vous rattachez une entrée à un enregistrement existant, vous la
créez ou vous la rejetez. En cas de conflit de champ, vous choisissez de
conserver l'état local ou d'appliquer l'état distant. La décision reste
visible sur l'entrée (Rapproché, Créé, Conserver le local, Distant appliqué,
Rejeté) ; le filtre de statut permet de retrouver les entrées traitées.

**Groupes :** les entrées liées apparaissent en haut sous forme de groupe et
se décident en une seule fois :

- **Temps importés** d'un projet inconnu : choisir ou nommer le client,
  éventuellement le client final, et le projet, puis comptabiliser le groupe.
- **Appareils inconnus** de la télémaintenance : les lier à un appareil et
  comptabiliser.
- **Numéros de téléphone inconnus :** les attribuer à un client ;
  « Mémoriser durablement le numéro » vaut pour les appels futurs, un numéro
  partagé seulement pour cet appel.
- **Utilisateurs inconnus** d'un import de temps : les attribuer à un
  utilisateur.
- **Rendez-vous récurrents** issus d'agendas : tout créer comme rendez-vous.
- **Commandes** du catalogue B2B : comptabiliser comme commande.

« Afficher les entrées » déplie le contenu d'un groupe. Un groupe peut aussi
être ignoré en bloc.

**Filtres :** les onglets séparent par source ; le nombre indique les entrées
ouvertes. Vous filtrez en outre par statut, cas et entité. Les listes de
choix très longues sont limitées à 1000 entrées – le champ de recherche en
haut à droite restreint la sélection.

**Gérer les affectations :** WorkDiary mémorise une affectation effectuée ;
les imports ultérieurs du même enregistrement passent alors sans question.
Sous « Gérer les affectations », vous consultez et supprimez ces liens.

**E-mails :** Une entrée issue de la réception des e-mails peut être reprise
comme note de communication chez le client, comme ticket de service, dans
l'archivage de documents ou comme facture entrante. Si WorkDiary a reconnu
un numéro de facture ou de document sans affecter l'e-mail de lui-même
(plusieurs numéros, expéditeur inconnu), l'entrée affiche les candidats ;
**Enregistrer comme demande sur facture** reprend l'e-mail avec le numéro
saisi comme demande concernant la facture ou le document du logiciel de
comptabilité. **Enregistrer comme note sur la commande** rattache l'e-mail à
une commande ouverte, par exemple lorsqu'un client répond à la facture mais
vise la commande.

La page est ouverte aux personnes autorisées à gérer la facturation.
