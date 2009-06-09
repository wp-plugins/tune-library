=== Tune Library ===
Contributors: jackdewey
Donate link: http://yannickcorner.nayanna.biz/wordpress-plugins/
Tags: iTunes, music, collection, list, XML, AJAX
Requires at least: 2.7
Tested up to: 2.8
Stable tag: trunk

The purpose of this plugin is to allow users to import their iTunes music list into Wordpress and print their song collection in a page.

Users can choose to only show artists whose names start with a single letter at a time to accomodate large collections. Tune Library also features an AJAX-based query mode to avoid refreshing the page for every query and to only request data when required from the database.

== Description ==

This plugin is used to import an XML iTunes Music Library file into your Wordpress database. Once imported, you can display a complete listing of your music collection on a page of your Wordpress site.

You can see a demonstration of the output of the plugin [here](http://yannickcorner.nayanna.biz/my-music/).

== Installation ==

1. Download the plugin and unzip it.
1. Upload the tune-library folder to the /wp-content/plugins/ directory of your web site.
1. Activate the plugin in the Wordpress Admin.
1. Upload the iTunes Music Library.xml file from your user profile to the plugin directory. ([Help finding your XML File](http://support.apple.com/kb/HT1660))
1. In the Tune Library Plugin Configuration Screen, make sure that the name of the iTunes library matches the name of the file that you uploaded.
1. Select Import iTunes Library to load the contents of your library into the Wordpress database.
1. Configure the plugin based on the desired output.
1. In the Wordpress Admin, create a new page containing the following code:<br/>
   [tune-library]

== Changelog ==

1. 1.4: Re-architected main function to print output where the link-library shortcode is used on a page. Used to always print output before any page content.
1. 1.3.3: Fix to avoid javascript error on pages that don't have a folder tree
1. 1.3.2: Changed code around Loading Icon styling
1. 1.3.1: Added support for AJAX query mode to avoid unnecessary screen refreshes and database queries
1. 1.3: Development version released by mistake
1. 1.2.1: Changed code for default letter shown in filter mode. Was previously hard-coded to A. Now shows appropriate first letter
1. 1.2: Added new functionality to only show artists whose names start with a single letter at a time to accomodate large collections. Added alphabetical list for regular library display to jump to a specific letter quickly.
1. 1.1: Changed main function structure to print data directly as it parses track list instead of building large string in memory. This allows Tune Library to support large iTunes libraries.
1. 1.0.1: Added new option to display black or white expand and collapse icons

== Frequently Asked Questions ==

There are no FAQs at this time.

== Screenshots ==

1. The Tune Library Configuration Screen
2. A sample output of the Tune Library plugin when used inside of a Wordpress page
