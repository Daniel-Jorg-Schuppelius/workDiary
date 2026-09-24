<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderMailIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\B2bCatalog\Mail;

use App\Models\B2b\B2bOrder;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\B2bCatalog\B2bOrderIntakeService;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\ParsedMessage;

/**
 * openTRANS-Bestellungen aus dem Postfach (Feature 099, MVP-458): jede
 * XML-Anlage wird als openTRANS-2.1-ORDER versucht — vor der E-Rechnung,
 * sonst frisst die den XML-Anhang. Dubletten (ORDER-ID + Käufer) erzeugen
 * keinen zweiten Vorschlag.
 */
final class B2bOrderMailIntakeHandler implements MailIntakeHandler {
    public function __construct(
        private readonly B2bOrderIntakeService $orders,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function priority(): int {
        return 10;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        if ($message->attachments === [] || ! $this->modules->isActiveFor($organization, 'module.b2b_katalog')) {
            return null;
        }

        $created = 0;
        $duplicates = 0;
        foreach ($message->attachments as $attachment) {
            if (! str_contains($attachment->mime, 'xml') && ! str_ends_with(strtolower($attachment->filename), '.xml')) {
                continue;
            }
            try {
                $result = $this->orders->intake($organization, $attachment->content, B2bOrder::SOURCE_MAIL);
            } catch (\RuntimeException) {
                continue; // kein openTRANS-ORDER → andere Handler
            }
            $result['status'] === 'created' ? $created++ : $duplicates++;
        }

        return $created > 0 ? 'b2b_order' : ($duplicates > 0 ? 'skipped' : null);
    }
}
