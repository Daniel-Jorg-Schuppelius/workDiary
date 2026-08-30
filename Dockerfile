# syntax=docker/dockerfile:1.7
#
# WorkDiary — Container-Image für On-Premise-/Single-Host-Betrieb (MVP-720,
# Feature 015). Betreiber-Handbuch: docs/on-premise-docker.md.
#
# Stages:
#   base    php:8.4-fpm-bookworm + PHP-Extensions + System-Binaries der
#           Toolkits (ConfigToolkit\CommandBuilder-Konfiguration, s. u.)
#   vendor  composer install --no-dev (Scripts laufen erst im Runtime-Stage)
#   assets  npm ci && npm run build (Vite; ALPINE_CSP_BUILD als Build-Arg)
#   web     nginx mit deploy/docker/nginx.conf + public/ (Ziel: --target web)
#   app     Runtime (Default-Ziel): non-root, Entrypoint, Healthcheck
#
# Build:   docker build -t workdiary:local .
#          docker build --target web -t workdiary-web:local .
# Private Composer-Pakete (composer.local.json ist ausgeschlossen): Build mit
#   --secret id=composer_auth,src=$HOME/.composer/auth.json

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---------------------------------------------------------------------------
# base: PHP-Runtime + Extensions + System-Binaries
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS base

# Optionale, große Zusatzpakete (Default aus): 1 = installieren.
#   WD_WITH_LIBREOFFICE  Office→PDF-Konvertierung (office_executables.json)
#   WD_WITH_JAVA         KoSIT-Validator/PDFBox/pdftk (java-*, pdftk-java)
#   WD_WITH_INOTIFY      ext-inotify für integrity:watch (composer.json suggest)
#   WD_WITH_FFMPEG       Video-Transcoding (Feature 150; media_executables.json)
ARG WD_WITH_LIBREOFFICE=0
ARG WD_WITH_JAVA=0
ARG WD_WITH_INOTIFY=0
ARG WD_WITH_FFMPEG=0

# System-Binaries — Quelle je Zeile: die CommandBuilder-Konfiguration der
# Toolkits (vendor/dschuppelius/php-common-toolkit/config/*_executables.json,
# vendor/daniel-jorg-schuppelius/php-pdf-toolkit/config/executables.json) bzw.
# app-seitige Symfony-Process-Aufrufe. Fehlende Binaries sind nie fatal: der
# ConfigToolkit lädt den Eintrag als „nicht verfügbar" und deaktiviert die
# abhängige Funktion (ExecutableConfigType).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        # pdf_executables.json (pdfinfo, required) + pdf-toolkit (pdftotext required, pdftoppm, pdfdetach, pdfunite)
        poppler-utils \
        # pdf_executables.json valid-pdf (mutool, required)
        mupdf-tools \
        # pdf_executables.json pdf-decrypt/-encrypt/-check + pdf-toolkit Rotation
        qpdf \
        # image_executables.json (convert/identify), tiff_executables.json (required), pdf-toolkit Deskew (mogrify)
        imagemagick \
        # tiff_executables.json tiff2pdf/tiffcp/tiffinfo (required)
        libtiff-tools \
        # pdf-toolkit gs (Crop/Resize); zugleich Abhängigkeit von ocrmypdf
        ghostscript \
        # pdf-toolkit tesseract (required) — settings.json tesseract_lang = deu+eng
        tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng \
        # pdf-toolkit ocrmypdf (required)
        ocrmypdf \
        # common_executables.json mimetype/mime-encoding (file + /usr/share/misc/magic)
        file \
        # scripts/backup.sh + BackupSnapshotBuilder (mysqldump), SQLite-Online-Backup (sqlite3)
        mariadb-client sqlite3 \
        # InvoicePdfImportService::legacyWordText (catdoc, optional)
        catdoc \
        # deploy/docker/healthcheck.sh (cgi-fcgi → /up), Zertifikate, Zeitzonen
        libfcgi-bin ca-certificates tzdata curl \
    ; \
    if [ "$WD_WITH_JAVA" = "1" ]; then \
        # common_executables.json java/java-program (KoSIT validator.jar, PDFBox), pdf-toolkit pdftk
        apt-get install -y --no-install-recommends default-jre-headless pdftk-java; \
    fi; \
    if [ "$WD_WITH_LIBREOFFICE" = "1" ]; then \
        # office_executables.json libreoffice --headless --convert-to
        apt-get install -y --no-install-recommends libreoffice-writer libreoffice-calc libreoffice-impress; \
    fi; \
    if [ "$WD_WITH_FFMPEG" = "1" ]; then \
        # media_executables.json ffmpeg/ffmpeg-info — ohne dieses Paket bleibt
        # der media-Dienst arbeitslos und Videos hängen in „pending".
        # Whisper (maschinelle Untertitel) ist bewusst NICHT im Bild: ein
        # Python-Stack mit Modellgewichten vervielfacht die Bildgröße.
        apt-get install -y --no-install-recommends ffmpeg; \
    fi; \
    rm -rf /var/lib/apt/lists/*

# PHP-Extensions: composer.json der Toolkits (ext-bcmath/dom/intl/zip:
# common-toolkit, ext-gd: pdf-toolkit, ext-mbstring: translation-toolkit,
# ext-libxml: erechnung-toolkit) + Laravel/DB-Treiber (pdo_mysql, pdo_pgsql;
# pdo_sqlite/sodium/mbstring/dom/curl/fileinfo sind im Basis-Image enthalten).
# pcntl: queue:work-Signale (SIGTERM-Graceful), exif: Bild-Orientierung,
# redis: Profil „redis" in compose.yml, inotify: optional (integrity:watch).
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN set -eux; \
    install-php-extensions pdo_mysql pdo_pgsql intl gd zip bcmath opcache pcntl exif redis; \
    if [ "$WD_WITH_INOTIFY" = "1" ]; then install-php-extensions inotify; fi

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-workdiary.ini
COPY deploy/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-workdiary.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# vendor: Composer-Abhängigkeiten ohne Dev-Pakete
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY composer.json composer.lock ./
# Zwei Schritte, damit der Paket-Download als Layer gecacht bleibt und nur der
# optimierte Autoloader den Quellbaum sieht. --no-scripts: package:discover
# läuft im app-Stage. composer.local.json (private Pakete) ist per
# .dockerignore ausgeschlossen — Build aus einem sauberen Checkout
# (ComposerLockHygieneTest), sonst Secret composer_auth mitgeben.
RUN --mount=type=cache,target=/root/.composer/cache \
    --mount=type=secret,id=composer_auth,dst=/root/.composer/auth.json \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------
# assets: Vite-Build (Tailwind scannt Views/App; Pagination-Views aus vendor)
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets

# Muss zum Laufzeit-Flag ALPINE_CSP_BUILD passen (vite.config.js ↔
# config/security.php); Umschalten erfordert einen neuen Image-Build.
ARG ALPINE_CSP_BUILD=true
ENV ALPINE_CSP_BUILD=${ALPINE_CSP_BUILD}

WORKDIR /build
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund

COPY . .
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------------------------------------------------------------------------
# web: nginx mit public/ (Ziel: docker build --target web)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY deploy/docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/
COPY --from=assets /build/public/build /var/www/html/public/build
# /storage → storage/app/public (Symlink relativ; storage-Volume wird ro gemountet)
RUN ln -s ../storage/app/public /var/www/html/public/storage

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null || exit 1

# ---------------------------------------------------------------------------
# app: Runtime (Default-Ziel)
# ---------------------------------------------------------------------------
FROM base AS app

ARG WD_VERSION=0.1.0-dev
ENV APP_VERSION=${WD_VERSION} \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY --chown=root:root . .
COPY --from=vendor --chown=root:root /var/www/html/vendor ./vendor
COPY --from=assets --chown=root:root /build/public/build ./public/build

RUN set -eux; \
    # Storage-Skelett (per .dockerignore leer) + Schreibrechte für den Betriebs-User
    mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/framework/testing \
             storage/logs bootstrap/cache; \
    ln -sfn ../storage/app/public public/storage; \
    # Paket-Manifest (bootstrap/cache/packages.php) — im Entrypoint erneut, falls das Volume leer startet
    php artisan package:discover --ansi; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache; \
    install -m 0755 deploy/docker/entrypoint.sh /usr/local/bin/wd-entrypoint; \
    install -m 0755 deploy/docker/healthcheck.sh /usr/local/bin/wd-healthcheck; \
    rm -f .env .env.docker .env.testing

# storage: persistente Daten (compose: benanntes Volume, geteilt).
# bootstrap/cache: pro Container ephemer (anonymes Volume) — die Caches werden
# vom Entrypoint bei jedem Start neu gebaut, nie zwischen Diensten geteilt.
VOLUME ["/var/www/html/storage", "/var/www/html/bootstrap/cache"]

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD ["/usr/local/bin/wd-healthcheck"]

ENTRYPOINT ["/usr/local/bin/wd-entrypoint"]
CMD ["php-fpm"]
