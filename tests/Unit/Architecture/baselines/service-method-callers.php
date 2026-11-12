<?php

// ServiceMethodCallerRuleTest (MVP-1620): öffentliche Dienstmethoden ohne Aufrufer außerhalb von Tests
// und Demo-Blöcken — nur schrumpfen. Stand 2026-11-11: Befunde gebaut, toter Rest samt Testumbau entfernt (Paket 18c).
return [
    // Begründete Ausnahmen
    'app/Services/DocumentDesign/PdfGeneratorInventory.php::coveredKindValues' => 'Prüfgrundlage des Gates PdfGeneratorRegistryRuleTest (Vollständigkeit gegen RenderDocumentKind), kein Laufzeitpfad',
];
