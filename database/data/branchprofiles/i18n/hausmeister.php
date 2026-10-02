<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hausmeister.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „hausmeister" (MVP-1062); Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'kontrollgang' => ['en' => 'Inspection round', 'es' => 'Ronda de control', 'fr' => 'Ronde de contrôle', 'it' => 'Giro di controllo'],
        'kleinreparatur' => ['en' => 'Minor repair', 'es' => 'Pequeña reparación', 'fr' => 'Petite réparation', 'it' => 'Piccola riparazione'],
        'gruenpflege' => ['en' => 'Grounds maintenance', 'es' => 'Mantenimiento de jardines', 'fr' => 'Entretien des espaces verts', 'it' => 'Manutenzione del verde'],
        'winterdienst' => ['en' => 'Winter service', 'es' => 'Servicio invernal', 'fr' => 'Service hivernal', 'it' => 'Servizio invernale'],
        'muell' => ['en' => 'Bin service', 'es' => 'Servicio de contenedores', 'fr' => 'Service des poubelles', 'it' => 'Servizio cassonetti'],
        'ablesung' => ['en' => 'Meter reading', 'es' => 'Lectura de contadores', 'fr' => 'Relevé de compteurs', 'it' => 'Lettura contatori'],
    ],
    'activity' => [
        'pruefen' => ['en' => 'Inspect', 'es' => 'Comprobar', 'fr' => 'Contrôler', 'it' => 'Verificare'],
        'reparieren' => ['en' => 'Repair', 'es' => 'Reparar', 'fr' => 'Réparer', 'it' => 'Riparare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'streuen' => ['en' => 'Clear and grit', 'es' => 'Retirar nieve y esparcir sal', 'fr' => 'Déneiger et saler', 'it' => 'Sgomberare e spargere sale'],
        'ablesen' => ['en' => 'Read meters', 'es' => 'Leer contadores', 'fr' => 'Relever', 'it' => 'Leggere'],
        'melden' => ['en' => 'Report a defect', 'es' => 'Notificar defecto', 'fr' => 'Signaler un défaut', 'it' => 'Segnalare un difetto'],
    ],
    'defect_type' => [
        'beleuchtung' => ['en' => 'Lighting faulty', 'es' => 'Iluminación averiada', 'fr' => 'Éclairage défectueux', 'it' => 'Illuminazione guasta'],
        'tuer' => ['en' => 'Door/lock faulty', 'es' => 'Puerta/cerradura averiada', 'fr' => 'Porte/serrure défectueuse', 'it' => 'Porta/serratura guasta'],
        'wasser' => ['en' => 'Water damage', 'es' => 'Daño por agua', 'fr' => 'Dégât des eaux', 'it' => 'Danno da acqua'],
        'verschmutzung' => ['en' => 'Soiling', 'es' => 'Suciedad', 'fr' => 'Encrassement', 'it' => 'Sporcizia'],
        'sicherheit' => ['en' => 'Safety defect', 'es' => 'Defecto de seguridad', 'fr' => 'Défaut de sécurité', 'it' => 'Difetto di sicurezza'],
    ],
    'root_cause' => [
        'verschleiss' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'vandalismus' => ['en' => 'Vandalism', 'es' => 'Vandalismo', 'fr' => 'Vandalisme', 'it' => 'Vandalismo'],
        'witterung' => ['en' => 'Weathering', 'es' => 'Intemperie', 'fr' => 'Intempéries', 'it' => 'Agenti atmosferici'],
        'nutzung' => ['en' => 'Use', 'es' => 'Uso', 'fr' => 'Utilisation', 'it' => 'Utilizzo'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'fremdfirma' => ['en' => 'Handed to a specialist firm', 'es' => 'Entregado a empresa especializada', 'fr' => 'Confié à une entreprise spécialisée', 'it' => 'Affidato a ditta specializzata'],
        'verwaltungInformiert' => ['en' => 'Property management informed', 'es' => 'Administración informada', 'fr' => 'Gestionnaire informé', 'it' => 'Amministrazione informata'],
        'offen' => ['en' => 'Open', 'es' => 'Abierto', 'fr' => 'Ouvert', 'it' => 'Aperto'],
    ],
];
