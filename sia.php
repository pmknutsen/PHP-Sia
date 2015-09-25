<?php
/**
 * PHP-Sia API
 *
 * Provides a library of methods for PHP developers to communicate with the Sia RPC
 * server. Currently PHP-Sia can send funds, upload/download/share files, get balances,
 * check for deposits and view transactions.
 *
 * Sia API Reference Documentation:
 * https://github.com/NebulousLabs/Sia/blob/master/doc/API.md
 *
 * Troubleshooting:
 *	Install required dependencies
 *	Connect to RPC server from command line with cURL:
 * 		curl -s -X GET http://localhost:9980/consensus -A "Sia-Agent" | jq .
 * 
 * 
 *	Wallet:
 *		is_wallet_locked()
 *		lock_wallet()
 *		unlock_wallet()
 *		backup_wallet()
 *		get_sc_balance()
 *		get_sf_balance()
 *		get_address()
 *		get_addresses()
 *		get_transactions()
 *		send_sc()
 *		get_transaction_id()
 *		get_transactions_addr()
 *		get_addr_deposits()
 *
 *	Network:
 *		is_connected_to_peers()
 *		get_consensus()
 *		get_gateway()
 *		get_hostdb()
 *
 *	Renter:
 *		get_file_list()
 *		upload_file()
 *		download_file()
 *		delete_file()
 *		load_sia_file()
 *		rename_file() *temporarily disabled*
 *		create_sia_share_file()
 *		create_sia_share_ascii()
 *
 *	Conversion:
 *		sc_to_hastings()
 *		hastings_to_sc()
 *
 *	Validation:
 *		is_valid_address()
 *
 */

class PHP_Sia
{
	// @var string IP:Port of the wallet daemon.  Usually 127.0.0.1:18082
    	public $rpc_address = '127.0.0.1:9980';

	// Initialize the class, override defaults if needed
	public function __construct( string $rpc_address = NULL ) {
	        // Change default configuration options if requested
	        $this->rpc_address = empty($rpc_address) ? $this->rpc_address : $rpc_address;

	        // Validate configuration
		if (!preg_match (
			'/((0|1[0-9]{0,2}|2[0-9]?|2[0-4][0-9]|25[0-5]|[3-9][0-9]?)\.){3}(0|1[0-9]{0,2}|2[0-9]?|2[0-4][0-9]|25[0-5]|[3-9][0-9]?):([0-9]{1,5})/',
			$this->rpc_address
		)) {
			throw new \Exception('RPC address configuration value is invalid.');
			return false;
	        }
	        return true;
	}

	/**
	 * Unlock wallet
	 *
	 * @param string $encryptionpassword Wallet encryption password
	 * @return bool
	 * @uses rpc()
	 * @uses is_wallet_locked()
	 */
	public function unlock_wallet($encryptionpassword) {
		if (!$this->is_wallet_locked()) {
			throw new \Exception('Wallet is already unlocked');
			return false;
		}
		$result = $this->rpc('/wallet/unlock', 'POST', array('encryptionpassword'=>$encryptionpassword));
		return $result->Success;
	}

	/**
	 * Lock wallet
	 *
	 * @return bool
	 * @uses rpc()
	 * @uses is_wallet_locked()
	 */
	public function lock_wallet() {
		if ($this->is_wallet_locked()) {
			throw new \Exception('Wallet is already locked');
			return false;
		}
		$result = $this->rpc('/wallet/lock', 'POST');
		return $result->Success;
	}

	/**
	 * Check if wallet is locked
	 *
	 * @return bool
	 * @uses rpc()
	 */
	public function is_wallet_locked() {
		$wallet = $this->rpc('/wallet');
		if ($wallet->unlocked == 1)
			return false;
		return true;
	}

	/**
	 * Backup wallet
	 *
	 * @param string $path Path and filename of backup file
	 * @uses rpc()
	 */
	public function backup_wallet($filepath) {
		$this->rpc('/wallet/backup', 'POST', 'filepath='.$filepath);
	}

	/**
	 * Return information about consensus set
	 *
	 * @return stdClass
	 * @uses rpc()
	 */
	public function get_consensus() {
		$result = $this->rpc('/consensus');
		if (empty($result)) {
			throw new \Exception('Failed retrieving consensus set');
			return false;
		}
		return $result;
	}

	/**
	 * Verify that daemon is connected to peers
	 *
	 * @return bool
	 * @uses rpc()
	 */
	public function is_connected_to_peers() {
		$status = $this->rpc('/gateway/status');
		if (count($status->Peers) <= 2)
			return false;
		return true;
	}

	/**
	 * Return information the gateway
	 *
	 * @return stdClass
	 * @uses rpc()
	 */
	public function get_gateway() {
		$json = $this->rpc('/gateway/status');
		return $json;
	}

	/**
	 * Get list of all active hosts in the hostdb
	 *
	 * @return stdClass List of hosts with details
	 * @uses rpc()
	 */
	public function get_hostdb() {
		$json = $this->rpc('/hostdb/hosts/active');
		return $json->Hosts;
	}


	/**
	 * Get new address from wallet that can receive siacoins or siafunds
	 *
	 * @return string New siacoin/fund address
	 * @uses rpc()
	 */
	public function get_address() {
		$json = $this->rpc('/wallet/address');
		return $json->address;
	}

	/**
	 * Fetch the list of addresses from the wallet
	 *
	 * @return string Array of wallet addresses
	 * @uses rpc()
	 */
	public function get_addresses() {
		if ($this->is_wallet_locked()) {
			throw new \Exception('Wallet is locked');
			return false;
		}
		$json = $this->rpc('/wallet/addresses');
		$addresses = array();
		foreach($json->addresses as $address)
			$addresses[] = $address->address;
		return $addresses;
	}

	/**
	 * Return a list of transactions IDs related to the wallet.
	 *
	 * @param int $startheight Block height where transaction history should start
	 * @param int $endheight Block height where transaction history should end
	 * @return stdClass Array of strings containing transactions IDs
	 * @uses rpc()
	 */
	public function get_transactions($startheight, $endheight) {
		$json = $this->rpc('/wallet/transactions?startheight='.$startheight.'&endheight='.$endheight);
		$transactions = new stdClass();
		$transactions->confirmed = array();
		$transactions->unconfirmed = array();
		if (!empty($json->confirmedtransactions)) {
			foreach($json->confirmedtransactions as $transaction)
				$transactions->confirmed[] = $transaction->transactionid;
		}
		if (!empty($json->unconfirmedtransactions)) {
			foreach($json->unconfirmedtransactions as $transaction)
				$transactions->unconfirmed[] = $transaction->transactionid;
		}
		return $transactions;
	}

	/**
	 * Return all transaction related to a specific address
	 *
	 * @param int $address 
	 * @return stdClass Array of strings containing transactions IDs
	 * @uses rpc()
	 */
	public function get_transactions_addr($address) {
		$json = $this->rpc('/wallet/transactions/'.$address);
		$transactions = new stdClass();
		if (!empty($json->confirmedtransactions)) {
			$transactions->confirmed = array();
			foreach($json->confirmedtransactions as $transaction)
				$transactions->confirmed[] = $transaction->transactionid;
		}
		if (!empty($json->unconfirmedtransactions)) {
			$transactions->unconfirmed = array();
			foreach($json->unconfirmedtransactions as $transaction)
				$transactions->unconfirmed[] = $transaction->transactionid;
		}
		return $transactions;
	}

	/**
	 * Get transaction details associated with a specific transaction id
	 *
	 * @param int $transactionid Transaction ID to look up
	 * @return stdClass Transaction object
	 * @uses rpc()
	 */
	public function get_transaction_id($transactionid) {
		$json = $this->rpc('/wallet/transaction/'.$transactionid);
		return $json->transaction;
	}

	/**
	 * Get Siacoin balance in wallet
	 *
	 * @param int $decimals Rounding factor
	 * @uses rpc()
	 */
	public function get_sc_balance($decimals=NULL) {
		$json = $this->rpc('/wallet');
		return $this->sia_round($json->confirmedsiacoinbalance / 10E+23, $decimals);
	}

	/**
	 * Get Siafund balance in wallet
	 *
	 * @param int $decimals Rounding factor
	 * @uses rpc()
	 */
	public function get_sf_balance($decimals=NULL) {
		$json = $this->rpc('/wallet');
		return $this->sia_round($json->siafundbalance / 10E+23, $decimals);
	}

	private function sia_round($n, $decimals=NULL) {
		if ($decimals != NULL)
			$n = number_format($n, $decimals);
		return $n;
	}

	/**
	 * Send Siacoin to an address
	 *
	 * @param double $siacoins
	 * @param string $address
	 * @return string Transactions IDs
	 * @uses rpc()
	 */
	public function send_sc($siacoins, $address) {
		if (!$this->is_valid_address($address)) {
			throw new \Exception('Invalid address');
			return false;
		}
		$hastings = $this->sc_to_hastings($siacoins);
		$json = $this->rpc('/wallet/siacoins', 'POST', array('amount'=>$hastings, 'destination'=>$address));
		return $json->transactionids;
	}

	/**
	 * Validate a Sia address
	 *
	 * @param string $address
	 * @return bool
	 * @uses rpc()
	 */
	public function is_valid_address($address) {
		if (
			!ctype_alnum($address) ||
			strlen($address) != 76
		) {
			return false;
		}
		return true;
	}
	
	/**
	 * Get list of deposits to an input address
	 *
	 * @param string $address
	 * @return int Array of deposits in siacoin
	 * @uses rpc()
	 */
	public function get_addr_deposits($address) {
		$deposits = array();
		if (!$this->is_valid_address($address)) {
			throw new \Exception('Invalid address');
			return false;
		}
		$transactions = $this->get_transactions_addr($address);
		if (empty((array)$transactions)) {
			throw new \Exception('No transactions found');
			return $deposits;
		}
		foreach ($transactions->confirmed as $id) {
			if (!empty($id)) {
				$transaction = $this->get_transaction_id($id);
				$deposit = $this->get_transaction_hastings_net($transaction);
				if ($deposit <= 0)
					continue;
				$deposits[] = $this->hastings_to_sc($deposit);
			}
		}
		return $deposits;
	}

	/**
	 * Get net amount moved from/to wallet in a transaction
	 *
	 * @param string $transaction Transaction id
	 * @return float Siacoin total
	 */
	public function get_transaction_hastings_net($transaction) {
		$sum = 0;
		foreach ($transaction->inputs as $input)
			if ($input->walletaddress) $sum -= $input->value;
		foreach ($transaction->outputs as $output)
			if ($output->walletaddress) $sum += $output->value;
		return $sum;
	}

	/**
	 * Upload a file
	 * Source and nickname must share the same extension
	 *
	 * @param string $source Path to the file to be uploaded.
	 * @param string $nickname Name that will be used to reference the file
	 * @return bool Success
	 * @uses rpc()
	 */
	public function upload_file($source, $nickname) {
		if ($this->nickname_exists($nickname)) {
			throw new \Exception('Nickname is already in use');
			return false;
		}
		if (!$this->is_connected_to_peers()) {
			throw new \Exception('Not connected to enough peers');
			return false;
		}
		if (!strcasecmp(pathinfo($source, PATHINFO_EXTENSION), pathinfo($nickname, PATHINFO_EXTENSION)) == 0) {
			throw new \Exception('Extension of source and nickname must match');
			return false;
		}
		$result = $this->rpc('/renter/files/upload', 'POST', array('source'=>$source, 'nickname'=>$nickname));
		return $result->Success;
	}

	/**
	 * Load a .sia file into the renter
	 *
	 * @param string $file Filepath or ASCII code
	 * @return string Nickname of added file
	 * @uses rpc()
	 */
	public function load_sia_file($file) {
		if (!$this->is_connected_to_peers()) {
			throw new \Exception('Not connected to enough peers');
			return false;
		}
		if (strcasecmp(pathinfo($file, PATHINFO_EXTENSION), 'sia') == 0) {
			// Load a .sia file
			$result = $this->rpc('/renter/files/load', 'POST', array('filename'=>$file));
		} else {
			// Load ASCII representation of .sia file
			$result = $this->rpc('/renter/files/loadascii', 'POST', array('file'=>$file));
		}
		return $result->FilesAdded[0];
	}

	/**
	 * Create a .sia file that can be shared
	 *
	 * @param string $nickname Nickname of file to share
	 * @param string $filepath Filepath of .sia file
	 * @return bool Success
	 * @uses rpc()
	 */
	public function create_sia_share_file($nickname, $filepath) {
		if (!$this->is_connected_to_peers()) {
			throw new \Exception('Not connected to enough peers');
			return false;
		}
		if (!$this->nickname_exists($nickname)) {
			throw new \Exception('Nickname does not exist');
			return false;
		}
		$result = $this->rpc('/renter/files/share', 'POST', array('nickname'=>$nickname, 'filepath'=>$filepath));
		return $result->Success;
	}
	
	/**
	 * Get ASCII representation of a .sia share file
	 *
	 * @param string $nickname Nickname of file to share
	 * @return string ASCII representation of .sia file
	 * @uses rpc()
	 */
	public function create_sia_share_ascii($nickname) {
		if (!$this->is_connected_to_peers()) {
			throw new \Exception('Not connected to enough peers');
			return false;
		}
		if (!$this->nickname_exists($nickname)) {
			throw new \Exception('Nickname does not exist');
			return false;
		}
		$result = $this->rpc('/renter/files/shareascii', 'POST', array('nickname'=>$nickname));
		return $result->File;
	}

	/**
	 * Delete a file by nickname
	 * 
	 * @param string $nickname Nickname of file
	 * @return bool Success
	 * @uses rpc()
	 */
	public function delete_file($nickname) {
		if (!$this->nickname_exists($nickname)) {
			throw new \Exception('Nickname does not exist');
			return false;
		}
		$result = $this->rpc('/renter/files/delete', 'POST', array('nickname'=>$nickname));
		return $result->Success;
	}

	/**
	 * Rename a file by nickname
	 *
	 * @TODO Renaming temporarily disabled as of v0.4.2
	 * @param string $nickname Nickname of file
	 * @param string $newname New nickname of file
	 * @return bool
	 * @uses rpc()
	 */
	public function rename_file($nickname, $newname) {
		if (!$this->nickname_exists($nickname)) {
			throw new \Exception('Nickname does not exist');
			return false;
		}
		if (!strcasecmp(pathinfo($nickname, PATHINFO_EXTENSION), pathinfo($newname, PATHINFO_EXTENSION)) == 0) {
			throw new \Exception('Extension of existing and new nickname must match');
			return false;
		}
		$result = $this->rpc('/renter/files/rename', 'POST', array('nickname'=>$nickname, 'newname'=>$newname));
		print_r($result);
		return $result->Success;
	}

	/**
	 * Download a file by nickname
	 *
	 * @param string $nickname Nickname of file
	 * @param string $nickname Destination filepath
	 * @return bool
	 * @uses rpc(), nickname_exists()
	 */
	public function download_file($nickname, $destination) {
		if (!$this->nickname_exists($nickname)) {
			throw new \Exception('Nickname does not exist');
			return false;
		}
		$result = $this->rpc('/renter/files/download', 'POST', array('nickname'=>$nickname, 'destination'=>$destination));
		return $result->Success;
	}

	/**
	 * Check whether a nickname is already in use
	 *
	 * @param string $nickname Nickname to check
	 * @return bool
	 * @uses rpc()
	 */
	public function nickname_exists($nickname) {
		$files = $this->get_file_list();
		foreach($files as $file)
			if (strcmp($nickname, $file->Nickname) == 0)
				return true; // nickname is already in use
		return false;
	}

	/**
	 * Get list of uploaded files
	 *
	 * @return array
	 * @uses rpc()
	 */
	public function get_file_list() { return $this->rpc('/renter/files/list'); }

	/**
	 * Convert hastings to siacoins
	 *
	 * @param int $hastings
	 * @return double Siacoins
	 */
	public function hastings_to_sc($hastings) { return $hastings / 10E+23; }

	/**
	 * Convert siacoins to hastings
	 *
	 * @param int $siacoins
	 * @return double Hastings
	 */
	public function sc_to_hastings($siacoins) { return $siacoins * 10E+23; }

	/**
	 * Remote procedure call handler
	 *
	 * @param string $cmd
	 * @param string $request
	 * @param string $postfield
	 * @return stdClass Returned fields
	 */
	public function rpc($cmd, $request='GET', $postfields=null) {
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $this->rpc_address.$cmd);
		// For debugging, set URL to http://httpbin.org/post and read output
		//curl_setopt($c, CURLOPT_URL, 'http://httpbin.org/post');
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, $request);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_USERAGENT, 'Sia-Agent');
		if (!strcasecmp($request, 'POST')) {
			curl_setopt($c, CURLOPT_POST, count($postfields));
			curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
		}
		$data = curl_exec($c);
		curl_close($c);
		$json = json_decode($data);

		// Throw any non-JSON string as error
		if (json_last_error() != JSON_ERROR_NONE) {
			throw new \Exception($data);
			return false;
		}

		return $json;
	}
}


?>
