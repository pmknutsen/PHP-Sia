# PHP-Sia
PHP-Sia provides a library of methods for PHP developers to communicate with the Sia RPC
server.

## Features
* View balances
* Send funds
* Upload, share and download files
* Create addresses
* Check deposits to addresses
* View transactions
* and more! See `sia.php` for complete list of methods.

## Sia API Reference Documentation
https://github.com/NebulousLabs/Sia/blob/master/doc/API.md

## Troubleshooting
* Install required PHP dependencies: `php5-curl`, `php5-json`
* Confirm RPC server is running by connecting from command line with cURL:
	`curl -s -X GET http://localhost:9980/consensus -A "Sia-Agent"`

## Usage
* Edit `sia.php` and set the class variables to your local setup
* Instantiate the `PHP_Sia` class
* View the `examples.php` file

## Donations (Siacoin)
04c1a3317df0e7cc29aaee9fdfc524cc4c7998ef3a204613fcb206d4773b5275572647d3bfea
