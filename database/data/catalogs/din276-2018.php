<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : din276-2018.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Kostengruppen nach DIN 276:2018-12 (Feature 109, MVP-637).
 *
 * Ausgeliefert werden ausschließlich **Nummern und Kurzbezeichnungen** (D6) —
 * der Normtext ist lizenzpflichtig (DIN) und steht hier nicht.
 *
 * Die Ausgabe 2018 fasst die früheren Gruppen 200/300/400/500 neu: Aus
 * „Bauwerk — Technische Anlagen" (400) und „Außenanlagen" (500) wurden
 * „Bauwerk — Technische Anlagen" (400) und „Außenanlagen und Freiflächen"
 * (500), und die Ausstattung wanderte von 600 nach 600/700. Deshalb ist ein
 * Ausgabenwechsel eine fachliche Entscheidung, keine Umnummerierung.
 *
 * Format je Zeile: [code, level, de, en, fr, it, es]
 *
 * @return list<array{0: string, 1: int, 2: string, 3: string, 4: string, 5: string, 6: string}>
 */
return [
    // ── 100 Grundstück ───────────────────────────────────────────────────
    ['100', 1, 'Grundstück', 'Land', 'Terrain', 'Terreno', 'Terreno'],
    ['110', 2, 'Grundstückswert', 'Land value', 'Valeur du terrain', 'Valore del terreno', 'Valor del terreno'],
    ['120', 2, 'Grundstücksnebenkosten', 'Land incidental costs', 'Frais annexes du terrain', 'Oneri accessori del terreno', 'Gastos accesorios del terreno'],
    ['130', 2, 'Rechte Dritter', 'Third-party rights', 'Droits de tiers', 'Diritti di terzi', 'Derechos de terceros'],

    // ── 200 Vorbereitende Maßnahmen ──────────────────────────────────────
    ['200', 1, 'Vorbereitende Maßnahmen', 'Preparatory measures', 'Mesures préparatoires', 'Misure preparatorie', 'Medidas preparatorias'],
    ['210', 2, 'Herrichten', 'Site preparation', 'Aménagement préalable', 'Predisposizione', 'Acondicionamiento'],
    ['220', 2, 'Öffentliche Erschließung', 'Public development', 'Viabilisation publique', 'Urbanizzazione pubblica', 'Urbanización pública'],
    ['230', 2, 'Nichtöffentliche Erschließung', 'Private development', 'Viabilisation privée', 'Urbanizzazione privata', 'Urbanización privada'],
    ['240', 2, 'Ausgleichsmaßnahmen und -abgaben', 'Compensation measures and levies', 'Mesures et redevances compensatoires', 'Misure e oneri compensativi', 'Medidas y tasas compensatorias'],
    ['250', 2, 'Übergangsmaßnahmen', 'Interim measures', 'Mesures transitoires', 'Misure transitorie', 'Medidas transitorias'],

    // ── 300 Bauwerk — Baukonstruktionen ──────────────────────────────────
    ['300', 1, 'Bauwerk — Baukonstruktionen', 'Building — construction works', 'Ouvrage — gros œuvre', 'Opera — costruzioni edili', 'Edificio — construcción'],
    ['310', 2, 'Baugrube, Erdbau', 'Excavation, earthworks', 'Fouille, terrassement', 'Scavo, movimento terra', 'Excavación, movimiento de tierras'],
    ['311', 3, 'Baugrubenherstellung', 'Excavation works', 'Réalisation de la fouille', 'Realizzazione dello scavo', 'Ejecución de la excavación'],
    ['312', 3, 'Baugrubenumschließung', 'Excavation support', 'Soutènement de fouille', 'Sostegno dello scavo', 'Entibación de la excavación'],
    ['313', 3, 'Wasserhaltung', 'Dewatering', 'Épuisement des eaux', 'Aggottamento', 'Agotamiento de aguas'],
    ['319', 3, 'Baugrube, Erdbau, sonstiges', 'Excavation, earthworks, other', 'Fouille, terrassement, divers', 'Scavo, movimento terra, altro', 'Excavación, movimiento de tierras, otros'],
    ['320', 2, 'Gründung, Unterbau', 'Foundations, substructure', 'Fondations, infrastructure', 'Fondazioni, sottostruttura', 'Cimentación, subestructura'],
    ['321', 3, 'Baugrundverbesserung', 'Ground improvement', 'Amélioration du sol', 'Miglioramento del terreno', 'Mejora del terreno'],
    ['322', 3, 'Flachgründungen und Bodenplatten', 'Shallow foundations and slabs', 'Fondations superficielles et dallages', 'Fondazioni superficiali e platee', 'Cimentaciones superficiales y losas'],
    ['323', 3, 'Tiefgründungen', 'Deep foundations', 'Fondations profondes', 'Fondazioni profonde', 'Cimentaciones profundas'],
    ['324', 3, 'Gründungsbeläge', 'Foundation coverings', 'Revêtements de fondation', 'Rivestimenti di fondazione', 'Revestimientos de cimentación'],
    ['325', 3, 'Abdichtungen und Bekleidungen', 'Sealing and cladding', 'Étanchéités et habillages', 'Impermeabilizzazioni e rivestimenti', 'Impermeabilizaciones y revestimientos'],
    ['326', 3, 'Dränagen', 'Drainage', 'Drainages', 'Drenaggi', 'Drenajes'],
    ['329', 3, 'Gründung, Unterbau, sonstiges', 'Foundations, substructure, other', 'Fondations, infrastructure, divers', 'Fondazioni, sottostruttura, altro', 'Cimentación, subestructura, otros'],
    ['330', 2, 'Außenwände, vertikale Baukonstruktionen, außen', 'External walls, vertical structures', 'Murs extérieurs, structures verticales', 'Pareti esterne, strutture verticali', 'Muros exteriores, estructuras verticales'],
    ['331', 3, 'Tragende Außenwände', 'Load-bearing external walls', 'Murs extérieurs porteurs', 'Pareti esterne portanti', 'Muros exteriores portantes'],
    ['332', 3, 'Nichttragende Außenwände', 'Non-load-bearing external walls', 'Murs extérieurs non porteurs', 'Pareti esterne non portanti', 'Muros exteriores no portantes'],
    ['333', 3, 'Außenstützen', 'External columns', 'Poteaux extérieurs', 'Pilastri esterni', 'Pilares exteriores'],
    ['334', 3, 'Außentüren und -fenster', 'External doors and windows', 'Portes et fenêtres extérieures', 'Porte e finestre esterne', 'Puertas y ventanas exteriores'],
    ['335', 3, 'Außenwandbekleidungen, außen', 'External wall claddings, outer', 'Revêtements de murs extérieurs', 'Rivestimenti esterni delle pareti', 'Revestimientos exteriores de muros'],
    ['336', 3, 'Außenwandbekleidungen, innen', 'External wall claddings, inner', 'Habillages intérieurs des murs extérieurs', 'Rivestimenti interni delle pareti esterne', 'Revestimientos interiores de muros exteriores'],
    ['337', 3, 'Elementierte Außenwandkonstruktionen', 'Prefabricated external wall systems', 'Façades préfabriquées', 'Pareti esterne prefabbricate', 'Fachadas prefabricadas'],
    ['338', 3, 'Lichtschutz zur Außenwand', 'External sun protection', 'Protection solaire extérieure', 'Protezione solare esterna', 'Protección solar exterior'],
    ['339', 3, 'Außenwände, sonstiges', 'External walls, other', 'Murs extérieurs, divers', 'Pareti esterne, altro', 'Muros exteriores, otros'],
    ['340', 2, 'Innenwände, vertikale Baukonstruktionen, innen', 'Internal walls, vertical structures', 'Murs intérieurs, structures verticales', 'Pareti interne, strutture verticali', 'Muros interiores, estructuras verticales'],
    ['341', 3, 'Tragende Innenwände', 'Load-bearing internal walls', 'Murs intérieurs porteurs', 'Pareti interne portanti', 'Muros interiores portantes'],
    ['342', 3, 'Nichttragende Innenwände', 'Non-load-bearing internal walls', 'Murs intérieurs non porteurs', 'Pareti interne non portanti', 'Muros interiores no portantes'],
    ['343', 3, 'Innenstützen', 'Internal columns', 'Poteaux intérieurs', 'Pilastri interni', 'Pilares interiores'],
    ['344', 3, 'Innentüren und -fenster', 'Internal doors and windows', 'Portes et fenêtres intérieures', 'Porte e finestre interne', 'Puertas y ventanas interiores'],
    ['345', 3, 'Innenwandbekleidungen', 'Internal wall claddings', 'Revêtements de murs intérieurs', 'Rivestimenti di pareti interne', 'Revestimientos de muros interiores'],
    ['346', 3, 'Elementierte Innenwandkonstruktionen', 'Prefabricated internal wall systems', 'Cloisons préfabriquées', 'Pareti interne prefabbricate', 'Tabiques prefabricados'],
    ['347', 3, 'Lichtschutz zur Innenwand', 'Internal light protection', 'Protection solaire intérieure', 'Protezione dalla luce interna', 'Protección solar interior'],
    ['349', 3, 'Innenwände, sonstiges', 'Internal walls, other', 'Murs intérieurs, divers', 'Pareti interne, altro', 'Muros interiores, otros'],
    ['350', 2, 'Decken, horizontale Baukonstruktionen', 'Floors, horizontal structures', 'Planchers, structures horizontales', 'Solai, strutture orizzontali', 'Forjados, estructuras horizontales'],
    ['351', 3, 'Deckenkonstruktionen', 'Floor structures', 'Structures de plancher', 'Strutture dei solai', 'Estructuras de forjado'],
    ['352', 3, 'Deckenöffnungen', 'Floor openings', 'Trémies de plancher', 'Aperture nei solai', 'Huecos en forjados'],
    ['353', 3, 'Deckenbeläge', 'Floor coverings', 'Revêtements de sol', 'Pavimentazioni', 'Pavimentos'],
    ['354', 3, 'Deckenbekleidungen', 'Ceiling claddings', 'Plafonds', 'Controsoffitti', 'Techos'],
    ['359', 3, 'Decken, sonstiges', 'Floors, other', 'Planchers, divers', 'Solai, altro', 'Forjados, otros'],
    ['360', 2, 'Dächer', 'Roofs', 'Toitures', 'Coperture', 'Cubiertas'],
    ['361', 3, 'Dachkonstruktionen', 'Roof structures', 'Charpentes', 'Strutture di copertura', 'Estructuras de cubierta'],
    ['362', 3, 'Dachöffnungen', 'Roof openings', 'Ouvertures en toiture', 'Aperture in copertura', 'Huecos en cubierta'],
    ['363', 3, 'Dachbeläge', 'Roof coverings', 'Couvertures', 'Manti di copertura', 'Cubrición'],
    ['364', 3, 'Dachbekleidungen', 'Roof soffits', 'Sous-faces de toiture', 'Rivestimenti di copertura', 'Revestimientos de cubierta'],
    ['369', 3, 'Dächer, sonstiges', 'Roofs, other', 'Toitures, divers', 'Coperture, altro', 'Cubiertas, otros'],
    ['370', 2, 'Infrastrukturanlagen', 'Infrastructure works', 'Ouvrages d’infrastructure', 'Opere infrastrutturali', 'Obras de infraestructura'],
    ['380', 2, 'Baukonstruktive Einbauten', 'Built-in construction fittings', 'Aménagements intégrés', 'Elementi integrati', 'Elementos empotrados'],
    ['390', 2, 'Sonstige Maßnahmen für Baukonstruktionen', 'Other construction measures', 'Autres mesures de gros œuvre', 'Altre misure costruttive', 'Otras medidas constructivas'],
    ['391', 3, 'Baustelleneinrichtung', 'Site facilities', 'Installation de chantier', 'Cantierizzazione', 'Instalación de obra'],
    ['392', 3, 'Gerüste', 'Scaffolding', 'Échafaudages', 'Ponteggi', 'Andamios'],
    ['393', 3, 'Sicherungsmaßnahmen', 'Safety measures', 'Mesures de sécurisation', 'Misure di sicurezza', 'Medidas de seguridad'],
    ['394', 3, 'Abbruchmaßnahmen', 'Demolition works', 'Travaux de démolition', 'Demolizioni', 'Demoliciones'],
    ['395', 3, 'Instandsetzungen', 'Repairs', 'Remises en état', 'Riparazioni', 'Reparaciones'],
    ['396', 3, 'Materialentsorgung', 'Material disposal', 'Évacuation des matériaux', 'Smaltimento materiali', 'Eliminación de materiales'],
    ['397', 3, 'Zusätzliche Maßnahmen', 'Additional measures', 'Mesures supplémentaires', 'Misure aggiuntive', 'Medidas adicionales'],
    ['398', 3, 'Provisorische Baukonstruktionen', 'Temporary structures', 'Constructions provisoires', 'Costruzioni provvisorie', 'Construcciones provisionales'],
    ['399', 3, 'Sonstige Maßnahmen, sonstiges', 'Other measures, other', 'Autres mesures, divers', 'Altre misure, altro', 'Otras medidas, otros'],

    // ── 400 Bauwerk — Technische Anlagen ─────────────────────────────────
    ['400', 1, 'Bauwerk — Technische Anlagen', 'Building — services', 'Ouvrage — équipements techniques', 'Opera — impianti tecnici', 'Edificio — instalaciones'],
    ['410', 2, 'Abwasser-, Wasser-, Gasanlagen', 'Sewage, water, gas systems', 'Installations sanitaires et gaz', 'Impianti idrico-sanitari e gas', 'Instalaciones de agua y gas'],
    ['420', 2, 'Wärmeversorgungsanlagen', 'Heating systems', 'Installations de chauffage', 'Impianti di riscaldamento', 'Instalaciones de calefacción'],
    ['430', 2, 'Raumlufttechnische Anlagen', 'Ventilation and air-conditioning', 'Installations aérauliques', 'Impianti di climatizzazione', 'Instalaciones de climatización'],
    ['440', 2, 'Elektrische Anlagen', 'Electrical systems', 'Installations électriques', 'Impianti elettrici', 'Instalaciones eléctricas'],
    ['450', 2, 'Kommunikationstechnische Anlagen', 'Communication systems', 'Installations de communication', 'Impianti di comunicazione', 'Instalaciones de comunicación'],
    ['460', 2, 'Förderanlagen', 'Conveying systems', 'Installations de transport', 'Impianti di sollevamento', 'Instalaciones de transporte'],
    ['470', 2, 'Nutzungsspezifische und verfahrenstechnische Anlagen', 'Use-specific and process systems', 'Installations spécifiques et de process', 'Impianti specifici e di processo', 'Instalaciones específicas y de proceso'],
    ['480', 2, 'Gebäude- und Anlagenautomation', 'Building automation', 'Automatisation du bâtiment', 'Automazione dell’edificio', 'Automatización del edificio'],
    ['490', 2, 'Sonstige Maßnahmen für technische Anlagen', 'Other measures for services', 'Autres mesures pour équipements techniques', 'Altre misure per impianti', 'Otras medidas para instalaciones'],

    // ── 500 Außenanlagen und Freiflächen ─────────────────────────────────
    ['500', 1, 'Außenanlagen und Freiflächen', 'External works and open spaces', 'Aménagements extérieurs et espaces libres', 'Sistemazioni esterne e spazi aperti', 'Urbanización y espacios libres'],
    ['510', 2, 'Erdbau', 'Earthworks', 'Terrassement', 'Movimento terra', 'Movimiento de tierras'],
    ['520', 2, 'Gründung, Unterbau', 'Foundations, substructure', 'Fondations, infrastructure', 'Fondazioni, sottostruttura', 'Cimentación, subestructura'],
    ['530', 2, 'Oberbau, Deckschichten', 'Superstructure, surface layers', 'Superstructure, couches de surface', 'Sovrastruttura, strati superficiali', 'Superestructura, capas superficiales'],
    ['540', 2, 'Baukonstruktionen in Außenanlagen', 'Structures in external works', 'Constructions extérieures', 'Costruzioni negli spazi esterni', 'Construcciones exteriores'],
    ['550', 2, 'Technische Anlagen in Außenanlagen', 'Services in external works', 'Équipements techniques extérieurs', 'Impianti negli spazi esterni', 'Instalaciones exteriores'],
    ['560', 2, 'Einbauten in Außenanlagen', 'Fittings in external works', 'Équipements intégrés extérieurs', 'Elementi integrati esterni', 'Elementos integrados exteriores'],
    ['570', 2, 'Vegetationsflächen', 'Planted areas', 'Espaces végétalisés', 'Aree a verde', 'Zonas verdes'],
    ['580', 2, 'Wasserflächen', 'Water areas', 'Plans d’eau', 'Specchi d’acqua', 'Láminas de agua'],
    ['590', 2, 'Sonstige Maßnahmen für Außenanlagen', 'Other measures for external works', 'Autres mesures extérieures', 'Altre misure esterne', 'Otras medidas exteriores'],

    // ── 600 Ausstattung und Kunstwerke ───────────────────────────────────
    ['600', 1, 'Ausstattung und Kunstwerke', 'Furnishings and works of art', 'Équipement et œuvres d’art', 'Arredi e opere d’arte', 'Equipamiento y obras de arte'],
    ['610', 2, 'Allgemeine Ausstattung', 'General furnishings', 'Équipement général', 'Arredi generali', 'Equipamiento general'],
    ['620', 2, 'Besondere Ausstattung', 'Special furnishings', 'Équipement spécifique', 'Arredi speciali', 'Equipamiento especial'],
    ['630', 2, 'Informationstechnische Ausstattung', 'IT equipment', 'Équipement informatique', 'Dotazione informatica', 'Equipamiento informático'],
    ['640', 2, 'Künstlerische Ausstattung', 'Artistic furnishings', 'Équipement artistique', 'Dotazione artistica', 'Equipamiento artístico'],
    ['650', 2, 'Sonstige Ausstattung', 'Other furnishings', 'Autre équipement', 'Altri arredi', 'Otro equipamiento'],
    ['690', 2, 'Sonstige Maßnahmen für Ausstattung', 'Other measures for furnishings', 'Autres mesures d’équipement', 'Altre misure per arredi', 'Otras medidas de equipamiento'],

    // ── 700 Baunebenkosten ───────────────────────────────────────────────
    ['700', 1, 'Baunebenkosten', 'Ancillary construction costs', 'Frais annexes de construction', 'Oneri accessori di costruzione', 'Gastos accesorios de construcción'],
    ['710', 2, 'Bauherrenaufgaben', 'Client tasks', 'Missions du maître d’ouvrage', 'Compiti del committente', 'Tareas de la propiedad'],
    ['720', 2, 'Vorbereitung der Objektplanung', 'Preparation of design', 'Préparation de la conception', 'Preparazione della progettazione', 'Preparación del proyecto'],
    ['730', 2, 'Objektplanung', 'Object planning', 'Conception de l’ouvrage', 'Progettazione dell’opera', 'Proyecto de la obra'],
    ['740', 2, 'Fachplanung', 'Specialist planning', 'Études spécialisées', 'Progettazione specialistica', 'Ingeniería especializada'],
    ['750', 2, 'Künstlerische Leistungen', 'Artistic services', 'Prestations artistiques', 'Prestazioni artistiche', 'Servicios artísticos'],
    ['760', 2, 'Allgemeine Baunebenkosten', 'General ancillary costs', 'Frais annexes généraux', 'Oneri accessori generali', 'Gastos accesorios generales'],
    ['770', 2, 'Finanzierung', 'Financing', 'Financement', 'Finanziamento', 'Financiación'],
    ['790', 2, 'Sonstige Baunebenkosten', 'Other ancillary costs', 'Autres frais annexes', 'Altri oneri accessori', 'Otros gastos accesorios'],

    // ── 800 Finanzierung (Ausgabe 2018) ──────────────────────────────────
    ['800', 1, 'Finanzierung', 'Financing', 'Financement', 'Finanziamento', 'Financiación'],
    ['810', 2, 'Finanzierungsnebenkosten', 'Financing incidental costs', 'Frais annexes de financement', 'Oneri accessori di finanziamento', 'Gastos accesorios de financiación'],
    ['820', 2, 'Fremdkapitalzinsen', 'Interest on borrowed capital', 'Intérêts des emprunts', 'Interessi su capitale di terzi', 'Intereses de capital ajeno'],
    ['830', 2, 'Eigenkapitalzinsen', 'Interest on own capital', 'Intérêts des fonds propres', 'Interessi su capitale proprio', 'Intereses de capital propio'],
    ['840', 2, 'Bürgschaften', 'Guarantees', 'Cautions', 'Fideiussioni', 'Avales'],
    ['890', 2, 'Sonstige Finanzierungskosten', 'Other financing costs', 'Autres frais de financement', 'Altri costi di finanziamento', 'Otros costes de financiación'],
];
