<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : din276-2008.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Kostengruppen nach DIN 276-1:2008-12 (Feature 109, MVP-637).
 *
 * Die Ausgabe 2008 ist weiterhin in Umlauf: Ältere Leistungsverzeichnisse und
 * laufende Vorhaben rechnen danach ab, und ein Vergabeportal liefert
 * Katalogtyp `cost group DIN 276-1 2008-12`. **Sie wird deshalb geführt, nicht
 * migriert** (D3): „610" heißt hier „Ausstattung", in der Ausgabe 2018 gibt es
 * die Gruppe so nicht mehr.
 *
 * Nur Nummern und Kurzbezeichnungen (D6).
 *
 * Format je Zeile: [code, level, de, en, fr, it, es]
 *
 * @return list<array{0: string, 1: int, 2: string, 3: string, 4: string, 5: string, 6: string}>
 */
return [
    ['100', 1, 'Grundstück', 'Land', 'Terrain', 'Terreno', 'Terreno'],
    ['110', 2, 'Grundstückswert', 'Land value', 'Valeur du terrain', 'Valore del terreno', 'Valor del terreno'],
    ['120', 2, 'Grundstücksnebenkosten', 'Land incidental costs', 'Frais annexes du terrain', 'Oneri accessori del terreno', 'Gastos accesorios del terreno'],
    ['130', 2, 'Freimachen', 'Clearing', 'Libération du terrain', 'Liberazione del terreno', 'Liberación del terreno'],

    ['200', 1, 'Herrichten und Erschließen', 'Site preparation and development', 'Aménagement et viabilisation', 'Predisposizione e urbanizzazione', 'Acondicionamiento y urbanización'],
    ['210', 2, 'Herrichten', 'Site preparation', 'Aménagement préalable', 'Predisposizione', 'Acondicionamiento'],
    ['220', 2, 'Öffentliche Erschließung', 'Public development', 'Viabilisation publique', 'Urbanizzazione pubblica', 'Urbanización pública'],
    ['230', 2, 'Nichtöffentliche Erschließung', 'Private development', 'Viabilisation privée', 'Urbanizzazione privata', 'Urbanización privada'],
    ['240', 2, 'Ausgleichsabgaben', 'Compensation levies', 'Redevances compensatoires', 'Oneri compensativi', 'Tasas compensatorias'],
    ['250', 2, 'Übergangsmaßnahmen', 'Interim measures', 'Mesures transitoires', 'Misure transitorie', 'Medidas transitorias'],

    ['300', 1, 'Bauwerk — Baukonstruktionen', 'Building — construction works', 'Ouvrage — gros œuvre', 'Opera — costruzioni edili', 'Edificio — construcción'],
    ['310', 2, 'Baugrube', 'Excavation', 'Fouille', 'Scavo', 'Excavación'],
    ['320', 2, 'Gründung', 'Foundations', 'Fondations', 'Fondazioni', 'Cimentación'],
    ['330', 2, 'Außenwände', 'External walls', 'Murs extérieurs', 'Pareti esterne', 'Muros exteriores'],
    ['340', 2, 'Innenwände', 'Internal walls', 'Murs intérieurs', 'Pareti interne', 'Muros interiores'],
    ['350', 2, 'Decken', 'Floors', 'Planchers', 'Solai', 'Forjados'],
    ['360', 2, 'Dächer', 'Roofs', 'Toitures', 'Coperture', 'Cubiertas'],
    ['370', 2, 'Baukonstruktive Einbauten', 'Built-in construction fittings', 'Aménagements intégrés', 'Elementi integrati', 'Elementos empotrados'],
    ['390', 2, 'Sonstige Maßnahmen für Baukonstruktionen', 'Other construction measures', 'Autres mesures de gros œuvre', 'Altre misure costruttive', 'Otras medidas constructivas'],

    ['400', 1, 'Bauwerk — Technische Anlagen', 'Building — services', 'Ouvrage — équipements techniques', 'Opera — impianti tecnici', 'Edificio — instalaciones'],
    ['410', 2, 'Abwasser-, Wasser-, Gasanlagen', 'Sewage, water, gas systems', 'Installations sanitaires et gaz', 'Impianti idrico-sanitari e gas', 'Instalaciones de agua y gas'],
    ['420', 2, 'Wärmeversorgungsanlagen', 'Heating systems', 'Installations de chauffage', 'Impianti di riscaldamento', 'Instalaciones de calefacción'],
    ['430', 2, 'Lufttechnische Anlagen', 'Air handling systems', 'Installations aérauliques', 'Impianti aeraulici', 'Instalaciones de aire'],
    ['440', 2, 'Starkstromanlagen', 'Power systems', 'Installations de courant fort', 'Impianti elettrici di potenza', 'Instalaciones de fuerza'],
    ['450', 2, 'Fernmelde- und informationstechnische Anlagen', 'Telecommunication and IT systems', 'Installations de télécommunication', 'Impianti di telecomunicazione', 'Instalaciones de telecomunicación'],
    ['460', 2, 'Förderanlagen', 'Conveying systems', 'Installations de transport', 'Impianti di sollevamento', 'Instalaciones de transporte'],
    ['470', 2, 'Nutzungsspezifische Anlagen', 'Use-specific systems', 'Installations spécifiques', 'Impianti specifici', 'Instalaciones específicas'],
    ['480', 2, 'Gebäudeautomation', 'Building automation', 'Automatisation du bâtiment', 'Automazione dell’edificio', 'Automatización del edificio'],
    ['490', 2, 'Sonstige Maßnahmen für technische Anlagen', 'Other measures for services', 'Autres mesures pour équipements techniques', 'Altre misure per impianti', 'Otras medidas para instalaciones'],

    ['500', 1, 'Außenanlagen', 'External works', 'Aménagements extérieurs', 'Sistemazioni esterne', 'Urbanización'],
    ['510', 2, 'Geländeflächen', 'Terrain areas', 'Surfaces de terrain', 'Superfici del terreno', 'Superficies del terreno'],
    ['520', 2, 'Befestigte Flächen', 'Paved areas', 'Surfaces revêtues', 'Superfici pavimentate', 'Superficies pavimentadas'],
    ['530', 2, 'Baukonstruktionen in Außenanlagen', 'Structures in external works', 'Constructions extérieures', 'Costruzioni esterne', 'Construcciones exteriores'],
    ['540', 2, 'Technische Anlagen in Außenanlagen', 'Services in external works', 'Équipements techniques extérieurs', 'Impianti esterni', 'Instalaciones exteriores'],
    ['550', 2, 'Einbauten in Außenanlagen', 'Fittings in external works', 'Équipements intégrés extérieurs', 'Elementi integrati esterni', 'Elementos integrados exteriores'],
    ['560', 2, 'Wasserflächen', 'Water areas', 'Plans d’eau', 'Specchi d’acqua', 'Láminas de agua'],
    ['570', 2, 'Pflanz- und Saatflächen', 'Planting and seeding areas', 'Surfaces plantées et semées', 'Aree di piantumazione e semina', 'Áreas de plantación y siembra'],
    ['590', 2, 'Sonstige Maßnahmen für Außenanlagen', 'Other measures for external works', 'Autres mesures extérieures', 'Altre misure esterne', 'Otras medidas exteriores'],

    ['600', 1, 'Ausstattung und Kunstwerke', 'Furnishings and works of art', 'Équipement et œuvres d’art', 'Arredi e opere d’arte', 'Equipamiento y obras de arte'],
    ['610', 2, 'Ausstattung', 'Furnishings', 'Équipement', 'Arredi', 'Equipamiento'],
    ['620', 2, 'Kunstwerke', 'Works of art', 'Œuvres d’art', 'Opere d’arte', 'Obras de arte'],

    ['700', 1, 'Baunebenkosten', 'Ancillary construction costs', 'Frais annexes de construction', 'Oneri accessori di costruzione', 'Gastos accesorios de construcción'],
    ['710', 2, 'Bauherrenaufgaben', 'Client tasks', 'Missions du maître d’ouvrage', 'Compiti del committente', 'Tareas de la propiedad'],
    ['720', 2, 'Vorbereitung der Objektplanung', 'Preparation of design', 'Préparation de la conception', 'Preparazione della progettazione', 'Preparación del proyecto'],
    ['730', 2, 'Architekten- und Ingenieurleistungen', 'Architect and engineer services', 'Prestations d’architecte et d’ingénieur', 'Prestazioni di architetti e ingegneri', 'Servicios de arquitectura e ingeniería'],
    ['740', 2, 'Gutachten und Beratung', 'Expert opinions and consulting', 'Expertises et conseil', 'Perizie e consulenza', 'Peritajes y asesoramiento'],
    ['750', 2, 'Künstlerische Leistungen', 'Artistic services', 'Prestations artistiques', 'Prestazioni artistiche', 'Servicios artísticos'],
    ['760', 2, 'Finanzierungskosten', 'Financing costs', 'Frais de financement', 'Costi di finanziamento', 'Costes de financiación'],
    ['770', 2, 'Allgemeine Baunebenkosten', 'General ancillary costs', 'Frais annexes généraux', 'Oneri accessori generali', 'Gastos accesorios generales'],
    ['790', 2, 'Sonstige Baunebenkosten', 'Other ancillary costs', 'Autres frais annexes', 'Altri oneri accessori', 'Otros gastos accesorios'],
];
