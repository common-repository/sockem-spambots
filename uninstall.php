<?php
/**
 * Sock'Em SPAMbots - Uninstall
 *
 * Clean up plugin data on removal.
 *
 * @package sockem-spambots
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

// Make sure WordPress is calling this page.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit(1);
}

// Remove the default debug log.
$files = array(
	'sockem_debug.log',
	'sockem_debug.json',
);
foreach ($files as $v) {
	if (@file_exists(__DIR__ . "/$file")) {
		@unlink(__DIR__ . "/$file");
	}
}

// Remove the options.
delete_option('sockem_options');
