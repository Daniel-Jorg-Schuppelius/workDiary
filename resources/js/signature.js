// Lazy-Entry für die Unterschriften-Felder (Stundenzettel signieren). Wird nur
// auf den wenigen Signatur-Seiten via @vite geladen, damit signature_pad nicht
// im globalen app.js-Bundle landet (das auf JEDER Seite ausgeliefert wird).
// Der Alpine-Component "signaturePad" (in alpine/components.js) nimmt
// window.SignaturePad, falls schon gesetzt; sonst lädt er signature_pad selbst
// per dynamischem Import (dieses Modul läuft erst nach Alpine.start()).
import SignaturePad from "signature_pad";

window.SignaturePad = SignaturePad;
