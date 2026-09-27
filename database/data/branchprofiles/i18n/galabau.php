<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : galabau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „galabau" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'pflegegang' => ['en' => 'Maintenance visit', 'es' => 'Visita de mantenimiento', 'fr' => 'Passage d\'entretien', 'it' => 'Intervento di manutenzione'],
        'neuanlage' => ['en' => 'New landscaping', 'es' => 'Nueva instalación', 'fr' => 'Création', 'it' => 'Nuova realizzazione'],
        'pflanzung' => ['en' => 'Planting', 'es' => 'Plantación', 'fr' => 'Plantation', 'it' => 'Piantumazione'],
        'erdarbeit' => ['en' => 'Earthwork', 'es' => 'Movimiento de tierras', 'fr' => 'Terrassement', 'it' => 'Movimento terra'],
        'pflaster' => ['en' => 'Paving', 'es' => 'Pavimentación', 'fr' => 'Pavage', 'it' => 'Pavimentazione'],
        'baumpflege' => ['en' => 'Tree care', 'es' => 'Cuidado de árboles', 'fr' => 'Entretien des arbres', 'it' => 'Cura degli alberi'],
        'bewaesserung' => ['en' => 'Irrigation', 'es' => 'Riego', 'fr' => 'Irrigation', 'it' => 'Irrigazione'],
        'winterdienst' => ['en' => 'Winter service', 'es' => 'Servicio invernal', 'fr' => 'Service hivernal', 'it' => 'Servizio invernale'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
    ],
    'activity' => [
        'maehen' => ['en' => 'Mowing', 'es' => 'Segar', 'fr' => 'Tondre', 'it' => 'Falciare'],
        'schneiden' => ['en' => 'Cut', 'es' => 'Cortar', 'fr' => 'Couper', 'it' => 'Tagliare'],
        'pflanzen' => ['en' => 'Planting', 'es' => 'Plantar', 'fr' => 'Planter', 'it' => 'Piantare'],
        'giessen' => ['en' => 'Watering', 'es' => 'Regar', 'fr' => 'Arroser', 'it' => 'Annaffiare'],
        'duengen' => ['en' => 'Fertilising', 'es' => 'Abonar', 'fr' => 'Fertiliser', 'it' => 'Concimare'],
        'roden' => ['en' => 'Clearing', 'es' => 'Desbrozar', 'fr' => 'Défricher', 'it' => 'Disboscare'],
        'baggern' => ['en' => 'Excavating', 'es' => 'Excavar', 'fr' => 'Excaver', 'it' => 'Scavare'],
        'pflastern' => ['en' => 'Paving', 'es' => 'Adoquinar', 'fr' => 'Paver', 'it' => 'Pavimentare'],
        'reinigen' => ['en' => 'Clean', 'es' => 'Limpiar', 'fr' => 'Nettoyer', 'it' => 'Pulire'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'ausfallPflanze' => ['en' => 'Plant loss', 'es' => 'Pérdida de plantas', 'fr' => 'Perte de végétaux', 'it' => 'Perdita di piante'],
        'trockenheit' => ['en' => 'Drought', 'es' => 'Sequía', 'fr' => 'Sécheresse', 'it' => 'Siccità'],
        'schaedling' => ['en' => 'Pest infestation', 'es' => 'Plaga', 'fr' => 'Infestation de nuisibles', 'it' => 'Infestazione'],
        'setzung' => ['en' => 'Settlement', 'es' => 'Asentamiento', 'fr' => 'Tassement', 'it' => 'Cedimento'],
        'frostschaden' => ['en' => 'Frost damage', 'es' => 'Daño por helada', 'fr' => 'Dégât dû au gel', 'it' => 'Danno da gelo'],
        'geraeteschaden' => ['en' => 'Device damage', 'es' => 'Daño en el equipo', 'fr' => 'Dommage à l\'appareil', 'it' => 'Danno all\'apparecchio'],
        'zugangFehlt' => ['en' => 'Access missing', 'es' => 'Falta el acceso', 'fr' => 'Accès manquant', 'it' => 'Accesso mancante'],
    ],
    'root_cause' => [
        'wetter' => ['en' => 'Weather', 'es' => 'Clima', 'fr' => 'Météo', 'it' => 'Meteo'],
        'pflegefehler' => ['en' => 'Care error', 'es' => 'Error de cuidados', 'fr' => 'Erreur de soins', 'it' => 'Errore assistenziale'],
        'material' => ['en' => 'Material', 'es' => 'Material', 'fr' => 'Matériel', 'it' => 'Materiale'],
        'standort' => ['en' => 'Location', 'es' => 'Ubicación', 'fr' => 'Site', 'it' => 'Sede'],
        'fremdeinwirkung' => ['en' => 'Third-party interference', 'es' => 'Intervención de terceros', 'fr' => 'Intervention extérieure', 'it' => 'Intervento di terzi'],
        'kundenaenderung' => ['en' => 'Customer change request', 'es' => 'Cambio del cliente', 'fr' => 'Modification client', 'it' => 'Modifica del cliente'],
        'lieferverzug' => ['en' => 'Delivery delay', 'es' => 'Retraso en la entrega', 'fr' => 'Retard de livraison', 'it' => 'Ritardo di consegna'],
    ],
    'result' => [
        'erledigt' => ['en' => 'Done', 'es' => 'Hecho', 'fr' => 'Fait', 'it' => 'Fatto'],
        'teilErledigt' => ['en' => 'Partially done', 'es' => 'Hecho en parte', 'fr' => 'Partiellement fait', 'it' => 'Fatto in parte'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Retouche', 'it' => 'Rilavorazione'],
        'saisonbedingtOffen' => ['en' => 'Open for the season', 'es' => 'Abierto por temporada', 'fr' => 'Ouvert selon la saison', 'it' => 'Aperto per stagione'],
        'kundeInformiert' => ['en' => 'Customer informed', 'es' => 'Cliente informado', 'fr' => 'Client informé', 'it' => 'Cliente informato'],
        'abgenommen' => ['en' => 'Accepted', 'es' => 'Aceptado', 'fr' => 'Réceptionné', 'it' => 'Collaudato'],
    ],
    'product_group' => [
        'rasen' => ['en' => 'Lawn', 'es' => 'Césped', 'fr' => 'Pelouse', 'it' => 'Prato'],
        'hecke' => ['en' => 'Hedge', 'es' => 'Seto', 'fr' => 'Haie', 'it' => 'Siepe'],
        'baum' => ['en' => 'Tree', 'es' => 'Árbol', 'fr' => 'Arbre', 'it' => 'Albero'],
        'beet' => ['en' => 'Flower bed', 'es' => 'Parterre', 'fr' => 'Parterre', 'it' => 'Aiuola'],
        'pflaster' => ['en' => 'Paving', 'es' => 'Adoquinado', 'fr' => 'Pavage', 'it' => 'Pavimentazione'],
        'zaun' => ['en' => 'Fence', 'es' => 'Valla', 'fr' => 'Clôture', 'it' => 'Recinzione'],
        'bewaesserung' => ['en' => 'Irrigation', 'es' => 'Riego', 'fr' => 'Arrosage', 'it' => 'Irrigazione'],
        'teich' => ['en' => 'Pond', 'es' => 'Estanque', 'fr' => 'Étang', 'it' => 'Laghetto'],
        'aussenanlage' => ['en' => 'Outdoor facilities', 'es' => 'Instalaciones exteriores', 'fr' => 'Aménagements extérieurs', 'it' => 'Area esterna'],
        'winterdienst' => ['en' => 'Winter service', 'es' => 'Servicio invernal', 'fr' => 'Service hivernal', 'it' => 'Servizio invernale'],
    ],
    'trade' => [
        'erdbau' => ['en' => 'Earthworks', 'es' => 'Movimiento de tierras', 'fr' => 'Terrassement', 'it' => 'Movimento terra'],
        'pflasterbau' => ['en' => 'Paving construction', 'es' => 'Construcción de adoquinado', 'fr' => 'Construction de pavage', 'it' => 'Posa di pavimentazioni'],
        'pflanzung' => ['en' => 'Planting / greening', 'es' => 'Plantación / ajardinamiento', 'fr' => 'Plantation / végétalisation', 'it' => 'Piantagione / rinverdimento'],
        'baumpflege' => ['en' => 'Tree care', 'es' => 'Cuidado de árboles', 'fr' => 'Entretien des arbres', 'it' => 'Cura degli alberi'],
        'bewaesserung' => ['en' => 'Irrigation', 'es' => 'Riego', 'fr' => 'Arrosage', 'it' => 'Irrigazione'],
        'zaunbau' => ['en' => 'Fencing', 'es' => 'Montaje de vallas', 'fr' => 'Pose de clôtures', 'it' => 'Costruzione di recinzioni'],
        'teichbau' => ['en' => 'Pond / water features', 'es' => 'Estanques / obras hidráulicas', 'fr' => 'Bassins / aménagements aquatiques', 'it' => 'Laghetti / opere idrauliche'],
        'holzbau' => ['en' => 'Timber construction', 'es' => 'Construcción en madera', 'fr' => 'Construction bois', 'it' => 'Costruzioni in legno'],
    ],
    'permit_type' => [
        'baumfaellung' => ['en' => 'Tree felling permit', 'es' => 'Permiso de tala', 'fr' => 'Autorisation d\'abattage', 'it' => 'Autorizzazione all\'abbattimento'],
        'wasserrecht' => ['en' => 'Water rights permit', 'es' => 'Permiso de aguas', 'fr' => 'Autorisation au titre de la loi sur l\'eau', 'it' => 'Autorizzazione idrica'],
        'sondernutzung' => ['en' => 'Special use of public space', 'es' => 'Uso especial del espacio público', 'fr' => 'Occupation du domaine public', 'it' => 'Occupazione di suolo pubblico'],
        'naturschutz' => ['en' => 'Nature conservation permit', 'es' => 'Permiso de protección de la naturaleza', 'fr' => 'Autorisation environnementale', 'it' => 'Autorizzazione paesaggistica'],
        'entsorgungsnachweis' => ['en' => 'Disposal certificate', 'es' => 'Certificado de eliminación', 'fr' => 'Justificatif d\'élimination', 'it' => 'Certificato di smaltimento'],
    ],
];
