#!/bin/bash

set -e

LARAVEL_VERSION_CONSTRAINT="${1:-^12.0}"

echo "Install Laravel ${LARAVEL_VERSION_CONSTRAINT}"
composer create-project --quiet --prefer-dist "laravel/laravel:${LARAVEL_VERSION_CONSTRAINT}" ../laravel
cd ../laravel/
SAMPLE_APP_DIR="$(pwd)"
composer show --direct

echo "Add pacakge from source"
composer config minimum-stability dev
composer config repositories.0 '{ "type": "path", "url": "../laravel-mnb", "options": { "symlink": false } }'

# No version information with "type":"path"
composer require --dev --optimize-autoloader "csaba215/laravel-mnb:*"