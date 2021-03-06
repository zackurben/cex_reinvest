<?php
	/**
	 * This project is licensed under the terms of the MIT license,
	 * you can read more in LICENSE.txt.
	 *
	 * Reinvestor	:	reinvest.php
	 * Version		:	1.0.8
	 * Author		:	Zack Urben
	 * Contact		:	zackurben@gmail.com
	 * Creation		:	12/23/13 (public)
	 *
	 * This script requires a free API Key from Cex.io, which can be obtained
	 * here: https://cex.io/trade/profile
	 * This API Key requires the following permissions:
	 * Account Balance, Place Order, Cancel Order, Open Order
	 *
	 * Motivation BTC	@ 1HvXfXRP9gZqHPkQUCPKmt5wKyXDMADhvQ
	 * Cex.io Referral	@ https://cex.io/r/0/kannibal3/0/
	 * Cryptsy Trade Key@ e5447842f0b6605ad45ced133b4cdd5135a4838c
	 * Other donations accepted via email request.
	 *
	 * TODO:
	 * - Add start file config
	 * - Add buy/sell limits for each coin.
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
	include_once("cexapi.class.php");
	
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
	 * Format input to display in the console.
	 *
	 * Input	: String to display in console, with timestamp.
	 * Output	: None
	 */
	function out($input, $prepend = NULL){
		echo $prepend, "[", date("Y-m-d h:i:s A", time()), "] ", $input;
	}
		
	/**
	 * Cleanly format the blance output for terminal
	 *
	 * Input	: Cex.io API JSON output
	 * Output	: None 
	 */
	function format_balance($json) {	
		$json = json_decode($json, true);
			
		if(isset($json["timestamp"])) {
			out("\nCur Available  Order\n", "\n");
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
		out("\nLast       High       Low        Bid        Ask        Volume\n" .
			format_number($json["last"]) . " " . format_number($json["high"]) . " " . format_number($json["low"]) . " " .
			format_number($json["bid"]) . " " . format_number($json["ask"]) . " " . format_number($json["volume"]) . "\n", "\n");
	}
	
	/**
	 * Analyze trade for the given coin. This will cancel incomplete buy transactions
	 * after ~1 minute, and resubmit at the current price; to ensure all funds are utilized.
	 * This will additionaly use the given coin to purchase the maximum amount of coins with
	 * the given coin reserve amount.
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
		
		// if pending, determine if each order is too old and cancel
		if($pending) {
			$cur = time();

			$temp_oo = execute($user, $data, "open_orders", $data["coins"][$coin]["ticker"]);
			foreach($data["pending"][$coin] as $key) {
				// if order is present in open_orders
				if(isset($temp_oo[$key["id"]])) {
					if($cur > ($key["time"] + 60)) {
						// order is older than 1 min, cancel order by id
						$temp_co = execute($user, $data, "cancel_order", $key["id"]);
						
						// remove order by id
						if($temp_co) {
							out("Reinvestor: Canceled pending order (" . $data["pending"][$coin][$key["id"]] . ").\n");
							unset($data["pending"][$coin][$key["id"]]);
						} else {
							// TODO: log error to file
							file_put_contents("error_log.txt", ("[" . date("Y-m-d h:i:s A", time()) .
								"] Reinvestor, could not cancel pending order:\ntemp_co:" . print_r($temp_co, true) .
								"\ntemp_oo:" . print_r($temp_oo, true)), FILE_APPEND | LOCK_EX);
							out("ERROR: could not cancel pending order\n" . print_r($temp_co, true) . "\n");
						}
					}
				} else {
					//out("temp_oo dbg: " . print_r($temp_oo, true) . "\n");
					// item is no longer in the order (transaction was successful)				
					out("Reinvestor: Purchase order complete (ID: " . $key["id"] . ", " . $key["amount"] . 
						" GHS @ " . $key["price"] . " " . $data["coins"][$coin]["ticker"] . ")\n");
					unset($data["pending"][$coin][strval($key["id"])]);
				}
			}
		}
		
		// recalc balance
		$balance = execute($user, $data, "balance");
		out("Reinvestor: Current " . strtoupper($coin) . " balance: " . $balance[strtoupper($coin)]["available"] . "\n");
		
		// do purchases here
		if($balance[strtoupper($coin)]["available"] > $data["coins"][$coin]["reserve"]) {
			$price = execute($user, $data, "ticker", $data["coins"][$coin]["ticker"]); // get pair info
	
			// calculate purchase amount
			// catch random divide by zero
			if($price["last"] != 0) {
				$amt = ((($balance[strtoupper($coin)]["available"]-$data["coins"][$coin]["reserve"])/$price["last"]));
				$amt = (round($amt, 8));
			} else {
				// error
				out("ERROR: Last price from ticker was zero, DBG: (" . $data["coins"][$coin]["ticker"] . ")" . print_r($price, true));
			}
			
			if($amt > $balance[strtoupper($coin)]["available"]/$price["last"]) {
				$amt -= 0.00000001; // rounding made amt exceed available funds
			}
			
			if($amt > 0.00000001) {
				$amt = number_format((float) $amt, 8, ".", ""); // correct floatval() converting to scientific notation
				$temp = execute($user, $data, "place_order", array("buy", $amt, $price["last"], $data["coins"][$coin]["ticker"]));
				
				
				if(!isset($temp["error"])) {
					file_put_contents("buy_order.txt", ("[" . date("Y-m-d h:i:s A", time()) . "] Reinvestor Purchase (" . 
						strtoupper($coin) . "):\n" . print_r($temp, true) . "\n"), FILE_APPEND | LOCK_EX);
					
					if((isset($temp["id"]) && ($temp["pending"] == 0)) && isset($temp["id"])) {
						// actual purchase done
						out("Reinvestor: Purchased " . $temp["amount"] . " GHS @ " . $temp["price"] . " " .
							$data["coins"][$coin]["ticker"] ." (Pending: " . $temp["pending"] . " GHS)\n");
					} else {
						// purchase pending, or obscure error
						out("Reinvestor: Purchase order for " . $temp["amount"] . " GHS @ " . $temp["price"] . " " .
							$data["coins"][$coin]["ticker"] ." (Pending: " . $temp["pending"] . " GHS, ID: " . $temp["id"] . ")\n");
						$data["pending"][$coin][strval($temp["id"])] = $temp;
					}
				} else {
					out("Reinvestor: Purchase error for "  . $amt . " GHS @ " . $price["last"] . " " . 
						$data["coins"][$coin]["ticker"] . "\n");
				}
			} else {
				// coin balance cannot purchase minimum GHS
				// remove because of spam?
				// allow with verbose flag
				//out("Reinvestor: " . strtoupper($coin) . " balance is too low to place the minimum order.\n");
			}
		} else {
			// Generic waiting time to lower CPU time
			sleep(60);
			// TODO: edit output
			//out("Waiting, " . strtoupper($coin) . " balance (" . $balance[strtoupper($coin)]["available"] . 
			//	") is lower than the specified reserve amount (" . $data["coins"][$coin]["reserve"] . ")\n"); // DEBUG
		}
	}
	
	/**
	 * Considers the feasibility of reinvestment.
	 * 
	 * Input	: User API object, program data.
	 * Output	: None
	 */
	function reinvest(&$user, &$data) {
		$done = false;

		while(!$done) {
			// get account info, determine if can trade
			$balance = execute($user, $data, "balance");
			
			// determine which trades will occur
			$trade_btc = (($data["coins"]["btc"]["active"]) && 
				(isset($balance["BTC"]["available"]) && ($balance["BTC"]["available"] > $data["coins"]["btc"]["reserve"]))) ||
				(count($data["pending"]["btc"]) > 0);
			$trade_nmc = (($data["coins"]["nmc"]["active"]) && 
				(isset($balance["NMC"]["available"]) && ($balance["NMC"]["available"] > $data["coins"]["nmc"]["reserve"]))) ||
				(count($data["pending"]["nmc"]) > 0);
			
			if($trade_btc || $trade_nmc) {
				if($trade_btc) {
					analyze($user, $data, "btc");
				}
				if($trade_nmc) {
					analyze($user, $data, "nmc");
				}
			} else {
				// wait till next call, no trades available
				out("Reinvestor: Waiting, insufficient funds to initiate new positions.\n");
				sleep(60);	
			}
			
			// wait to iterate again
			sleep(60); // hardcoded
		}
	}
	
	/**
	 * Wrapper class for API calls, to ensure the maximum limit is not exceeded.
	 *
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
					// not used for reinvest (yet)
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
					out("API Limit reached, waiting 60 seconds to try again.\n");
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
			
			out("\n[B]alance | [1] Display 'GHS/BTC' | [2] Display 'GHS/NMC' | [E]xit\n" .
				"[R]einvest\n" .
				"Reinvestor> ", "\n");
			$stdin = fopen("php://stdin", "r");
			$input = fgetc($stdin);
			if ($input == "B" || $input == "b") {
				// Display formatted user balance
				format_balance(json_encode(execute($user, $data, "balance")));
			} elseif ($input == "1") {
				// Display formatted GHS/BTC data
				format_ticker(json_encode(execute($user, $data, "ticker", "GHS/BTC")));
			} elseif ($input == "2") {
				// Display formatted GHS/NMC data
				format_ticker(json_encode(execute($user, $data, "ticker", "GHS/NMC")));
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
		out("\nThanks for using my Cex.io Reinvestment tool.\n" .
			"Motivation BTC: 1HvXfXRP9gZqHPkQUCPKmt5wKyXDMADhvQ\n" .
			"Reinvestment ran for " . round(((time() - $data["start_time"])/60), 2) . " minutes!\n");
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
			out("\nTrading requires a Username, API Key, and API Secret.\n" .
				"Please visit: Cex.io/api, if you do not have an API Key and Secret.\n" .
				"Proper use is: \"php reinvest.php Username API_Key API_Secret\"\n" .
				"Authentication error: " . print_r($api, true) . "\n"); // DEBUG
		}
	} else {
		out("\nTrading requires a Username, API Key, and API Secret.\n" .
			"Please visit: Cex.io/api, if you do not have an API Key and Secret.\n" .
			"Proper use is: \"php reinvest.php Username API_Key API_Secret\"\n");
	}
?>
