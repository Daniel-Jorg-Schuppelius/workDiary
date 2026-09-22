<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bau-ausbau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „bau-ausbau" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'bautagesbericht' => ['en' => 'Daily site report', 'es' => 'Parte diario de obra', 'fr' => 'Rapport journalier de chantier', 'it' => 'Rapporto giornaliero di cantiere'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
        'montage' => ['en' => 'Assembly', 'es' => 'Montaje', 'fr' => 'Montage', 'it' => 'Montaggio'],
        'mangel' => ['en' => 'Defect', 'es' => 'Defecto', 'fr' => 'Défaut', 'it' => 'Difetto'],
        'nachtrag' => ['en' => 'Change order', 'es' => 'Adicional', 'fr' => 'Avenant', 'it' => 'Variante'],
        'teilabnahme' => ['en' => 'Partial acceptance', 'es' => 'Recepción parcial', 'fr' => 'Réception partielle', 'it' => 'Collaudo parziale'],
        'material' => ['en' => 'Material draw', 'es' => 'Retirada de material', 'fr' => 'Sortie de matériel', 'it' => 'Prelievo materiale'],
        'behinderung' => ['en' => 'Obstruction notice', 'es' => 'Aviso de impedimento', 'fr' => 'Avis d\'entrave', 'it' => 'Segnalazione di impedimento'],
        'restarbeit' => ['en' => 'Remaining work', 'es' => 'Trabajo pendiente', 'fr' => 'Travaux restants', 'it' => 'Lavori residui'],
    ],
];
