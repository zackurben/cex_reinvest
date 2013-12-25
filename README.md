# Cex.io PHP Reinvestor
The PHP Reinvestor is an opensource project, licensed under MIT, focused on automatic reinvesting in to GHS from the [Cex.io](https://cex.io/r/0/kannibal3/0/) Bitcoin cloud mining platform.

The Reinvestor is a platform agnostic script.

## Features
+ Uses BTC and/or NMC
+ Each coin has a seperate reserve limit to keep
+ Resubmits stale orders
+ Interactive console
+ Purchase and Error logging

## Getting Started
1. Obtain a free [Cex.io API Key](https://cex.io/trade/profile).
... This Key needs the following permissions: 
..* Account Balance 
..* Open Order 
..* Place Order 
..* Cancel Order 
2. Download the latest version of the [Cex.io API](https://github.com/zackurben/cex.io-api-php).
3. Download the latest version of the [Cex.io PHP Reinvestor](https://github.com/zackurben/cex_reinvest).
4. Place both items in the same directory.

### *nix Box Specific
#### Usage
In terminal, execute:
```
php reinvest.php Username API_Key API_Secret
```

### Window Box Specific
1. Download the lastest version of [PHP for Windows](http://windows.php.net/downloads/releases/php-5.5.7-Win32-VC11-x64.zip)
2. Create a batch script to run the Reinvestor.
... A working example:
```
cd "php" 
php.exe ..\reinvest.php Username API_Key API_Secret" 
pause 
```

#### Usage
Double click your batch fie icon!

## Contact
* Author: Zack Urben
* Contact: zackurben@gmail.com

### Support
If you would like to support the development of this project, please spread the word and donate!

* Motivation BTC @ 1HvXfXRP9gZqHPkQUCPKmt5wKyXDMADhvQ
* Cex.io referral @ https://cex.io/r/0/kannibal3/0/
* Cryptsy Trade Key @ e5447842f0b6605ad45ced133b4cdd5135a4838c
* Other donations accepted via email request!
