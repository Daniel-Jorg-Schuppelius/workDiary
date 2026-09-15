<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentResponseCspTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Models\{LexofficeVoucher, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route, Storage};
use Tests\TestCase;

/**
 * Dokument-CSP für PDF-/Bildantworten (Belegvorschau beim Kunden leer, 2026-09-15):
 * Chrome und Edge wenden die CSP der PDF-Antwort auf ihren Viewer an — mit der
 * Seiten-CSP (`object-src 'none'`) blieb jede Inline-PDF-Anzeige blockiert.
 */
final class DocumentResponseCspTest extends TestCase {
    use RefreshDatabase;

    public function test_pdf_antwort_traegt_dokument_csp_mit_object_src_self(): void {
        Route::middleware('web')->get('/_csp/pdf', fn () => response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));

        $csp = (string) $this->get('/_csp/pdf')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertSame(SecurityHeaders::DOCUMENT_CSP, $csp);
        $this->assertStringContainsString("object-src 'self'", $csp);
        $this->assertStringNotContainsString('script-src', $csp);
    }

    public function test_bildantwort_traegt_dokument_csp(): void {
        Route::middleware('web')->get('/_csp/bild', fn () => response('png', 200, ['Content-Type' => 'image/png; charset=binary']));

        $this->assertSame(SecurityHeaders::DOCUMENT_CSP, (string) $this->get('/_csp/bild')->headers->get('Content-Security-Policy'));
    }

    public function test_html_antwort_behaelt_seiten_csp(): void {
        Route::middleware('web')->get('/_csp/html', fn () => response('<p>ok</p>'));

        $csp = (string) $this->get('/_csp/html')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('script-src', $csp);
    }

    public function test_eigene_csp_der_antwort_bleibt_unangetastet(): void {
        Route::middleware('web')->get('/_csp/eigen', fn () => response('%PDF-1.4', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Security-Policy' => "default-src 'none'",
        ]));

        $this->assertSame("default-src 'none'", (string) $this->get('/_csp/eigen')->headers->get('Content-Security-Policy'));
    }

    public function test_lexoffice_belegbild_kommt_mit_dokument_csp(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $path = "lexoffice-vouchers/{$org->id}/1.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4');
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $org->id,
            'external_id' => 'lx-1',
            'voucher_type' => 'purchaseinvoice',
            'voucher_number' => 'LX-1',
            'currency' => 'EUR',
            'archived' => false,
            'payload' => [],
        ]);
        $voucher->forceFill(['file_path' => $path, 'file_materialized_at' => now()])->save();

        $response = $this->actingAs($admin)->get(route('lexoffice.vouchers.file', $voucher));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Security-Policy', SecurityHeaders::DOCUMENT_CSP);
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_rechnungsimport_vorschau_nutzt_iframe_statt_object(): void {
        $view = (string) file_get_contents(resource_path('views/invoices/import-review.blade.php'));

        // <object> fällt unter object-src 'none' der Seiten-CSP; iframe wie Belegvorschau und Dokumentdesign.
        $this->assertStringNotContainsString('<object', $view);
        $this->assertStringContainsString('<iframe', $view);
    }
}
