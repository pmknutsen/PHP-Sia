# PHP-Sia
PHP-Sia provides a library of methods for PHP developers to communicate with the Sia RPC
server through its API. PHP-Sia can manipulating the wallet, renter, host and more.

Additionally, PHP-Sia implements a simple SQL based backend for receiving and tracking
payments into local Sia addresses, which can be used for setting up an accounts based
billing system.

### Features
* View balances
* Send funds
* Upload, share and download files
* Create addresses
* Check deposits to addresses
* View transactions
* and more!

### Sia API Reference Documentation
https://github.com/NebulousLabs/Sia/blob/master/doc/API.md

### Usage
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

### Dependencies:
* `php7.0-mysql`
* `php7.0-curl`
* `php7.0-bcmath`

### Troubleshooting
* Install required PHP dependencies (see Dependencies)
* Confirm RPC server is running by connecting from command line with cURL:
	`curl -s -X GET http://localhost:9980/consensus -A "Sia-Agent"`

### Donations (Siacoin)
e2356d2f621d571684b5f8f1fd5c8f2aa79c9d35f3100a5ec1669f38ad0135df309b816caaf0

### Methods
#### Network:
```
is_connected_to_peers()
get_consensus()
get_gateway()
add_peer($address)
remove_peer($address)
get_peer_address_parts($address)
```

#### Wallet:
```
wallet_islocked()
wallet_lock()
wallet_unlock($encryptionpassword)
wallet_backup($destination)
wallet_sc_balance($decimals=NULL)
wallet_sf_balance($decimals = NULL)
wallet_address()
wallet_addresses()
wallet_transactions($startheight, $endheight)
wallet_transactions_addr($address)
wallet_addr_txn($address)
send_sc($siacoins, $address)
wallet_transaction($transactionid)
wallet_transaction_hastings_net($transaction)
```

#### Renter:
```
hostdb()
renter_files()
renter_file($siapath)
renter_upload($source, $siapath)
renter_download($siapath, $destination)
renter_delete($siapath)
renter_rename($siapath, $newname)
renter_check_siapath($siapath)
```

#### Conversion:
```
sia_round($n, $decimals = NULL)
sc_to_hastings($siacoins)
hastings_to_sc($hastings)
```

#### Validation:
```
is_valid_address($address)
```

#### Database:
```
db_insert
db_select
db_withdraw_funds
db_new_receivable
db_update
```

#### RPC:
```
rpc($cmd, $request='GET', $postfields=null)
```