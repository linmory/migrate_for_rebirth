list:
	@echo "cc-dev"
	@echo "run-dev"
	@echo "log-dev"

cc-dev:
	# sudo php app/console cache:clear --env=prod --no-debug
	rm -rf backup/*
	mysql -hlocalhost -uroot -ppassword scrapy<./truncate_table.sql

log-dev:
	tail -f ./logs/run-dev.log

run-dev:
	# sudo php app/console cache:clear --env=dev
	php index.php>./logs/run-dev.log 2>./logs/run-dev_error.log &
	tail -f ./logs/run-dev.log
