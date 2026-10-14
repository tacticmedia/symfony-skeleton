#syntax=docker/dockerfile:1

# Versions
# 1.13 is the first release with Mercure 1.0, which the Caddyfile `issuer` blocks need.
FROM dunglas/frankenphp:1.13-php8.5-trixie AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/build/building/multi-stage/#stop-at-a-specific-build-stage
# https://docs.docker.com/reference/compose-file/build/#target


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

# persistent deps
# hadolint ignore=DL3008
RUN <<-EOF
    apt-get update
    apt-get install -y --no-install-recommends \
        file \
        git
    install-php-extensions \
        @composer \
        apcu \
        intl \
        pcntl \
        xsl \
        zip
    rm -rf /var/lib/apt/lists/*
EOF

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

# Installing the local CA needs root and write access to the trust stores, which only
# the dev image has. The dev stage clears this.
ENV CADDY_GLOBAL_OPTIONS=skip_install_trust

###> recipes ###
###> symfony/panther ###
# Chromium and ChromeDriver
ENV PANTHER_NO_SANDBOX=1
# Not mandatory, but recommended
ENV PANTHER_CHROME_ARGUMENTS='--disable-dev-shm-usage'
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends chromium chromium-driver && rm -rf /var/lib/apt/lists/*
###< symfony/panther ###
###> doctrine/doctrine-bundle ###
RUN install-php-extensions pdo_pgsql
###< doctrine/doctrine-bundle ###
###< recipes ###

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

# Static Go binary driving the `cron` compose service.
ARG TARGETARCH
ARG SUPERCRONIC_VERSION=v0.2.49
ADD --chmod=755 https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH} /usr/local/bin/supercronic
COPY --link frankenphp/crontab /etc/frankenphp/crontab

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch
# Caddy adds its local CA to the system store and, via certutil from libnss3-tools, to
# the NSS database Chromium reads. The container's own browser then trusts https://php,
# so the e2e journeys are not restricted to plain HTTP.
ENV CADDY_GLOBAL_OPTIONS=""

# dev dependencies
RUN <<-EOF
    apt-get update
    # nodejs, npm: playwright-php runs its bridge on Node.js 20 or later.
    apt-get install -y --no-install-recommends libnss3-tools nodejs npm
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
    # gd: tacticmedia/qa-bundle
    install-php-extensions gd xdebug
    git config --system --add safe.directory /app
    # Chromium reads NSS, not the system bundle. Caddy only installs the CA into a
    # database that already exists, so create an empty one for it to write to.
    mkdir -p /root/.pki/nssdb
    certutil -N --empty-password -d sql:/root/.pki/nssdb
    rm -rf /var/lib/apt/lists/*
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/
# The entrypoint calls these when present, which is only ever in this image.
COPY --link --chmod=755 frankenphp/adopt-unversioned-schema.sh /usr/local/bin/adopt-unversioned-schema
COPY --link --chmod=755 frankenphp/trust-local-ca.sh /usr/local/bin/trust-local-ca

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# Builder for the prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod_builder

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# copy sources
COPY --link --exclude=frankenphp/ . ./

RUN <<-EOF
    mkdir -p var/cache var/log var/share
    composer dump-autoload --classmap-authoritative --no-dev
    # Gzips the locale data; symfony/intl reads it compressed.
    php vendor/symfony/intl/Resources/bin/compress
    composer dump-env prod
    composer run-script --no-dev post-install-cmd
    php bin/console tailwind:build
    php bin/console asset-map:compile
    chmod +x bin/console
    sync
EOF

# Collect shared libraries needed by FrankenPHP and PHP extensions
# hadolint ignore=DL3008,SC3054,DL4006
RUN <<-'EOF'
    apt-get update
    apt-get install -y --no-install-recommends libtree
    mkdir -p /tmp/libs
    BINARIES=(frankenphp php)
    for target in $(printf '%s\n' "${BINARIES[@]}" | xargs -I{} which {}) \
        $(find "$(php -r 'echo ini_get("extension_dir");')" -maxdepth 2 -name "*.so"); do
        libtree -pv "$target" 2>/dev/null | grep -oP '(?:── )\K/\S+(?= \[)' | while IFS= read -r lib; do
            [ -f "$lib" ] && cp -n "$lib" /tmp/libs/
        done
    done
    rm -rf /var/lib/apt/lists/*
EOF

# The complete file tree of the prod image, an allow-list, so the prod stage copies it in one layer.
RUN <<-'EOF'
    rootfs=/rootfs
    mkdir -p $rootfs/app/assets $rootfs/app/bin $rootfs/app/var \
        $rootfs/usr/local/bin $rootfs/usr/local/lib/php $rootfs/usr/local/etc/php \
        $rootfs/usr/lib $rootfs/usr/share \
        $rootfs/etc/frankenphp $rootfs/etc/ssl/certs \
        $rootfs/data/caddy $rootfs/config/caddy

    # Kernel::getProjectDir() locates the project by composer.json.
    cp -a composer.json .env.local.php config migrations public src templates translations vendor $rootfs/app/
    rm $rootfs/app/config/reference.php
    cp -a bin/console $rootfs/app/bin/
    # Read at run time: the email stylesheets (@email in twig.yaml) and the UX icons.
    cp -a assets/email assets/icons $rootfs/app/assets/
    cp -a var/cache var/log var/share $rootfs/app/var/

    cp -a /usr/local/bin/frankenphp /usr/local/bin/php /usr/local/bin/docker-php-entrypoint \
        /usr/local/bin/docker-entrypoint /usr/local/bin/supercronic $rootfs/usr/local/bin/
    cp -a /usr/local/lib/php/extensions $rootfs/usr/local/lib/php/
    cp -a /usr/local/etc/php/conf.d /usr/local/etc/php/php.ini /usr/local/etc/php/app.conf.d $rootfs/usr/local/etc/php/
    cp -a /tmp/libs/. $rootfs/usr/lib/
    cp -a /etc/frankenphp/Caddyfile /etc/frankenphp/crontab $rootfs/etc/frankenphp/
    # Go's time package needs zoneinfo for the crontab's CRON_TZ; slim ships no tzdata.
    cp -a /usr/share/zoneinfo $rootfs/usr/share/
    # CA certificates for TLS. MIME type detection uses ext-fileinfo, so no `file` binary.
    cp -a /etc/ssl/certs/ca-certificates.crt $rootfs/etc/ssl/certs/
    cp -a /etc/ssl/openssl.cnf $rootfs/etc/ssl/

    # www-data is 33 in Debian. Group 0 + g=u for arbitrary-UID runtimes (e.g. OpenShift).
    chown -R 33:0 $rootfs/app/var
    chmod -R g=u $rootfs/app/var
    chown -R 33:33 $rootfs/data $rootfs/config
EOF

# Prod FrankenPHP image
FROM debian:13-slim AS frankenphp_prod

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

ENV APP_ENV=prod
ENV PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"
ENV OPENSSL_CONF=/etc/ssl/openssl.cnf XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data
# This stage starts from Debian, so it does not inherit the base stage's value.
ENV CADDY_GLOBAL_OPTIONS=skip_install_trust

# Remove setuid/setgid bits
RUN find / -xdev -perm /6000 -type f -exec chmod a-s {} + 2>/dev/null || true

COPY --link --from=frankenphp_prod_builder /rootfs /

USER www-data

WORKDIR /app

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]
