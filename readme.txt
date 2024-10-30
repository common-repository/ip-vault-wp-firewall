=== Two-factor authentication (formerly IP Vault) ===
Contributors: youtag
Donate link: https://www.paypal.com/donate/?hosted_button_id=Y7VNAG4WC8YMC
Tags: protection, security, lock, IP, brute force
Requires at least: 4.0
Tested up to: 6.2.2
Stable tag: 0.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect your website against Brute Force Attacks and other malicious requests that have potential to jeopardise the website’s safety or hijacking your site's server to use as phishing platform, run malware, mine cryptocurrency etc. Save your server’s bandwidth for the actual site visitors. No captcha, no nagging. No privacy concerns : all the logging is done locally. Simple and efficient.


== Description ==

IP Vault lets you protect your WordPress backend – and any other part of your website – from non verified users.

IP Vault Firewall also preserves your server ressources and bandwidth by blocking hacking attempts before they reach your site.


== How does it work ? ==

Requests to protected files and folders are redirected to the *Authentication Page*. IP Vault unlocks user's IP addresses using a key
that is emailed for authentication. Once users verify their account, they can access all restricted areas. Users are automatically verified on registration.


== What is protected ? ==

Out-of-the box, IP Vault restricts access to `.php` and `.phtml` files, as well as `wp-admin` folder, which are frequently exploited by bad bots and hackers.
You can choose which part of your site to protect. Need to make the whole website private ? No problem, just restrict access to `/`.


== The story behind this plugin ==

In the past 20 years, I have been monitoring a few dozen client sites to prevent malicious access. I have also helped a great number of people to clean their website from malware.
I noticed that even marginal WordPress sites or non-wordpress PHP based sites are constantly exposed to hacking attempts.

Almost all exploits I have seen work by either calling a vulnerable PHP script already on the server, by adding such a script or by injecting their own code into an existing script.

I have tried and tested quite a few security plugins. They can be quite complex to set up and to maintain. Some security plugins try to block access to vulnerable files by comparing requests to a blacklist.
These tend to become quite large and need frequent updates to be efficient. Others use geo-blocking services to block requests from certain countries. However in my experience, hacking attempts can come from just about any location.

I thought there must be a better way using whitelists for verified users instead. And that's how the idea for IP Vault was born.


== To Dos ==

- add option to get auth code by SMS (requires users to register phone number)


== I love this plugin. How can I contribute ? ==

* [Rate plugin](https://wordpress.org/support/plugin/ip-vault-wp-firewall/reviews/#new-post) and leave feedback on WordPress.org
* Help resolve questions in support forums
* Help with translations
* [Donate](https://www.paypal.com/donate/?hosted_button_id=Y7VNAG4WC8YMC)


== Screenshots ==

1. Authentication Page
2. Dashboard Widget
3. Which files and folders should be protected ?
4. IP Address Whitelist
5. Blocked connection logs & stats


== Changelog ==

= 2.1 =
- optimization : added a 404 header to disallowed requests, in order to discourage bots from returning
- optimization : mapping (frequently changing) IPv6 addresses to IPv4 using third party service _ipify_
- fixed potential XSS vulnerabilities

= 2.0 =
- optimization : complete rewrite of authentication method : replaced secret URL by a 4-digit pin code
- various small fixes

= 1.1 =
- optimization : set transient for api calls (cache results for 1 week)
- experimental feature : use ASN for authentication (useful if your public IP changes often)

= 1.0.2.1 =
- optimisation : limit requests to ip-api to unknown IP addresses (IPs not yet logged)
- optimisation : settings link added to plugin screen
- optimisation : allow custom comments for whitelisted IPs
- fixed minor bug : title on stats screen displays correct date
- fixed minor bug : removing IP addresses with backslashes from whitelist

= 1.0.1 =
- fixed minor bug : missing envelope.svg
- tested up to WP version 5.7.2

= 1.0 =
- redesigned bar chart and added daily tables in statistics
- authentication mail back to plain text to optimise deliverability
- various small fixes

= 0.7 =
- added a <code>soft rewrite</code> mode, as <code>.htaccess</code> mode can be tricky on some installs
- cosmetic changes to authentication mails, now using html
- improved logging and statistics, database cleaned through daily cron job

= 0.5 =
- Reengineered auth page (no longer depending on frontend page)
- New logo and redesigned auth page
- Improved style and optimised ressource usage
- *a lot* of small changes

= 0.4.1 =
Fixed issue where settings were not properly removed on uninstall

= 0.4 =
First release.


== Upgrade Notice ==

Update normally via the plugins dashboard. Logs and Settings are preserved on deactivation. All settings and logs are removed on uninstall. Changes to `.htaccess` file are restored on deactivation and on uninstall.

== Disclaimer ==

This plugin uses the following **3rd Party services** :

* [ip-api.com](https://ip-api.com) - used to offer insights into IP addresses, namely country and city information. [Terms and Policies](https://ip-api.com/docs/legal)

* [ipify.org](https://www.ipify.org) - used to map IPv6 addresses to IPv4. [Terms and Policies](https://geo.ipify.org/terms-of-service)
