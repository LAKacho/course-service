#!/bin/sh
set -eu
if [ "${APP_ENV:-prod}" != "test" ] && [ "${APP_SECRET:-not-configured}" = "not-configured" ]; then
    echo "Generate .env.docker and pass it with --env-file before starting the application." >&2
    exit 1
fi
mkdir -p var/cache var/log var/sessions
chown -R www-data:www-data var
# The named var volume survives image updates. Drop only the compiled production
# container so Symfony rebuilds it against the code in the newly deployed image.
if [ "${APP_ENV:-prod}" = "prod" ]; then
    rm -rf var/cache/prod
fi
exec "$@"
