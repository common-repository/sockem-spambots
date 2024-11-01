<?php
/**
 * Sock'Em SPAMbots - Settings
 *
 * @package sockem-spambots
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

// Let's make sure this page is being accessed through WP.
if (!function_exists('current_user_can')) {
	die('Sorry');
}
// And let's make sure the current user has sufficient permissions.
elseif (!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.'));
}



// Basic variables.
$sockem_options = sockem_get_option();
$statuses = array('open', 'closed');



// Update values!
if (getenv('REQUEST_METHOD') === 'POST') {
	// Get rid of magic quotes.
	$_POST = stripslashes_deep($_POST);

	// Don't save if the nonce is bad.
	if (!wp_verify_nonce($_POST['_wpnonce'], 'sockem-settings')) {
		?>
		<div class="error fade">
			<p><?php
				echo __('The form had expired.', 'sockem-spambots');
				echo ' ';
				echo __('Please reload the page and try again.', 'sockem-spambots');
			?></p>
		</div>
		<?php
	}
	else {
		// Do something with the debug log?
		if (file_exists(SOCKEM_DEBUG_PATH)) {
			// Delete if disabled.
			if (
				!isset($_POST['sockem_debug']) ||
				!intval($_POST['sockem_debug'])
			) {
				if (!@unlink(SOCKEM_DEBUG_PATH)) {
					?>
					<div class="error fade">
						<p><?php
							echo sprintf(
								__("The Sock'Em SPAMbots debug log could not be deleted.  Please manually remove %s.", 'sockem-spambots'),
								'<code>' . esc_html(SOCKEM_DEBUG_PATH) . '</code>'
							);
						?></p>
					</div>
					<?php
				}
				else {
					?>
					<div class="updated fade">
						<p><?php
							echo __("The Sock'Em SPAMbots debug log has been removed.", 'sockem-spambots');
						?></p>
					</div>
					<?php
				}
			}
			// Empty the file.
			elseif (
				isset($_POST['sockem_empty_debug_log']) &&
				intval($_POST['sockem_empty_debug_log'])
			) {
				if (!@file_put_contents(SOCKEM_DEBUG_PATH, '[]')) {
					?>
					<div class="error fade">
						<p><?php
							echo __("The Sock'Em SPAMbots debug log could not be emptied.  Please make sure the server is allowed to write changes to it.", 'sockem-spambots');
						?></p>
					</div>
					<?php
				}
				else {
					?>
					<div class="updated fade">
						<p><?php
							echo __("The Sock'Em SPAMbots debug log has been emptied.", 'sockem-spambots');
						?></p>
					</div>
					<?php
				}
			}
		}
		// Make sure debug file is writeable.
		elseif (
			isset($_POST['sockem_debug']) &&
			intval($_POST['sockem_debug']) &&
			!@file_put_contents(SOCKEM_DEBUG_PATH, '[]')
		) {
			?>
			<div class="error fade">
				<p><?php
					echo sprintf(
						__("The Sock'Em SPAMbots log could not be created.  Please make sure the server is allowed to write changes to %s.", 'sockem-spambots'),
						'<code>' . esc_html(SOCKEM_DEBUG_PATH) . '</code>'
					);
				?></p>
			</div>
			<?php
		}

		// Updating comment/ping statuses.
		$tmp = array();
		if (
			isset($_POST['sockem_comment_status']) &&
			in_array($_POST['sockem_comment_status'], $statuses, true)
		) {
			// Possible values are only "open" and "closed"; no need to
			// escape.
			$tmp['comment_status'] = "`comment_status`='{$_POST['sockem_comment_status']}'";
		}

		if (
			isset($_POST['sockem_ping_status']) &&
			in_array($_POST['sockem_ping_status'], $statuses, true)
		) {
			// Possible values are only "open" and "closed"; no need to
			// escape.
			$tmp['ping_status'] = "`ping_status`='{$_POST['sockem_ping_status']}'";
		}

		// Update.
		if (count($tmp)) {
			global $wpdb;
			$wpdb->query("
				UPDATE `{$wpdb->posts}`
				SET " . implode(', ', $tmp)
			);

			// Generate success messages.
			$translated = array(
				'closed'=>__('closed', 'sockem-spambots'),
				'comment_status'=>__('comment statuses', 'sockem-spambots'),
				'open'=>__('open', 'sockem-spambots'),
				'ping_status'=>__('ping statuses', 'sockem-spambots'),
			);
			foreach ($tmp as $k=>$v) {
				?>
				<div class="updated fade">
					<p><?php
						echo sprintf(
							__('The %s of your existing posts have been set to %s.', 'sockem-spambots'),
							$translated[$k],
							$translated[$_POST["sockem_$k"]]
						);
					?></p>
				</div>
				<?php
			}
		}

		// Fix up our bools.
		$fields = array(
			'debug',
			'disable_comment_author_links',
			'disable_pingbacks',
			'disable_trackbacks',
			'exempt_users',
			'test_cookie',
			'test_expiration',
			'test_filler',
			'test_js',
			'test_length',
			'test_links',
			'test_speed',
		);
		foreach ($fields as $key) {
			$sockem_options[$key] = (
				isset($_POST["sockem_$key"]) &&
				intval($_POST["sockem_$key"])
			);
		}

		// Save for later.
		update_option('sockem_options', $sockem_options);

		?>
		<div class="updated fade">
			<p><?php
				echo __("Sock'Em SPAMbots' settings have been successfully saved.", 'sockem-spambots');
			?></p>
		</div>
		<?php
	}
}



// Warn about plugin incompatibilities.
if (is_plugin_active('bbpress/bbpress.php')) {
	?>
	<div class="notice notice-warning">
		<p><?php
			echo '<strong>' . __('Warning', 'sockem-spambots') . ':</strong> ';
			echo __("bbPress forum posts are outside the purview of this plugin; Sock'Em SPAMbots only helps protect against SPAM in regular comments.", 'sockem-spambots');
		?></p>
	</div>
	<?php
}

if (is_plugin_active('buddypress/bp-loader.php')) {
	?>
	<div class="notice notice-warning">
		<p><?php
			echo '<strong>' . __('Warning', 'sockem-spambots') . ':</strong> ';
			echo __("BuddyPress has its own commenting system outside the purview of this plugin; Sock'Em SPAMbots only helps protect against SPAM in regular comments.", 'sockem-spambots');
		?></p>
	</div>
	<?php
}

// Also warn about debug mode, which is good for testing, bad for life.
if ($sockem_options['debug']) {
	?>
	<div class="notice notice-warning">
		<p><?php
			echo '<strong>' . __('Warning', 'sockem-spambots') . ':</strong> ';
			echo sprintf(
				__('Debug mode is currently enabled. This feature is %s intended for testing and should %s be left enabled on a live site.', 'sockem-spambots'),
				'<strong><em>' . __('only', 'sockem-spambots') . '</em></strong>',
				'<strong><em>' . __('not', 'sockem-spambots') . '</em></strong>'
			);
		?></p>
	</div>
	<?php
}



// Lastly, set up some strings for display.
$tests = array(
	'exempt_users'=>array(
		'label'=>__('Trust Authenticated Users', 'sockem-spambots'),
		'text'=>__('Unless you have a SPAM registration problem (which is a whole other pickle), you can probably assumem that any user who has successfully logged into WordPress is human and exempt them from the other tests.', 'sockem-spambots'),
	),
	'test_js'=>array(
		'label'=>__('Require Javascript', 'sockem-spambots'),
		'text'=>__('To test for Javascript support, a small script will be inserted into comment forms which will then insert a hidden field to the form.', 'sockem-spambots'),
	),
	'test_cookie'=>array(
		'label'=>__('Require Cookies', 'sockem-spambots'),
		'text'=>__('To test for cookie support, a small cookie will be set when the form is loaded, and verified upon submission.', 'sockem-spambots'),
	),
	'test_filler'=>array(
		'label'=>__('Honeypot Field', 'sockem-spambots'),
		'text'=>__('An invisible text field is added to comment forms. Many generic formbots will try to fill it out anyway, which will help us reject their submission.', 'sockem-spambots'),
	),
	'test_speed'=>array(
		'label'=>__('Haste Makes SPAM', 'sockem-spambots'),
		'text'=>__('Robots are much faster than humans. This test will reject comments submitted in fewer than 5 seconds from the time the page was first loaded.', 'sockem-spambots'),
	),
	'test_expiration'=>array(
		'label'=>__('Form Expiration', 'sockem-spambots'),
		'text'=>__('This test adds an expiration to comment forms so the values cannot be indefinitely resubmitted.', 'sockem-spambots'),
	),
	'test_links'=>array(
		'label'=>__('Excessive Links', 'sockem-spambots'),
		'text'=>__('SPAM comments are usually trying to sell something and contain many, many links. This test will reject comments containing more than 5.', 'sockem-spambots'),
	),
	'test_length'=>array(
		'label'=>__('Short and Sweet', 'sockem-spambots'),
		'text'=>__('Comments should not be novels. This test rejects comments longer than 1500 characters.', 'sockem-spambots'),
	),
);

// Same thing for pingback/trackback.
$other = array(
	'disable_trackbacks'=>array(
		'label'=>'Disable Trackbacks',
		'text'=>sprintf(
			__('%s are intended to provide a means for authors to keep track of who links to their posts, but more often than not this system is abused to send SPAM.', 'sockem-spambots'),
			'<a href="https://en.wikipedia.org/wiki/Trackbacks" target="_blank" rel="noopener">Trackbacks</a>'
		),
	),
	'disable_pingbacks'=>array(
		'label'=>'Disable Pingbacks',
		'text'=>sprintf(
			__('%s are similar to Trackbacks in that their purpose is to notify authors of links to their posts, however there is at least some degree of authentication, so they are not quite as bad.', 'sockem-spambots'),
			'<a href="https://en.wikipedia.org/wiki/Pingback" target="_blank" rel="noopener">Pingbacks</a>'
		),
	),
);
?>
<style type="text/css">
	.form-table {
		clear: left!important;
	}
	.logo {
		width: 100%;
		height: auto;
	}
</style>


<div class="wrap">
	<h1>
		<?php echo __("Sock'Em SPAMbots", 'sockem-spambots');?>:
		<?php echo __('Settings', 'sockem-spambots'); ?>
	</h1>

	<div class="metabox-holder has-right-sidebar">
		<form id="form-sockem-settings" method="post" action="<?php echo esc_url(admin_url('options-general.php?page=sockem-settings')); ?>">

			<!-- Nonce. -->
			<?php wp_nonce_field('sockem-settings'); ?>

			<!-- Sidebar. -->
			<div class="inner-sidebar">
				<!-- Debug -->
				<div class="postbox">
					<h3 class="hndle"><?php
						echo __('Debugging', 'sockem-spambots');
					?></h3>
					<div class="inside">
						<p>
							<label for="sockem_debug">
								<input type="checkbox" id="sockem_debug" name="sockem_debug" value="1" <?php echo $sockem_options['debug'] ? 'checked=checked' : ''; ?> />

								<?php echo __('Enable debugging', 'sockem-spambots'); ?>
							</label>
						</p>
						<p class="description"><?php
							echo sprintf(
								__("Comment details and Sock'Em test results will be logged to %s. This can be useful if you want to make sure the plugin is working correctly, but you should not leave this enabled as it could expose comment information to outside parties.", 'sockem-spambots'),
								'<code>' . esc_html(SOCKEM_DEBUG_PATH) . '</code>'
							);
						?></p>

						<div class="sockem-debug-options" style="display: <?php echo $sockem_options['debug'] ? 'block' : 'none'; ?>">
							<?php
							// If the log exists, let's present some
							// options.
							if (file_exists(SOCKEM_DEBUG_PATH)) {
								?>
								<p>
									<label for="sockem_empty_debug_log">
										<input type="checkbox" id="sockem_empty_debug_log" name="sockem_empty_debug_log" value="1" />

										<?php echo __('Empty log', 'sockem-spambots'); ?>
									</label>
								</p>

								<p class="description"><?php
									echo sprintf(
										__('Click %s to view the log.', 'sockem-spambots'),
										'<a href="' . esc_url(SOCKEM_DEBUG_URL) . '" target="_blank">' . __('here', 'sockem-spambots') . '</a>'
									);
								?></p>
								<?php
							}
							?>
						</div>
					</div>
				</div><!--.postbox-->

				<!-- Mass update. -->
				<div class="postbox">
					<h3 class="hndle"><?php
						echo __('Mass Update', 'sockem-spambots');
					?></h3>
					<div class="inside">
						<?php
							// The options are the same for both of these.
							$options = '<option value="">---</option>';
							foreach ($statuses as $v) {
								$options .= '<option value="' . $v . '">' . __($v, 'sockem-spambots') . '</option>';
							}
						?>
						<p>
							<select name="sockem_comment_status" id="sockem_comment_status">
								<?php echo $options; ?>
							</select>

							<label for="sockem_comment_status"><?php
								echo __('Comment Status', 'sockem-spambots');
							?></label>
						</p>

						<p>
							<select name="sockem_ping_status" id="sockem_ping_status">
								<?php echo $options; ?>
							</select>

							<label for="sockem_ping_status"><?php
								echo __('Ping Status', 'sockem-spambots');
							?></label>
						</p>

						<p class="description"><?php
							echo __('WordPress does not retroactively apply changes to comment settings. Use the above forms to set the comment and/or pingback status for *ALL* posts.', 'sockem-spambots');
						?></p>
					</div>
				</div><!--.postbox-->
			</div><!--.inner-sidebar-->

			<div id="post-body-content" class="has-sidebar">
				<div class="has-sidebar-content">

					<!-- comment validation methods -->
					<div class="postbox">
						<h3 class="hndle"><?php
							echo __('Comment Validation Methods', 'sockem-spambots');
						?></h3>
						<div class="inside">
							<p><?php
								echo sprintf(
									__('SPAMbots are usually simple, lightweight, automated scripts, lacking robust features (and common decency).  The following modifications to the comment process can help trip them up %s interfering with your human visitors at all.', 'sockem-spambots'),
									'<strong><em>' . __('without', 'sockem-spambots') . '</em></strong>'
								);
							?></p>

							<blockquote>
								<?php
								foreach ($tests as $k=>$v) {
									?>
									<fieldset class="sockem-fieldset">
										<p>
											<label for="sockem_<?php echo $k; ?>">
												<input type="checkbox" name="sockem_<?php echo $k; ?>" id="sockem_<?php echo $k; ?>" value="1" <?php echo $sockem_options[$k] ? 'checked' : ''; ?> />
												<?php echo $v['label']; ?>
											</label>
										</p>

										<p class="description"><?php
											echo $v['text'];
										?></p>
									</fieldset>
									<?php
								}
								?>
							</blockquote>
						</div>
					</div><!--.postbox-->

					<!-- comment validation methods -->
					<div class="postbox">
						<h3 class="hndle"><?php
							echo __('Other Comment Features', 'sockem-spambots');
						?></h3>
						<div class="inside">
							<p>
								<label for="sockem_disable_comment_author_links">
									<input type="checkbox" name="sockem_disable_comment_author_links" id="sockem_disable_comment_author_links" value="1" <?php echo $sockem_options['disable_comment_author_links'] ? 'checked=checked' : ''; ?> />

									<?php echo __('Hide Comment Author Link', 'sockem-spambots'); ?>
								</label>
							</p>

							<p class="description"><?php
								// phpcs:disable
								echo __("The point of SPAM comments is largely to trick human beings into clicking their links.  SPAM links are at best annoying, and at worst dangerous, so unless your visitors expect cross-promotion when they post comments, you shouldn't display them. This option only disables the *display* of commenter URLs on the frontend; the data is still collected and retained in case you change your mind.", 'sockem-spambots');
								// phpcs:enable
							?></p>

							<p><?php
								echo __('Some kinds of comments are actually supposed to come from robots, though their usefulness is questionable:', 'sockem-spambots');
							?></p>

							<blockquote>
								<?php
								foreach ($other as $k=>$v) {
									?>
									<fieldset class="sockem-fieldset">
										<p>
											<label for="sockem_<?php echo $k; ?>">
												<input type="checkbox" name="sockem_<?php echo $k; ?>" id="sockem_<?php echo $k; ?>" value="1" <?php echo $sockem_options[$k] ? 'checked' : ''; ?> />
												<?php echo $v['label']; ?>
											</label>
										</p>

										<p class="description"><?php
											echo $v['text'];
										?></p>
									</fieldset>
									<?php
								}
								?>
							</blockquote>
						</div>
					</div><!--.postbox-->

				</div><!-- .has-sidebar-content -->
			</div><!-- .has-sidebar -->


			<p class="submit">
				<button type="submit" class="button button-primary" name="submit"><?php
					echo __('Save', 'sockem-spambots');
				?></button>
			</p>
		</form>

	</div><!-- /metabox-holder has-right-sidebar -->
</div><!-- /wrap -->

<script>
	// Toggle visibility.
	jQuery('#sockem_debug').click(function(){

		if(jQuery(this).prop('checked'))
			jQuery('.sockem-debug-options').css({display:'block'});
		else
			jQuery('.sockem-debug-options').css({display:'none'});

	});
</script>
