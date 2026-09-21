<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'panel_title' => 'Costi del materiale e utile',
    'add_title' => 'Assegnare costi del materiale',
    'source' => 'Origine dei costi',
    'source_hint' => 'Selezioni un documento d\'acquisto Lexoffice o inserisca un importo libero.',
    'voucher' => 'Documento d\'acquisto',
    'voucher_hint' => 'Facoltativo — è possibile un importo parziale; un documento può essere ripartito su più clienti.',
    'manual_amount' => '— Importo libero —',
    'description' => 'Descrizione',
    'description_hint' => 'Obbligatorio senza documento — denomina i costi del materiale.',
    'allocation' => 'Assegnazione',
    'amount' => 'Importo',
    'amount_hint' => 'Importo (parziale) assegnato al cliente.',
    'date' => 'Data',
    'project' => 'Progetto',
    'project_hint' => 'Facoltativo — per un\'assegnazione più dettagliata.',
    'no_project' => '— Nessun progetto —',
    'source_lexoffice' => 'Documento Lexoffice',
    'revenue' => 'Ricavi (fatturati)',
    'material_cost' => 'Costi del materiale',
    'profit' => 'Utile (calc.)',
    'margin' => 'margine',
    'range_hint' => 'Valori del periodo selezionato (:range).',
    'double_count_hint' => 'Vista gestionale (senza costi generali). Assegni il materiale tramite un documento d\'acquisto OPPURE tramite un prelievo da magazzino — non entrambi per la stessa merce.',
    'empty_hint' => 'Nessun costo del materiale assegnato finora. Usi «Assegnare costi del materiale» per attribuire documenti o importi liberi al cliente e rappresentare l\'utile.',
    'confirm_delete' => 'Rimuovere davvero questa assegnazione dei costi del materiale?',
    'delete' => 'Rimuovi',
    'flash_saved' => 'Costi del materiale assegnati.',
    'flash_deleted' => 'Assegnazione dei costi del materiale rimossa.',
    'error_description_required' => 'Indichi una descrizione se non è selezionato alcun documento.',
    'error_voucher_not_purchase' => 'Il documento selezionato non è un documento d\'acquisto.',
    'error_amount_over_voucher' => 'L\'importo supera il totale del documento.',
    'error_project_foreign' => 'Il progetto non appartiene a questo cliente.',
    'stock_title' => 'Prelevare dal magazzino',
    'stock_issue' => 'Preleva e registra',
    'stock_source' => 'Prelievo da magazzino',
    'stock_hint' => 'Valutazione al costo medio ponderato; il prelievo riduce la giacenza ed è registrato come costo del materiale.',
    'article' => 'Articolo',
    'warehouse' => 'Magazzino',
    'qty' => 'Quantità',
    'qty_hint' => 'Nell\'unità di base.',
    'choose' => '— Selezioni —',
    'source_stock' => 'Magazzino',
    'stock_item' => 'Articolo di magazzino',
    'flash_stock_issued' => 'Prelievo da magazzino registrato e assegnato come costo del materiale.',
    'book_to_customer' => 'Cliente dei costi del materiale',
    'no_customer' => '— Nessun cliente —',
];
