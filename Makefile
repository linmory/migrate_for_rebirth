list:
	@echo "cc-dev"
	@echo "log-dev"
	@echo "user-dev"
	@echo "post-dev"
	@echo "livenews-dev"


cc-all:
	sudo rm -rf ../wallstreetcn/public/uploads/*
	mysql -hlocalhost -uroot -ppassword scrapy<./sql/truncate_tables.sql

log-dev:
	tail -f ./logs/run-dev.log

run-all:
	php user.php 0 >./logs/user-run-dev.log 2>./logs/user-run-dev_error.log &
	php post.php 0 >./logs/post-run-dev.log 2>./logs/post-run-dev_error.log &
	php livenews.php 0 >./logs/livenews-run-dev.log 2>./logs/livenews-run-dev_error.log &

user-dev:
	php user.php 0 >./logs/user-run-dev.log 2>./logs/user-run-dev_error.log &
	tail -f ./logs/user-run-dev.log

post-dev:
	php post.php 0 >./logs/post-run-dev.log 2>./logs/post-run-dev_error.log &
	tail -f ./logs/post-run-dev.log

livenews-dev:
	php livenews.php 0 >./logs/livenews-run-dev.log 2>./logs/livenews-run-dev_error.log &
	tail -f ./logs/livenews-run-dev.log