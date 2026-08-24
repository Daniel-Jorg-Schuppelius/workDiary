<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Kundenrundschreiben (Feature 119): Versand läuft in der Queue
 * (CustomerCircularSendJob) in Batches — jeder Lauf bleibt unter dem
 * Laufzeitbudget, große Verteiler reichen sich selbst weiter
 * (Vollscan 2026-08-23, A3/J4).
 */
return [
    // Empfänger je Queue-Lauf.
    'batch_size' => (int) env('CIRCULAR_BATCH_SIZE', 200),
];
