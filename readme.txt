=== Comment Hash ===
Tags: anti spam, comment, captcha
Contributors: sphinxid
Stable tag: 1.0.2
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 7.4
License: GPLv2 or later

Protect your WordPress comments from spam by requiring proof-of-work. This innovative solution makes commenters' devices solve a small computational puzzle before posting, effectively preventing automated spam while maintaining a smooth user experience. No captchas, no annoying verifications - just intelligent spam prevention.

== Description ==

Protect your WordPress comments from spam by requiring proof-of-work. This innovative solution makes commenters' devices solve a small computational puzzle before posting, effectively preventing automated spam while maintaining a smooth user experience. No captchas, no annoying verifications - just intelligent spam prevention.

This completes the WordPress plugin implementation. The plugin provides:

- A proof-of-work system for comment submission using SHA-256
- A clean, modern loading animation during verification
- Configurable settings for secret key and time window
- Responsive design that works on all devices
- Detailed error handling and user feedback
- Security features like timestamp verification and HMAC validation

To install:

- Create a directory named comment-hash in your WordPress plugins directory
- Place all the files in their respective directories as shown in the structure above
- Activate the plugin through WordPress admin
- Configure the settings in Settings -> Comment Hash

The plugin will automatically:

- Generate a secure random 64-character secret key on installation
- Set default time window to 180 seconds
- Add necessary JavaScript and CSS to comment forms
- Protect all comment submissions with proof-of-work verification in order to decrease spams
