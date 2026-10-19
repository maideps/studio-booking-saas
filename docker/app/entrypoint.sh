#!/bin/sh
# Gives every PHP container the same APP_KEY without committing one: when APP_KEY is not
# set, the first container generates a key into the shared app-secrets volume and the
# others read it. The worker and scheduler wait for the app to be healthy, so only the app
# ever writes the file.
set -e

KEY_FILE=/var/app-secrets/app_key

if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$KEY_FILE" ]; then
        php artisan key:generate --show --no-ansi > "$KEY_FILE.tmp"
        mv "$KEY_FILE.tmp" "$KEY_FILE"
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

exec "$@"
