=== Sock'Em SPAMbots ===
Contributors: blobfolio
Donate link: http://www.blobfolio.com/donate.html
Tags: comment, comment, spam, captcha, junk, trackback, pingback
Requires at least: 3.6
Tested up to: 6.0
Requires PHP: 7.3
Stable tag: trunk
License: WTFPL
License URI: http://www.wtfpl.net/

A more seamless approach to deflecting the vast majority of SPAM comments.

== Description ==

CAPTCHA fields inhibit both human and robot participation in important kitty-related discussions.  Sock'Em SPAMbots exists to take a more seamless approach to SPAM blocking, placing the burden on the robots, not the humans.  Any combination of the following can be enabled:

  * Javascript: require basic Javascript support, and in the process prove the user visited the actual comment form (instead of just submitting straight to WP).
  * Cookies: require basic cookie support, and again, prove the user visited the site before submitting a comment.
  * Honeypot: generic formbots will often populate all form fields with gibberish, so we can assume that if text is added to an invisible field, something robotic is happening!
  * Speed: automated scripts complete comment forms with inhuman speed, thus if submissions happen really quickly, we can assume it is a robot doing the submitting!
  * Links: reject comments with excessive number of links.
  * Disable trackbacks or pingbacks independently of one another.

== Requirements ==

 * WordPress 3.6 or later.
 * PHP 7.3 or later.

Please note: it is **not safe** to run WordPress atop a version of PHP that has reached its [End of Life](http://php.net/supported-versions.php). Future releases of this plugin might, out of necessity, drop support for old, unmaintained versions of PHP. To ensure you continue to receive plugin updates, bug fixes, and new features, just make sure PHP is kept up-to-date. :)

== Installation ==

1. Unzip the archive and upload the entire `sockem-spambots` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Review and change the settings by selecting 'SockEm SPAMbots' in the 'Settings' menu in WordPress.

== Frequently Asked Questions ==

= Is this plugin compatible with WPMU? =

The plugin is only meant to be used with single-site WordPress installations.  Some features may still work under multi-site environments, however it would be safer to use some other plugin that is specifically marked WPMU-compatible instead.

= Does the Javascript test have any additional dependencies, like jQuery? =

Nope.  We like things as lightweight as possible, so the Javascript is naked as the day it was born.

= What happens to comments that failed to pass the enabled Sock'Em SPAMbots test(s) =

If a comment fails to pass one or more of the Sock'Em SPAMbots tests, the comment is rejected outright and an error is returned to the (human or robot) user explaining what went wrong.  Humans can take the appropriate action and resubmit the comment if they so desire, while robots will likely just go bother someone else.

= Why are there settings to disable trackbacks and pingbacks?  Doesn't WP offer this itself? =

WordPress lumps the two together.  We've separated them so you can be more selective.  It is worth pointing out that Sock'Em only affects comments that would otherwise be allowed, so if you have disabled both via the WP discussion settings, then the Sock'Em options have no effect.

= What happens after Sock'Em SPAMbots test(s) are passed? =

WordPress continues doing whatever it would normally do with the comment based on your settings and any other relevant plugins you have installed (e.g. Akismet).

= Does Sock'Em SPAMbots protect against SPAM registrations? =

No. This plugin only concerns itself with comments. For similar protections covering the registration process, see [Apocalypse Meow](https://wordpress.org/plugins/apocalypse-meow/).

= Does Sock'Em SPAMbots protect against SPAM bbPress or BuddyPress posts and comments? =

No. This plugin only concerns itself with regular WordPress comments.

== Screenshots ==

1. All options are easily configurable via a settings page.
2. Debug log shows comment data, $_POST data, $_COOKIE data, and the results of each enabled Sock'Em SPAMbots test.

== Privacy Policy ==

This plugin makes use of the same "Personal Data" WordPress does when parsing comment form submissions.

When the optional debugging mode is enabled — which should *never* be the case on a public-facing site! — all comment form submissions are logged to a file on the local server.

When the optional cookie requirement test is enabled, a small cookie will be placed on each visitor's machine. These cookies are not personally identifiable or used for any kind of tracking purposes; they merely enable the plugin to answer the question, "Do you support cookies?"

== Changelog ==

= 1.0.1 =
* [Fix] Extra checks not being run due to [#51082](https://core.trac.wordpress.org/ticket/51082).

= 1.0.0 =
* [New] Add privacy policy hook for GDPR compliance.
* [New] Added translation support.
* [New] Debug log is now in JSON format.
* [New] A warning is now displayed on the Sock'Em SPAMbots page when debug mode is enabled.
* [Misc] Code cleanup.
* [Misc] The plugin has been relicensed under [WTFPL](http://www.wtfpl.net/); go wild!

= 0.9.0 =
* [New] Form expiration test.
* [New] max comment length.
* [New] Ability to disable comment author links.

= 0.8.1 =
* [Fix] Enahnced link counting to include [url] tags and plaintext URLs.

= 0.8.0 =
* [New] Ability to reject comments with excessive number of links.

== Upgrade Notice ==

= 1.0.1 =
This release fixes compatibility issues resulting from [#51082](https://core.trac.wordpress.org/ticket/51082).

= 1.0.0 =
This release introduces a privacy policy hook for GDPR compliance, translation support, and improvements to the debug mode.

= 0.9.0 =
Added form expiration test, max length restriction, and ability to disable comment author links.

= 0.8.1 =
Link counts now include [url] tags and plaintext URLs.

= 0.8.0 =
New test (excessive links).
