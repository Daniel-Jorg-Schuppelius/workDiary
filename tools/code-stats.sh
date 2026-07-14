#!/bin/bash
#
# workDiary Code-Statistik Script
# Analysiert den gesamten eigenen Code inkl. eigener Toolkits (Libraries)
#
# Autor: Daniel Jörg Schuppelius
# Lizenz: MIT
#

set -e

# Farben für Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Script-Verzeichnis ermitteln
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"          # workDiary
WORKSPACE_DIR="$(dirname "$APP_DIR")"        # Elternverzeichnis mit allen Repos

# Hauptprojekt (Laravel-App)
# Analysierte App-Verzeichnisse (kein vendor/, node_modules/)
APP_CODE_DIRS=(app config database routes lang scripts bootstrap)
APP_VIEW_DIRS=(resources)
APP_TEST_DIRS=(tests)

# Eigene Toolkits/Libraries (jeweils mit src/ und tests/)
TOOLKITS=(
    php-error-toolkit
    php-common-toolkit
    php-api-toolkit
    php-financial-formats
    php-erechnung-toolkit
    php-pdf-toolkit
    datev-php-sdk
    lexoffice-php-sdk
)

# Architektur-/Doku-Repo (Markdown)
DOCS_DIR="$WORKSPACE_DIR/WorkDiary-Architecture"

# Prüfen ob cloc installiert ist
if ! command -v cloc &>/dev/null; then
    echo -e "${RED}Fehler: cloc ist nicht installiert.${NC}"
    echo "Installation: sudo apt install cloc"
    exit 1
fi

# Hilfsfunktion für Trennlinie
print_separator() {
    echo -e "${CYAN}════════════════════════════════════════════════════════════════════════════════${NC}"
}

print_header() {
    echo ""
    print_separator
    echo -e "${BOLD}${BLUE}  $1${NC}"
    print_separator
}

# Optionen
DETAILED=false
BY_FILE=false
OUTPUT_FORMAT=""
EXCLUDE_TESTS=false
INCLUDE_DOCS=false

# Hilfe anzeigen
show_help() {
    echo "Verwendung: $0 [OPTIONEN]"
    echo ""
    echo "Analysiert die workDiary-App und alle eigenen Toolkits mit cloc."
    echo ""
    echo "Optionen:"
    echo "  -d, --detailed      Detaillierte Ausgabe pro Projekt"
    echo "  -f, --by-file       Zeige Statistik pro Datei"
    echo "  -t, --exclude-tests Tests ausschließen"
    echo "  --docs              WorkDiary-Architecture (Markdown-Doku) mit einbeziehen"
    echo "  --json              Ausgabe als JSON"
    echo "  --csv               Ausgabe als CSV"
    echo "  --md                Ausgabe als Markdown"
    echo "  -h, --help          Diese Hilfe anzeigen"
    echo ""
    echo "Beispiele:"
    echo "  $0                  Standard-Übersicht"
    echo "  $0 -d               Detailliert pro Projekt"
    echo "  $0 -t               Ohne Tests"
    echo "  $0 --md > stats.md  Als Markdown exportieren"
    exit 0
}

# Argumente parsen
while [[ $# -gt 0 ]]; do
    case $1 in
    -d | --detailed)
        DETAILED=true
        shift
        ;;
    -f | --by-file)
        BY_FILE=true
        shift
        ;;
    -t | --exclude-tests)
        EXCLUDE_TESTS=true
        shift
        ;;
    --docs)
        INCLUDE_DOCS=true
        shift
        ;;
    --json)
        OUTPUT_FORMAT="json"
        shift
        ;;
    --csv)
        OUTPUT_FORMAT="csv"
        shift
        ;;
    --md)
        OUTPUT_FORMAT="md"
        shift
        ;;
    -h | --help)
        show_help
        ;;
    *)
        echo "Unbekannte Option: $1"
        show_help
        ;;
    esac
done

# Basis-cloc-Optionen
CLOC_OPTS="--quiet"
CLOC_OPTS="$CLOC_OPTS --exclude-dir=vendor,node_modules,.git,var,storage,bootstrap-cache,build,dist,.phpunit.cache"

if [ "$EXCLUDE_TESTS" = true ]; then
    CLOC_OPTS="$CLOC_OPTS --not-match-d=(tests|test|__tests__)"
fi

case "$OUTPUT_FORMAT" in
json) CLOC_OPTS="$CLOC_OPTS --json" ;;
csv) CLOC_OPTS="$CLOC_OPTS --csv" ;;
md) CLOC_OPTS="$CLOC_OPTS --md" ;;
esac

if [ "$BY_FILE" = true ]; then
    CLOC_OPTS="$CLOC_OPTS --by-file"
fi

# Sammle alle zu analysierenden Verzeichnisse
DIRS_TO_ANALYZE=()

add_dir() {
    [ -d "$1" ] && DIRS_TO_ANALYZE+=("$1")
}

# workDiary-App
for d in "${APP_CODE_DIRS[@]}" "${APP_VIEW_DIRS[@]}"; do
    add_dir "$APP_DIR/$d"
done
if [ "$EXCLUDE_TESTS" = false ]; then
    for d in "${APP_TEST_DIRS[@]}"; do
        add_dir "$APP_DIR/$d"
    done
fi

# Toolkits
for tk in "${TOOLKITS[@]}"; do
    add_dir "$WORKSPACE_DIR/$tk/src"
    if [ "$EXCLUDE_TESTS" = false ]; then
        add_dir "$WORKSPACE_DIR/$tk/tests"
    fi
done

# Doku
if [ "$INCLUDE_DOCS" = true ]; then
    add_dir "$DOCS_DIR"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# Maschinenlesbare Formate: nur cloc-Rohausgabe
# ═══════════════════════════════════════════════════════════════════════════════
if [ -n "$OUTPUT_FORMAT" ]; then
    cloc $CLOC_OPTS "${DIRS_TO_ANALYZE[@]}"
    exit 0
fi

# ═══════════════════════════════════════════════════════════════════════════════
# Menschenlesbare Ausgabe
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║                          workDiary Code-Statistik                            ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Datum:${NC}      $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${YELLOW}Workspace:${NC}  $WORKSPACE_DIR"
echo -e "${YELLOW}Tests:${NC}      $([ "$EXCLUDE_TESTS" = true ] && echo 'ausgeschlossen' || echo 'einbezogen')"
echo ""

if [ "$DETAILED" = true ]; then
    # Detaillierte Ausgabe pro Projekt

    print_header "🗓️  workDiary (Laravel-App)"
    APP_DIRS=()
    for d in "${APP_CODE_DIRS[@]}" "${APP_VIEW_DIRS[@]}"; do
        [ -d "$APP_DIR/$d" ] && APP_DIRS+=("$APP_DIR/$d")
    done
    cloc $CLOC_OPTS "${APP_DIRS[@]}" 2>/dev/null || true

    if [ "$EXCLUDE_TESTS" = false ] && [ -d "$APP_DIR/tests" ]; then
        print_header "🧪 workDiary Tests"
        cloc $CLOC_OPTS "$APP_DIR/tests" 2>/dev/null || true
    fi

    print_header "📚 Eigene Toolkits (Libraries)"
    for tk in "${TOOLKITS[@]}"; do
        if [ -d "$WORKSPACE_DIR/$tk/src" ]; then
            echo -e "\n${YELLOW}→ $tk${NC}"
            TK_DIRS=("$WORKSPACE_DIR/$tk/src")
            [ "$EXCLUDE_TESTS" = false ] && [ -d "$WORKSPACE_DIR/$tk/tests" ] && TK_DIRS+=("$WORKSPACE_DIR/$tk/tests")
            cloc $CLOC_OPTS "${TK_DIRS[@]}" 2>/dev/null || true
        fi
    done

    if [ "$INCLUDE_DOCS" = true ] && [ -d "$DOCS_DIR" ]; then
        print_header "📝 WorkDiary-Architecture (Doku)"
        cloc $CLOC_OPTS "$DOCS_DIR" 2>/dev/null || true
    fi

    print_header "📊 GESAMT (Alle eigenen Projekte)"
fi

# Gesamtstatistik
cloc $CLOC_OPTS "${DIRS_TO_ANALYZE[@]}"

echo ""
print_separator
echo -e "${GREEN}Analyse abgeschlossen.${NC}"
echo ""
echo -e "${BOLD}Analysierte Bereiche:${NC}"
echo -e "  ${CYAN}•${NC} workDiary: ${APP_CODE_DIRS[*]} ${APP_VIEW_DIRS[*]}"
for tk in "${TOOLKITS[@]}"; do
    [ -d "$WORKSPACE_DIR/$tk/src" ] && echo -e "  ${CYAN}•${NC} $tk/src"
done
if [ "$EXCLUDE_TESTS" = false ]; then
    echo -e "  ${CYAN}•${NC} tests/ (in allen Projekten)"
fi
[ "$INCLUDE_DOCS" = true ] && echo -e "  ${CYAN}•${NC} WorkDiary-Architecture (Doku)"
echo ""
echo -e "${BOLD}Ausgeschlossen:${NC} vendor/, node_modules/, kimai/ (Fremdcode)"
echo ""
