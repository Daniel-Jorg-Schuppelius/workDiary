---
applyTo: "lang/**"
---

# Übersetzungen (`lang/**`) — HART: niemals Keys löschen

Diese App nutzt inline `__('Deutscher Quelltext')`. **Der deutsche Text IST der
Übersetzungsschlüssel**, nicht der Anzeigewert einer Sprache. `de` ist die
Quellsprache; es gibt **kein** `lang/de.json`.

## Verbindlich

- **Deutscher Text als Key in `lang/en.json`, `lang/fr.json`, `lang/it.json`,
  `lang/es.json` ist KORREKT und PFLICHT** — der Key ist deutsch, der Wert daneben
  ist die Übersetzung. Beispiel (`lang/en.json`): `"Webhook anmelden": "Register webhook"`.
- **NIEMALS** deutsche/„non-German"-Strings aus en/fr/it/es.json entfernen,
  verschieben oder „aufräumen". Das ist **kein** Fehler, sondern die Konvention.
  Ein solcher „Fix" bricht sofort `Tests\Unit\Architecture\TranslationParityTest`
  und damit die CI. (Genau dieser Fehler ist wiederholt passiert.)
- Jeder im Quellcode verwendete `__('…')`-String **muss** in `lang/en.json`
  existieren (Referenz-Katalog) und in allen Locale-JSONs abgedeckt sein.
- Jeder Job in `config/scheduler.php` braucht das Label `scheduler.job.<key>` in
  **allen fünf** `lang/*/scheduler.php` (inkl. `de`).
- Namespaced Dateien (`lang/de/foo.php`) müssen in en/fr/it/es existieren und
  alle en-Keys abdecken.

## Vorgehen

- Fehlende Keys **ergänzen**, nicht vorhandene entfernen. Auto-Füllung:
  `php artisan lang:sync --fill`.
- Vor dem Commit prüfen:
  `php artisan test tests/Unit/Architecture/TranslationParityTest.php`.
- Wenn ein CodeQL-/Review-Finding auf eine Übersetzungsdatei zeigt: **nicht durch
  Löschen „lösen".** Ursache verstehen; im Zweifel den Key unangetastet lassen.
  Macht die Änderung die Parität rot, ist sie falsch.
