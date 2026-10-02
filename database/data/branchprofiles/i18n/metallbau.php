<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metallbau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „metallbau" (MVP-1062); Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'gelaender' => ['en' => 'Railings', 'es' => 'Barandillas', 'fr' => 'Garde-corps', 'it' => 'Ringhiere'],
        'treppe' => ['en' => 'Steel stairs', 'es' => 'Escalera de acero', 'fr' => 'Escalier métallique', 'it' => 'Scala in acciaio'],
        'tor' => ['en' => 'Gate/fence', 'es' => 'Portón/valla', 'fr' => 'Portail/clôture', 'it' => 'Cancello/recinzione'],
        'konstruktion' => ['en' => 'Steel structure', 'es' => 'Estructura de acero', 'fr' => 'Charpente métallique', 'it' => 'Struttura in acciaio'],
        'schweissen' => ['en' => 'Welding work', 'es' => 'Trabajos de soldadura', 'fr' => 'Travaux de soudure', 'it' => 'Lavori di saldatura'],
        'wartung' => ['en' => 'Gate maintenance', 'es' => 'Mantenimiento de portones', 'fr' => 'Maintenance de portail', 'it' => 'Manutenzione cancelli'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
    ],
    'activity' => [
        'fertigen' => ['en' => 'Fabricate', 'es' => 'Fabricar', 'fr' => 'Fabriquer', 'it' => 'Fabbricare'],
        'schweissen' => ['en' => 'Weld', 'es' => 'Soldar', 'fr' => 'Souder', 'it' => 'Saldare'],
        'beschichten' => ['en' => 'Coat', 'es' => 'Recubrir', 'fr' => 'Revêtir', 'it' => 'Rivestire'],
        'montieren' => ['en' => 'Install', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Montare'],
        'pruefen' => ['en' => 'Inspect', 'es' => 'Comprobar', 'fr' => 'Contrôler', 'it' => 'Verificare'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'korrosion' => ['en' => 'Corrosion', 'es' => 'Corrosión', 'fr' => 'Corrosion', 'it' => 'Corrosione'],
        'schweissnaht' => ['en' => 'Faulty weld seam', 'es' => 'Cordón de soldadura defectuoso', 'fr' => 'Soudure défectueuse', 'it' => 'Saldatura difettosa'],
        'mass' => ['en' => 'Dimensional deviation', 'es' => 'Desviación de medidas', 'fr' => 'Écart de cote', 'it' => 'Scostamento dimensionale'],
        'befestigung' => ['en' => 'Fixing loose', 'es' => 'Fijación suelta', 'fr' => 'Fixation desserrée', 'it' => 'Fissaggio allentato'],
        'antrieb' => ['en' => 'Drive faulty', 'es' => 'Motor averiado', 'fr' => 'Motorisation défectueuse', 'it' => 'Motore guasto'],
    ],
    'root_cause' => [
        'witterung' => ['en' => 'Weathering', 'es' => 'Intemperie', 'fr' => 'Intempéries', 'it' => 'Agenti atmosferici'],
        'ausfuehrung' => ['en' => 'Workmanship', 'es' => 'Ejecución', 'fr' => 'Exécution', 'it' => 'Esecuzione'],
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'fremdeinwirkung' => ['en' => 'External impact', 'es' => 'Acción de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Azione esterna'],
    ],
    'result' => [
        'fertig' => ['en' => 'Finished', 'es' => 'Terminado', 'fr' => 'Terminé', 'it' => 'Finito'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Reprise', 'it' => 'Rilavorazione'],
        'ersatzteil' => ['en' => 'Spare part ordered', 'es' => 'Repuesto pedido', 'fr' => 'Pièce commandée', 'it' => 'Ricambio ordinato'],
        'stillgelegt' => ['en' => 'Shut down', 'es' => 'Fuera de servicio', 'fr' => 'Mis hors service', 'it' => 'Messo fuori servizio'],
    ],
];
