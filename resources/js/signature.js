// Lazy-Entry für die Unterschriften-Felder (Stundenzettel signieren). Wird nur
// auf den wenigen Signatur-Seiten via @vite geladen, damit signature_pad nicht
// im globalen app.js-Bundle landet (das auf JEDER Seite ausgeliefert wird).
// Der Alpine-Component "signaturePad" (in alpine/components.js) liest
// window.SignaturePad erst zur Init-Zeit, daher genügt es, die Klasse hier
// global bereitzustellen.
import SignaturePad from "signature_pad";

window.SignaturePad = SignaturePad;
