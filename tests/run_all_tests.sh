#!/bin/bash

php_versions=("php7.4" "php8.0" "php8.1" "php8.2" "php8.3")

count=${#php_versions[@]}


echo "Prettifying code first"
vendor/bin/phpcbf --standard=phpcs.xml

set -e
for ((i = 0; i < count; i++)); do
    if type "${php_versions[$i]}" &> /dev/null; then
        echo "Running tests for ${php_versions[$i]}"
        echo "  ${php_versions[$i]} vendor/bin/phpunit"
        ${php_versions[$i]} vendor/bin/phpunit

        echo "Running PHPStan"
        echo "  ${php_versions[$i]} vendor/bin/phpstan"
        ${php_versions[$i]} vendor/bin/phpstan

        echo "Running PHPCS"
        echo "  ${php_versions[$i]} vendor/bin/phpcs --standard=phpcs.xml -n"
        ${php_versions[$i]} vendor/bin/phpcs --standard=phpcs.xml -n
    fi
done