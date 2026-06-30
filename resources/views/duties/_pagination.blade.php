{{-- Wiederverwendbarer Pagination-Block für Duties-Tabs.
     Delegiert an die zentrale <x-pagination>-Komponente im stehenden Modus
     (Footer-Panel unter dem main), damit ALLE Seiten dasselbe Verhalten nutzen. --}}
<x-pagination :paginator="$paginator" standing />
