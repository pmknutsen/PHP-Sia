# PHP-Sia
PHP-Sia provides a library of methods for PHP developers to communicate with the Sia RPC
server through its API. PHP-Sia can manipulating the wallet, renter, host and more.

Additionally, PHP-Sia implements a simple SQL based backend for receiving and tracking
payments into local Sia addresses, which can be used for setting up an accounts based
billing system.

## Features
* View balances
* Send funds
* Upload, share and download files
* Create addresses
* Check deposits to addresses
* View transactions
* and more!

## Sia API Reference Documentation
https://github.com/NebulousLabs/Sia/blob/master/doc/API.md

## Usage
Instantiating `class.sia.php` with database backend:

```
require('class.sia.php');
$mysqli = new mysqli(HOST, DATABASE, PASS, USER);
$Sia = new PHP_Sia($mysqli);
```

Ẁithout the optional with database backend:
```
require('class.sia.php');
$Sia = new PHP_Sia();
```

## Dependencies:
* `php7.0-mysql`
* `php7.0-curl`
* `php7.0-bcmath`

## Troubleshooting
* Install required PHP dependencies (see Dependencies)
* Confirm RPC server is running by connecting from command line with cURL:
	`curl -s -X GET http://localhost:9980/consensus -A "Sia-Agent"`

## Donations (Siacoin)
e2356d2f621d571684b5f8f1fd5c8f2aa79c9d35f3100a5ec1669f38ad0135df309b816caaf0

## Methods Reference
### Network:
OK	`is_connected_to_peers()`
OK	`get_consensus()`
OK	`get_gateway()`
OK	`add_peer($address)`
OK	`remove_peer($address)`
OK	`get_peer_address_parts($address)`

### Wallet:
OK	`wallet_islocked()`
OK	`wallet_lock()`
OK	`wallet_unlock($encryptionpassword)`
OK	`wallet_backup($destination)`
OK	`wallet_sc_balance($decimals=NULL)`
OK	`wallet_sf_balance($decimals = NULL)`
OK	`wallet_address()`
OK	`wallet_addresses()`
OK	`wallet_transactions($startheight, $endheight)`
OK	`wallet_transactions_addr($address)`
OK	`wallet_addr_txn($address)`
OK	`send_sc($siacoins, $address)`
OK	`wallet_transaction($transactionid)`
OK	`wallet_transaction_hastings_net($transaction)`

### Renter:
OK	`hostdb()`
OK	`renter_files()`
OK	`renter_file($siapath)`
OK	`renter_upload($source, $siapath)`
	`renter_download($siapath, $destination)`
OK	`renter_delete($siapath)`
OK	`renter_rename($siapath, $newname)`
OK	`renter_check_siapath($siapath)`

### Conversion:
OK	`sia_round($n, $decimals = NULL)`
OK	`sc_to_hastings($siacoins)`
OK	`hastings_to_sc($hastings)`

### Validation:
OK	`is_valid_address($address)`

### Database:
	`db_insert`
	`db_select`
	`db_withdraw_funds`
	`db_new_receivable`
	`db_update`

### RPC:
OK	`rpc($cmd, $request='GET', $postfields=null)`
