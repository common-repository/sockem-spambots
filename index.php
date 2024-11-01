<?php
/**
 * A seamless approach to deflecting the vast majority of SPAM comments.
 *
 * @package Sock'Em SPAMbots
 * @version 1.0.1
 *
 * @wordpress-plugin
 * Plugin Name: Sock'Em SPAMbots
 * Version: 1.0.1
 * Plugin URI: https://wordpress.org/plugins/sockem-spambots/
 * Description: A seamless approach to deflecting the vast majority of SPAM comments.
 * Text Domain: sockem-spambots
 * Domain Path: /languages/
 * Author: Blobfolio, LLC
 * Author URI: https://blobfolio.com/
 * License: WTFPL
 * License URI: http://www.wtfpl.net
 */

/**
 * Do not execute this file directly.
 */
if (!defined('ABSPATH')) {
	exit;
}

define('SOCKEM_PLUGIN_DIR', __DIR__ . '/');
define('SOCKEM_INDEX', __FILE__);

// Are we installed in Must-Use mode?
$sockem_must_use = (
	defined('WPMU_PLUGIN_DIR') &&
	@is_dir(WPMU_PLUGIN_DIR) &&
	(0 === strpos(SOCKEM_PLUGIN_DIR, WPMU_PLUGIN_DIR))
);
define('SOCKEM_MUST_USE', $sockem_must_use);

// Set up a few constants.
define('SOCKEM_DEBUG_PATH', __DIR__ . '/sockem_debug.json');
define('SOCKEM_DEBUG_URL', plugins_url('sockem_debug.json', __FILE__));



// ---------------------------------------------------------------------
// Options
// ---------------------------------------------------------------------

/**
 * Get Option
 *
 * @param string $option Option.
 * @param bool $refresh Refresh.
 * @return mixed Option(s)
 */
function sockem_get_option($option=null, $refresh=false) {
	static $sockem_options;

	// Need to pull the options!
	if ($refresh || !is_array($sockem_options)) {
		// Load the saved settings.
		$sockem_options = get_option('sockem_options', array());
		if (!is_array($sockem_options)) {
			$sockem_options = array();
		}

		// The default settings.
		$sockem_defaults = array(
			// Require Javascript support.
			'test_js'=>true,
			// Require cookie support.
			'test_cookie'=>true,
			// Enable honeypot field.
			'test_filler'=>true,
			// Speed limit.
			'test_speed'=>true,
			// Form expiration.
			'test_expiration'=>true,
			// Seconds before comment can be submitted.
			'test_speed_seconds'=>5,
			// Seconds before form expires.
			'test_expiration_seconds'=>14400,
			// Check for excessive links.
			'test_links'=>true,
			// Maximum number of links.
			'test_links_max'=>4,
			// Maximum comment length.
			'test_length'=>false,
			// Maximum length.
			'test_length_max'=>1500,
			// Exempt logged-in users from tests.
			'exempt_users'=>true,
			// Disable comment author links.
			'disable_comment_author_links'=>false,
			// A salt.
			'salt'=>sockem_make_salt(),
			// The hashing algorithm.
			'algo'=>'sha512',
			// Disable trackbacks.
			'disable_trackbacks'=>true,
			// Disable pingbacks.
			'disable_pingbacks'=>false,
			// Maintain a debug log.
			'debug'=>false,
		);

		// Keep track of changes.
		$changed = false;

		// Supply any missing settings with defaults.
		$tmp = array_diff(array_keys($sockem_defaults), array_keys($sockem_options));
		if (count($tmp)) {
			foreach ($tmp as $key) {
				$sockem_options[$key] = $sockem_defaults[$key];
			}

			$changed = true;
		}

		// Remove extraneous fields.
		$tmp = array_diff(array_keys($sockem_options), array_keys($sockem_defaults));
		if (count($tmp)) {
			foreach ($tmp as $key) {
				unset($sockem_options[$key]);
			}

			$changed = true;
		}

		// Make sure the hashing algorithm is supported.
		$tmp = hash_algos();
		if (!in_array($sockem_options['algo'], $tmp, true)) {
			// Let's run through preferences until we find one that matches.
			foreach (array('sha512', 'sha256', 'sha1', 'md5') as $algo) {
				if (in_array($algo, $tmp, true)) {
					$sockem_options['algo'] = $algo;
					$changed = true;
					break;
				}
			}
		}

		// Last thing: fix types.
		foreach ($sockem_defaults as $k=>$v) {
			$default_type = gettype($v);
			$now_type = gettype($sockem_options[$k]);

			// It's fine.
			if ($default_type === $now_type) {
				continue;
			}

			// If the type is really wrong it could explode. Haha.
			try {
				settype($sockem_options[$k], $default_type);
			} catch (Throwable $e) {
				$sockem_options[$k] = $v;
			} catch (Exception $e) {
				$sockem_options[$k] = $v;
			}
		}

		// Save changes, if any.
		if ($changed) {
			update_option('sockem_options', $sockem_options);
		}

		// Free up some memory.
		unset($tmp);

	} // End options.

	// Return all options.
	if (!$option) {
		return $sockem_options;
	}
	// Return a specific option.
	elseif (isset($sockem_options[$option])) {
		return $sockem_options[$option];
	}

	return null;
}

/**
 * Generate Salt
 *
 * @param int $length Length.
 * @return string Salt.
 */
function sockem_make_salt($length=15) {
	$length = (int) $length;
	if ($length < 1) {
		return '';
	}

	// Possible characters.
	$soup = array(
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L',
		'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
		'Y', 'Z', '2', '3', '4', '5', '6', '7', '8', '9', '.',
		'_', '@', '#', '$', '%', '^', '&', '?', ':', '!', ';',
	);

	// Pre-shuffle for extra randomness.
	shuffle($soup);

	// Build the salt.
	$salt = '';
	$soup_max = count($soup) - 1;
	for ($x = 0; $x < $length; ++$x) {
		$index = wp_rand(0, $soup_max);
		$salt .= $soup[$index];
	}

	return $salt;
}

/**
 * Generate Javascript Hash
 *
 * @param int $post_id Post ID.
 * @return string Hash.
 */
function sockem_make_js_hash($post_id=0) {
	$sockem_options = sockem_get_option();

	// Hash a handful of things.
	$soup = array(
		site_url(),
		$sockem_options['salt'],
		intval($post_id),
		date('Y-m-d'),
	);

	return hash($sockem_options['algo'], implode('|', $soup));
}

/**
 * Generate Cookie Hash
 *
 * @return string Hash.
 */
function sockem_make_cookie_hash() {
	$sockem_options = sockem_get_option();

	// Hash a handful of things.
	$soup = array(
		site_url(),
		$sockem_options['salt'],
		date('Y-m-d'),
	);

	$hash = hash($sockem_options['algo'], implode('|', $soup));
	return substr($hash, 0, 10);
}

/**
 * Generate Hash for Speed Tests
 *
 * @param int $timestamp Timestamp.
 * @return string Hash.
 */
function sockem_make_speed_hash($timestamp=0) {
	$timestamp = (int) $timestamp;
	$sockem_options = sockem_get_option();

	// Default to NOW.
	if ($timestamp <= 0) {
		$timestamp = (int) current_time('timestamp');
	}

	// Hash a handful of things.
	$soup = array(
		site_url(),
		$sockem_options['salt'],
		$timestamp,
		date('Y-m-d'),
	);

	return hash($sockem_options['algo'], implode('|', $soup)) . ",$timestamp";
}

/**
 * Validate Speed Hash
 *
 * @param string $hash Hash.
 * @param string $mode Mode.
 * @return bool True/false.
 */
function sockem_validate_speed_hash($hash='', $mode='speed') {
	$sockem_options = sockem_get_option();

	// Make sure hash looks hashish.
	if (!$hash || !is_string($hash) || !preg_match('/^.+,\d+$/', $hash)) {
		return false;
	}

	// Split the hash into its components.
	list($hash_hash, $hash_time) = explode(',', $hash);
	$hash_time = (int) $hash_time;
	$now = (int) current_time('timestamp');

	// Make sure the hash is itself valid.
	if (sockem_make_speed_hash($hash_time) !== $hash) {
		return false;
	}

	// Check it wasn't too quick.
	if ('speed' === $mode) {
		return ($now - $hash_time) >= $sockem_options['test_speed_seconds'];
	}

	// Check it wasn't too slow.
	return ($now - $hash_time) < $sockem_options['test_expiration_seconds'];
}

// --------------------------------------------------------------------- end variables



// ---------------------------------------------------------------------
// Admin Area
// ---------------------------------------------------------------------

/**
 * Menu: Sock'Em Settings
 *
 * @return void Nothing.
 */
function sockem_settings_menu() {
	add_options_page(
		"Sock'Em SPAMbots",
		"Sock'Em SPAMbots",
		'manage_options',
		'sockem-settings',
		'sockem_settings'
	);
}
add_action('admin_menu', 'sockem_settings_menu');

/**
 * Plugin Link: Sock'Em Settings
 *
 * @param array $links Links.
 * @return array Links.
 */
function sockem_plugin_settings_link($links) {
  $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=sockem-settings')) . '">' . __('Settings', 'sockem-spambots') . '</a>';
  return $links;
}
add_filter('plugin_action_links_sockem-spambots/index.php', 'sockem_plugin_settings_link');

/**
 * Admin Page: Sock'Em Settings
 *
 * @return void Nothing.
 */
function sockem_settings() {
	require(__DIR__ . '/settings.php');
}

/**
 * Localize.
 *
 * @return void Nothing.
 */
function sockem_localize() {
	// For reasons that are unclear, there is a slightly different
	// localization function that must be used when a plugin is
	// installed in "Must Use" mode.
	if (SOCKEM_MUST_USE) {
		load_muplugin_textdomain(
			'sockem-spambots',
			basename(SOCKEM_PLUGIN_DIR) . '/languages'
		);
	}
	else {
		load_plugin_textdomain(
			'sockem-spambots',
			false,
			basename(SOCKEM_PLUGIN_DIR) . '/languages'
		);
	}
}
add_action('plugins_loaded', 'sockem_localize');

/**
 * Privacy Policy
 *
 * @return void Nothing.
 */
function sockem_privacy() {
	// Unfortunately we can't check for this prior to hooking to the
	// action.
	if (!function_exists('wp_add_privacy_policy_content')) {
		return;
	}

	$sockem_options = sockem_get_option();
	$privacy = array();

	// Cookies are a thing, perhaps.
	if ($sockem_options['test_cookie']) {
		$privacy[] = __('This site uses cookies to help verify the humanity of comment form submissions. Because most SPAM comes from robots, and many robots lack support for cookies, this is an effective and non-intrusive means of filtering out the junk.', 'sockem-spambots');
	}

	// Debugging is a big flag.
	if ($sockem_options['debug']) {
		$privacy[] = __('This site is currently logging all comment form submissions, including those which are rejected as SPAM. This is done for quality assurance purposes only, and old data is automatically removed once it is no longer relevant.', 'sockem-spambots');
	}

	// Add the notice!
	wp_add_privacy_policy_content(
		__("Sock'Em SPAMbots", 'sockem-spambots'),
		wp_kses_post(wpautop(implode("\n\n", $privacy)))
	);
}
add_action('admin_init', 'sockem_privacy');

// --------------------------------------------------------------------- end admin



// ---------------------------------------------------------------------
// Comment form modification(s)
// ---------------------------------------------------------------------

// --------------------------------------------------
// Comment form modification(s), based on enabled
// settings
//
// @since 0.5.0
//
// @param post id
// @return true (content is echoed)
/**
 * Comment Form Modification(s)
 *
 * @param int $post_id Post ID.
 * @return void Nothing.
 */
function sockem_comment_form($post_id=0) {
	$sockem_options = sockem_get_option();

	// If we can trust the user, leave!
	if ($sockem_options['exempt_users'] && is_user_logged_in()) {
		return;
	}

	// Require Javascript.
	if ($sockem_options['test_js']) {
		?>
		<script id="sockem-js-hash">
			(function() {
				var el = document.createElement('input');
				el.type = 'hidden';
				el.name = 'sockem_js';
				el.value = '<?php echo esc_js(sockem_make_js_hash($post_id)); ?>';

				var script = document.getElementById('sockem-js-hash');
				script.parentNode.insertBefore(el, script);
			})();
		</script>
		<noscript>
			<p class="form-allowed-tags"><?php
				echo __('Your browser must have Javascript support enabled to leave comments.', 'sockem-spambots');
			?></p>
		</noscript>
		<?php
	}

	// Honepot test.
	if ($sockem_options['test_filler']) {
		?>
		<input type="text" name="sockem_filler" value="" placeholder="Please leave this field blank" style="position: fixed; top: -9999px; left: -9999px; speak: none; width: 1px; height: 1px; overflow: hidden; pointer-events: none; opacity: 0;" tabindex="-1" />
		<?php
	}

	// Speed test?
	if ($sockem_options['test_speed']) {
		?>
		<input type="hidden" name="sockem_speed" value="<?php echo esc_attr(sockem_make_speed_hash()); ?>" />
		<?php
	}

	// Form expiration.
	if ($sockem_options['test_expiration']) {
		?>
		<input type="hidden" name="sockem_expiration" value="<?php echo esc_attr(sockem_make_speed_hash()); ?>" />
		<?php
	}
}
add_action('comment_form', 'sockem_comment_form');

/**
 * Cookie Support
 *
 * This has to be done before headers are sent.
 *
 * @return void Nothing.
 */
function sockem_test_cookie() {
	// Only applies if the test is enabled.
	if (!sockem_get_option('test_cookie')) {
		return;
	}

	// Save it if we need to.
	$v = sockem_make_cookie_hash();
	if (
		!isset($_COOKIE['sockem_cookie']) ||
		($_COOKIE['sockem_cookie'] !== $v)
	) {
		setcookie(
			'sockem_cookie',
			$v,
			0,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}
}
add_action('send_headers', 'sockem_test_cookie');

/**
 * Validate Comment Form Submissions
 *
 * @param array $data Comment data.
 * @return mixed Comment data.
 */
function sockem_comment_form_validation($data) {
	// Don't do this for admin areas.
	if (is_admin()) {
		return $data;
	}

	$sockem_options = sockem_get_option();

	// Statuses for debugging.
	$debug = array();
	// Errors, if any.
	$errors = array();

	$fail = '[' . __('FAIL', 'sockem-spambots') . '] ';
	$pass = '[' . __('PASS', 'sockem-spambots') . '] ';
	$na = '[' . __('N/A', 'sockem-spambots') . '] ';

	// Regular comments.
	if (empty($data['comment_type']) || 'comment' === $data['comment_type']) {
		// Can we trust the user?
		if ($sockem_options['exempt_users'] && is_user_logged_in()) {
			// Log if applicable.
			if ($sockem_options['debug']) {
				sockem_debug_log(
					$data,
					array($pass . __('Logged in users are exempt from tests.', 'sockem-spambots'))
				);
			}

			return $data;
		}

		// Javascript.
		if ($sockem_options['test_js']) {
			if (
				!isset($_POST['sockem_js']) ||
				(sockem_make_js_hash($data['comment_post_ID']) !== $_POST['sockem_js'])
			) {
				$str = __('Javascript support must be enabled to leave comments.', 'sockem-spambots');
				$debug[] = $fail . $str;
				$errors[] = $str;
			}
			else {
				$debug[] = $pass . __('Javascript is enabled.', 'sockem-spambots');
			}
		}
		else {
			$debug[] = $na . __('Javascript is not required.', 'sockem-spambots');
		}

		// Cookies.
		if ($sockem_options['test_cookie']) {
			if (
				!isset($_COOKIE['sockem_cookie']) ||
				(sockem_make_cookie_hash() !== $_COOKIE['sockem_cookie'])
			) {
				$str = __('Cookie support must be enabled to leave comments.', 'sockem-spambots');
				$debug[] = $fail . $str;
				$errors[] = $str;
			}
			else {
				$debug[] = $pass . __('Cookies are enabled.', 'sockem-spambots');
			}
		}
		else {
			$debug[] = $na . __('Cookies are not required.', 'sockem-spambots');
		}

		// Honeypot.
		if ($sockem_options['test_filler']) {
			if (!isset($_POST['sockem_filler']) || $_POST['sockem_filler']) {
				$str = __('Invisible text fields should be left blank.', 'sockem-spambots');
				$debug[] = $fail . $str;
				$errors[] = $str;
			}
			else {
				$debug[] = $pass . __('Invisible text field is empty.', 'sockem-spambots');
			}
		}
		else {
			$debug[] = $na . __('Honeypot is not enabled.', 'sockem-spambots');
		}

		// Speed test.
		if ($sockem_options['test_speed']) {
			if (
				!isset($_POST['sockem_speed']) ||
				!sockem_validate_speed_hash($_POST['sockem_speed'])
			) {
				$str = __('Comment submitted too quickly.', 'sockem-spambots');
				$debug[] = $fail . $str;
				$errors[] = $str;
			}
			else {
				$debug[] = $pass . __('Comment was not hastily submitted.', 'sockem-spambots');
			}
		}
		else {
			$debug[] = $na . __('Comment speed is not tested.', 'sockem-spambots');
		}

		// Form expiration.
		if ($sockem_options['test_expiration']) {
			if (
				!isset($_POST['sockem_expiration']) ||
				!sockem_validate_speed_hash($_POST['sockem_expiration'], 'expiration')
			) {
				$str = __('The form had expired.', 'sockem-spambots');
				$debug[] = $fail . $str;
				$errors[] = $str . ' ' . __('Please reload the page and try again.', 'sockem-spambots');
			}
			else {
				$debug[] = $pass . __('Comment form has not expired.', 'sockem-spambots');
			}
		}
		else {
			$debug[] = $na . __('Comment expiration is not tested.', 'sockem-spambots');
		}

		// Links.
		if ($sockem_options['test_links']) {
			$link_count = sockem_count_links($data['comment_content']);
			if ($link_count > $sockem_options['test_links_max']) {
				$str = sprintf(
					__('Comment contains too many links (%d).', 'sockem-spambots'),
					$link_count
				);
				$debug[] = $fail . $str;
				$errors[] = sprintf(
					__('Comments may only contain up to %d links.', 'sockem-spambots'),
					$sockem_options['test_links_max']
				);
			}
			else {
				$debug[] = $pass . sprintf(
					__('Commend did not contain excessive links (%d).', 'sockem-spambots'),
					$link_count
				);
			}
		}
		else {
			$debug[] = $na . __('Comment link count is not tested.', 'sockem-spambots');
		}

		// Length?
		if ($sockem_options['test_length']) {
			$comment_length = (int) strlen($data['comment_content']);
			if ($comment_length > $sockem_options['test_length_max']) {
				$debug[] = $fail . sprintf(
					__('Comment is too long (%d).', 'sockem-spambots'),
					$comment_length
				);
				$errors[] = sprintf(
					__('Comments cannot exceed %d characters in length.', 'sockem-spambots'),
					$sockem_options['test_length_max']
				);
			}
			else {
				$debug[] = $pass . sprintf(
					__('Comment was not too long (%d).', 'sockem-spambots'),
					$comment_length
				);
			}
		}
		else {
			$debug[] = $na . __('Comment length is not tested.', 'sockem-spambots');
		}

	}// End regular comments.
	// Trackbacks.
	elseif (
		('trackback' === $data['comment_type']) &&
		$sockem_options['disable_trackbacks']
	) {
		$str = __('Trackbacks have been disabled.', 'sockem-spambots');
		$debug[] = $fail . $str;
		$errors[] = $str;
	}
	// Pingbacks.
	elseif (
		('pingback' === $data['comment_type']) &&
		$sockem_options['disable_pingbacks']
	) {
		$str = __('Pingbacks have been disabled.', 'sockem-spambots');
		$debug[] = $fail . $str;
		$errors[] = $str;
	}

	// One last status if we're good.
	if (!count($errors)) {
		$debug[] = $pass . __("Sock'EM SPAMbots has taken no action.", 'sockem-spambots');
	}

	// Submit to debug log if applicable.
	if ($sockem_options['debug']) {
		sockem_debug_log($data, $debug);
	}

	// If there are errors, we're done.
	if (count($errors)) {
		$str = '<strong>' . __('Error', 'sockem-spambots') . ':</strong> ';
		wp_die("<p>$str" . implode("<br>$str", $errors)) . '</p>';
	}

	return $data;
}
add_filter('preprocess_comment', 'sockem_comment_form_validation', 1);

/**
 * Count Links
 *
 * @param string $text Content.
 * @return int Count.
 */
function sockem_count_links($text) {
	$count = 0;

	// Use wordpress' function to make things clickable.
	$text = make_clickable($text);

	// Now count actual anchors.
	$count += (int) preg_match_all('/\<a\s[^>]+>/ui', $text, $tmp);

	// Also look for fake [url] tags.
	$count += (int) preg_match_all('/\[url[^a-z0-9_-]/ui', $text, $tmp);

	return $count;
}

/**
 * Disable Comment Author Links
 *
 * @param string $link Link.
 * @return string Link.
 */
function sockem_disable_comment_author_link($link) {
	if (sockem_get_option('disable_comment_author_links')) {
		$link = strip_tags($link);
	}

	return $link;
}
add_filter('get_comment_author_link', 'sockem_disable_comment_author_link');

// --------------------------------------------------------------------- end comments



// ---------------------------------------------------------------------
// Debugging
// ---------------------------------------------------------------------

/**
 * Log Comments
 *
 * @param array $data Comment data.
 * @param mixed $status Status.
 * @return void Nothing.
 */
function sockem_debug_log(&$data, $status) {
	$sockem_options = sockem_get_option();

	// If debugging is disabled, do nothing.
	if (!$sockem_options['debug']) {
		return;
	}

	$out = array(
		'date'=>current_time('c'),
		'ip'=>isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
		'ua'=>isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
		'referrer'=>isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
		'comment'=>array(),
		'post'=>array(),
		'cookie'=>array(),
		'status'=>array(),
	);

	// The comment.
	if (is_array($data)) {
		foreach ($data as $k=>$v) {
			if (!is_array($v) && !is_object($v)) {
				$out['comment'][$k] = sockem_format_debug_value($v);
			}
		}
	}

	// Let's save $_POST too!
	if (is_array($_POST)) {
		foreach ($_POST as $k=>$v) {
			if (!is_array($v) && !is_object($v)) {
				$out['post'][$k] = sockem_format_debug_value($v);
			}
		}
	}

	// And cookies.
	if (is_array($_COOKIE)) {
		$redacted = __('REDACTED', 'sockem-spambots');
		foreach ($_COOKIE as $k=>$v) {
			if (!is_array($v) && !is_object($v)) {
				if (preg_match('/^(wp|wordpress)/', $k)) {
					$out['cookie'][$k] = "[$redacted]";
				}
				else {
					$out['cookie'][$k] = sockem_format_debug_value($v);
				}
			}
		}
	}

	// The status.
	$status = (array) $status;
	foreach ($status as $v) {
		$out['status'][] = $v;
	}

	$json = null;
	if (is_file(SOCKEM_DEBUG_PATH)) {
		$json = trim(file_get_contents(SOCKEM_DEBUG_PATH));
		$json = json_decode($json, true);
	}
	if (!is_array($json)) {
		$json = array();
	}

	// Add it to the top.
	array_unshift($json, $out);

	// Keep the list trim.
	if (count($json) > 50) {
		array_splice($json, 50);
	}

	// Save it.
	file_put_contents(
		SOCKEM_DEBUG_PATH,
		json_encode($json, JSON_PRETTY_PRINT),
		LOCK_EX
	);
}

/**
 * Format Debug Values
 *
 * @param mixed $value Value.
 * @return mixed Value.
 */
function sockem_format_debug_value($value='') {
	if (is_numeric($value)) {
		return $value;
	}

	if (is_bool($value)) {
		return $value ? __('TRUE', 'sockem-spambots') : __('FALSE', 'sockem-spambots');
	}

	// Strip slashes.
	$value = (string) $value;
	$value = stripslashes($value);
	$value = wp_check_invalid_utf8($value, true);

	// Sanitize spacing.
	$value = trim(preg_replace('/\s+/u', ' ', $value));

	// Trim long values.
	if (strlen($value) > 200) {
		$value = trim(substr($value, 0, 199)) . '…';
	}

	return $value;
}

// ---------------------------------------------------------------------  end debugging
