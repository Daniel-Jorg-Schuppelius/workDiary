<?php

// SettingWriterRuleTest (MVP-1620): gelesene Einstellungen ohne Schreibstelle — nur schrumpfen.
return [
    // Begründete Ausnahmen
    'routing.nominatim.timeout' => 'Installationswert aus .env (ROUTING_NOMINATIM_TIMEOUT), Zeitlimit des Geocoders für die ganze Installation, keine Organisationseinstellung',
    'routing.nominatim.user_agent' => 'Installationswert aus .env (ROUTING_NOMINATIM_UA), Kennung der Installation gegenüber Nominatim, keine Organisationseinstellung',
    'valuation_method' => 'Rückfall auf den Altschlüssel vor inventory.valuation_method; ValuationMethodSwitch entfernt ihn beim nächsten Umschalten',
];
