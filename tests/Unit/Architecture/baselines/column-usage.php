<?php

// ColumnUsageRuleTest (MVP-1620): Spalten ohne Lese- oder Schreibstelle — nur schrumpfen.
return [
    // Befunde (zugesagte Angabe nicht erfassbar) — Entscheidung der Hauptsitzung
    'lexoffice_invoice_handovers.external_file_id' => 'Rückverweis der automatischen Übergabe (Datei-ID in Lexoffice, MVP-834) — blockiert bis zum Herstellernachweis „Paket A“ (Feature 158); der manuelle Export hat keine Lexoffice-ID',
    'lexoffice_invoice_handovers.external_voucher_id' => 'Rückverweis der automatischen Übergabe (Beleg-ID in Lexoffice, MVP-834) — blockiert bis zum Herstellernachweis „Paket A“ (Feature 158); der manuelle Export hat keine Lexoffice-ID',
];
