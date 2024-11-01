=== Zip Embed ===
Contributors: trepmal 
Donate link: http://kaileylampert.com/donate/
Tags: upload, media library, zip
Requires at least: 2.8
Tested up to: 3.2.1
Stable tag: trunk

Upload a zip archive and let WP embed its contents into a post.

== Description ==

Upload a zip archive and let WP embed its contents into a post.

Please note that you'll still be restricted by your server's maximum upload size.

New plugin. Please report bugs to trepmal (at) gmail (dot) com. Thanks!

* [I'm on twitter](http://twitter.com/trepmal)

== Installation ==

This is a new plugin. Only those comfortable using and providing feedback for new plugins should use this.
If you don't know how to install a plugin, this plugin isn't for you (yet).

== Frequently Asked Questions ==

= How can I disable the inluded javascript? =
The contents of text files (php, css, js, txt, html, htm) are stored as post meta. Other files (such as images) and the original zip file are saved as attachments to the plugin post.

To disable the included javascript, add the following to your theme's functions.php file

`add_action( 'init', function() {
	remove_action('wp_head', 'zip_embed_scripts');
});`

== Other Notes ==

= 0.4 =
* Multisite compatibility

== Changelog ==

= 0.4 =
* Multisite compatibility
* Prep for localization

= 0.3 =
* Fixed broken "Upload Zip" redirect
* Better naming of saved text files (recoginize directories)

= 0.2 =
* Added a formatting option

= 0.1 =
* First release

