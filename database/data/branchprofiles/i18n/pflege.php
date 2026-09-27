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
        'grundpflege' => ['en' => 'Basic care (SGB XI)', 'es' => 'Cuidados básicos (SGB XI)', 'fr' => 'Soins de base (SGB XI)', 'it' => 'Assistenza di base (SGB XI)'],
        'behandlungspflege' => ['en' => 'Medical care (SGB V)', 'es' => 'Cuidados médicos (SGB V)', 'fr' => 'Soins médicaux (SGB V)', 'it' => 'Assistenza sanitaria (SGB V)'],
        'hauswirtschaft' => ['en' => 'Domestic help', 'es' => 'Ayuda doméstica', 'fr' => 'Aide ménagère', 'it' => 'Aiuto domestico'],
        'betreuung' => ['en' => 'Support service (§45b)', 'es' => 'Servicio de acompañamiento (§45b)', 'fr' => 'Prestation d\'accompagnement (§45b)', 'it' => 'Servizio di assistenza (§45b)'],
        'beratungsbesuch' => ['en' => 'Advisory visit (§37.3)', 'es' => 'Visita de asesoramiento (§37.3)', 'fr' => 'Visite de conseil (§37.3)', 'it' => 'Visita di consulenza (§37.3)'],
        'erstbesuch' => ['en' => 'First visit / care assessment', 'es' => 'Primera visita / anamnesis', 'fr' => 'Première visite / anamnèse', 'it' => 'Prima visita / anamnesi'],
        'pflegevisite' => ['en' => 'Care review', 'es' => 'Visita de supervisión', 'fr' => 'Visite de supervision', 'it' => 'Visita di supervisione'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Queja / reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
    'activity' => [
        'koerperpflege' => ['en' => 'Personal hygiene', 'es' => 'Higiene personal', 'fr' => 'Soins corporels', 'it' => 'Igiene personale'],
        'medikamentengabe' => ['en' => 'Medication administration', 'es' => 'Administración de medicamentos', 'fr' => 'Administration de médicaments', 'it' => 'Somministrazione di farmaci'],
        'wundversorgung' => ['en' => 'Wound care', 'es' => 'Cura de heridas', 'fr' => 'Soins des plaies', 'it' => 'Medicazione'],
        'vitalzeichen' => ['en' => 'Vital signs check', 'es' => 'Control de constantes vitales', 'fr' => 'Contrôle des signes vitaux', 'it' => 'Controllo dei parametri vitali'],
        'injektion' => ['en' => 'Injection (s.c./i.m.)', 'es' => 'Inyección (s.c./i.m.)', 'fr' => 'Injection (s.c./i.m.)', 'it' => 'Iniezione (s.c./i.m.)'],
        'mobilisation' => ['en' => 'Mobilisation', 'es' => 'Movilización', 'fr' => 'Mobilisation', 'it' => 'Mobilizzazione'],
        'nahrungsaufnahme' => ['en' => 'Help with eating', 'es' => 'Ayuda para comer', 'fr' => 'Aide à l\'alimentation', 'it' => 'Aiuto nell\'alimentazione'],
        'prophylaxe' => ['en' => 'Prophylaxis (pressure ulcers/thrombosis)', 'es' => 'Profilaxis (úlceras por presión/trombosis)', 'fr' => 'Prophylaxie (escarres/thrombose)', 'it' => 'Profilassi (decubiti/trombosi)'],
        'anleiten' => ['en' => 'Guidance for relatives', 'es' => 'Orientación a familiares', 'fr' => 'Accompagnement des proches', 'it' => 'Istruzione dei familiari'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'sturz' => ['en' => 'Fall', 'es' => 'Caída', 'fr' => 'Chute', 'it' => 'Caduta'],
        'medikationsfehler' => ['en' => 'Medication error', 'es' => 'Error de medicación', 'fr' => 'Erreur de médication', 'it' => 'Errore terapeutico'],
        'wundeVerschlechtert' => ['en' => 'Wound deterioration', 'es' => 'Empeoramiento de la herida', 'fr' => 'Aggravation de la plaie', 'it' => 'Peggioramento della ferita'],
        'klientNichtAngetroffen' => ['en' => 'Client not at home', 'es' => 'Cliente no localizado', 'fr' => 'Client absent', 'it' => 'Cliente non trovato'],
        'dokumentationsluecke' => ['en' => 'Documentation gap', 'es' => 'Laguna de documentación', 'fr' => 'Lacune de documentation', 'it' => 'Lacuna nella documentazione'],
        'hygienemangel' => ['en' => 'Hygiene deficiency', 'es' => 'Deficiencia de higiene', 'fr' => 'Défaut d\'hygiène', 'it' => 'Carenza igienica'],
        'terminverzug' => ['en' => 'Schedule delay', 'es' => 'Retraso en el plazo', 'fr' => 'Retard de planning', 'it' => 'Ritardo sui tempi'],
        'kommunikationsproblem' => ['en' => 'Communication problem', 'es' => 'Problema de comunicación', 'fr' => 'Problème de communication', 'it' => 'Problema di comunicazione'],
    ],
    'root_cause' => [
        'personalengpass' => ['en' => 'Staff shortage', 'es' => 'Falta de personal', 'fr' => 'Manque de personnel', 'it' => 'Carenza di personale'],
        'zugang' => ['en' => 'Apartment access / key', 'es' => 'Acceso a la vivienda / llave', 'fr' => 'Accès au logement / clé', 'it' => 'Accesso all\'abitazione / chiave'],
        'fehlendeAnordnung' => ['en' => 'Missing medical order', 'es' => 'Falta la prescripción médica', 'fr' => 'Prescription médicale manquante', 'it' => 'Prescrizione medica mancante'],
        'materialmangel' => ['en' => 'Material shortage', 'es' => 'Falta de material', 'fr' => 'Pénurie de matériel', 'it' => 'Carenza di materiale'],
        'tourenplanung' => ['en' => 'Tour planning', 'es' => 'Planificación de rutas', 'fr' => 'Planification des tournées', 'it' => 'Pianificazione dei giri'],
        'klientVerweigerung' => ['en' => 'Refused by client', 'es' => 'Rechazo del cliente', 'fr' => 'Refus du client', 'it' => 'Rifiuto del cliente'],
        'angehoerige' => ['en' => 'Relatives', 'es' => 'Familiares', 'fr' => 'Proches', 'it' => 'Familiari'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'abgelehnt' => ['en' => 'Refused by client', 'es' => 'Rechazado por el cliente', 'fr' => 'Refusé par le client', 'it' => 'Rifiutato dal cliente'],
        'nichtAngetroffen' => ['en' => 'Not found at home', 'es' => 'No localizado', 'fr' => 'Absent', 'it' => 'Non trovato'],
        'arztInformiert' => ['en' => 'Doctor informed', 'es' => 'Médico informado', 'fr' => 'Médecin informé', 'it' => 'Medico informato'],
        'angehoerigeInformiert' => ['en' => 'Relatives informed', 'es' => 'Familiares informados', 'fr' => 'Proches informés', 'it' => 'Familiari informati'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
];
