<?php
/**
 * PHP-Sia API
 *
 * Provides a library of methods for PHP developers to communicate with the Sia RPC
 * server. Currently PHP-Sia can send funds, upload/download/share files, get balances,
 * check for deposits and view transactions. A payments SQL backend allows for maintaining
 * account balances and receiving payments (see examples.php). 
 * 
 * Sia API Reference Documentation:
 * https://github.com/NebulousLabs/Sia/blob/master/doc/API.md
 *
 * TODO
 *	SQL backend for receiving payments
 *		3 entry types:
 *			deposit		Incoming transfers (debit), ex. received payment
 *			withdrawal	Outgoing transfers (credit), ex. for faucet
 *			receivable	Expected payment, ex. for a service/product
 *			All activity is registered by separate entries in db
 *			Entries are never overwritten or modified in any way
 *
 *		Payment scenarios:
 *			Receiving payment for a service:
 *				a new address with negative amount is created
 *				user deposits to address
 *				each deposit results in a new db entry
 *				the current balance == sum across all address entries
 *				when balance is zero, user has 'paid'
 *
 *			Accounts tracking:
 *				a user is associated with a unique address
 *				user deposits to address increases balances
 *				user withdrawals reduces balance NOT TO BE IMPLEMENTED YET
 *					how can user prevent unauthorized withdrawals from address?
 *
 *		Withdrawals:
 *			New functions:
 *				db_withdraw_funds($amount, $address) just a wrapper for send, plus db insertion
 *				db_new_receivable($amount, $address)
 *					use to register payment
 *					create a new address with a negative balance
 *				db_update() Run as cron job
 *					check for transactions in last blocks
 *					looks for activity already registered in db
 *					creates new entries for activity not registered in db
 *
 */

class PHP_Sia {
	// @var string IP:Port of the wallet daemon.  Usually 127.0.0.1:9980
    public $rpc_address = '127.0.0.1:9980';

	/** @var object Instance of MySQLi class. */
	public $mysqli;

	// Initialize the class, override defaults if needed
	public function __construct(
	        \mysqli &$mysqli = NULL,
		string $rpc_address = NULL
	) {
	        // Make database available to other methods
	        $this->mysqli = &$mysqli;

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
	 * @uses wallet_islocked()
	 */
	public function wallet_unlock($encryptionpassword) {
		if (!$this->wallet_islocked()) {
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
	 * @uses wallet_islocked()
	 */
	public function wallet_lock() {
		if ($this->wallet_islocked()) {
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
	public function wallet_islocked() {
		$wallet = $this->rpc('/wallet');
		if ($wallet->unlocked == 1)
			return false;
		return true;
	}

	/**
	 * Backup wallet
	 *
	 * @param string $destination Path and filename of backup file
	 * @return stdClass
	 * @uses rpc()
	 */
	public function wallet_backup($destination) {
		$result = $this->rpc('/wallet/backup', 'GET', 'destination='.$destination);
		return $result;
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
		$status = $this->get_gateway();
		if (count($status->peers) <= 2)
			return false;
		return true;
	}

	/**
	 * Split an IP address into the IP and port
	 *
	 * @param string IP address and port (optional)
	 * @return array IP address and port number
	 */
	public function get_peer_address_parts($address) {
		$pos = strpos($address, ':');
		if (empty($pos)) {
			$port = 9981;
			$ip = $address;
		} else {
			$ip = substr($address, 0, $pos);
			$port = substr($address, $pos + 1);
		}
		return (object) array('ip' => $ip, 'port' => $port);
	}

	/**
	 * Add a peer to the gateway
	 *
	 * @return bool
	 * @uses rpc()
	 */
	public function add_peer($address) {
		$address = $this->get_peer_address_parts($address);
		$result = $this->rpc('/gateway/connect/'.$address->ip.':'.$address->port, 'POST');
		if (!array_key_exists('Success', $result))
			throw new \Exception($result->message);
		return true;
	}

	/**
	 * Remove a peer from the gateway
	 *
	 * @return bool
	 * @uses rpc()
	 */
	public function remove_peer($address) {
		$address = $this->get_peer_address_parts($address);
		$result = $this->rpc('/gateway/disconnect/'.$address->ip.':'.$address->port, 'POST');
		if (!array_key_exists('Success', $result))
			throw new \Exception($result->message);
		return true;
	}

	/**
	 * Return information the gateway
	 *
	 * @return stdClass
	 * @uses rpc()
	 */
	public function get_gateway() {
		$json = $this->rpc('/gateway');
		return $json;
	}

	/**
	 * Get list of all active hosts in the hostdb
	 *
	 * @return stdClass List of hosts with details
	 * @uses rpc()
	 */
	public function hostdb() {
		$json = $this->rpc('/hostdb/active');
		return $json->hosts;
	}

	/**
	 * Get new address from wallet that can receive siacoins or siafunds
	 *
	 * @return string New siacoin/fund address
	 * @uses rpc()
	 */
	public function wallet_address() {
		$json = $this->rpc('/wallet/address');
		return $json->address;
	}

	/**
	 * Fetch the list of addresses from the wallet
	 *
	 * @return string Array of wallet addresses
	 * @uses rpc()
	 */
	public function wallet_addresses() {
		if ($this->wallet_islocked()) {
			throw new \Exception('Wallet is locked');
			return false;
		}
		$result = $this->rpc('/wallet/addresses');
		return $result->addresses;
	}

	/**
	 * Return a list of transactions IDs related to the wallet.
	 *
	 * @param int $startheight Block height where transaction history should start
	 * @param int $endheight Block height where transaction history should end
	 * @return stdClass Array of strings containing transactions IDs
	 * @uses rpc()
	 */
	public function wallet_transactions($startheight, $endheight) {
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
	public function wallet_transactions_addr($address) {
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
	public function wallet_transaction($transactionid) {
		$json = $this->rpc('/wallet/transaction/'.$transactionid);
		return $json->transaction;
	}

	/**
	 * Get Siacoin balance in wallet
	 *
	 * @param int $decimals Rounding factor
	 * @uses rpc()
	 */
	public function wallet_sc_balance($decimals=NULL) {
		$json = $this->rpc('/wallet');
		return $this->sia_round($json->confirmedsiacoinbalance / 10E+23, $decimals);
	}

	/**
	 * Get Siafund balance in wallet
	 *
	 * @param int $decimals Rounding factor
	 * @uses rpc()
	 */
	public function wallet_sf_balance($decimals = NULL) {
		$json = $this->rpc('/wallet');
		return $this->sia_round($json->siafundbalance / 10E+23, $decimals);
	}

	private function sia_round($n, $decimals = NULL) {
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
		if ($this->wallet_islocked()) {
			throw new \Exception('Wallet is locked');
			return false;
		}
		if (!$this->is_valid_address($address)) {
			throw new \Exception('Invalid address');
			return false;
		}
		$hastings = $this->sc_to_hastings($siacoins);
		$json = $this->rpc('/wallet/siacoins', 'POST', array('amount'=>$hastings, 'destination'=>$address));
		return $json->transactionids[count($json->transactionids) - 1];
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
	 * Get all transactions involving a particular wallet address. Returns an array
	 * of objects containing transaction id, amount and timestamp.
	 *
	 * Note: This method is higher-level compared to wallet_transactions_addr() which
	 *		 only returns transactions IDs.
	 *
	 * @param string $address
	 * @return array Array of objects
	 * @uses rpc()
	 */
	public function wallet_addr_txn($address) {
		if (!$this->is_valid_address($address)) {
			throw new \Exception('Invalid address');
			return false;
		}
		$txns = $this->wallet_transactions_addr($address);
		$arr = (array)$txns;
		if (empty($arr)) {
			throw new \Exception('No transactions found');
			return $txns;
		}
		$transactions = array();
		foreach ($txns->confirmed as $id) {
			if (empty($id)) continue;
			$txn = $this->wallet_transaction($id);
			$txn_net = $this->wallet_transaction_hastings_net($txn);
			if ($txn_net == 0) continue;
			$transactions[] = (object) array(
				"txid" 		=> $id,
				"amount"	=> $this->hastings_to_sc($txn_net),
				"timestamp"	=> $txn->confirmationtimestamp * 1000,
			);
		}
		return $transactions;
	}

	/**
	 * Get net amount moved from/to wallet in a transaction
	 *
	 * @param string $transaction Transaction id
	 * @return float Siacoin total
	 */
	public function wallet_transaction_hastings_net($transaction) {
		$sum = 0;
		if (empty($transaction)) return $sum;
		if (is_array($transaction->inputs)) {
			foreach ($transaction->inputs as $input)
				if ($input->walletaddress) $sum -= $input->value;
		}
		if (is_array($transaction->outputs)) {
			foreach ($transaction->outputs as $output)
				if ($output->walletaddress) $sum += $output->value;
		}
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
	public function renter_upload($source, $siapath) {
		if ($this->renter_check_siapath($siapath)) {
			throw new \Exception('Siapath is already in use');
			return false;
		}
		if (!$this->is_connected_to_peers()) {
			throw new \Exception('Not connected to peers');
			return false;
		}
		$result = $this->rpc('/renter/upload/'.$siapath, 'POST', array('source'=>$source));
		if (!array_key_exists('Success', $result))
			throw new \Exception($result->message);
		return $result->Success;
	}

	/**
	 * Delete a file by siapath
	 * 
	 * @param string $siapath Siapath of file
	 * @return bool Success
	 * @uses rpc()
	 */
	public function renter_delete($siapath) {
		if (!$this->renter_check_siapath($siapath)) {
			throw new \Exception('Siapath does not exist');
			return false;
		}
		$result = $this->rpc('/renter/delete/'.$siapath, 'POST');
		return $result->Success;
	}

	/**
	 * Rename a file by siapath
	 *
	 * @TODO Renaming temporarily disabled as of v0.4.2
	 * @param string $siapath Siapath of file
	 * @param string $newname New siapath of file
	 * @return bool
	 * @uses rpc()
	 */
	public function renter_rename($siapath, $newsiapath) {
		if (!$this->renter_check_siapath($siapath)) {
			throw new \Exception('Siapath does not exist');
			return false;
		}
		if (!strcasecmp(pathinfo($siapath, PATHINFO_EXTENSION), pathinfo($newsiapath, PATHINFO_EXTENSION)) == 0) {
			throw new \Exception('Extension of existing and new siapath must match');
			return false;
		}
		$result = $this->rpc('/renter/rename/'.$siapath, 'POST', array('newsiapath'=>$newsiapath));
		return $result->Success;
	}

	/**
	 * Download a file by siapath
	 *
	 * @param string $siapath Siapath of file
	 * @param string $destination Destination filepath
	 * @return bool
	 * @uses rpc(), renter_check_siapath()
	 */
	public function renter_download($siapath, $destination) {
		if (!$this->renter_check_siapath($siapath)) {
			throw new \Exception('Siapath does not exist');
			return false;
		}
		$result = $this->rpc('/renter/download/'.$siapath, 'GET', array('destination'=>$destination));
		if (!array_key_exists('Success', $result))
			throw new \Exception($result->message);
		return $result->Success;
	}

	/**
	 * Check whether a siapath is already in use
	 *
	 * @param string $siapath Siapath to check
	 * @return bool
	 * @uses rpc()
	 */
	public function renter_check_siapath($siapath) {
		$files = $this->renter_files();
		foreach($files as $file)
			if (strcmp($siapath, $file->siapath) == 0)
				return true; // nickname is already in use
		return false;
	}

	/**
	 * Get list of uploaded files
	 *
	 * @return array
	 * @uses rpc()
	 */
	public function renter_files() { return $this->rpc('/renter/files')->files; }

	/**
	 * Get the details of file in renter
	 *
	 * @param string $nick Nickname
	 * @return array File details or 0 if no such file found
	 * @uses rpc()
	 */
	public function renter_file($siapath) {
		$files = $this->renter_files();
		foreach ($files as $file) {
			if (strcmp($file->siapath, $siapath) == 0)
				return $file;
		}
		return 0;
	}

	/**
	 * Convert hastings to siacoins
	 *
	 * @param string $hastings
	 * @return double Siacoins
	 */
	public function hastings_to_sc($hastings) { return bcdiv(sprintf('%f', $hastings), "1000000000000000000000000"); }

	/**
	 * Convert siacoins to hastings
	 *
	 * @param string $siacoins
	 * @return double Hastings
	 */
	public function sc_to_hastings($siacoins) { return bcmul($siacoins, "1000000000000000000000000"); }

	/**
	* Insert a transaction into database
	*
	* @param array Array of database field values. All table fields must be set
	* explicitly! Example:
	*
	*	db_insert( array(
	*		'type'		=> 'receivable',
	*		'address'	=> $address,
	*		'remoteaddress'	=> null,
	*		'txid'		=> null,
	*		'hastings'	=> $hastings,
	*		'expire'	=> $expire,
	*		'blockheight'	=> $blockheight
	*	));
	*
	* @return bool Success or error.
	* @uses
	*/
	public function db_insert($arr) {
		$this->mysqli->begin_transaction();
		if ($stmt = $this->mysqli->prepare('
			INSERT INTO sia (type, address, remoteaddress, txid, hastings, expire, blockheight, msg)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
		)) {
			$stmt->bind_param('ssssssis', $arr['type'], $arr['address'], $arr['remoteaddress'], $arr['txid'], $arr['hastings'], $arr['expire'], $arr['blockheight'], $arr['msg']);
			$stmt->execute();

			// Commit and return on success
			if ($stmt->affected_rows == 1) {
				$stmt->close();
				$this->mysqli->commit();
				return true;
			} else {
				// Rollback and throw exception in case of insert failure
				$stmt->close();
				$this->mysqli->rollback();
				throw new \Exception('Failed to insert entry in sia table');
				return false;
			}
		} else {
			// Database error
			$this->mysqli->rollback();
 			throw new \Exception('Could not query sia database.');
			return false;
		}
		// Unknown error, this code should never execute
		throw new \Exception('Unknown error');
		return false;
	}

	/**
	* Search Sia database by fieldname/string, and retrieve matching rows.
	* 
	* Example:
	*	db_select(array('txid' => $txid, 'type' => 'receivable'), 'OR', 'sia')
	* 
	* @param array Array of field=>string conditions to match
	* @param string Logical operator to use in SQL query (default 'AND')
	* @param string Table to search in (default 'sia')
	* @return bool/array False if error, or array of objects containing rows
	*/
	public function db_select($conds, $operator = 'AND', $table = 'sia') {
		$this->mysqli->begin_transaction();
		// Build SQL statement
		$query = 'SELECT * FROM '.$table.' WHERE ';
		foreach ($conds as $field => $value)
			$query .= $field.'="'.$value.'" '.$operator.' ';
		$query = chop($query, " ".$operator." ");

		if ($stmt = $this->mysqli->prepare($query)) {
			// Fetch results into an array of objects
			$stmt->execute();
			$result = $stmt->get_result();
			$rows = array();
			while ($row = $result->fetch_assoc()) $rows[] = (object) $row;
			$stmt->close();
			return $rows;
		} else {
			// Database error
 			throw new \Exception('Could not query database.');
			return false;
		}
		// Unknown error, this code should never execute
		throw new \Exception('Unknown error');
		return false;
	}

	/**
	* Send funds and register a 'withdrawal' transaction in database
	*
	* @param float $amount Siacoin amount to send
	* @param string $address Sia address to send funds to
	* @return bool False on failure
	* @uses send_sc()
	* @uses db_insert()
	* @uses sc_to_hastings()
	* @uses get_consensus()
	*/
	public function db_withdraw_funds($siacoins, $address) {
		if ($siacoins <= 0) return false; // cannot send zero or negative amount
		$txid = $this->send_sc($siacoins, $address);
		return $this->db_insert( array(
			'type'		=> 'withdrawal',
			'address'	=> 'null',
			'remoteaddress'	=> $address,
			'txid'		=> $txid,
			'hastings'	=> $this->sc_to_hastings($siacoins),
			'expire'	=> 'null',
			'blockheight'	=> $this->get_consensus()->height
		));
	}

	/**
	* Create a new receivable entry in database
	*
	* @param float $amount Siacoin amount to register
	* @param string $address Sia address funds should be sent to
	* @param string $expire_ext Time to expiration, expressed as '+1 day' (default)
	* @return bool False on failure
	* @uses db_insert()
	* @uses sc_to_hastings()
	* @uses get_consensus()
	*/
	public function db_new_receivable($siacoins, $address, $expire_ext = '+1 day') {
		$expire = date('Y-m-d H:i:s', strtotime($expire_ext));
		return $this->db_insert( array(
			'type'		=> 'receivable',
			'address'	=> $address,
			'remoteaddress'	=> 'null',
			'txid'		=> 'null',
			'hastings'	=> $this->sc_to_hastings($siacoins),
			'expire'	=> $expire,
			'blockheight'	=> $this->get_consensus()->height
		));
	}

	/*
	* Refresh database with recent transactions on the blockchain.
	*
	* The function iterates backwards through the blockchain from the most recent
	* block, and registers incoming transactions that match non-expired receivables.
	* If a transaction is found that already exists in the database, the function
	* aborts (unless the $processallblocks parameters is set to true) and assumes all
	* remaining transactions have already been registered.
	*
	* Unconfirmed transactions are not registered.
	*
	* It is recommended to run this function regularly as a cron job.
	*
	* @param bool $processallblocks Process all blocks (false)
	* @return array Array of objects containing new registered transactions
	* @uses get_consensus()
	* @uses wallet_transactions()
	* @uses db_select()
	*/
	public function db_update($processallblocks = false) {
		$newtxns = array();
		$blockheight = $this->get_consensus()->height;

		// Iterate through blocks backwards, starting at the current.
		for ($h = $blockheight; $h >= 22100; $h--) { // note: this code dates to 22130
			// Register block transactions
			$transactions = $this->wallet_transactions($h, $h);
			foreach ($transactions->confirmed as $txid) {
				$newtxn = array(
						'txid' => $txid,
						'type' => 'deposit',
						'address'	=> null,
						'remoteaddress'	=> null,
						'hastings'	=> null,
						'expire'	=> '0000-00-00 00:00:00',
						'blockheight'	=> $blockheight
					);
				$txn = $this->wallet_transaction($txid);

				// Skip withdrawals
				$newtxn['hastings'] = $this->wallet_transaction_hastings_net($txn);
				if ($newtxn['hastings'] <= 0) continue;

				// Abort if transaction is already registered
				$rows = $this->db_select(array('txid' => $txid));
				if (count($rows) > 0 & !$processallblocks) return $newtxns;

				// Get addresses that sent and received funds
				// Note: Transaction sent from local wallet are not registered!
				foreach ($txn->outputs as $output) {
					if (!$output->walletaddress)
						$newtxn['remoteaddress'] = $output->relatedaddress;
					if ($output->walletaddress)
						$newtxn['address'] = $output->relatedaddress;
				}

				// Check if deposit address matches a receivable
				$rows = $this->db_select(array('type' => 'receivable', 'address' => $newtxn['address']));
				if (count($rows) == 1) {
					// Insert transaction in database
					//if ($this->db_insert($newtxn))
						$newtxns[] = $newtxn;
				} elseif (count($rows) > 1) {
					// Multiple receivables are matched. Should not happen!
					// TODO Figure out what to do...
				}
			}
		}
		return $newtxns;
	}    

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
			//throw new \Exception($data);
			return (object) array('Success' => '0'); // standard response
		}
		return $json;
	}
}
?>
