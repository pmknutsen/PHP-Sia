# PHP-Sia
PHP Integration of Sia

PHP-Sia provides a library of methods for PHP developers to communicate with the Sia RPC
server.

## Features
* View balances
* Send funds
* Upload, share and download files
* Create addresses
* Check deposits to addresses
* View transactions
* see "sia.php" for complete list of class methods

## Sia API Reference Documentation
https://github.com/NebulousLabs/Sia/blob/master/doc/API.md

## Troubleshooting
* Install required PHP dependencies: php5-curl, php5-json
* Confirm RPC server is running by connecting from command line with cURL:
	`curl -s -X GET http://localhost:9980/consensus -A "Sia-Agent"`

## Usage
* Edit "sia.php" and set the class variables to your local setup
* Instantiate the PHP_Sia class
* View the "examples.php" file

## Donations:
* SC: b20bb2aa59fc1a12c12eda5bd9c1be533b17dad8c1ef61d5b56fdff41e5fd38a3da6f83eaf3b
* XMR: 49RPpNuDuLhayv8yHgVSNhgdvB4Uze3A9euEsBzp3groWssk2eZPEErf6LSDae9smQ78a5CfNmafYdgYnyjTEY6q4EvuPJ1

