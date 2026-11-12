<?php

// StatusCaseSetterRuleTest (MVP-1620): Statusfälle ohne Setzer — nur schrumpfen.
// Nur begründete Ausnahmen; die Befunde des Scans sind in Paket 18b gebaut oder gestrichen.
return [
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Approved' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Filed' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::FormallyAcknowledged' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::ObjectedToRejection' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Offered' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Recognised' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Rejected' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Gaeb\\BoqChangeOrderStatus::Withdrawn' => 'Gesetzt aus dem GAEB-Import ($order->getStatus()->value, GaebImportService::persistChangeOrders) — Wert aus der Datei',
    'App\\Enums\\Ideas\\IdeaNodeStatus::Decided' => 'Gesetzt im Ideen-Editor (idea-editor.js) aus IdeaNodeStatus::cases(); node_status ist als Text validiert',
    'App\\Enums\\Ideas\\IdeaNodeStatus::Done' => 'Gesetzt im Ideen-Editor (idea-editor.js) aus IdeaNodeStatus::cases(); node_status ist als Text validiert',
    'App\\Enums\\Ideas\\IdeaNodeStatus::InReview' => 'Gesetzt im Ideen-Editor (idea-editor.js) aus IdeaNodeStatus::cases(); node_status ist als Text validiert',
    'App\\Enums\\Ideas\\IdeaNodeStatus::Open' => 'Gesetzt im Ideen-Editor (idea-editor.js) aus IdeaNodeStatus::cases(); node_status ist als Text validiert',
    'App\\Enums\\Ideas\\IdeaNodeStatus::Rejected' => 'Gesetzt im Ideen-Editor (idea-editor.js) aus IdeaNodeStatus::cases(); node_status ist als Text validiert',
    'App\\Plugins\\Lexoffice\\Enums\\LexofficeHandoverStatus::Failed' => 'Setzt erst die automatische Übergabe (MVP-834, blockiert bis zum Herstellernachweis „Paket A“, Feature 158); der manuelle Weg kennt nur ausstehend, exportiert, bestätigt — das Filter bietet nur diese an',
    'App\\Plugins\\Lexoffice\\Enums\\LexofficeHandoverStatus::NeedsReview' => 'Setzt erst die automatische Übergabe (MVP-834, blockiert bis zum Herstellernachweis „Paket A“, Feature 158); der manuelle Weg kennt nur ausstehend, exportiert, bestätigt — das Filter bietet nur diese an',
    'App\\Plugins\\Lexoffice\\Enums\\LexofficeHandoverStatus::Transferred' => 'Setzt erst die automatische Übergabe (MVP-834, blockiert bis zum Herstellernachweis „Paket A“, Feature 158); der manuelle Weg kennt nur ausstehend, exportiert, bestätigt — das Filter bietet nur diese an',
];
