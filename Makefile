.DEFAULT_GOAL := help
.PHONY: help
help: ## Show help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: cs check-cs test

cs: ## PHP-CS Fixer
	vendor/bin/php-cs-fixer fix

check-cs: ## PHP-CS Fixer check
	vendor/bin/php-cs-fixer check

test: ## Run PHPUnit tests
	symfony run vendor/bin/phpunit
