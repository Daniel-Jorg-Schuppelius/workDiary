<?php

// WriteRouteEntryRuleTest (MVP-1620): schreibende Routen ohne Einstieg — nur schrumpfen.
return [
    // Begründete Ausnahmen (Einstieg, den die Messung nicht sieht)
    'customer.password.update' => 'Das Formular postet an die signierte Adresse der Seite selbst (action = $request->getRequestUri(), PasswordResetController::show)',
    'learning.courses.units.uploads.chunk' => 'Teil-Upload: chunk-upload.js hängt Token und Index an die Start-URL aus data-chunk-upload (courses.units.uploads.start)',
];
