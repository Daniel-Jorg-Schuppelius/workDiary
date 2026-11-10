---
title: "Qualifications"
topic: catalog.qualifications
version: 3
keywords:
    - compétences
    - savoir-faire
    - certificats
    - certifications
    - formations
    - habilitations
    - expertise
audience: []
related:
    - planning.shifts
    - catalog.activity-categories
    - glossary.core
---

Les qualifications recensent les compétences des collaborateurs
(certificats, habilitations, formations) ; chaque qualification possède un
nom unique et, en option, une abréviation et une description. Créez le nom
et éventuellement l'abréviation ; la vue d'ensemble indique combien de
personnes sont associées à chaque qualification. Désactivez les entrées
qui ne servent plus au lieu de les supprimer afin de conserver
l'historique des affectations.

## Qualifications obligatoires : indication ou blocage

Vous définissez les qualifications obligatoires sur le type de poste et sur
l’intervention (zone « Qualifications » de la régulation). Une qualification
ne compte que si elle est valable le jour de l’intervention : si le jour se
situe avant « valable du » ou après « valable jusqu’au » de l’attribution,
elle est considérée comme manquante.

Par défaut, le planning, l’intervention et la matrice des qualifications se
contentent de signaler le manque. Si l’organisation choisit **Bloquer
l’affectation** sous « Organisation et paramètres », ces actions refusent
l’affectation :

- créer un poste ou l’attribuer à une autre personne, un autre type de poste
  ou un autre jour – même si le contrôle de conformité est contourné,
- valider un échange de poste (dans les deux sens),
- import de postes ; le roulement laisse le service libre et le compte comme
  ignoré,
- affecter une intervention à une personne, ajouter une intervention à une
  tournée et accepter une proposition de créneau.

Les propositions d’affectation n’indiquent plus les personnes bloquées. Les
affectations existantes restent en place ; une telle intervention ne peut
être confirmée dans la régulation qu’avec une justification, qui est
consignée.

Si vous modifiez les qualifications obligatoires d’une intervention déjà
attribuée à une personne, WorkDiary vérifie aussitôt cette personne : en mode blocage, la
modification est refusée tant qu’une des qualifications lui manque ; en mode
indication, elle est enregistrée et la lacune est signalée.
