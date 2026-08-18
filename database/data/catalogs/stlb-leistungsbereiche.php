<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : stlb-leistungsbereiche.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Leistungsbereiche des StLB-Bau (Feature 109, MVP-637).
 *
 * Der Leistungsbereich sagt, **welches Gewerk** eine Position ausführt — die
 * Kostengruppe sagt, wofür das Geld ausgegeben wird. Beides zusammen trägt die
 * Auswertung: „Wer baut was, und auf welche Kostengruppe schlägt es?"
 *
 * Ausgeliefert wird **nur die Nummernliste mit Kurzbezeichnungen** (D6) — die
 * StLB-Bau-Texte selbst sind lizenzpflichtig (DBD) und stehen hier nicht. Die
 * Liste folgt der amtlichen Gliederung der Leistungsbereiche 000–098; nicht
 * belegte Nummern sind bewusst ausgelassen, statt sie zu erfinden.
 *
 * Format je Zeile: [code, de, en, fr, it, es]
 *
 * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
 */
return [
    ['000', 'Sicherheitseinrichtungen, Baustelleneinrichtungen', 'Site safety and facilities', 'Sécurité et installation de chantier', 'Sicurezza e cantierizzazione', 'Seguridad e instalación de obra'],
    ['001', 'Gerüstarbeiten', 'Scaffolding', 'Échafaudages', 'Ponteggi', 'Andamios'],
    ['002', 'Erdarbeiten', 'Earthworks', 'Terrassement', 'Movimento terra', 'Movimiento de tierras'],
    ['003', 'Landschaftsbauarbeiten', 'Landscaping', 'Aménagement paysager', 'Opere a verde', 'Jardinería'],
    ['004', 'Landschaftsbauarbeiten — Pflanzen', 'Landscaping — planting', 'Aménagement paysager — plantations', 'Opere a verde — piantumazione', 'Jardinería — plantación'],
    ['005', 'Brunnenbauarbeiten, Aufschlussbohrungen', 'Well construction, exploratory drilling', 'Forages de puits et de reconnaissance', 'Pozzi e sondaggi', 'Pozos y sondeos'],
    ['006', 'Spezialtiefbauarbeiten', 'Special foundation engineering', 'Travaux de fondations spéciales', 'Opere di fondazioni speciali', 'Cimentaciones especiales'],
    ['007', 'Untertagebauarbeiten', 'Underground construction', 'Travaux souterrains', 'Opere in sotterraneo', 'Obras subterráneas'],
    ['008', 'Wasserhaltungsarbeiten', 'Dewatering', 'Épuisement des eaux', 'Aggottamento', 'Agotamiento de aguas'],
    ['009', 'Entwässerungskanalarbeiten', 'Sewer works', 'Travaux d’assainissement', 'Opere fognarie', 'Obras de saneamiento'],
    ['010', 'Dränarbeiten', 'Drainage works', 'Travaux de drainage', 'Opere di drenaggio', 'Obras de drenaje'],
    ['012', 'Mauerarbeiten', 'Masonry', 'Maçonnerie', 'Opere murarie', 'Albañilería'],
    ['013', 'Betonarbeiten', 'Concrete works', 'Travaux de béton', 'Opere in calcestruzzo', 'Obras de hormigón'],
    ['014', 'Natursteinarbeiten', 'Natural stone works', 'Travaux de pierre naturelle', 'Opere in pietra naturale', 'Obras de piedra natural'],
    ['016', 'Zimmer- und Holzbauarbeiten', 'Carpentry and timber construction', 'Charpente et construction bois', 'Carpenteria e costruzioni in legno', 'Carpintería y construcción en madera'],
    ['017', 'Stahlbauarbeiten', 'Structural steelwork', 'Construction métallique', 'Carpenteria metallica', 'Estructura metálica'],
    ['018', 'Abdichtungsarbeiten', 'Waterproofing', 'Étanchéité', 'Impermeabilizzazioni', 'Impermeabilización'],
    ['020', 'Dachdeckungsarbeiten', 'Roofing', 'Couverture', 'Coperture', 'Cubiertas'],
    ['021', 'Dachabdichtungsarbeiten', 'Roof waterproofing', 'Étanchéité de toiture', 'Impermeabilizzazione di coperture', 'Impermeabilización de cubiertas'],
    ['022', 'Klempnerarbeiten', 'Plumbing sheet metal works', 'Zinguerie', 'Opere da lattoniere', 'Trabajos de fontanería y chapa'],
    ['023', 'Putz- und Stuckarbeiten', 'Plastering and stucco', 'Enduits et stucs', 'Intonaci e stucchi', 'Revocos y estucos'],
    ['024', 'Fliesen- und Plattenarbeiten', 'Tiling', 'Carrelage', 'Piastrellature', 'Alicatados y solados'],
    ['025', 'Estricharbeiten', 'Screed works', 'Chapes', 'Massetti', 'Soleras'],
    ['026', 'Fenster, Außentüren', 'Windows, external doors', 'Fenêtres, portes extérieures', 'Finestre, porte esterne', 'Ventanas, puertas exteriores'],
    ['027', 'Tischlerarbeiten', 'Joinery', 'Menuiserie', 'Falegnameria', 'Ebanistería'],
    ['028', 'Parkett-, Holzpflasterarbeiten', 'Parquet and wood block flooring', 'Parquets et pavés bois', 'Parquet e pavimenti in legno', 'Parqué y adoquín de madera'],
    ['029', 'Beschlagarbeiten', 'Fittings and hardware', 'Quincaillerie', 'Ferramenta', 'Herrajes'],
    ['030', 'Rollladenarbeiten', 'Roller shutter works', 'Volets roulants', 'Avvolgibili', 'Persianas enrollables'],
    ['031', 'Metallbauarbeiten', 'Metalwork', 'Serrurerie', 'Opere da fabbro', 'Cerrajería'],
    ['032', 'Verglasungsarbeiten', 'Glazing', 'Vitrerie', 'Vetrazioni', 'Acristalamiento'],
    ['033', 'Baureinigungsarbeiten', 'Construction cleaning', 'Nettoyage de chantier', 'Pulizie di cantiere', 'Limpieza de obra'],
    ['034', 'Maler- und Lackierarbeiten', 'Painting and varnishing', 'Peinture et vernissage', 'Tinteggiature e verniciature', 'Pintura y barnizado'],
    ['035', 'Korrosionsschutzarbeiten', 'Corrosion protection', 'Protection anticorrosion', 'Protezione dalla corrosione', 'Protección anticorrosiva'],
    ['036', 'Bodenbelagarbeiten', 'Floor covering works', 'Revêtements de sol', 'Pavimentazioni', 'Revestimientos de suelo'],
    ['037', 'Tapezierarbeiten', 'Wallpapering', 'Pose de papiers peints', 'Tappezzerie', 'Empapelado'],
    ['038', 'Vorgehängte hinterlüftete Fassaden', 'Ventilated curtain walls', 'Façades ventilées', 'Facciate ventilate', 'Fachadas ventiladas'],
    ['039', 'Trockenbauarbeiten', 'Dry construction', 'Cloisons sèches', 'Cartongesso', 'Tabiquería seca'],
    ['040', 'Wärmedämm-Verbundsysteme', 'External thermal insulation systems', 'Systèmes d’isolation thermique par l’extérieur', 'Sistemi a cappotto', 'Sistemas SATE'],
    ['044', 'Abbruch- und Rückbauarbeiten', 'Demolition and dismantling', 'Démolition et déconstruction', 'Demolizioni e smantellamenti', 'Demolición y desmontaje'],
    ['045', 'Schadstoffsanierung', 'Hazardous material remediation', 'Désamiantage et dépollution', 'Bonifica di sostanze nocive', 'Descontaminación'],
    ['046', 'Betoninstandsetzungsarbeiten', 'Concrete repair', 'Réparation du béton', 'Risanamento del calcestruzzo', 'Reparación de hormigón'],
    ['047', 'Holzschutzarbeiten', 'Timber preservation', 'Traitement du bois', 'Protezione del legno', 'Protección de la madera'],
    ['049', 'Sonnenschutzanlagen', 'Solar shading systems', 'Protections solaires', 'Sistemi di protezione solare', 'Sistemas de protección solar'],
    ['050', 'Blitzschutz- und Erdungsanlagen', 'Lightning protection and earthing', 'Paratonnerre et mise à la terre', 'Impianti di protezione contro i fulmini', 'Pararrayos y puesta a tierra'],
    ['051', 'Kabelleitungstiefbau', 'Cable civil works', 'Génie civil pour câbles', 'Opere civili per cavi', 'Obra civil para cables'],
    ['052', 'Mittelspannungsanlagen', 'Medium-voltage systems', 'Installations moyenne tension', 'Impianti di media tensione', 'Instalaciones de media tensión'],
    ['053', 'Niederspannungsanlagen', 'Low-voltage systems', 'Installations basse tension', 'Impianti di bassa tensione', 'Instalaciones de baja tensión'],
    ['054', 'Niederspannungsinstallationsanlagen', 'Low-voltage installations', 'Installations électriques BT', 'Impianti elettrici BT', 'Instalaciones eléctricas BT'],
    ['055', 'Ersatzstromversorgungsanlagen', 'Standby power systems', 'Alimentations de secours', 'Gruppi di continuità', 'Suministro eléctrico de emergencia'],
    ['057', 'Gebäudesystemtechnik', 'Building system technology', 'Domotique du bâtiment', 'Tecnica di sistema per edifici', 'Domótica del edificio'],
    ['058', 'Leuchten und Lampen', 'Luminaires and lamps', 'Luminaires et lampes', 'Apparecchi di illuminazione', 'Luminarias y lámparas'],
    ['059', 'Sicherheitsbeleuchtungsanlagen', 'Emergency lighting systems', 'Éclairage de sécurité', 'Illuminazione di sicurezza', 'Alumbrado de emergencia'],
    ['060', 'Elektroakustische Anlagen', 'Public address systems', 'Sonorisation', 'Impianti elettroacustici', 'Megafonía'],
    ['061', 'Kommunikationsnetze', 'Communication networks', 'Réseaux de communication', 'Reti di comunicazione', 'Redes de comunicación'],
    ['062', 'Kommunikationsanlagen', 'Communication systems', 'Installations de communication', 'Impianti di comunicazione', 'Instalaciones de comunicación'],
    ['063', 'Gefahrmeldeanlagen', 'Hazard alarm systems', 'Systèmes d’alarme', 'Impianti di allarme', 'Sistemas de alarma'],
    ['064', 'Zutrittskontroll- und Zeiterfassungsanlagen', 'Access control and time recording', 'Contrôle d’accès et pointage', 'Controllo accessi e rilevazione presenze', 'Control de acceso y fichaje'],
    ['069', 'Aufzüge', 'Lifts', 'Ascenseurs', 'Ascensori', 'Ascensores'],
    ['070', 'Gebäudeautomation', 'Building automation', 'Automatisation du bâtiment', 'Automazione dell’edificio', 'Automatización del edificio'],
    ['075', 'Raumlufttechnische Anlagen', 'Air-conditioning systems', 'Installations aérauliques', 'Impianti di climatizzazione', 'Instalaciones de climatización'],
    ['076', 'Kälteanlagen', 'Refrigeration systems', 'Installations frigorifiques', 'Impianti frigoriferi', 'Instalaciones frigoríficas'],
    ['078', 'Wärmeversorgungsanlagen', 'Heating systems', 'Installations de chauffage', 'Impianti di riscaldamento', 'Instalaciones de calefacción'],
    ['080', 'Gas- und Wasseranlagen', 'Gas and water systems', 'Installations gaz et eau', 'Impianti gas e acqua', 'Instalaciones de gas y agua'],
    ['081', 'Abwasseranlagen', 'Drainage systems', 'Installations d’évacuation', 'Impianti di scarico', 'Instalaciones de saneamiento'],
    ['082', 'Sanitärausstattung', 'Sanitary fittings', 'Appareils sanitaires', 'Apparecchi sanitari', 'Aparatos sanitarios'],
    ['083', 'Feuerlöschanlagen', 'Fire extinguishing systems', 'Installations d’extinction', 'Impianti antincendio', 'Instalaciones de extinción'],
    ['084', 'Wärmedämmung an technischen Anlagen', 'Insulation of technical systems', 'Calorifugeage', 'Coibentazioni impiantistiche', 'Aislamiento de instalaciones'],
    ['085', 'Küchentechnische Anlagen', 'Commercial kitchen systems', 'Équipements de cuisine professionnelle', 'Impianti per cucine', 'Instalaciones de cocina'],
    ['087', 'Medizintechnische Anlagen', 'Medical technology systems', 'Équipements médicaux', 'Impianti medicali', 'Instalaciones médicas'],
    ['090', 'Straßen, Wege, Plätze', 'Roads, paths, squares', 'Voiries et places', 'Strade, percorsi, piazze', 'Viales y plazas'],
    ['091', 'Verkehrssicherungsarbeiten', 'Traffic safety works', 'Signalisation de chantier', 'Segnaletica di cantiere', 'Señalización de obra'],
    ['092', 'Wasserbauarbeiten', 'Hydraulic engineering', 'Travaux hydrauliques', 'Opere idrauliche', 'Obras hidráulicas'],
    ['093', 'Gleisbauarbeiten', 'Track construction', 'Travaux de voie ferrée', 'Opere ferroviarie', 'Obras de vía férrea'],
    ['094', 'Brückenbauarbeiten', 'Bridge construction', 'Travaux de ponts', 'Opere di ponti', 'Obras de puentes'],
    ['096', 'Ingenieurbauwerke, Instandsetzung', 'Civil structures, repair', 'Ouvrages d’art, réparation', 'Opere d’arte, riparazione', 'Obras de fábrica, reparación'],
    ['097', 'Abfallentsorgung', 'Waste disposal', 'Élimination des déchets', 'Smaltimento rifiuti', 'Gestión de residuos'],
    ['098', 'Bauhilfsleistungen', 'Auxiliary construction services', 'Prestations auxiliaires', 'Prestazioni ausiliarie', 'Servicios auxiliares'],
];
