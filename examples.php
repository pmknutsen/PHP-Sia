<?php
/**
* PHP-Sia Examples
*
*/

// Load class definitions
require('class.sia.php');

// Create a MySQLi instance (optional)
$mysqli = new mysqli('localhost', 'phpsia', 'mypassword', 'phpsia');

// Initialize
$Sia = new PHP_Sia($mysqli);
$Sia->rpc_address = '127.0.0.1:9980';

// Get current block height
echo "Height: ".$Sia->get_consensus()->height."\n";

/**
* Wallet methods
*/
if ($Sia->wallet_islocked())
	echo "Wallet is locked\n";
else {
	echo "Wallet is unlocked\n";
	echo "SC balance: ".$Sia->wallet_sc_balance(2)."\n";
	echo "SF balance: ".$Sia->wallet_sf_balance(2)."\n";
	echo "New deposit address: ".$Sia->wallet_address()."\n";
}
//$Sia->send_sc(1000, e2356d2f621d571684b5f8f1fd5c8f2aa79c9d35f3100a5ec1669f38ad0135df309b816caaf0);


/**
* Renter methods
*/
$hostdb = $Sia->hostdb();
//$Sia->renter_upload("/path/file.pdf", "siapath/file.pdf");
//$Sia->renter_rename("siapath/file.pdf", "siapath/newfile.pdf");
//$Sia->renter_download("siapath/newfile.pdf", "newfile.pdf");
//$Sia->renter_delete("siapath/newfile.pdf");


/**
* Payments methods
*/
// Create a new receivable (i.e. payment to be received)
$result = $Sia->db_new_receivable('100', 'e2356d2f621d571684b5f8f1fd5c8f2aa79c9d35f3100a5ec1669f38ad0135df309b816caaf0', '+10 days');

//$dbresult = $Sia->db_select('type', 'withdrawal');



return;

// Create a new receivable
//$Sia->db_new_receivable(47, '00030631961cf39da235014fd97a049ddc6da532dbd8f01cba55699978ca066bc9211245777c');

// Update database
$newtxns = $Sia->db_update();
print_r($newtxns);
echo count($newtxns);



$result = $Sia->db_withdraw_funds('2', '00030631961cf39da235014fd97a049ddc6da532dbd8f01cba55699978ca066bc9211245777c');

// Get all transactions related to an address
$txns = $Sia->get_addr_txn('00030631961cf39da235014fd97a049ddc6da532dbd8f01cba55699978ca066bc9211245777c');
print_r($txns);


// Create a new account with an initial balance of 1000 SC that expires in 1 month
$receivable = $Sia->db_new_account('1000', '+1 month');
print_r($receivable);

// Get IDs of confirmed transactions in last 5 blocks
$height = $Sia->get_consensus()->height;
$confirmedtransactions = $Sia->wallet_transactions($height-10, $height)->confirmed;

// Get details on a specific transaction
$transaction = $Sia->wallet_transaction_id($confirmedtransactions[0]);

// Get all transactions related to a specific address
$transactions = $Sia->wallet_transactions_addr($addresses[0]);

// Get list of deposits to an address
$deposits = $Sia->get_addr_txn('04c1a3317df0e7cc29aaee9fdfc524cc4c7998ef3a204613fcb206d4773b5275572647d3bfea');

// Get gateway info (IP and list peers)
print_r($Sia->get_gateway());


?>
