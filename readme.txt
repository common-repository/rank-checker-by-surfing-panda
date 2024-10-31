=== Rank Checker by Surfing Panda ===
Contributors: surfingpanda
Donate link: http://surfingpanda.com/rank-checker-wordpress-plugin/
Tags: keyword ranking, rank checker, rank tracker, SEO, SERP, search engine
Requires at least: 3.01
Tested up to: 3.52
Stable tag: 0.22
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin will track your Google Search Engine ranking for keywords which
bring visitors to your website.

== Description ==

This plugin provides data on the various keywords that bring visitors to your Wordpress site. Each time you receive a visit from Google, this plugin will record the keyword they searched for. It will also record your website's ranking for that keyword. This data will be displayed on the Wordpress Administration pages.

== Installation ==

1. Upload `rank-checker-by-surfing-panda/` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this plugin violate any TOS for search engine providers? =

No. This plugin collects data based on the referral URL from incoming visits.
It does not scrape data from search engines or violate any TOS agreements.

= Do I need to specify my keywords? =

No. This plugin will automatically display any keywords that were used by 
visitors of your site.

= I visited my website from Google.  Why don't I see any keyword data? =

Google does not ALWAYS send keyword and ranking information. In order to provide 
increased privacy, Google does not pass along keyword information for any users 
logged into their Google accounts. If someone is logged into Gmail or another 
Google product when they use the Google search engine, Google will not pass 
along keyword data in the referral string.  This is the reason you may see a 
keyword of “not provided” when using traffic analytics programs including Google 
Analytics itself. Currently the Rank Checker plugin will filter out any results 
where either the keyword or ranking is unavailable.

Google also blocks keyword data when using SSL (https:// instead of http). If 
you log out of your Google account to try and generate some test data, please 
make sure you first navigate to the regular “http:// version” of www.google.com. 
By default you remain on the SSL version of www.google.com right after logging 
out of your Google account.

= Which search engines are supported by this plugin? =

Currently we support only Google keyword tracking. We are looking into ways to
add Bing and Yahoo results but as of now they are not supported.

== Screenshots ==

1. This screen shot shows the keyword report screen.
2. This screen shot shows where to find the settings page and keyword report link.

== Changelog ==

= 0.22 =
* Fixed a bug preventing statistics updates for cached pages
* Fixed a bug with the Keyword Report page due to missing URLs

= 0.21 =
* Added a hook to automatically remove duplicate keyword data related to a but in version 0.1

= 0.2 =
* Fixed typo in FAQ section and added screenshots
* Fixed bug causing duplicate keyword results and added activation function to remove duplicate rows.

= 0.1 =
* Initial version.

== Upgrade Notice ==

= 0.22 =
This upgrade fixes problems associated with page caching.

= 0.2 =
This upgrade fixes a bug that can cause duplicate keyword results to appear.