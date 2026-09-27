<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : steuerberater.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „steuerberater" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'fibu' => ['en' => 'Bookkeeping', 'es' => 'Contabilidad financiera', 'fr' => 'Comptabilité', 'it' => 'Contabilità'],
        'lohn' => ['en' => 'Payroll', 'es' => 'Nóminas', 'fr' => 'Paie', 'it' => 'Paghe'],
        'abschluss' => ['en' => 'Annual accounts', 'es' => 'Cierre anual', 'fr' => 'Clôture annuelle', 'it' => 'Bilancio annuale'],
        'steuererklaerung' => ['en' => 'Tax return', 'es' => 'Declaración de impuestos', 'fr' => 'Déclaration fiscale', 'it' => 'Dichiarazione dei redditi'],
        'voranmeldung' => ['en' => 'VAT advance return', 'es' => 'Declaración previa de IVA', 'fr' => 'Déclaration de TVA', 'it' => 'Dichiarazione IVA periodica'],
        'beratung' => ['en' => 'Consulting', 'es' => 'Asesoramiento', 'fr' => 'Conseil', 'it' => 'Consulenza'],
        'fristensache' => ['en' => 'Deadline matter', 'es' => 'Asunto con plazo', 'fr' => 'Dossier à échéance', 'it' => 'Pratica con scadenza'],
        'kommunikation' => ['en' => 'Communication', 'es' => 'Comunicación', 'fr' => 'Communication', 'it' => 'Comunicazione'],
        'pruefung' => ['en' => 'Audit', 'es' => 'Inspección', 'fr' => 'Contrôle', 'it' => 'Verifica'],
    ],
    'activity' => [
        'belegerfassung' => ['en' => 'Receipt entry', 'es' => 'Registro de comprobantes', 'fr' => 'Saisie des justificatifs', 'it' => 'Registrazione documenti'],
        'kontieren' => ['en' => 'Account assignment', 'es' => 'Contabilizar', 'fr' => 'Imputer', 'it' => 'Imputare'],
        'abstimmen' => ['en' => 'Coordinate', 'es' => 'Coordinar', 'fr' => 'Coordonner', 'it' => 'Coordinare'],
        'erstellen' => ['en' => 'Create', 'es' => 'Crear', 'fr' => 'Créer', 'it' => 'Creare'],
        'pruefen' => ['en' => 'Check', 'es' => 'Comprobar', 'fr' => 'Vérifier', 'it' => 'Verificare'],
        'einreichen' => ['en' => 'Submit', 'es' => 'Presentar', 'fr' => 'Déposer', 'it' => 'Presentare'],
        'beraten' => ['en' => 'Advise', 'es' => 'Asesorar', 'fr' => 'Conseiller', 'it' => 'Consigliare'],
        'korrespondenz' => ['en' => 'Correspondence', 'es' => 'Correspondencia', 'fr' => 'Correspondance', 'it' => 'Corrispondenza'],
        'archivieren' => ['en' => 'Archive', 'es' => 'Archivar', 'fr' => 'Archiver', 'it' => 'Archiviare'],
    ],
    'defect_type' => [
        'belegFehlt' => ['en' => 'Receipt missing', 'es' => 'Falta el comprobante', 'fr' => 'Justificatif manquant', 'it' => 'Documento mancante'],
        'belegUnleserlich' => ['en' => 'Receipt illegible', 'es' => 'Comprobante ilegible', 'fr' => 'Justificatif illisible', 'it' => 'Documento illeggibile'],
        'kontierungUnklar' => ['en' => 'Account assignment unclear', 'es' => 'Imputación poco clara', 'fr' => 'Imputation incertaine', 'it' => 'Imputazione non chiara'],
        'mandantNichtErreichbar' => ['en' => 'Client not reachable', 'es' => 'Cliente no localizable', 'fr' => 'Mandant injoignable', 'it' => 'Cliente non raggiungibile'],
        'fristKritisch' => ['en' => 'Deadline critical', 'es' => 'Plazo crítico', 'fr' => 'Délai critique', 'it' => 'Scadenza critica'],
        'datenInkonsistent' => ['en' => 'Data inconsistent', 'es' => 'Datos incoherentes', 'fr' => 'Données incohérentes', 'it' => 'Dati incoerenti'],
    ],
    'root_cause' => [
        'mandantSpaet' => ['en' => 'Client late', 'es' => 'Cliente con retraso', 'fr' => 'Mandant en retard', 'it' => 'Cliente in ritardo'],
        'datenLuecke' => ['en' => 'Data gap', 'es' => 'Laguna de datos', 'fr' => 'Lacune de données', 'it' => 'Lacuna nei dati'],
        'behoerdenanfrage' => ['en' => 'Authority request', 'es' => 'Solicitud de la autoridad', 'fr' => 'Demande administrative', 'it' => 'Richiesta dell\'autorità'],
        'gesetzAenderung' => ['en' => 'Change in legislation', 'es' => 'Cambio legislativo', 'fr' => 'Modification législative', 'it' => 'Modifica normativa'],
        'internerFehler' => ['en' => 'Internal error', 'es' => 'Error interno', 'fr' => 'Erreur interne', 'it' => 'Errore interno'],
        'systemausfall' => ['en' => 'System failure', 'es' => 'Caída del sistema', 'fr' => 'Panne système', 'it' => 'Guasto del sistema'],
    ],
    'result' => [
        'eingereicht' => ['en' => 'Submitted', 'es' => 'Presentado', 'fr' => 'Déposé', 'it' => 'Presentato'],
        'freigegebenDurchMandant' => ['en' => 'Approved by client', 'es' => 'Aprobado por el cliente', 'fr' => 'Validé par le mandant', 'it' => 'Approvato dal cliente'],
        'ruecklaufBehoerde' => ['en' => 'Authority feedback', 'es' => 'Respuesta de la autoridad', 'fr' => 'Retour de l\'autorité', 'it' => 'Riscontro dell\'autorità'],
        'vertagt' => ['en' => 'Adjourned', 'es' => 'Aplazado', 'fr' => 'Ajourné', 'it' => 'Rinviato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
        'archiviert' => ['en' => 'Archived', 'es' => 'Archivado', 'fr' => 'Archivé', 'it' => 'Archiviato'],
    ],
    'product_group' => [
        'einkommensteuer' => ['en' => 'Income tax', 'es' => 'Impuesto sobre la renta', 'fr' => 'Impôt sur le revenu', 'it' => 'Imposta sul reddito'],
        'koerperschaftsteuer' => ['en' => 'Corporate income tax', 'es' => 'Impuesto de sociedades', 'fr' => 'Impôt sur les sociétés', 'it' => 'Imposta sulle società'],
        'gewerbesteuer' => ['en' => 'Trade tax', 'es' => 'Impuesto sobre actividades económicas', 'fr' => 'Taxe professionnelle', 'it' => 'Imposta sulle attività produttive'],
        'umsatzsteuer' => ['en' => 'VAT', 'es' => 'IVA', 'fr' => 'TVA', 'it' => 'IVA'],
        'lohnsteuer' => ['en' => 'Wage tax', 'es' => 'Retención salarial', 'fr' => 'Impôt sur les salaires', 'it' => 'Ritenuta sui salari'],
        'sozialversicherung' => ['en' => 'Social security', 'es' => 'Seguridad social', 'fr' => 'Sécurité sociale', 'it' => 'Previdenza sociale'],
        'jahresabschluss' => ['en' => 'Annual financial statements', 'es' => 'Cierre anual', 'fr' => 'Comptes annuels', 'it' => 'Bilancio d\'esercizio'],
        'fibu' => ['en' => 'Financial accounting', 'es' => 'Contabilidad', 'fr' => 'Comptabilité', 'it' => 'Contabilità'],
        'lohn' => ['en' => 'Payroll', 'es' => 'Nómina', 'fr' => 'Paie', 'it' => 'Paghe'],
    ],
];
