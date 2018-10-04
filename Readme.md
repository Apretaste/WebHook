To use Multi Instance must add a line in crontab for each configuration added like this:


       * *		* * *	root	php /home/apretaste/MailListenerMultiThreads/start.php -c0 >> /var/log/apretaste/webhook0.log


the -c option is the index of the configuration to be used from configurations list.