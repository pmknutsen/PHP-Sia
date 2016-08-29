/*
Initialize PHP-Sia MySQL database:

mysql -u ADMIN -p < php-sia.sql


*/

/* Uncomment to reset database

DROP DATABASE  IF EXISTS phpsia;
DROP USER IF EXISTS phpsia;
DROP USER  IF EXISTS phpsiau;

*/

CREATE DATABASE IF NOT EXISTS phpsia;
CREATE USER IF NOT EXISTS 'phpsia'@'localhost' IDENTIFIED BY 'mysecret';
GRANT ALL PRIVILEGES ON phpsia. * TO 'phpsia'@'localhost';
USE phpsia;

/* Transactions (used by faucet) */
CREATE TABLE IF NOT EXISTS `phpsia` (
	`id` int(9) NOT NULL AUTO_INCREMENT,
	`type` enum('deposit','withdrawal','receivable') NOT NULL,
	`address` char(76) DEFAULT NULL,
	`remoteaddress` char(76) DEFAULT NULL,
	`txid` char(64) DEFAULT NULL,
	`hastings` char(35) NOT NULL,
	`added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`expire` timestamp NULL DEFAULT NULL,
	`blockheight` INT UNSIGNED NOT NULL,
	`msg` char(128) DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
