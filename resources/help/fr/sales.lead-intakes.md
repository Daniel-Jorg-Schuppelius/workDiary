---
title: "Dossier d'admission"
topic: sales.lead-intakes
version: 1
keywords:
    - Dossier d'admission
    - Admission
    - Acceptation de mandat
    - Contrôle des nouveaux clients
    - Évaluation initiale
    - Blanchiment
    - GwG
    - Identification
    - Bénéficiaires effectifs
    - Personne politiquement exposée
    - Niveau de risque
    - Copie de la pièce d'identité
    - Paquet de contrôle
    - Second contrôle
audience: []
modules:
    - module.vertrieb
related:
    - sales.leads
    - customers.deadlines
    - forms.fill
---

Le **dossier d'admission** est rattaché au lead et accompagne un nouveau
client de la demande à la mise en place : formulaire d'entretien, pièces,
contrôles exigés par le profil sectoriel et décision. Ce n'est qu'ensuite que
le lead devient client, avec l'étendue des prestations et les caractéristiques
d'échéances reprises du dossier.

## Ouvrir le dossier

Le lead affiche le panneau **Dossier d'admission** avec « Ouvrir l'admission ».
Le dossier reprend les paquets de contrôle activés à ce moment et les fige :
si un paquet change plus tard, le dossier reste tel quel.

## Étapes

**Demande → Entretien → Pièces → Décision → Mise en place.** Les premières
étapes se choisissent librement ; la mise en place découle de l'acceptation et
se termine avec la conversion du lead (« mis en place »). La tuile
**Admissions** du tableau de bord compte les dossiers ouverts par étape.

## Le formulaire

C'est un long formulaire avec sections, progression et sommaire. Chaque champ
est enregistré en le quittant ; le **mode entretien** agrandit les zones
tactiles pour la tablette pendant l'entretien. La partie de base demande la
demande, les prestations souhaitées, le début et des notes ; viennent ensuite
les **caractéristiques d'échéances** des règles activées et, par paquet, ses
champs et sa **liste de pièces** avec cases et fichiers.

## Paquets de contrôle

| Paquet | Profil | Contenu |
| --- | --- | --- |
| Loi sur le blanchiment (GwG) | Conseil fiscal (activé) | Identification, vérification par document, bénéficiaires effectifs, registre de transparence, personne politiquement exposée, objet, facteurs de risque, niveau de risque |
| Admission en soins | Soins à domicile (activé) | Niveau de dépendance et décision, type de prestation, procurations et tutelle, directives anticipées, contacts d'urgence, médecin traitant, contrat |
| Nouveau client B2B | Informatique, nettoyage, artisanat (activable) | Numéro de TVA (contrôle formel), rapport de solvabilité, délai de paiement, prépaiement |

Les paquets s'activent sous **Admissions → Paramètres** (droit « Décider des
admissions »). On y règle aussi la **mise à jour par niveau de risque** (par
défaut annuelle) et le **second contrôle**.

## Décision

Accepter ou refuser, toujours avec un motif et uniquement avec le droit
« Décider des admissions ». L'acceptation vérifie toutes les données
obligatoires et les règles du paquet : pour une personne politiquement
exposée, le niveau de risque doit être élevé, « faible » et « élevé » exigent
une justification, et qui a vérifié avec une pièce d'identité ou un extrait
du registre en classe la copie. Avec le **second contrôle**, une autre
personne confirme l'acceptation ou la renvoie avec un motif. L'acceptation
avec le paquet GwG crée la note « Identification et note de risque » sur le
lead.

**Conversion :** si un paquet avec obligation de décision est actif (GwG,
admission en soins), le lead ne devient client qu'après l'acceptation. Sans un
tel paquet, la conversion reste inchangée. À la conversion, le client reprend
les prestations souhaitées comme étendue des prestations, les
caractéristiques d'échéances, le niveau de dépendance ainsi que le numéro de
TVA et le délai de paiement à titre de proposition.

## Visibilité restreinte

Les données d'identité, la copie de la pièce et les données de santé ne sont
visibles et saisissables qu'avec leur propre droit — même qui voit le client
ne les voit pas autrement : ni dans le formulaire, ni dans les pièces, le PDF
ou la réponse au droit d'accès. Elles sont enregistrées chiffrées.

## Mise à jour et conservation

Après l'acceptation court le **délai de mise à jour** (GwG selon le niveau de
risque). Peu avant, une notification le rappelle et la file de travail montre
le dossier ; « Mettre à jour » crée une nouvelle **version**, l'ancienne reste
comme historique.

Le dossier GwG est conservé **cinq ans à compter de la fin de l'année civile**
au cours de laquelle la relation d'affaires prend fin (§ 8 al. 4 GwG). La fin
se saisit sur le client dans le panneau du dossier. Jusque-là, le dossier
survit à l'anonymisation du lead et à l'archivage du client ; ensuite,
l'analyse de conservation propose sa suppression. Les admissions refusées
courent à partir du refus. Les paquets sans règle propre disparaissent avec
l'anonymisation du lead.

## PDF

« PDF » produit le dossier en présentation papier : tableau d'en-tête,
sections, cases, chaque paquet sur sa propre page, pied de page avec date,
client et numéro de page. « Classer comme document » enregistre le PDF sur le
client ou le lead, toujours sans données d'identité ni de santé.
