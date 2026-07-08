---
title: "Export des temps & transfert paie"
topic: exports.payroll
version: 1
audience: []
related:
    - admin.surcharge-rules
    - finance.transfers
    - glossary.core
---

L'export des temps transmet à la paie les données mensuelles approuvées,
de façon traçable et reproductible avec piste d'audit. Le flux typique :
validation mensuelle (soumission par le collaborateur, approbation par
le responsable, verrouillage après export), création de l'export
(« en préparation » → « prêt » → « transmis » ou « refusé ») et choix du
profil — actuellement seul l'**export CSV générique** est disponible en
production, le **profil DATEV** n'étant qu'une préparation proche de
LODAS et non un format certifié. L'export couvre heures normales,
majorations nuit/dimanche/jours fériés, astreinte, congés, maladie et
temps de trajet facturable. Un export n'est possible que si toutes les
validations mensuelles concernées sont approuvées ou verrouillées ;
chaque export porte un **hachage SHA-256** reproductible et toute
correction crée un nouvel export, l'ancien étant marqué « remplacé »
sans écrasement silencieux.
