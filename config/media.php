<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : media.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Medienverarbeitung (Feature 150).
 *
 * **Warum das nicht in config/ai.php steht:** die KI-Registry dort beschreibt
 * Einsatzstellen, die Daten an einen Anbieter geben — mit Sensibilität,
 * Datenklassen, Budget und Datenfluss-Anzeige (Feature 016/025). Whisper und
 * ffmpeg laufen auf demselben Rechner wie die Anwendung; es verlässt kein
 * Byte den Server, es gibt keine Anbieterverbindung und kein Budget. Die
 * OCR-Strecke (Tesseract) ist aus demselben Grund dort nicht registriert.
 */
return [
    /*
     * Spracherkennung für Untertitel (Whisper, lokal).
     *
     * `model`: tiny|base|small|medium|large — größer heißt genauer und
     * deutlich langsamer. `base` ist der Kompromiss für CPU-Betrieb.
     * `model_dir`: Ablage der Modellgewichte; leer = Whisper-Standard
     * (~/.cache/whisper). Auf einem Webserver ohne Home-Verzeichnis muss
     * das gesetzt sein, sonst lädt jeder Lauf das Modell erneut.
     */
    'transcription' => [
        'model' => (string) env('WHISPER_MODEL', 'base'),
        'model_dir' => (string) env('WHISPER_MODEL_DIR', ''),
        'device' => (string) env('WHISPER_DEVICE', 'cpu'),
    ],
];
