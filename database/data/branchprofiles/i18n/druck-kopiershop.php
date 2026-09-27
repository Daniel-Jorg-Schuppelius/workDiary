<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : druck-kopiershop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „druck-kopiershop" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'datenpruefung' => ['en' => 'File check', 'es' => 'Comprobación de archivos', 'fr' => 'Contrôle des fichiers', 'it' => 'Verifica file'],
        'druckauftrag' => ['en' => 'Print job', 'es' => 'Pedido de impresión', 'fr' => 'Commande d\'impression', 'it' => 'Ordine di stampa'],
        'kopierauftrag' => ['en' => 'Copy/scan job', 'es' => 'Pedido de copia/escaneo', 'fr' => 'Commande de copie/numérisation', 'it' => 'Ordine di copia/scansione'],
        'grossformat' => ['en' => 'Large-format job', 'es' => 'Pedido de gran formato', 'fr' => 'Commande grand format', 'it' => 'Ordine grande formato'],
        'weiterverarbeitung' => ['en' => 'Finishing', 'es' => 'Acabado', 'fr' => 'Finition', 'it' => 'Finitura'],
        'versandauftrag' => ['en' => 'Shipping job', 'es' => 'Pedido de envío', 'fr' => 'Commande d\'expédition', 'it' => 'Ordine di spedizione'],
        'tresenverkauf' => ['en' => 'Counter sale', 'es' => 'Venta en mostrador', 'fr' => 'Vente au comptoir', 'it' => 'Vendita al banco'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
    'activity' => [
        'beraten' => ['en' => 'Advise', 'es' => 'Asesorar', 'fr' => 'Conseiller', 'it' => 'Consigliare'],
        'datenAnnehmen' => ['en' => 'Accept data', 'es' => 'Recibir los datos', 'fr' => 'Réceptionner les données', 'it' => 'Ricevere i dati'],
        'pruefen' => ['en' => 'Check (preflight)', 'es' => 'Comprobar (preflight)', 'fr' => 'Vérifier (preflight)', 'it' => 'Verificare (preflight)'],
        'kalkulieren' => ['en' => 'Calculate', 'es' => 'Calcular', 'fr' => 'Chiffrer', 'it' => 'Calcolare'],
        'freigeben' => ['en' => 'Approve', 'es' => 'Aprobar', 'fr' => 'Valider', 'it' => 'Approvare'],
        'drucken' => ['en' => 'Print', 'es' => 'Imprimir', 'fr' => 'Imprimer', 'it' => 'Stampare'],
        'kopieren' => ['en' => 'Copy / scan', 'es' => 'Copiar / escanear', 'fr' => 'Copier / numériser', 'it' => 'Copiare / scansionare'],
        'weiterverarbeiten' => ['en' => 'Finishing', 'es' => 'Postimpresión', 'fr' => 'Façonner', 'it' => 'Allestire'],
        'kontrollieren' => ['en' => 'Check quality', 'es' => 'Controlar la calidad', 'fr' => 'Contrôler la qualité', 'it' => 'Controllare la qualità'],
        'ausgeben' => ['en' => 'Issue / hand over', 'es' => 'Entregar / traspasar', 'fr' => 'Remettre / transmettre', 'it' => 'Consegnare / passare'],
        'versenden' => ['en' => 'Send', 'es' => 'Enviar', 'fr' => 'Envoyer', 'it' => 'Spedire'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'product_group' => [
        'visitenkarten' => ['en' => 'Business cards', 'es' => 'Tarjetas de visita', 'fr' => 'Cartes de visite', 'it' => 'Biglietti da visita'],
        'flyer' => ['en' => 'Flyers', 'es' => 'Folletos', 'fr' => 'Flyers', 'it' => 'Volantini'],
        'plakate' => ['en' => 'Posters', 'es' => 'Carteles', 'fr' => 'Affiches', 'it' => 'Manifesti'],
        'broschueren' => ['en' => 'Brochures', 'es' => 'Folletos', 'fr' => 'Brochures', 'it' => 'Opuscoli'],
        'geschaeftsdrucksachen' => ['en' => 'Business stationery', 'es' => 'Papelería comercial', 'fr' => 'Imprimés commerciaux', 'it' => 'Stampati commerciali'],
        'etiketten' => ['en' => 'Labels', 'es' => 'Etiquetas', 'fr' => 'Étiquettes', 'it' => 'Etichette'],
        'banner' => ['en' => 'Banner / large format', 'es' => 'Pancarta / gran formato', 'fr' => 'Bannière / grand format', 'it' => 'Banner / grande formato'],
        'fotodruck' => ['en' => 'Photo printing', 'es' => 'Impresión fotográfica', 'fr' => 'Impression photo', 'it' => 'Stampa fotografica'],
        'kopienScans' => ['en' => 'Copies / scans', 'es' => 'Copias / escaneos', 'fr' => 'Copies / scans', 'it' => 'Copie / scansioni'],
        'bindungen' => ['en' => 'Bindings', 'es' => 'Encuadernaciones', 'fr' => 'Reliures', 'it' => 'Rilegature'],
        'mailings' => ['en' => 'Personalised mailings', 'es' => 'Envíos personalizados', 'fr' => 'Publipostages personnalisés', 'it' => 'Mailing personalizzati'],
    ],
    'defect_type' => [
        'datenFehlerhaft' => ['en' => 'Print data faulty', 'es' => 'Datos de impresión erróneos', 'fr' => 'Fichiers d\'impression erronés', 'it' => 'Dati di stampa errati'],
        'aufloesungZuGering' => ['en' => 'Resolution too low', 'es' => 'Resolución insuficiente', 'fr' => 'Résolution trop faible', 'it' => 'Risoluzione troppo bassa'],
        'beschnittFehlt' => ['en' => 'Bleed missing', 'es' => 'Falta el sangrado', 'fr' => 'Fond perdu manquant', 'it' => 'Abbondanza mancante'],
        'farbabweichung' => ['en' => 'Colour deviation', 'es' => 'Desviación de color', 'fr' => 'Écart de couleur', 'it' => 'Scostamento di colore'],
        'schriftenNichtEingebettet' => ['en' => 'Fonts not embedded', 'es' => 'Fuentes no incrustadas', 'fr' => 'Polices non incorporées', 'it' => 'Font non incorporati'],
        'falschesMaterial' => ['en' => 'Wrong material', 'es' => 'Material incorrecto', 'fr' => 'Mauvais matériau', 'it' => 'Materiale errato'],
        'schneidfehler' => ['en' => 'Cutting/folding error', 'es' => 'Error de corte/plegado', 'fr' => 'Erreur de coupe/pliage', 'it' => 'Errore di taglio/piegatura'],
        'bindungDefekt' => ['en' => 'Binding defective', 'es' => 'Encuadernación defectuosa', 'fr' => 'Reliure défectueuse', 'it' => 'Rilegatura difettosa'],
        'mengeFalsch' => ['en' => 'Wrong quantity', 'es' => 'Cantidad incorrecta', 'fr' => 'Quantité erronée', 'it' => 'Quantità errata'],
        'maschinenstoerung' => ['en' => 'Machine fault', 'es' => 'Avería de la máquina', 'fr' => 'Panne de machine', 'it' => 'Guasto alla macchina'],
        'terminUeberschritten' => ['en' => 'Deadline exceeded', 'es' => 'Plazo superado', 'fr' => 'Échéance dépassée', 'it' => 'Scadenza superata'],
    ],
    'root_cause' => [
        'kundendaten' => ['en' => 'Customer data', 'es' => 'Datos del cliente', 'fr' => 'Données client', 'it' => 'Dati del cliente'],
        'datenpruefung' => ['en' => 'Data check', 'es' => 'Revisión de datos', 'fr' => 'Contrôle des données', 'it' => 'Controllo dati'],
        'bedienung' => ['en' => 'Service staff', 'es' => 'Servicio de sala', 'fr' => 'Service', 'it' => 'Servizio'],
        'maschine' => ['en' => 'Machine / device', 'es' => 'Máquina / equipo', 'fr' => 'Machine / appareil', 'it' => 'Macchina / apparecchio'],
        'material' => ['en' => 'Material / substrate', 'es' => 'Material / soporte de impresión', 'fr' => 'Matériau / support d\'impression', 'it' => 'Materiale / supporto di stampa'],
        'kalibrierung' => ['en' => 'Calibration / colour profile', 'es' => 'Calibración / perfil de color', 'fr' => 'Étalonnage / profil couleur', 'it' => 'Calibrazione / profilo colore'],
        'weiterverarbeitung' => ['en' => 'Finishing', 'es' => 'Acabado', 'fr' => 'Façonnage', 'it' => 'Allestimento'],
        'transport' => ['en' => 'Transport / shipping', 'es' => 'Transporte / envío', 'fr' => 'Transport / expédition', 'it' => 'Trasporto / spedizione'],
        'planung' => ['en' => 'Planning / appointment', 'es' => 'Planificación / cita', 'fr' => 'Planification / rendez-vous', 'it' => 'Pianificazione / appuntamento'],
    ],
    'result' => [
        'datenOk' => ['en' => 'Data OK', 'es' => 'Datos correctos', 'fr' => 'Données correctes', 'it' => 'Dati corretti'],
        'datenKorrigiert' => ['en' => 'Data corrected', 'es' => 'Datos corregidos', 'fr' => 'Données corrigées', 'it' => 'Dati corretti'],
        'freigegeben' => ['en' => 'Approved', 'es' => 'Aprobado', 'fr' => 'Validé', 'it' => 'Approvato'],
        'produziert' => ['en' => 'Produced', 'es' => 'Producido', 'fr' => 'Produit', 'it' => 'Prodotto'],
        'nacharbeit' => ['en' => 'Rework required', 'es' => 'Retrabajo necesario', 'fr' => 'Retouche nécessaire', 'it' => 'Rilavorazione necessaria'],
        'ausgegeben' => ['en' => 'Issued / handed over', 'es' => 'Entregado / traspasado', 'fr' => 'Remis / transmis', 'it' => 'Consegnato / passato'],
        'versendet' => ['en' => 'Sent', 'es' => 'Enviado', 'fr' => 'Envoyé', 'it' => 'Spedito'],
        'storniert' => ['en' => 'Cancelled', 'es' => 'Anulado', 'fr' => 'Annulé', 'it' => 'Stornato'],
    ],
    'priority' => [
        'standard' => ['en' => 'Standard', 'es' => 'Estándar', 'fr' => 'Standard', 'it' => 'Standard'],
        'express' => ['en' => 'Express', 'es' => 'Exprés', 'fr' => 'Express', 'it' => 'Espresso'],
        'overnight' => ['en' => 'Overnight', 'es' => 'Nocturno', 'fr' => 'Express de nuit', 'it' => 'Consegna notturna'],
        'sofort' => ['en' => 'Immediately (counter)', 'es' => 'Inmediato (mostrador)', 'fr' => 'Immédiat (comptoir)', 'it' => 'Subito (banco)'],
    ],
    'rework_reason' => [
        'farbkorrektur' => ['en' => 'Colour correction', 'es' => 'Corrección de color', 'fr' => 'Correction des couleurs', 'it' => 'Correzione colore'],
        'neudruck' => ['en' => 'Reprint', 'es' => 'Reimpresión', 'fr' => 'Réimpression', 'it' => 'Ristampa'],
        'nachschnitt' => ['en' => 'Re-cut', 'es' => 'Recorte', 'fr' => 'Recoupe', 'it' => 'Rifilatura'],
        'neubindung' => ['en' => 'Rebinding', 'es' => 'Reencuadernación', 'fr' => 'Nouvelle reliure', 'it' => 'Nuova rilegatura'],
        'mengenergaenzung' => ['en' => 'Quantity addition', 'es' => 'Ampliación de cantidad', 'fr' => 'Complément de quantité', 'it' => 'Integrazione della quantità'],
    ],
    'dienstmittel_type' => [
        'digitaldruckmaschine' => ['en' => 'Digital press', 'es' => 'Impresora digital', 'fr' => 'Presse numérique', 'it' => 'Macchina da stampa digitale'],
        'offsetmaschine' => ['en' => 'Offset press', 'es' => 'Máquina offset', 'fr' => 'Presse offset', 'it' => 'Macchina offset'],
        'grossformatdrucker' => ['en' => 'Large-format printer', 'es' => 'Impresora de gran formato', 'fr' => 'Imprimante grand format', 'it' => 'Stampante grande formato'],
        'kopierer' => ['en' => 'Copier / MFP', 'es' => 'Copiadora / MFP', 'fr' => 'Copieur / MFP', 'it' => 'Fotocopiatrice / MFP'],
        'schneidemaschine' => ['en' => 'Guillotine', 'es' => 'Guillotina', 'fr' => 'Massicot', 'it' => 'Taglierina'],
        'falzmaschine' => ['en' => 'Folding machine', 'es' => 'Plegadora', 'fr' => 'Plieuse', 'it' => 'Piegatrice'],
        'bindegeraet' => ['en' => 'Binding machine', 'es' => 'Encuadernadora', 'fr' => 'Relieuse', 'it' => 'Rilegatrice'],
        'laminiergeraet' => ['en' => 'Laminator', 'es' => 'Plastificadora', 'fr' => 'Plastifieuse', 'it' => 'Plastificatrice'],
        'plotter' => ['en' => 'Cutting plotter', 'es' => 'Plóter de corte', 'fr' => 'Plotter de découpe', 'it' => 'Plotter da taglio'],
    ],
];
