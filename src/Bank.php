<?php
	abstract class Bank {

		/**
		 * Get the sub-accounts of this object.
		 * This will return cached account objects.
		 *
		 * @param $useCached (Default: true) Return cached values if possib;e?
		 * @param $transactions (Default: false) Also update transactions?
		 *                      (This will force a reload of the accounts only if
		 *                       none of them have any associated transactions)
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions (not applicable to all Banks)
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok? (not applicable to all Banks)
		 * @return accounts associated with this login.
		 */
		abstract function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false);

		/**
		 * Update the transactions on the given account object.
		 *
		 * @param $account Account to update.
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		abstract function updateTransactions($account, $historical = false, $historicalVerbose = true);
	}
?>