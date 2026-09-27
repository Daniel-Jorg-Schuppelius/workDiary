<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gebaeudereinigung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „gebaeudereinigung" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'unterhaltsreinigung' => ['en' => 'Routine cleaning', 'es' => 'Limpieza de mantenimiento', 'fr' => 'Nettoyage d\'entretien', 'it' => 'Pulizia ordinaria'],
        'grundreinigung' => ['en' => 'Deep cleaning', 'es' => 'Limpieza a fondo', 'fr' => 'Nettoyage en profondeur', 'it' => 'Pulizia di fondo'],
        'glasreinigung' => ['en' => 'Window cleaning', 'es' => 'Limpieza de cristales', 'fr' => 'Nettoyage des vitres', 'it' => 'Pulizia vetri'],
        'sonderreinigung' => ['en' => 'Special cleaning', 'es' => 'Limpieza especial', 'fr' => 'Nettoyage spécial', 'it' => 'Pulizia speciale'],
        'qualitaetskontrolle' => ['en' => 'Quality check', 'es' => 'Control de calidad', 'fr' => 'Contrôle qualité', 'it' => 'Controllo qualità'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'begehung' => ['en' => 'Walk-through', 'es' => 'Inspección', 'fr' => 'Visite', 'it' => 'Sopralluogo'],
    ],
    'activity' => [
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'desinfizieren' => ['en' => 'Disinfect', 'es' => 'Desinfectar', 'fr' => 'Désinfecter', 'it' => 'Disinfettare'],
        'saugen' => ['en' => 'Vacuum', 'es' => 'Aspirar', 'fr' => 'Aspirer', 'it' => 'Aspirare'],
        'wischen' => ['en' => 'Mopping', 'es' => 'Fregar', 'fr' => 'Laver le sol', 'it' => 'Lavare i pavimenti'],
        'polieren' => ['en' => 'Polish', 'es' => 'Pulir', 'fr' => 'Polir', 'it' => 'Lucidare'],
        'auffuellen' => ['en' => 'Refill', 'es' => 'Reponer', 'fr' => 'Réapprovisionner', 'it' => 'Rifornire'],
        'entsorgen' => ['en' => 'Dispose', 'es' => 'Eliminar', 'fr' => 'Éliminer', 'it' => 'Smaltire'],
        'kontrollieren' => ['en' => 'Check', 'es' => 'Controlar', 'fr' => 'Contrôler', 'it' => 'Controllare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'nichtGereinigt' => ['en' => 'Not cleaned', 'es' => 'No limpiado', 'fr' => 'Non nettoyé', 'it' => 'Non pulito'],
        'materialFehlt' => ['en' => 'Material missing', 'es' => 'Falta material', 'fr' => 'Matériel manquant', 'it' => 'Materiale mancante'],
        'zugangFehlt' => ['en' => 'Access missing', 'es' => 'Falta el acceso', 'fr' => 'Accès manquant', 'it' => 'Accesso mancante'],
        'qualitaetsmangel' => ['en' => 'Quality defect', 'es' => 'Defecto de calidad', 'fr' => 'Défaut de qualité', 'it' => 'Difetto di qualità'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Dommage', 'it' => 'Danno'],
        'hygienemangel' => ['en' => 'Hygiene deficiency', 'es' => 'Deficiencia de higiene', 'fr' => 'Défaut d\'hygiène', 'it' => 'Carenza igienica'],
        'kundenbeschwerde' => ['en' => 'Customer complaint', 'es' => 'Queja del cliente', 'fr' => 'Réclamation client', 'it' => 'Reclamo del cliente'],
    ],
    'root_cause' => [
        'personalEngpass' => ['en' => 'Staff shortage', 'es' => 'Falta de personal', 'fr' => 'Manque de personnel', 'it' => 'Carenza di personale'],
        'zugang' => ['en' => 'Access', 'es' => 'Acceso', 'fr' => 'Accès', 'it' => 'Accesso'],
        'material' => ['en' => 'Material', 'es' => 'Material', 'fr' => 'Matériel', 'it' => 'Materiale'],
        'fremdverschmutzung' => ['en' => 'Soiling by third parties', 'es' => 'Suciedad causada por terceros', 'fr' => 'Salissure par des tiers', 'it' => 'Sporco causato da terzi'],
        'planungsfehler' => ['en' => 'Planning error', 'es' => 'Error de planificación', 'fr' => 'Erreur de planification', 'it' => 'Errore di pianificazione'],
        'nacharbeitNoetig' => ['en' => 'Rework needed', 'es' => 'Se necesita retrabajo', 'fr' => 'Retouche nécessaire', 'it' => 'Serve rilavorazione'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Retouche', 'it' => 'Rilavorazione'],
        'nichtMoeglich' => ['en' => 'Not possible', 'es' => 'No es posible', 'fr' => 'Impossible', 'it' => 'Non possibile'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'eskaliert' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'buero' => ['en' => 'Office', 'es' => 'Oficina', 'fr' => 'Bureau', 'it' => 'Ufficio'],
        'sanitaer' => ['en' => 'Plumbing', 'es' => 'Fontanería', 'fr' => 'Sanitaire', 'it' => 'Idraulica'],
        'treppenhaus' => ['en' => 'Stairwell', 'es' => 'Escalera', 'fr' => 'Cage d\'escalier', 'it' => 'Vano scale'],
        'glas' => ['en' => 'Glass', 'es' => 'Vidrio', 'fr' => 'Verre', 'it' => 'Vetro'],
        'boden' => ['en' => 'Floor', 'es' => 'Suelo', 'fr' => 'Sol', 'it' => 'Pavimento'],
        'kueche' => ['en' => 'Kitchen', 'es' => 'Cocina', 'fr' => 'Cuisine', 'it' => 'Cucina'],
        'industrie' => ['en' => 'Industry', 'es' => 'Industria', 'fr' => 'Industrie', 'it' => 'Industria'],
        'medizinisch' => ['en' => 'Medical', 'es' => 'Médico', 'fr' => 'Médical', 'it' => 'Medico'],
        'aussenbereich' => ['en' => 'Outdoor area', 'es' => 'Zona exterior', 'fr' => 'Zone extérieure', 'it' => 'Area esterna'],
    ],
];
