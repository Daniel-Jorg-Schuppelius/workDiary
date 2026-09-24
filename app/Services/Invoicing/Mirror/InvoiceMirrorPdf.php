<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMirrorPdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mirror;

use App\Models\Invoicing\Invoice;
use App\Plugins\Support\Mirror\Contracts\MirrorPdfRenderer;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Database\Eloquent\Model;

final class InvoiceMirrorPdf implements MirrorPdfRenderer {
    public function __construct(private readonly InvoicePdfRenderer $renderer) {}

    public function modelClass(): string {
        return Invoice::class;
    }

    public function pdf(Model $document): string {
        if (! $document instanceof Invoice) {
            throw new \InvalidArgumentException('Invoice erwartet.');
        }

        return $this->renderer->output($document);
    }
}
