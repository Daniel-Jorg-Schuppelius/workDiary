<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'retention_area' => [
        'audit_logs' => 'Journal d’audit',
        'exports' => 'Exports de paie et de temps',
        'gobd_financial' => 'Données fiscalement pertinentes',
        'claims' => 'Réclamations (clôturées)',
        'leads' => 'Leads (non convertis)',
        'applications' => 'Candidatures (refusées/retirées)',
        'privacy_requests' => 'Demandes des personnes concernées (clôturées)',
        'cti_calls' => 'Métadonnées d’appels CTI',
        'idea_maps' => 'Cartes d’idées (corbeille)',
        'dictations' => 'Dictées vocales (transcriptions)',
        'problem_reports' => 'Signalements de problèmes (clôturés)',
        'driver_license_checks' => 'Contrôles du permis de conduire',
        'documents_invoice' => 'Factures (DMS)',
        'passenger_rides' => 'Dossiers de courses (données de lieu et de passagers)',
        'employee_records' => 'Données du personnel (salariés sortis)',
        'personnel_files' => 'Dossiers du personnel (salariés sortis)',
        'customer_master' => 'Fichier clients (sans opérations)',
        'customer_intakes' => 'Demandes clients (refusées ou retirées)',
        'time_records' => 'Données brutes du temps de travail (présences)',
        'location_points' => 'Données brutes de localisation (points GPS)',
        'documents_general' => 'Documents (sans lien avec le droit fiscal ou commercial)',
        'learning_records' => 'Plateforme d’apprentissage (inscriptions, temps d’apprentissage, tentatives)',
        'learning_certificates' => 'Certificats de la plateforme d’apprentissage (pseudonymisation)',
    ],

    'requirement' => [
        'avv_required' => 'Contrat de sous-traitance avec le sous-traitant',
        'avv_current' => 'Contrat de sous-traitance valide (non expiré)',
        'gvv_required' => 'Accord de responsabilité conjointe avec le responsable conjoint',
        'dpia_required' => 'AIPD lorsqu’une AIPD est requise',
        'tom_assigned' => 'TOM par activité de traitement',
        'tom_proof_current' => 'Preuves TOM valides (non expirées)',
    ],
    // Datenkategorien der Datenschutz-Übersicht (Schlüssel == config/privacy.php categories.*.code).
    'category' => [
        'employees' => ['label' => 'Collaborateurs', 'retention' => 'jusqu’à la fin du contrat', 'delete_path' => 'Administrateur de l’organisation → Membres'],
        'working_time' => ['label' => 'Temps de travail', 'retention' => '10 ans (GoBD)', 'delete_path' => 'verrouillé après la clôture, non supprimable'],
        'absences' => ['label' => 'Absences rémunérées', 'retention' => 'selon la convention collective/la loi', 'delete_path' => 'Administrateur de l’organisation après le délai'],
        'diary' => ['label' => 'Registre des commandes', 'retention' => '5 ans (configurable)', 'delete_path' => 'Administrateur de l’organisation'],
        'tours' => ['label' => 'Tournées / sites', 'retention' => '2 ans (proposition)', 'delete_path' => 'suppression automatique'],
        'expenses' => ['label' => 'Frais / frais de déplacement', 'retention' => '10 ans (GoBD)', 'delete_path' => 'verrouillé, archivé'],
        'customers' => ['label' => 'Fichier clients', 'retention' => 'jusqu’à la fin de la relation commerciale + délai', 'delete_path' => 'Administrateur de l’organisation'],
        'attachments' => ['label' => 'Pièces jointes', 'retention' => 'avec l’enregistrement parent', 'delete_path' => 'avec l’enregistrement parent'],
        'signatures' => ['label' => 'Signatures', 'retention' => 'comme la commande', 'delete_path' => 'avec la commande'],
        'qualifications' => ['label' => 'Qualifications', 'retention' => 'jusqu’à la fin du contrat', 'delete_path' => 'Administrateur de l’organisation'],
        'push' => ['label' => 'Abonnements push', 'retention' => 'à la désinscription', 'delete_path' => 'automatiquement à la déconnexion/au nettoyage'],
        'audit' => ['label' => 'Journal d’audit', 'retention' => '24 mois (proposition)', 'delete_path' => 'tâche de rotation du système'],
    ],
    'retention_years' => ':years ans',
    // Befunde der Lückenanalyse (ComplianceAnalysisService, gespeichert in der Sprache der Organisation).
    'gap' => [
        'avv_missing' => 'Sous-traitant « :name » sans accord de sous-traitance',
        'avv_expiring' => 'L’accord de sous-traitance « :name » expire ou a expiré',
        'gvv_missing' => 'Responsable conjoint « :name » sans accord de responsabilité conjointe',
        'dpia_missing' => '« :name » nécessite une AIPD mais n’a pas d’AIPD achevée',
        'tom_missing' => '« :name » sans mesures techniques et organisationnelles attribuées',
        'tom_proof_expiring' => 'Des preuves de mesures techniques et organisationnelles expirent ou ont expiré : :names',
    ],
];
