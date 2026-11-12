{{-- Einleitung der Beleg-PDFs: Kopftext des Designs, sofern der Block „Einleitung“ dynamisch ist (MVP-1609). --}}
@if ($design->show(\App\Enums\DocumentDesign\InformationBlock::IntroText) && $design->headerText() !== null)
    <div class="tpl-header">{{ $design->headerText() }}</div>
@endif
