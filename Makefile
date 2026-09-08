.PHONY: help install lint format analyse smoke clean

help:
	@echo "CRNP dev tooling"
	@echo "  make install  - composer install (dev deps)"
	@echo "  make lint     - php-cs-fixer check (no changes)"
	@echo "  make format   - php-cs-fixer apply"
	@echo "  make analyse  - phpstan analyse"
	@echo "  make smoke    - offline tests (firebaseRDB token paths)"
	@echo "  make clean    - drop vendor/ and tool caches"

install:
	composer install --no-interaction

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff --no-interaction

format:
	vendor/bin/php-cs-fixer fix --no-interaction

analyse:
	vendor/bin/phpstan analyse --no-progress

smoke:
	php tests/smoke_token.php && php tests/indexed_rules.php && php tests/cashier_poll.php && php tests/otp_resend.php && php tests/mailer_api.php && php tests/uploads_pipeline.php

clean:
	rm -rf vendor .php-cs-fixer.cache
