#!/bin/sh
set -eu
if [ "${APP_DATABASE_PASSWORD:-not-configured}" = "not-configured" ]; then
    echo "Generate Docker configuration before initializing PostgreSQL." >&2
    exit 1
fi
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set=ON_ERROR_STOP=1 --set=app_password="$APP_DATABASE_PASSWORD" <<'SQL'
CREATE ROLE course_app WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD :'app_password';
ALTER DATABASE course_service OWNER TO course_app;
ALTER SCHEMA public OWNER TO course_app;
SQL
