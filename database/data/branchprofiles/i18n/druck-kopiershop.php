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
];
