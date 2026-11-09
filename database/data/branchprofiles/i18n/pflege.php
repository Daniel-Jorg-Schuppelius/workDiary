<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pflege.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „pflege" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'tourEinsatz' => ['en' => 'Visit on the round', 'es' => 'Visita en la ruta', 'fr' => 'Intervention en tournée', 'it' => 'Intervento nel giro'],
        'grundpflege' => ['en' => 'Basic care (SGB XI)', 'es' => 'Cuidados básicos (SGB XI)', 'fr' => 'Soins de base (SGB XI)', 'it' => 'Assistenza di base (SGB XI)'],
        'behandlungspflege' => ['en' => 'Medical care (SGB V)', 'es' => 'Cuidados médicos (SGB V)', 'fr' => 'Soins médicaux (SGB V)', 'it' => 'Assistenza sanitaria (SGB V)'],
        'betreuung' => ['en' => 'Support service (§45b)', 'es' => 'Servicio de acompañamiento (§45b)', 'fr' => 'Prestation d\'accompagnement (§45b)', 'it' => 'Servizio di assistenza (§45b)'],
        'hauswirtschaft' => ['en' => 'Domestic help', 'es' => 'Ayuda doméstica', 'fr' => 'Aide ménagère', 'it' => 'Aiuto domestico'],
        'beratungsbesuch' => ['en' => 'Advisory visit (§37.3)', 'es' => 'Visita de asesoramiento (§37.3)', 'fr' => 'Visite de conseil (§37.3)', 'it' => 'Visita di consulenza (§37.3)'],
        'pflegevisite' => ['en' => 'Care review', 'es' => 'Visita de supervisión', 'fr' => 'Visite de supervision', 'it' => 'Visita di supervisione'],
        'vorfall' => ['en' => 'Incident', 'es' => 'Incidente', 'fr' => 'Incident', 'it' => 'Incidente'],
        'qualitaetspruefung' => ['en' => 'Quality audit (Medical Service)', 'es' => 'Auditoría de calidad (Servicio Médico)', 'fr' => 'Contrôle qualité (Service médical)', 'it' => 'Verifica della qualità (Servizio medico)'],
        'erstbesuch' => ['en' => 'First visit / care assessment', 'es' => 'Primera visita / anamnesis', 'fr' => 'Première visite / anamnèse', 'it' => 'Prima visita / anamnesi'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Queja / reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
    'activity' => [
        'koerperpflege' => ['en' => 'Personal hygiene', 'es' => 'Higiene personal', 'fr' => 'Soins corporels', 'it' => 'Igiene personale'],
        'mobilisation' => ['en' => 'Mobilisation', 'es' => 'Movilización', 'fr' => 'Mobilisation', 'it' => 'Mobilizzazione'],
        'lagerung' => ['en' => 'Positioning', 'es' => 'Cambios posturales', 'fr' => 'Positionnement', 'it' => 'Posizionamento'],
        'nahrungsgabe' => ['en' => 'Feeding assistance', 'es' => 'Ayuda en la alimentación', 'fr' => 'Aide à l\'alimentation', 'it' => 'Aiuto nell\'alimentazione'],
        'medikamentenstellen' => ['en' => 'Preparing medication', 'es' => 'Preparación de medicamentos', 'fr' => 'Préparation des médicaments', 'it' => 'Preparazione dei farmaci'],
        'medikamentengabe' => ['en' => 'Medication administration', 'es' => 'Administración de medicamentos', 'fr' => 'Administration de médicaments', 'it' => 'Somministrazione di farmaci'],
        'injektion' => ['en' => 'Injection (s.c./i.m.)', 'es' => 'Inyección (s.c./i.m.)', 'fr' => 'Injection (s.c./i.m.)', 'it' => 'Iniezione (s.c./i.m.)'],
        'wundversorgung' => ['en' => 'Wound care', 'es' => 'Cura de heridas', 'fr' => 'Soins des plaies', 'it' => 'Medicazione'],
        'blutzucker' => ['en' => 'Blood glucose measurement', 'es' => 'Medición de glucemia', 'fr' => 'Mesure de la glycémie', 'it' => 'Misurazione della glicemia'],
        'vitalwerte' => ['en' => 'Vital signs', 'es' => 'Constantes vitales', 'fr' => 'Signes vitaux', 'it' => 'Parametri vitali'],
        'dokumentation' => ['en' => 'Documentation', 'es' => 'Documentación', 'fr' => 'Documentation', 'it' => 'Documentazione'],
        'anleitung' => ['en' => 'Guidance for relatives', 'es' => 'Orientación a familiares', 'fr' => 'Accompagnement des proches', 'it' => 'Istruzione dei familiari'],
        'hauswirtschaftlich' => ['en' => 'Household assistance', 'es' => 'Ayuda doméstica', 'fr' => 'Aide domestique', 'it' => 'Aiuto domestico'],
        'prophylaxe' => ['en' => 'Prophylaxis (pressure ulcers/thrombosis)', 'es' => 'Profilaxis (úlceras por presión/trombosis)', 'fr' => 'Prophylaxie (escarres/thrombose)', 'it' => 'Profilassi (decubiti/trombosi)'],
    ],
    'defect_type' => [
        'hautlaesion' => ['en' => 'Skin lesion', 'es' => 'Lesión cutánea', 'fr' => 'Lésion cutanée', 'it' => 'Lesione cutanea'],
        'dekubitusVerdacht' => ['en' => 'Suspected pressure ulcer', 'es' => 'Sospecha de úlcera por presión', 'fr' => 'Suspicion d\'escarre', 'it' => 'Sospetto decubito'],
        'sturz' => ['en' => 'Fall', 'es' => 'Caída', 'fr' => 'Chute', 'it' => 'Caduta'],
        'schmerz' => ['en' => 'Pain', 'es' => 'Dolor', 'fr' => 'Douleur', 'it' => 'Dolore'],
        'infektVerdacht' => ['en' => 'Suspected infection', 'es' => 'Sospecha de infección', 'fr' => 'Suspicion d\'infection', 'it' => 'Sospetta infezione'],
        'medikamentenproblem' => ['en' => 'Medication problem', 'es' => 'Problema con la medicación', 'fr' => 'Problème de médication', 'it' => 'Problema con i farmaci'],
        'mangelernaehrung' => ['en' => 'Malnutrition', 'es' => 'Desnutrición', 'fr' => 'Dénutrition', 'it' => 'Malnutrizione'],
        'dehydration' => ['en' => 'Dehydration', 'es' => 'Deshidratación', 'fr' => 'Déshydratation', 'it' => 'Disidratazione'],
        'kommunikation' => ['en' => 'Communication problem', 'es' => 'Problema de comunicación', 'fr' => 'Problème de communication', 'it' => 'Problema di comunicazione'],
        'wundeVerschlechtert' => ['en' => 'Wound deterioration', 'es' => 'Empeoramiento de la herida', 'fr' => 'Aggravation de la plaie', 'it' => 'Peggioramento della ferita'],
        'klientNichtAngetroffen' => ['en' => 'Client not at home', 'es' => 'Cliente no localizado', 'fr' => 'Client absent', 'it' => 'Cliente non trovato'],
        'dokumentationsluecke' => ['en' => 'Documentation gap', 'es' => 'Laguna de documentación', 'fr' => 'Lacune de documentation', 'it' => 'Lacuna nella documentazione'],
        'hygienemangel' => ['en' => 'Hygiene deficiency', 'es' => 'Deficiencia de higiene', 'fr' => 'Défaut d\'hygiène', 'it' => 'Carenza igienica'],
        'terminverzug' => ['en' => 'Schedule delay', 'es' => 'Retraso en el plazo', 'fr' => 'Retard de planning', 'it' => 'Ritardo sui tempi'],
    ],
    'root_cause' => [
        'grunderkrankung' => ['en' => 'Underlying condition', 'es' => 'Enfermedad de base', 'fr' => 'Maladie sous-jacente', 'it' => 'Patologia di base'],
        'neueMedikation' => ['en' => 'New medication', 'es' => 'Nueva medicación', 'fr' => 'Nouveau traitement', 'it' => 'Nuova terapia'],
        'immobilitaet' => ['en' => 'Immobility', 'es' => 'Inmovilidad', 'fr' => 'Immobilité', 'it' => 'Immobilità'],
        'sturzAnamnese' => ['en' => 'History of falls', 'es' => 'Antecedentes de caídas', 'fr' => 'Antécédents de chutes', 'it' => 'Anamnesi di cadute'],
        'externerArzt' => ['en' => 'External doctor', 'es' => 'Médico externo', 'fr' => 'Médecin externe', 'it' => 'Medico esterno'],
        'angehoerige' => ['en' => 'Relatives', 'es' => 'Familiares', 'fr' => 'Proches', 'it' => 'Familiari'],
        'geraeteausfall' => ['en' => 'Device failure', 'es' => 'Fallo del equipo', 'fr' => 'Panne d\'appareil', 'it' => 'Guasto del dispositivo'],
        'personalengpass' => ['en' => 'Staff shortage', 'es' => 'Falta de personal', 'fr' => 'Manque de personnel', 'it' => 'Carenza di personale'],
        'zugang' => ['en' => 'Apartment access / key', 'es' => 'Acceso a la vivienda / llave', 'fr' => 'Accès au logement / clé', 'it' => 'Accesso all\'abitazione / chiave'],
        'fehlendeAnordnung' => ['en' => 'Missing medical order', 'es' => 'Falta la prescripción médica', 'fr' => 'Prescription médicale manquante', 'it' => 'Prescrizione medica mancante'],
        'materialmangel' => ['en' => 'Material shortage', 'es' => 'Falta de material', 'fr' => 'Pénurie de matériel', 'it' => 'Carenza di materiale'],
        'tourenplanung' => ['en' => 'Tour planning', 'es' => 'Planificación de rutas', 'fr' => 'Planification des tournées', 'it' => 'Pianificazione dei giri'],
        'klientVerweigerung' => ['en' => 'Refused by client', 'es' => 'Rechazo del cliente', 'fr' => 'Refus du client', 'it' => 'Rifiuto del cliente'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'abgelehntVomKlienten' => ['en' => 'Refused by client', 'es' => 'Rechazado por el cliente', 'fr' => 'Refusé par le client', 'it' => 'Rifiutato dal cliente'],
        'nichtAngetroffen' => ['en' => 'Not found at home', 'es' => 'No localizado', 'fr' => 'Absent', 'it' => 'Non trovato'],
        'abgebrochen' => ['en' => 'Aborted', 'es' => 'Interrumpido', 'fr' => 'Interrompu', 'it' => 'Interrotto'],
        'eskaliertArzt' => ['en' => 'Escalated to doctor', 'es' => 'Escalado al médico', 'fr' => 'Transmis au médecin', 'it' => 'Segnalato al medico'],
        'eskaliertLeitung' => ['en' => 'Escalated to nursing management', 'es' => 'Escalado a la dirección de enfermería', 'fr' => 'Transmis à la direction des soins', 'it' => 'Segnalato alla direzione infermieristica'],
        'arztInformiert' => ['en' => 'Doctor informed', 'es' => 'Médico informado', 'fr' => 'Médecin informé', 'it' => 'Medico informato'],
        'angehoerigeInformiert' => ['en' => 'Relatives informed', 'es' => 'Familiares informados', 'fr' => 'Proches informés', 'it' => 'Familiari informati'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'wundauflage' => ['en' => 'Wound dressings', 'es' => 'Apósitos', 'fr' => 'Pansements', 'it' => 'Medicazioni'],
        'kompressionsstrumpf' => ['en' => 'Compression stockings', 'es' => 'Medias de compresión', 'fr' => 'Bas de contention', 'it' => 'Calze a compressione'],
        'inkontinenzmaterial' => ['en' => 'Incontinence products', 'es' => 'Material para incontinencia', 'fr' => 'Produits d\'incontinence', 'it' => 'Ausili per incontinenza'],
        'hilfsmittel' => ['en' => 'Care aids', 'es' => 'Ayudas técnicas', 'fr' => 'Aides techniques', 'it' => 'Ausili assistenziali'],
        'medikament' => ['en' => 'Medication', 'es' => 'Medicamentos', 'fr' => 'Médicaments', 'it' => 'Farmaci'],
        'pen' => ['en' => 'Pen (insulin etc.)', 'es' => 'Pluma (insulina, etc.)', 'fr' => 'Stylo (insuline, etc.)', 'it' => 'Penna (insulina ecc.)'],
        'sondennahrung' => ['en' => 'Tube feeding', 'es' => 'Nutrición enteral', 'fr' => 'Nutrition entérale', 'it' => 'Nutrizione enterale'],
        'sauerstoff' => ['en' => 'Oxygen', 'es' => 'Oxígeno', 'fr' => 'Oxygène', 'it' => 'Ossigeno'],
    ],
    'leistungsart' => [
        'sgbVBehandlungspflege' => ['en' => 'Medical care (SGB V)', 'es' => 'Cuidados médicos (SGB V)', 'fr' => 'Soins médicaux (SGB V)', 'it' => 'Assistenza sanitaria (SGB V)'],
        'sgbXIGrundpflege' => ['en' => 'Basic care (SGB XI)', 'es' => 'Cuidados básicos (SGB XI)', 'fr' => 'Soins de base (SGB XI)', 'it' => 'Assistenza di base (SGB XI)'],
        'sgbXIBetreuung' => ['en' => 'Support (SGB XI)', 'es' => 'Acompañamiento (SGB XI)', 'fr' => 'Accompagnement (SGB XI)', 'it' => 'Assistenza (SGB XI)'],
        'sgbXIHauswirtschaft' => ['en' => 'Domestic care (SGB XI)', 'es' => 'Atención doméstica (SGB XI)', 'fr' => 'Aide ménagère (SGB XI)', 'it' => 'Assistenza domestica (SGB XI)'],
        'sgbXVerhinderungspflege' => ['en' => 'Respite care', 'es' => 'Cuidados de relevo', 'fr' => 'Soins de répit', 'it' => 'Assistenza di sollievo'],
        'selbstzahler' => ['en' => 'Self-payer', 'es' => 'Pago particular', 'fr' => 'Payeur privé', 'it' => 'Pagante in proprio'],
    ],
    'pflegegrad' => [
        'pg1' => ['en' => 'Care level 1', 'es' => 'Grado de dependencia 1', 'fr' => 'Niveau de dépendance 1', 'it' => 'Grado di non autosufficienza 1'],
        'pg2' => ['en' => 'Care level 2', 'es' => 'Grado de dependencia 2', 'fr' => 'Niveau de dépendance 2', 'it' => 'Grado di non autosufficienza 2'],
        'pg3' => ['en' => 'Care level 3', 'es' => 'Grado de dependencia 3', 'fr' => 'Niveau de dépendance 3', 'it' => 'Grado di non autosufficienza 3'],
        'pg4' => ['en' => 'Care level 4', 'es' => 'Grado de dependencia 4', 'fr' => 'Niveau de dépendance 4', 'it' => 'Grado di non autosufficienza 4'],
        'pg5' => ['en' => 'Care level 5', 'es' => 'Grado de dependencia 5', 'fr' => 'Niveau de dépendance 5', 'it' => 'Grado di non autosufficienza 5'],
    ],
    'kommunikation' => [
        'uebergabe' => ['en' => 'Handover', 'es' => 'Relevo', 'fr' => 'Transmission', 'it' => 'Passaggio di consegne'],
        'telefonHausarzt' => ['en' => 'Call with GP', 'es' => 'Llamada al médico de cabecera', 'fr' => 'Appel au médecin traitant', 'it' => 'Telefonata al medico di base'],
        'telefonAngehoerige' => ['en' => 'Call with relatives', 'es' => 'Llamada a familiares', 'fr' => 'Appel aux proches', 'it' => 'Telefonata ai familiari'],
        'faxApotheke' => ['en' => 'Fax to pharmacy', 'es' => 'Fax a la farmacia', 'fr' => 'Fax à la pharmacie', 'it' => 'Fax alla farmacia'],
        'klinikbericht' => ['en' => 'Hospital report', 'es' => 'Informe hospitalario', 'fr' => 'Compte rendu hospitalier', 'it' => 'Referto ospedaliero'],
        'mdkAnfrage' => ['en' => 'Medical Service request', 'es' => 'Solicitud del Servicio Médico', 'fr' => 'Demande du Service médical', 'it' => 'Richiesta del Servizio medico'],
    ],
];
