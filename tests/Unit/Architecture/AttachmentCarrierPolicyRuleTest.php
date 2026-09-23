<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentCarrierPolicyRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Anhänge folgen ihrem Träger" (Datenfluss-Audit
 * 2026-09-17, idor-attachment-5): `AttachmentPolicy::view` fiel für Träger
 * ohne eigene Policy auf „gleiche Organisation" zurück — wer eine
 * Anhang-Kennung kannte, lud damit auch Dateien eines Vorgangs, den er nicht
 * öffnen darf.
 *
 * Jedes Modell mit `HasAttachments` braucht deshalb eines von dreien:
 * eine eigene Policy, eine Auflösung auf sein Elternobjekt in
 * {@see \App\Policies\Attachments\AttachmentPolicy} oder einen Eintrag in der Liste
 * bewusst org-weiter Träger.
 */
class AttachmentCarrierPolicyRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Modellklasse (kurz) → Begründung für org-weiten Zugriff */
    private const ORG_WIDE = [
        'Organization' => 'Stammdaten der Organisation selbst.',
        'Comment' => 'Kommentare tragen die Sichtbarkeit ihres Trägers über die CommentPolicy.',
        'KnowledgeArticle' => 'Wissensartikel sind org-weit gedacht; die Policy prüft Sichtbarkeit.',
        'Construction/ConstructionNotice' => 'Bautagebuch-Mitteilung: org-weit einsehbar wie das Bauvorhaben.',
        'Guarantee/Guarantee' => 'Gewährleistungsfall: org-weit einsehbar (keine personenbezogenen Anlagen).',
        'CashEntry' => 'Kassenbeleg: org-weit einsehbar, GoBD-append-only.',
        'Claims/ClaimCase' => 'Reklamationsfall mit eigener Policy im Unterordner Claims.',
        'Learning/LearningQuestion' => 'Fragenpool: Anhang ist Teil der Frage, sichtbar wie der Kurs.',
        'ProblemReport' => 'Störungsmeldung: org-weit einsehbar, die Bearbeitung hängt an den Rechten der Seite.',
        'Shipment' => 'Sendung: org-weit einsehbar wie die Lieferung selbst.',
    ];

    public function test_every_attachment_carrier_is_covered(): void {
        $policySource = (string) file_get_contents($this->repoRoot() . '/app/Policies/Attachments/AttachmentPolicy.php');
        $missing = [];

        foreach ($this->phpFiles('app/Models') as $file) {
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'use HasAttachments;')) {
                continue;
            }

            $relative = $this->relativePath($file);
            $short = str_replace(['app/Models/', '.php'], '', $relative);
            $class = str_replace('/', '\\', $short);

            if (array_key_exists($short, self::ORG_WIDE)) {
                continue;
            }
            // Eigene Policy?
            if (file_exists($this->repoRoot() . '/app/Policies/' . $short . 'Policy.php')) {
                continue;
            }
            // Auflösung auf das Elternobjekt?
            if (str_contains($policySource, 'Models\\' . $class . ' =>')) {
                continue;
            }

            $missing[] = $short;
        }

        sort($missing);
        $this->assertSame([], $missing, "Anhang-Träger ohne Regel.\n"
            . "Entweder eine Policy für das Modell anlegen, es in AttachmentPolicy auf sein Elternobjekt auflösen\n"
            . "oder mit Begründung in ORG_WIDE aufnehmen.\n\n"
            . implode("\n", $missing));
    }
}
