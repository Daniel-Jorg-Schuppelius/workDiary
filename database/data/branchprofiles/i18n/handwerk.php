<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : handwerk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „handwerk" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'service' => ['en' => 'Service', 'es' => 'Servicio', 'fr' => 'Service', 'it' => 'Servizio'],
        'maintenance' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'repair' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'inspection' => ['en' => 'Inspection', 'es' => 'Inspección', 'fr' => 'Inspection', 'it' => 'Ispezione'],
        'advice' => ['en' => 'Consulting', 'es' => 'Asesoramiento', 'fr' => 'Conseil', 'it' => 'Consulenza'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
    ],
    'activity' => [
        'install' => ['en' => 'Install', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Montare'],
        'dismantle' => ['en' => 'Dismantle', 'es' => 'Desmontar', 'fr' => 'Démonter', 'it' => 'Smontare'],
        'repair' => ['en' => 'Repair', 'es' => 'Reparar', 'fr' => 'Réparer', 'it' => 'Riparare'],
        'measure' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'document' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
        'cleanUp' => ['en' => 'Clean construction site', 'es' => 'Limpiar la obra', 'fr' => 'Nettoyer le chantier', 'it' => 'Pulire il cantiere'],
    ],
    'defect_type' => [
        'mechanical' => ['en' => 'Mechanical', 'es' => 'Mecánico', 'fr' => 'Mécanique', 'it' => 'Meccanico'],
        'electrical' => ['en' => 'Electrical', 'es' => 'Eléctrico', 'fr' => 'Électrique', 'it' => 'Elettrico'],
        'plumbing' => ['en' => 'Plumbing', 'es' => 'Fontanería', 'fr' => 'Sanitaire', 'it' => 'Idraulica'],
        'surface' => ['en' => 'Surface', 'es' => 'Superficie', 'fr' => 'Surface', 'it' => 'Superficie'],
        'wear' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'accidental' => ['en' => 'Accident damage', 'es' => 'Daño por accidente', 'fr' => 'Dommage accidentel', 'it' => 'Danno da incidente'],
    ],
    'root_cause' => [
        'wear' => ['en' => 'Wear', 'es' => 'Desgaste', 'fr' => 'Usure', 'it' => 'Usura'],
        'misuse' => ['en' => 'Operating error', 'es' => 'Error de manejo', 'fr' => 'Erreur de manipulation', 'it' => 'Errore di utilizzo'],
        'defect' => ['en' => 'Defective', 'es' => 'Defectuoso', 'fr' => 'Défectueux', 'it' => 'Difettoso'],
        'installation' => ['en' => 'Installation error', 'es' => 'Error de instalación', 'fr' => 'Erreur d\'installation', 'it' => 'Errore di installazione'],
        'externalDamage' => ['en' => 'External damage', 'es' => 'Daño externo', 'fr' => 'Dommage externe', 'it' => 'Danno esterno'],
    ],
    'result' => [
        'resolved' => ['en' => 'Resolved', 'es' => 'Resuelto', 'fr' => 'Résolu', 'it' => 'Risolto'],
        'partialResolved' => ['en' => 'Partially resolved', 'es' => 'Resuelto en parte', 'fr' => 'Partiellement résolu', 'it' => 'Risolto in parte'],
        'materialMissing' => ['en' => 'Material missing', 'es' => 'Falta material', 'fr' => 'Matériel manquant', 'it' => 'Materiale mancante'],
        'customerDecided' => ['en' => 'Customer decision', 'es' => 'Decisión del cliente', 'fr' => 'Décision du client', 'it' => 'Decisione del cliente'],
        'escalated' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'heater' => ['en' => 'Heating', 'es' => 'Calefacción', 'fr' => 'Chauffage', 'it' => 'Riscaldamento'],
        'boiler' => ['en' => 'Boiler', 'es' => 'Calentador', 'fr' => 'Chauffe-eau', 'it' => 'Boiler'],
        'sanitary' => ['en' => 'Plumbing', 'es' => 'Fontanería', 'fr' => 'Sanitaire', 'it' => 'Idraulica'],
        'lighting' => ['en' => 'Lighting', 'es' => 'Iluminación', 'fr' => 'Éclairage', 'it' => 'Illuminazione'],
        'switchgear' => ['en' => 'Switchgear', 'es' => 'Aparamenta', 'fr' => 'Appareillage de commutation', 'it' => 'Quadro elettrico'],
        'surface' => ['en' => 'Surface', 'es' => 'Superficie', 'fr' => 'Surface', 'it' => 'Superficie'],
        'door' => ['en' => 'Door', 'es' => 'Puerta', 'fr' => 'Porte', 'it' => 'Porta'],
        'window' => ['en' => 'Window', 'es' => 'Ventana', 'fr' => 'Fenêtre', 'it' => 'Finestra'],
    ],
    'dienstmittel_type' => [
        'tool' => ['en' => 'Tool', 'es' => 'Herramienta', 'fr' => 'Outil', 'it' => 'Attrezzo'],
        'ladder' => ['en' => 'Ladder', 'es' => 'Escalera', 'fr' => 'Échelle', 'it' => 'Scala'],
        'vehicle' => ['en' => 'Vehicle', 'es' => 'Vehículo', 'fr' => 'Véhicule', 'it' => 'Veicolo'],
        'lift' => ['en' => 'Lifting platform', 'es' => 'Plataforma elevadora', 'fr' => 'Pont élévateur', 'it' => 'Piattaforma elevatrice'],
        'instrument' => ['en' => 'Measuring device', 'es' => 'Instrumento de medición', 'fr' => 'Appareil de mesure', 'it' => 'Strumento di misura'],
    ],
    'trade' => [
        'elektro' => ['en' => 'Electrical', 'es' => 'Electricidad', 'fr' => 'Électricité', 'it' => 'Elettrico'],
        'sanitaer_heizung' => ['en' => 'Plumbing / heating', 'es' => 'Fontanería / calefacción', 'fr' => 'Sanitaire / chauffage', 'it' => 'Idraulica / riscaldamento'],
        'maler' => ['en' => 'Painter', 'es' => 'Pintor', 'fr' => 'Peintre', 'it' => 'Imbianchino'],
        'fliesenleger' => ['en' => 'Tiler', 'es' => 'Alicatador', 'fr' => 'Carreleur', 'it' => 'Piastrellista'],
        'schreiner' => ['en' => 'Carpenter / joiner', 'es' => 'Carpintero', 'fr' => 'Menuisier', 'it' => 'Falegname'],
        'metallbau' => ['en' => 'Metalwork', 'es' => 'Cerrajería metálica', 'fr' => 'Construction métallique', 'it' => 'Carpenteria metallica'],
        'dachdecker' => ['en' => 'Roofer', 'es' => 'Tejador', 'fr' => 'Couvreur', 'it' => 'Copritetto'],
        'trockenbau' => ['en' => 'Drywall construction', 'es' => 'Construcción en seco', 'fr' => 'Plâtrerie', 'it' => 'Cartongesso'],
    ],
];
