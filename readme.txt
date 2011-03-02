=== Android Market Badges ===
Contributors: splitfeed
Tags: android, market, badge
Requires at least: 3.0.0
Tested up to: 3.1
Stable tag: 0.7

Adds a BBcode to display a generated image containing information from the Android Market.

== Description ==

**This plugin requires a Gmail account connected to a phone from which you can get your
[market_checkin value](http://code.google.com/p/android-market-api-php/wiki/HowToGetDeviceID#market_checkin). As far as I know this can only be done (reasonably) easy on rooted devices**

Adds a shortcode to display a generated image containing information from the Android Market. The shortcode format is [app]&lt;app pname&gt;[/app]
to output a badge of the selected application.
There is also a simple support for QR-codes built in that can be used by [qr]&lt;app pname&gt;[/qr].

## Examples ##
[app]org.wordpress.android[/app]

[qr]org.wordpress.android][/qr]

[qr size=10]org.wordpress.android][/qr]

* [Plugin web page](http://www.splitfeed.net/market-badges/)

== Installation ==

1. Install normally (will expand on this if someone needs me to, but just do it)
2. Go to "App Badges" settings under "Plugin menu"
3. Enter your GMail username and password
4. Enter your android deviceId
5. Check the rest of the settings if you want to, but they should work fine out of the box

To test the installation, simply type [app=org.wordpress.android] into a post.

== Frequently Asked Questions ==

= Problems with fetching the market data? =

There is a project site and news group at http://code.google.com/p/android-market-api-php/ that is the part that fetches information
from the Android Market. The wiki there is somewhat bare bones, but check it out and mail the newsgroup if it doesn't fill your needs

== Screenshots ==

1. The default badge for the WordPress client for Android

2. QR code for the WordPress client for Android

== Changelog ==

= 0.7 =
* Discovered shortcode stuff in WP and rewrote some code to use that instead. **This renders the old shortcode format
invalid and will leave them as they are. I will try to work this out before releasing 0.7.**

= 0.6 =
* Reset messed up versioning, sorry
* Added another screenshot
* Tweaked this document

= 0.51 =
* Renamed default badge to "default"
* Cleaned up the code a little bit
* Better error handling when lacking write permissions to the cache folder

= 0.5 =
* First version uploaded to WP Plugin Site