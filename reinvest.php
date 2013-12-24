<?php
	/**
	 * Reinvestor	:	reinvest.php
	 * Version		:	1.0.0
	 * Author		:	Zack Urben
	 * Contact		:	zackurben@gmail.com
	 * Creation		:	12/23/13 (public)
	 *
	 * Motivation BTC	@ 1HvXfXRP9gZqHPkQUCPKmt5wKyXDMADhvQ
	 * Cex.io referral	@ https://cex.io/r/0/kannibal3/0/
	 * Other donations accepted via email request.
	 *
	 * TODO:
	 * - Add start file config
	 */
	
	/**
	 * Define time as US/Central
	 * Change to your desired PHP supported Timezone
	 *
	 * Zones	: http://www.php.net/manual/en/timezones.php
	 */
	date_default_timezone_set("US/Central");

	/**
	 * Include Cex.io PHP API
	 *
	 * Online Documentation	: https://cex.io/api
	 * Master Branch		: https://github.com/matveyco/cex.io-api-php
	 * My Branch			: https://github.com/zackurben/cex.io-api-php
	 *
	 * Since I do not have access to push updates to the Master Branch, and I am a
	 * large contributor to the API project (PHP), please use my branch of the API
	 * for script stability, as this is what I am using to make this.
	 */
	include_once("../../../Documents/Code/GitHub/cex.io-api-php/cexapi.class.php");
	
	/**
	 * Data array to store all trade data. We will pass our data
	 * array as a parameter since this is a script without OOP design.
	 *
	 * Variables:
	 * - max_api_calls	: Max = 600
	 * - coins			: btc{true/false, reserve}, nmc{true/false, reserve}
	 */
	$data = array();
		$data["start_time"] = time();
		$data["last_time"] += $data["start_time"];
		$data["max_api_calls"] = 400; // Keep track of API calls, to avoid IP ban.
		$data["api_calls"] = 0; // Keep track of analytics for trades
		$data["coins"] = array(
				"btc" => array("active"=>true, "reserve"=>0, "ticker"=>"GHS/BTC"),
				"nmc" => array("active"=>true, "reserve"=>0, "ticker"=>"GHS/NMC")); // coins to use 
		$data["position"] = array(); // current sales data
		$data["pending"] = array(); // pending orders (to complete)
		
	/**
	 * Cleanly format the blance output for terminal
	 *
	 * Input	: Cex.io API JSON output
	 * Output	: None 
	 */
	function format_balance($json) {	
		$json = json_decode($json, true);
			
		if(isset($json["timestamp"])) {
			echo "\n", date("Y-m-d h:i:s A", $json["timestamp"]), "\nCur Available  Order\n";
		}
		if(isset($json["BTC"])) {
			echo "BTC ", format_number($json["BTC"]["available"]), " ", format_number($json["BTC"]["orders"]), "\n";	
		}
		if(isset($json["GHS"])) {
			echo "GHS ", format_number($json["GHS"]["available"]), " ", format_number($json["GHS"]["orders"]), "\n";
		}
		if(isset($json["IXC"])) {
			echo "IXC ", format_number($json["IXC"]["available"]), "\n";
		}
		if(isset($json["DVC"])) {
			echo "DVC ", format_number($json["DVC"]["available"]), "\n";
		}
		if(isset($json["NMC"])) {
			echo "NMC ", format_number($json["NMC"]["available"]), "\n";
		}
	}
	
	/**
	 * Cleanly format the numbers to align with eachother.
	 *
	 * Input	: Number with x chars
	 * Output	: Number with 10 chars (decimal included)
	 */
	function format_number($num) {
		$temp = strlen($num);

		if($temp > 10) {
			$temp = substr(strval($num), 0, 10);
		} elseif($temp < 10) {
			if(strpos($str, ".") !== false) {
				// number has decimal already
			    $temp = number_format($num, (9-$temp), ".", "");
			} else {
				// number does not have decimal already
				$temp = number_format($num, (10-$temp), ".", "");	
			}
			
			if(strlen($temp) < 10) {
				$zeros = "";
				
				for($a = 0; $a < (10 - strlen($temp)); $a++) {
					$zeros .= "0";	
				}
				$temp = $temp . $zeros;
			}
		} else {
			$temp = $num;
		}
		
		return strval($temp);
	}
	
	/**
	 * Format ticker data
	 * Input	: JSON array of ticker data
	 * Output	: Print GHS/BTC last/high/low/bid/ask/volume
	 */
	function format_ticker($json) {
		$json = json_decode($json, true);
		
		// Display Time and formatted JSON Data
		echo "\n", date("Y-m-d h:i:s A", $json['timestamp']), "\nLast       High       Low        Bid        Ask        Volume\n";
		echo format_number($json["last"]), " ", format_number($json["high"]), " ",	format_number($json["low"]), " ";
		echo format_number($json["bid"]), " ", format_number($json["ask"]), " ", format_number($json["volume"]), "\n";
	}
	
	/**
	 *
	 * Input	: coin = string {btc | nmc}
	 */
	function analyze(&$user, &$data, $coin) {
		// determine if pending orders exist
		if(isset($data["pending"][$coin]) && (count($data["pending"][$coin]) > 0)) {
			$pending = true;
		} else {
			$pending = false;	
		}
		
		// if pending, determine if order is too old
		if($pending) {
			$cur = time();

			$temp_oo = execute($user, $data, "open_orders", $data["coins"][$coin]["ticker"]);
			foreach($data["pending"][$coin] as $key => $trade) {
				// if order is present in open_orders
				if(isset($temp_oo[strval($trade["id"])])) {
					if($cur < ($trade["time"] + 60)) {
						// order is older than 1 min, cancel order by id
						$temp_co = execute($user, $data, "cancel_order", $trade["id"]);
						
						// remove order by id
						if($temp_co) {
							echo "[", date("Y-m-d h:i:s A", time()), "] ", "Reinvestor: Canceled pending order (", $data["pending"][$coin][$trade["id"]], ").\n";
							unset($data["pending"][$coin][$trade["id"]]);
						} else {
							echo "[", date("Y-m-d h:i:s A", time()), "] ", "ERROR: could not cancel pending order\n", print_r($temp_co, true), "\n";
						}
					}
				} else {
					// item is no longer in the order (transaction was successful)
					unset($data["pending"][$coin][strval($trade["id"])]);
				}
			}
		}
		
		// recalc balance
		$balance = execute($user, $data, "balance");
		echo "[", date("Y-m-d h:i:s A", time()), "] ", "Reinvestor: Current ", strtoupper($coin),
				" balance: ", $balance[strtoupper($coin)]["available"], "\n";
		
		// do purchases here
		if($balance[strtoupper($coin)]["available"] > $data["coins"][$coin]["reserve"]) {
			$price = execute($user, $data, "ticker", $data["coins"][$coin]["ticker"]); // get pair info
	
			// calculate purchase amount
			$amt = ($balance[strtoupper($coin)]["available"]/$price["last"]);
			$amt = round($amt, 8);
			
			if($amt > $balance[strtoupper($coin)]["available"]) {
				$amt -= 0.00000001; // round, exceeded funds
			}
			
			$temp = execute($user, $data, "place_order", array("buy", $amt, $price["last"], $data["coins"][$coin]["ticker"]));
			file_put_contents("buy_order.txt", ("[" . date("Y-m-d h:i:s A", time()) . "] Reinvestor Purchase:\n" . print_r($temp, true) . "\n"), FILE_APPEND | LOCK_EX);
			
			if($temp["pending"] == 0) {
				// purchase done
				echo "[", date("Y-m-d h:i:s A", time()), "] ", "Reinvestor: Purchased ", $temp["amount"], " GHS @ ", $temp["price"], " (Pending: ", $temp["pending"], " GHS)\n";
			} else {
				// purchase pending
				$data["pending"][$coin][strval($temp["id"])] = $temp;
			}
			
			// Estimation for average time allowed per call, per lockout period.
			// sleep(intval((($data["max_api_calls"]/3)/60)*2));
			//sleep(60); // hardcoded
		} else {
			// Generic waiting time to lower CPU time
			sleep(60);
			echo "[", date("Y-m-d h:i:s A", time()), "] ", "Waiting, insufficient funds to initiate new positions.\n"; // DEBUG
		}
	}
	
	/**
	 *
	 */
	function reinvest(&$user, &$data) {
		$done = false;
		
		while(!$done) {
			// get account info, determine if can trade
			$balance = execute($user, $data, "balance");
			
			// determine which trades will occur
			$trade_btc = ($data["coins"]["btc"]["active"]) && 
				(isset($balance["BTC"]["available"]) && ($balance["BTC"]["available"] > $data["coins"]["btc"]["reserve"]));
			$trade_nmc = ($data["coins"]["nmc"]["active"]) && 
				(isset($balance["NMC"]["available"]) && ($balance["NMC"]["available"] > $data["coins"]["nmc"]["reserve"]));
			
			if($trade_btc || $trade_nmc) {
				if($trade_btc) {
					analyze($user, $data, "btc");
				}
				if($trade_nmc) {
					analyze($user, $data, "nmc");
				}
			} else {
				// wait till next call, no trades available
				echo "[", date("Y-m-d h:i:s A", time()), "] ", "Reinvestor: Waiting, insufficient funds to initiate new positions.\n";
				sleep(60);	
			}
			
			// wait to iterate again
			echo "[", date("Y-m-d h:i:s A", time()), "] ", "Reinvestor: Round complete!\n";
			sleep(60); // hardcoded
		}
	}
	
	/**
	 * Wrapper class for API calls, to ensure the maximum limit is not exceeded.
	 * Input	: Function which calls Cex.io API
	 * Output	: Any function output
	 */
	function execute(&$user, &$data, $function, $param = NULL) {
		$done = false;
		$out = "";
		
		// Initial time check, to reset api_timer
		if(time() > ($data["last_time"] + 600)) {
			$data["api_calls"] = 0;
			$data["last_time"] = time();
		}
		
		while(!$done) {
			if($data["api_calls"] < $data["max_api_calls"]) {
				if($function == "balance") {
					$out = $user->balance();
				} elseif($function == "ticker") {
					$out = $user->ticker($param); // get ticker of param
				} elseif($function == "order_book") {
					// not used for reinvest
					$out = $user->order_book($param); // get order book of pair
				} elseif($function == "place_order") {
					$out = $user->place_order($param[0], $param[1], $param[2], $param[3]); // place order for param[3]
				} elseif($function == "open_orders") {
					$out = $user->open_orders($param);	
				} elseif($function == "cancel_order") {
					$out = $user->cancel_order($param); // cancel order by id=$param
				} elseif($function == "trade_history") {
					$out = $user->trade_history($param); // trade history since time=$param
				}
				
				$data["api_calls"]++;
				$done = true;
			} else {
				// additional time check, to reset api_timer
				if(time() > ($data["last_time"] + 600)) {
					$data["api_calls"] = 0;
					$data["last_time"] = time();
				} else {
					// api calls was too high, sleep
					echo "API Limit reached, waiting 60 seconds to try again.\n";
					sleep(60);
				}
			}
		}
		
		if(isset($out["error"])) {
			$output = "API Error: (" . $function . ")" . $out["error"] . "\n";
			$output .= "ERROR DBG: " . $function . ": " . print_r($param, true) . "\n";
			
			// debug and log		
			file_put_contents("error_log.txt", ("[" . date("Y-m-d h:i:s A", time()) . "] " . $output), FILE_APPEND | LOCK_EX);
		}
		
		if($out != NULL) {
			// If their is output, display it.
			return $out;
		}
	}
	
	/**
	 * Infinitly loop the program waiting for user response
	 * Displays possible reponse types.
	 *
	 * Input	: User API Object, Program data
	 * Output	: None
	 */
	function loop($user, &$data) {
		$done = false;
		
		while(!$done) {
			echo "[B]alance | [1] Display 'GHS/BTC' | [2] Display 'GHS/NMC' | [E]xit\n";
			echo "[R]einvest\n";
			echo "Reinvestor> ";
			$stdin = fopen("php://stdin", "r");
			$input = fgetc($stdin);
			if ($input == "B" || $input == "b") {
				// Display formatted user balance
				format_balance(json_encode(execute($user, $data, "balance")));
				echo "\n";
			} elseif ($input == "1") {
				// Display formatted GHS/BTC data
				format_ticker(json_encode(execute($user, $data, "ticker", "GHS/BTC")));
				echo "\n";
			} elseif ($input == "2") {
				// Display formatted GHS/NMC data
				format_ticker(json_encode(execute($user, $data, "ticker", "GHS/NMC")));
				echo "\n";
			} elseif($input == "E" || $input == "e") {
				// request to quit was made
				$done = true;
			} elseif($input == "R" || $input == "r") {
				// reinvest core
				reinvest($user, $data);
			} else {
				// halt cpu
				sleep(7);
			}
		}
		
		// tightguy spam
		echo "Thanks for using my Cex.io Reinvestment tool.\n";
		echo "Motivation BTC: 1HvXfXRP9gZqHPkQUCPKmt5wKyXDMADhvQ\n";
		echo "Reinvestment ran for ", round(((time() - $data["start_time"])/60), 2), " minutes!\n";
		exit;
	}
	
	/**
	 * Authenticate User and their Credentials, loop infinitely until exit is requested.
	 *
	 * Variables:
	 * - argv[1] = Cex.io Username
	 * - argv[2] = Cex.io API Key
	 * - argv[3] = Cex.io API Secret
	 */
	if($argv[1] != "" && $argv[2] != "" && $argv[3] != "") {
		$api = new cexapi($argv[1], $argv[2], $argv[3]);
		if(isset($api)) {
			loop($api, $data);
		} else {
			echo "Trading requires a Username, API Key, and API Secret.\n";
			echo "Please visit: Cex.io/api, if you do not have an API Key and Secret.\n";
			echo "Proper use is: \"php trade.php Username API_Key API_Secret\"\n";
			echo "Authentication error: ", print_r($api, true), "\n"; // DEBUG
		}
	} else {
		echo "Trading requires a Username, API Key, and API Secret.\n";
		echo "Please visit: Cex.io/api, if you do not have an API Key and Secret.\n";
		echo "Proper use is: \"php trade.php Username API_Key API_Secret\"\n";
	}
?>