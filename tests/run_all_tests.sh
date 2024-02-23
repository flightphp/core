#!/bin/bash

# Run all tests
composer lint
composer beautify
composer phpcs
composer test-coverage
xdg-open http://localhost:8000
composer test-server