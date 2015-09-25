<?php
/**
* PHP-Sia examples
*
*/

require('sia.php');

// Initialize
$SiaAPI = new PHP_Sia();
$SiaAPI->rpc_address = '127.0.0.1:9980';

// Get current block height
$height = $SiaAPI->get_consensus()->height;

// Get balances (formatted to 2 decimals)
$scbalance = $SiaAPI->get_sc_balance(2);
$sfbalance = $SiaAPI->get_sf_balance(2);

// Send developer some siacoin
$transactionids = $SiaAPI->send_sc('100', '04c1a3317df0e7cc29aaee9fdfc524cc4c7998ef3a204613fcb206d4773b5275572647d3bfea');

// Upload a file
$SiaAPI->upload_file('/home/username/test.txt', 'test.txt');

// Delete a file
$SiaAPI->delete_file('test.txt');

// Download a file
$SiaAPI->download_file('test.txt', '/home/username/test.txt');

// Share a filename by creating a .sia file
$SiaAPI->create_sia_share_file('test.txt', '/home/username/test.sia');

// Share a filename by creating a .sia file
$ascii = $SiaAPI->create_sia_share_ascii('test.txt');

// Load a local .sia file into the renter
$SiaAPI->load_sia_file('/home/username/test.sia');

// Get IDs of confirmed transactions in last 5 blocks
$height = $SiaAPI->get_consensus()->height;
$confirmedtransactions = $SiaAPI->get_transactions($height-10, $height)->confirmed;

// Get details on a specific transaction
$transaction = $SiaAPI->get_transaction_id($confirmedtransactions[0]);

// Get list of all wallet addresses
$addresses = $SiaAPI->get_addresses();

// Get all transactions related to a specific address
$transactions = $SiaAPI->get_transactions_addr($addresses[0]);

// Get list of deposits to an address
$deposits = $SiaAPI->get_addr_deposits('04c1a3317df0e7cc29aaee9fdfc524cc4c7998ef3a204613fcb206d4773b5275572647d3bfea');

// Get current list of active hosts
$hostdb = $SiaAPI->get_hostdb();

// Get gateway info (IP and list peers)
print_r($SiaAPI->get_gateway());

// Get a new address
$address = $SiaAPI->get_address();

?>
