<?php
/*
Plugin Name: Tune Library
Plugin URI: http://yannickcorner.nayanna.biz/wordpress-plugins/
Description: A plugin that can be used to import an iTunes Library into a MySQl database and display the contents of the collection on a Wordpress Page.
Version: 1.5.5
Author: Yannick Lefebvre
Author URI: http://yannickcorner.nayanna.biz
*/

/*  Copyright 2009  Yannick Lefebvre  (email : ylefebvre@gmail.com)
	Part of XML Loading Code based on Musiker (http://code.google.com/p/musiker/) by Jarvis Badgley, re-used with permission
	Thanks to Gary Traffanstedt for his help on testing, his great suggestions and with AJAX

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/*--------INITIAL FUNCTIONS--------------------------------------------------------*/

function parseValue( $valueNode, $depth = -1) {
  $valueType = $valueNode->nodeName;

  $transformerName = "parse_$valueType";

  if ( is_callable($transformerName)) {
    // there is a transformer function for this node type
    return call_user_func($transformerName, $valueNode, $depth);
  }

  // if no transformer was found
  return null;
}

	function parse_integer( $integerNode ) {
		return $integerNode->textContent;
	}

	function parse_string( $stringNode ) {
		return $stringNode->textContent;
	}

	function parse_date( $dateNode ) {
		return $dateNode->textContent;
	}

	function parse_true( $trueNode ) {
		return true;
	}

	function parse_false( $trueNode ) {
		return false;
	}
	
	function parse_data( $dataNode ) {
		return $dataNode->textContent;
	}
	
	function parse_dict( $dictNode, $depth = -1 ) {
		if ($depth==0) return $dictNode;//"DICT BELOW LEVEL";
		
		$dict = array();

		// for each child of this node
		for (
			$node = $dictNode->firstChild;
		$node != null;
		$node = $node->nextSibling
		) {
			if ( $node->nodeName == "key" ) {
				$key = $node->textContent;

				$valueNode = $node->nextSibling;

				// skip text nodes
				while ( $valueNode->nodeType == XML_TEXT_NODE ) {
					$valueNode = $valueNode->nextSibling;
				}


				// recursively parse the children
				$value = parseValue($valueNode, $depth-1);

				$dict[$key] = $value;

				//if ($value instanceof DOMElement) $value = "[XML Node: ".$value->childNodes->length."children]";
				//print "$key -> $value\n";
			}
		}

		return $dict;
	}

	function parse_array( $arrayNode, $depth = -1 ) {
		if ($depth==0) return $arrayNode;//"ARRAY BELOW LEVEL";
		$array = array();

		for (
			$node = $arrayNode->firstChild;
		$node != null;
		$node = $node->nextSibling
		) {
			if ( $node->nodeType == XML_ELEMENT_NODE && $depth!=0) {
				
				array_push($array, parseValue($node, $depth-1));
				
			}
		}

		return $array;
	}

function iTunesDateTOMySQLDate ($date) {
	return str_replace('T',' ',str_replace('Z','',$date));
}

if ( ! class_exists( 'TL_Admin' ) ) {

	class TL_Admin {

		function TL_Admin() {
			// adds the menu item to the admin interface
			add_action( 'admin_menu', array( $this,'add_config_page' ) );
		}

		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_options_page('Tune Library for Wordpress', 'Tune Library', 'manage_options', basename(__FILE__), array( $this, 'config_page' ) );
				add_filter( 'plugin_action_links', array( $this, 'filter_plugin_actions'), 10, 2 );
				add_filter( 'ozh_adminmenu_icon', array( $this, 'add_ozh_adminmenu_icon' ) );
			}
		} // end add_LL_config_page()

		function add_ozh_adminmenu_icon( $hook ) {
			static $tlicon;
			if (!$tlicon) {
				$tlicon = WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/chart_curve.png';
			}
			if ($hook == 'tune-library.php') return $tlicon;
			return $hook;
		}

		function filter_plugin_actions( $links, $file ){
			//Static so we don't call plugin_basename on every plugin row.
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

			if ( $file == $this_plugin ){
				$settings_link = '<a href="options-general.php?page=tune-library.php">' . __('Settings') . '</a>';
				array_unshift( $links, $settings_link ); // before other links
			}
			return $links;
		}
		
		function config_page() {
			global $dlextensions;
			global $wpdb;
			
			// Pre-2.6 compatibility
				if ( !defined('WP_CONTENT_URL') )
					define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
				if ( !defined('WP_CONTENT_DIR') )
					define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

				// Guess the location
				$tlpluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';
			
			if ( isset($_GET['reset']) && $_GET['reset'] == "true") {
					$options['filename'] = 'iTunes Music Library.xml';
					$options['albumartistpriority'] = false;
					$options['iconcolor'] = 'black';
					$options['oneletter'] = false;
					$options['useDHTML'] = false;
					$options['loadingicon'] = 'Ajax-loader.gif';
					$options['buildmenufromitems'] = false;
					$options['displayshowall'] = false;
					$options['defaultlettertodisplay'] = '';
					$options['groupnonalphaentries'] = false;
					
				update_option('TuneLibraryPP',$options);
			}
			elseif ( isset($_GET['flush']) && $_GET['flush'] == "true") {
				$droptrackstable = "DELETE FROM ". $wpdb->get_blog_prefix() . "tracks";
				
				$wpdb->get_results($droptrackstable);

				echo "<div id='message' class='updated fade'>Music Data deleted</div>";
			}
			elseif ( isset($_POST['importitunes'])) {
				echo "<div id='message' class='updated fade'>";
				$options  = get_option('TuneLibraryPP');

				$handle = fopen($_FILES['ituneslibfilename']['tmp_name'], "r");
				
				if ($handle)
				{
					$filetoload = $_FILES['ituneslibfilename']['tmp_name'];
					$xmlDoc = new DOMDocument();
					$xmlDoc->load($filetoload);

					$root = $xmlDoc->documentElement->firstChild;

					while ($root->nodeName == "#text") $root = $root->nextSibling;

					//load root tree structure, this contains lib info, the songs database, and the playlists array
					$docRootValues = parseValue($root,1);

					//load the track list
					$songsDict = parseValue($docRootValues['Tracks'],1);
					$songCount = count($songsDict);
					$i=0;
					foreach ($songsDict as $trackElement) {
						$i++;
						$track = parseValue($trackElement);

						$tracktitle = ( isset( $track['Name'] ) ? $track['Name'] : '' );
						$artist = ( isset( $track['Artist'] ) ? $track['Artist'] : '' );
						$albumartist = ( isset( $track['Album Artist'] ) ? $track['Album Artist'] : '' );
						$album = ( isset( $track['Album'] ) ? $track['Album'] : '' );
						$tracknumber = ( isset( $track['Track Number'] ) ? $track['Track Number'] : '' );

						$wpdb->insert( $wpdb->get_blog_prefix() . "tracks", array( 'title' => sanitize_text_field( $tracktitle ), 'artist' => sanitize_text_field( $artist ), 'albumartist' => sanitize_text_field( $albumartist ), 'album' => sanitize_text_field( $album ), 'tracknum' => intval( $tracknumber ) ) );
						
					}
					
					echo "Imported " . $i . " Tracks Successfully";				
				}
				else
					echo "File not found";
				
				echo "</div>";

			}
			elseif (isset($_POST['importcsv'])) {
				global $wpdb;
				$row = 0;

				$handle = fopen($_FILES['csvfilename']['tmp_name'], "r");

				if ($handle)
				{
					$skiprow = 1;
	 
					while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
						$row += 1;
						if ($skiprow == 1 && $row >= 2)
							$skiprow = 0;

						if (!$skiprow)
						{
							if (count($data) == 5)
							{
								$wpdb->insert( $wpdb->get_blog_prefix() . "tracks", array( 'title' => $data[0], 'artist' => $data[1], 'albumartist' => $data[2], 'album' => $data[3], 'tracknum' => $data[4]));
							}
						}
					}
					
					echo "<div id='message' class='updated fade'>Import Successful</div>";
				}
			}
			elseif ( isset($_POST['submit']) ) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Tune Library for WordPress options.'));
				check_admin_referer('tunelibrarypp-config');
				
				foreach (array('filename','iconcolor','loadingicon','defaultlettertodisplay') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = $_POST[$option_name];
					}
				}
				
				foreach (array('albumartistpriority', 'oneletter', 'useDHTML', 'displayshowall','groupnonalphaentries') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = true;
					} else {
						$options[$option_name] = false;
					}
				}
				
				if ($_POST['buildmenufromitems'] == 'true') {
					$options['buildmenufromitems'] = true;
				} 
				else if ($_POST['buildmenufromitems'] == 'false') {
					$options['buildmenufromitems'] = false;
				}
				
				update_option('TuneLibraryPP', $options);
			}

			$options  = get_option('TuneLibraryPP');
			?>
			<div class="wrap">
				<h2>Tune Library Configuration</h2>
				<form action="" method="post" enctype="multipart/form-data" id="analytics-conf">
					<input type="hidden" name="MAX_FILE_SIZE" value="128000000" />
					<table class="form-table" style="width:100%;">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('tunelibrarypp-config');
					?>
					<tr>
						<th class='tltooltip' title='The iTunes Library files is an XML file where iTunes stores all information about your library contents. To find it, read the following article: http://support.apple.com/kb/ht1660' scope="row" valign="top">
							Import iTunes Library
						</th>
						<td class='tltooltip' title='The iTunes Library files is an XML file where iTunes stores all information about your library contents. To find it, read the following article: http://support.apple.com/kb/ht1660'>
							<input type="file" id="ituneslibfilename" name="ituneslibfilename" size="40" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;"/>
							<input type="submit" name="importitunes" value="<?php _e('Import iTunes Library', 'link-library'); ?>" />
						</td>
					</tr>
					<tr>
						<th class='tltooltip' title='This import allows you to read in tunes in a file format that follows the import template provided with Tune Library' scope="row" valign="top">
							CSV Import (<a href='<?php echo $tlpluginpath . "importtemplate.csv"?>'>import template</a>)
						</th>
						<td class='tltooltip' title='This import allows you to read in tunes in a file format that follows the import template provided with Tune Library'>
							<input type="file" id="csvfilename" name="csvfilename" size="40" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;"/>
							<input type="submit" name="importcsv" value="<?php _e('Import CSV Tune List', 'link-library'); ?>" />
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							Use Album Artist instead of Artist when present
						</th>
						<td>
							<input type="checkbox" id="albumartistpriority" name="albumartistpriority" <?php if ($options['albumartistpriority']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>
					<tr>
						<th scope="row" valign="top">
							Filter artists by letter and show alphabetical navigation
						</th>
						<td>
							<input type="checkbox" id="oneletter" name="oneletter" <?php if ($options['oneletter']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>
					<tr>
						<th scope="row" valign="top">
							Letter to be shown by default (selects first available if empty)
						</th>
						<td>
							<input type="text" id="defaultlettertodisplay" name="defaultlettertodisplay" size="1" value="<?php echo strval($options['defaultlettertodisplay']); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;"/>
						</td>
					</tr>	
					
					<tr>
						<th scope="row" valign="top">
							Use AJAX Queries (Useful for larger music collections)
						</th>
						<td>
							<input type="checkbox" id="useDHTML" name="useDHTML" <?php if ($options['useDHTML']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>						
					<tr>
						<th scope="row" valign="top">
							Icon to display when performing AJAX queries (relative to Tune Library plugin directory)
						</th>
						<td>
							<input type="text" id="loadingicon" name="loadingicon" size="40" value="<?php echo strval($options['loadingicon']); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;"/>
						</td>
					</tr>						
					<tr>
						<th scope="row" valign="top">
							Expand/Collapse Icon Color
						</th>
						<td>
							<select name="iconcolor" id="flatlist" style="width:200px;">
								<option value="black"<?php selected( $options['iconcolor'], 'black' ); ?>>Black</option>
								<option value="white"<?php selected( $options['iconcolor'], 'white' ); ?>>White</option>
							</select>
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							Navigation Menu Content
						</th>
						<td>
							<select name="buildmenufromitems" id="flatlist" style="width:400px;">
								<option value="false"<?php if ($options['buildmenufromitems'] == false) { echo ' selected="selected"';} ?>>Display full alphabet</option>
								<option value="true"<?php if ($options['buildmenufromitems'] == true) { echo ' selected="selected"';} ?>>Only display letters for items in collection</option>
							</select>
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							Display "Show All" in Navigation Menu
						</th>
						<td>
							<input type="checkbox" id="displayshowall" name="displayshowall" <?php if ($options['displayshowall']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>
					<tr>
						<th scope="row" valign="top">
							Group Non-Alphabetic Entries
						</th>
						<td>
							<input type="checkbox" id="groupnonalphaentries" name="groupnonalphaentries" <?php if ($options['groupnonalphaentries']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							<a href="?page=tune-library.php&amp;flush=true">Delete imported library</a>
						</th>
						<td>
						</td>
					</tr>	
					<tr>
						<th scope="row" valign="top">
							<a href="?page=tune-library.php&amp;reset=true">Reset Settings</a>
						</th>
						<td>
						</td>
					</tr>										
					</table>
									
					<p style="border:0;" class="submit"><input type="submit" name="submit" value="Update Settings &raquo;" /></p>
					
				</form>
			</div>
			
			<script type="text/javascript">
			jQuery(document).ready( function($) {			
				jQuery('.tltooltip').each(function()
							{
							$(this).tipTip();
							}
					);

			});

		</script>
			<?php

		} // end config_page()

		function restore_defaults() {
				$options['filename'] = 'iTunes Music Library.xml';
				$options['albumartistpriority'] = false;
				$options['iconcolor'] = 'black';
				$options['oneletter'] = false;
				$options['useDHTML'] = false;
				$options['loadingicon'] = 'Ajax-loader.gif';
				$options['buildmenufromitems'] = false;
				$options['displayshowall'] = false;
				$options['defaultlettertodisplay'] = '';
				$options['groupnonalphaentries'] = false;
	
			update_option('TuneLibraryPP',$options);
		}
		
	} // end class LL_Admin

} //endif


function tune_library_func($atts) {
	extract(shortcode_atts(array(
	), $atts));

	return tune_library();
}


function tune_library() {

	global $wpdb;
	$artistnumber = 1;
	$albumnumber = 1;
	
	$options  = get_option('TuneLibraryPP');
	
	$artistletter  = urldecode(get_query_var('artistletter'));
	
	$showallartists = get_query_var('showallartists');
	
	// Pre-2.6 compatibility
	if ( !defined('WP_CONTENT_URL') ) {
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	} elseif ( !defined('WP_CONTENT_DIR') ) {
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	}

	// Guess the location
	$tlpluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';

	$output = "<!-- Tune Library 1.4 Output -->";
	$output .= "<div id=\"TuneLibrary\">";

	$output .= "<SCRIPT LANGUAGE=\"JavaScript\">\n";
	$output .= "var plusImg = new Image();\n";

	if ($options['iconcolor'] == 'black' || $options['iconcolor'] == '')
		$output .= "\tplusImg.src = \"" . $tlpluginpath . "/plusbl.gif\"\n";
	else if ($options['iconcolor'] == 'white')
		$output .= "\tplusImg.src = \"" . $tlpluginpath . "/plusbl-white.gif\"\n";

	$output .= "var minusImg = new Image()\n";

	if ($options['iconcolor'] == 'black' || $options['iconcolor'] == '')
		$output .= "\tminusImg.src = \"" . $tlpluginpath . "/minusbl.gif\"\n\n";
	else if ($options['iconcolor'] == 'white')
		$output .= "\tminusImg.src = \"" . $tlpluginpath . "/minusbl-white.gif\"\n\n";

	$output .= "function showAlbums() {\n";
	$output .= "\tif (document.getElementsByTagName)\n";
	$output .= "\t\tx = document.getElementsByTagName('div');\n";
	$output .= "\telse if (document.all)\n";
	$output .= "\t\tx = document.all.tags('div');\n\n";

	$output .= "\tfor (var i=0;i<x.length;i++)\n";
	$output .= "\t{\n";
	$output .= "\t\tif (x[i].id.indexOf(\"Set\") != -1) {\n";
	$output .= "\t\t\tx[i].style.display = \"\";\n";
	$output .= "\t\t}\n";
	$output .= "\t}\n\n";

	$output .= "\tif (document.getElementsByTagName)\n";
	$output .= "\t\tx = document.getElementsByTagName('img');\n";
	$output .= "\telse if (document.all)\n";
	$output .= "\t\tx = document.all.tags('img');\n\n";

	$output .= "\tfor (var i=0;i<x.length;i++)\n";
	$output .= "\t{\n";
	$output .= "\t\tif (x[i].id.indexOf(\"Set\") != -1) {\n";
	$output .= "\t\t\tx[i].src = minusImg.src;\n";
	$output .= "\t\t}\n";
	$output .= "\t}\n";
	$output .= "}\n\n";

	$output .= "function hideAlbums() {\n";
	$output .= "\tif (document.getElementsByTagName)\n";
	$output .= "\t\tx = document.getElementsByTagName('div');\n";
	$output .= "\telse if (document.all)\n";
	$output .= "\t\tx = document.all.tags('div');\n\t";

	$output .= "\tfor (var i=0;i<x.length;i++)\n";
	$output .= "\t{\n";
	$output .= "\t\tif ((x[i].id.indexOf(\"Set\") != -1) || (x[i].id.indexOf(\"Album\") != -1)) {\n";
	$output .= "\t\t\tx[i].style.display = \"none\"\n";
	$output .= "\t\t}\n";
	$output .= "\t}\n\n";

	$output .= "\tif (document.getElementsByTagName)\n";
	$output .= "\t\tx = document.getElementsByTagName('img');\n";
	$output .= "\telse if (document.all)\n";
	$output .= "\t\tx = document.all.tags('img');\n\n";

	$output .= "\tfor (var i=0;i<x.length;i++)\n";
	$output .= "\t{\n";
	$output .= "\t\tif ((x[i].id.indexOf(\"Set\") != -1) || (x[i].id.indexOf(\"Album\") != -1)) {\n";
	$output .= "\t\t\tx[i].src = plusImg.src;\n";
	$output .= "\t\t}\n";
	$output .= "\t}\n";
	$output .= "}\n\n";

	$output .= "function showLevel( _levelId, _imgId ) {\n";
	$output .= "\tvar thisLevel = document.getElementById( _levelId );\n";
	$output .= "\tvar thisImg = document.getElementById( _imgId );\n";
	$output .= "\tif ( thisLevel.style.display == \"none\") {\n";
	$output .= "\t\tthisLevel.style.display = \"\";\n";
	$output .= "\t\tthisImg.src = minusImg.src;\n";
	$output .= "\t}\n";
	$output .= "\telse {\n";
	$output .= "\t\tthisLevel.style.display = \"none\";\n";
	$output .= "\t\tthisImg.src = plusImg.src;\n";
	$output .= "\t}\n";
	$output .= "}\n\n";

	$output .= "function showArtistLetter ( _incomingletter) {\n";
	$output .= "var map = {action:'tune_library_ajax', letter : _incomingletter}\n";
	$output .= "\tjQuery('#contentLoading').toggle();jQuery.get('" . admin_url( 'admin-ajax.php' ) . "', map, function(data){jQuery('#dhtmlgoodies_tree').replaceWith(data);initTree();jQuery('#contentLoading').toggle();});\n";
	$output .= "}\n";

	$output .= "</SCRIPT>\n\n";

	$albums = '';
		
 	if ( !$options['albumartistpriority'] ) {

		if ( $options['oneletter'] == false || $showallartists == true ) {
			$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' order by artist";
		} else {
			if ( empty( $artistletter ) && empty( $options['defaultlettertodisplay'] ) ) {
				$lowestletterquery = 'SELECT min( substring( artist, 1, 1 ) ) as letter FROM ' . $wpdb->get_blog_prefix() . "tracks where artist != ''";
				$lowestletters = $wpdb->get_results( $lowestletterquery );

				if ( $lowestletters ) {
				   foreach ( $lowestletters as $lowestletter ) {
						if ( $options['groupnonalphaentries'] == true ) {
							if ($lowestletter->letter < 'A' || $lowestletter->letter > 'Z')
								$artistletter = '#';
							else
								$artistletter = $lowestletter->letter;
						} else {
							$artistletter = $lowestletter->letter;
						}
				   }
				}
			} else if ( empty( $artistletter ) && !empty( $options['defaultlettertodisplay'] ) ) {
				$artistletter = $options['defaultlettertodisplay'];
			}

			if ( $artistletter != '#' ) {
				$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' and artist like '%s' order by artist";
				$querystr = $wpdb->prepare( $querystr, $artistletter . '%');
			} else {
				$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z') order by artist";
			}
		}

		$albums = $wpdb->get_results( $querystr );
	} else {
		if ( $options['oneletter'] == false || $showallartists == true ) {
			$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' and (albumartist is NULL or artist = albumartist)) UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where albumartist is not NULL and artist != albumartist) order by artist";
		} else {
			if ( empty( $artistletter ) && empty( $options['defaultlettertodisplay'] ) ) {
				$lowestletterquery = "SELECT min( letter ) as letter FROM ((Select substring(artist, 1, 1) as letter from " . $wpdb->get_blog_prefix() . "tracks where artist != '' and (albumartist is NULL or artist = albumartist)) UNION (Select substring(albumartist, 1, 1) as letter from " . $wpdb->get_blog_prefix() . "tracks where albumartist != '' and artist != albumartist)) as FirstGroup";

				$lowestletters = $wpdb->get_results( $lowestletterquery );

				if ( $lowestletters ) {
				   foreach ( $lowestletters as $lowestletter ) {
						if ( $options['groupnonalphaentries'] == true ) {
							if ( $lowestletter->letter < 'A' || $lowestletter->letter > 'Z' ) {
								$artistletter = '#';
							} else {
								$artistletter = $lowestletter->letter;
							}
						} else {
							$artistletter = $lowestletter->letter;
						}
					}
				}
			} else if ($artistletter == '' && $options['defaultlettertodisplay'] != '') {
				$artistletter = $options['defaultlettertodisplay'];
			}

				
			if ($artistletter != '#') {
				$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) and artist like '%s') UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where albumartist is not NULL and artist != albumartist and albumartist like '%s') order by artist";
				$querystr = $wpdb->prepare( $querystr, $artistletter . '%', $artistletter . '%' );
			} else {
				$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z') ) UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->get_blog_prefix() . "tracks where albumartist is not NULL and artist != albumartist and (substring(albumartist, 1, 1) < 'A' or substring(albumartist, 1, 1) > 'Z')) order by artist";
			}
		}

		$albums = $wpdb->get_results( $querystr );
	} 

    if ( !empty( $albums ) ) {
		
			// Code for navigation menu at top of page
			
			if ($options['oneletter'] == true)
				$output .= "\t<div class=LetterSelector>Show Letter ";
			else
				$output .= "\t<div class=LetterSelector>Jump to Letter ";
			
			if (!$options['albumartistpriority'])
			{
				if ($options['groupnonalphaentries'] == false)
				{
					$letterquery = "select substring(artist, 1, 1) as letter, count(substring(artist, 1, 1)) as count from (SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null order by artist) artists group by substring(artist, 1, 1)";
					$nonletterquery = '';
				}
				else
				{
					$letterquery = "select substring(artist, 1, 1) as letter, count(substring(artist, 1, 1)) as count from (SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null and (substring(artist, 1, 1) >= 'A' and substring(artist, 1, 1) <= 'Z') order by artist) artists group by substring(artist, 1, 1)";					
					$nonletterquery = "select '#' as letter, count(substring(artist, 1, 1)) as count from (SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z') order by artist) artists";
				}
				
			}
			else
			{
				if ($options['groupnonalphaentries'] == false)
				{
					$letterquery = "select substring(artist, 1, 1) as letter, count(substring(artist, 1, 1)) as count from ((SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null and (albumartist is NULL or artist = albumartist)) UNION (SELECT distinct albumartist as artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE albumartist is not null and artist != albumartist order by artist) ) artists group by substring(artist, 1, 1)";
					$nonletterquery = '';
				}
				else
				{
					$letterquery = "select substring(artist, 1, 1) as letter, count(substring(artist, 1, 1)) as count from ((SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null and (albumartist is NULL or artist = albumartist) and (substring(artist, 1, 1) >= 'A' and substring(artist, 1, 1) <= 'Z')) UNION (SELECT distinct albumartist as artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE albumartist is not null and artist != albumartist and (substring(artist, 1, 1) >= 'A' and substring(artist, 1, 1) <= 'Z') order by artist) ) artists group by substring(artist, 1, 1)";			
					$nonletterquery = "select '#' as letter, count(substring(artist, 1, 1)) as count from ((SELECT distinct artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist is not null and (albumartist is NULL or artist = albumartist) and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z')) UNION (SELECT distinct albumartist as artist FROM " . $wpdb->get_blog_prefix() . "tracks WHERE albumartist is not null and artist != albumartist and (substring(albumartist, 1, 1) < 'A' or substring(albumartist, 1, 1) > 'Z') order by artist) ) artists";
				}
			}
			
			$artistletters = $wpdb->get_results( $letterquery );
	        $nonletterartists = '';

	        if ( !empty( $nonletterquery ) ) {
		        $nonletterartists = $wpdb->get_results($nonletterquery);
	        }
				
			if ( $artistletters && $options['buildmenufromitems'] == true )
				{
					if ( isset( $nonletterartists ) && !empty( $nonletterartists ) ) {
						foreach ($nonletterartists as $nonletterartist)
						{
							if ($options['oneletter'] == true)
							{
								if ($options['useDHTML'] == true)
									$output .= "<a href='#' onClick=\"showArtistLetter('#');\" title='".$nonartistletter->count." Artists'>#</a>";
								else
									$output .= '<a href="?artistletter=' . urlencode('#') . '" title="' . $artistletter->count. ' artists">#</a>';
							}
						}
					}

					if ( !empty( $artistletters ) ) {
						foreach ($artistletters as $artistletter){
							if ($options['oneletter'] == true)
							{
								if ($options['useDHTML'] == true)
									$output .= "<a href='#' onClick=\"showArtistLetter('" . $artistletter->letter. "');\" title='".$artistletter->count." Artists'>" . $artistletter->letter . "</a>";
								else
									$output .= '<a href="?artistletter=' . urlencode($artistletter->letter) . '" title="' . $artistletter->count. ' artists">' . $artistletter->letter . "</a>";
							}
							else
								$output .= '<a href="#' . $artistletter->letter . '" title="' . $artistletter->count. ' artists">' . $artistletter->letter . "</a>";
						}
					}
				}
			else {
				$letters = array();
				if ( $artistletters ) {
					foreach($artistletters as $letter){
						$letters[strtoupper($letter->letter)]=array();
						array_push($letters[strtoupper($letter->letter)], strtoupper($letter->letter));
						array_push($letters[strtoupper($letter->letter)], $letter->count);
					}
				}
				
				if ( isset( $nonletterartists ) && !empty( $nonletterartists ) ) {
					foreach($nonletterartists as $nonletterartist)
					{
						$letters['#']=array();
						array_push($letters['#'], '#');
						array_push($letters['#'], $nonletterartist->count);
					}
				}
				
				if (array_key_exists('#', $letters))
				{
					if ($letters['#'][1] > 0)
					{
						if ($options['oneletter'] == true)
							$output .= "<a href='#' onClick=\"showArtistLetter('#');\" title='".$letters['#'][1]." Artists'>#</a>";
						else
							$output .= '';
					
					}
					else
						$output .= '<span class=emptyletter>#</span>';
				}
				else
					$output .= '<span class=emptyletter>#</span>';
	
				foreach (range('A', 'Z') as $letter) {
					if (array_key_exists($letter, $letters)) {
						if($letters[$letter][1] > 0){
							if ($options['oneletter'] == true && $options['useDHTML'] == true)
								$output .= "<a href=\"#\" onClick=\"showArtistLetter('" . $letter. "');\" title='".$letters[$letter][1]." Artists'>$letter</a>";
							else if ($options['oneletter'] == true && $options['useDHTML'] == false)
								$output .= '<a href="?artistletter=' . urlencode($letter) . '" title="' . $letters[$letter][1] . ' artists">' . $letter . "</a>";
							else if ($options['oneletter'] == false)
								$output .= '<a href="#' . $letter . '" title="' . $letters[$letter][1] . ' artists">' . $letter . "</a>";
						}
					}
					else
					{
						$output .= "<span class=emptyletter>" . $letter . "</span>";
					}
				}		
			}
				
			if ($options['oneletter'] == true && $options['useDHTML'] == false && $options['displayshowall'] == true)
				$output .= '<a href="?showallartists=true">Show All</a>';
			else if ($options['oneletter'] == true && $options['useDHTML'] == true && $options['displayshowall'] == true)
				$output .= "<a href='#' onClick=\"showArtistLetter('');\" title='Show all'>Show All</a>";
				
			$output .= "<span class='contentLoading' id='contentLoading' style='display: none;'><img src='" . WP_PLUGIN_URL . "/tune-library/" . $options['loadingicon'] . "' alt='Loading data, please wait...'></span>";

			$output .= "</div>";
			
		$output .= "\n<ul id=\"dhtmlgoodies_tree\" class=\"dhtmlgoodies_tree\">\n";
		$count = 1;
		
		$currentletter = '';
		
       foreach ($albums as $album){

			if ($currentletter != substr($album->artist, 0, 1))
					$output .= '<a name="' . substr($album->artist, 0, 1) . '">';
	   
			if ($options['useDHTML'] == true)
			{
	   		
				$output .= "<li><a href=\"#\" id='".urlencode('node_'.$count)."'> ".$album->artist."</a>";
				if ($album->source == "artist")
					$output .= "<ul><li parentId='artist::".urlencode($album->artist)."'><a href='#' id='node_2'><img src=\"" . WP_PLUGIN_URL . "/tune-library/" . $options['loadingicon'] . "\" style=\"float: left;\" alt=\"Loading data, please wait...\"></a></li></ul></li>";
				else
					$output .= "<ul><li parentId='albumartist::".urlencode($album->artist)."'><a href='#' id='node_2'><img src=\"" . WP_PLUGIN_URL . "/tune-library/" . $options['loadingicon'] . "\" style=\"float: left;\" alt=\"Loading data, please wait...\"></a></li></ul></li>";
							
				$count++;
					
								
			}
			else
			{	
			
				$output .= "\t<div id=" . substr($album->artist, 0, 1) . "><div class=ArtistHeader>\n<a href=\"javascript:showLevel('Set" . $artistnumber . "','imgSet" . $artistnumber . "');\">\n";
				$output .= "\t\t<img id=imgSet" . $artistnumber . " border=0 src=\"". $tlpluginpath;
				
				if ($options['iconcolor'] == 'black' || $options['iconcolor'] == '')
					$output .= "/plusbl.gif\"><b> ";
				else if ($options['iconcolor'] == 'white')
					$output .= "/plusbl-white.gif\"><b> ";
	 
				$output .= $album->artist . "</b></a><br>\n\n";
							
				if (!$options['albumartistpriority'])
				{
					$secondquerystr ="SELECT distinct album FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist = '%s' order by album";
					$albumslists = $wpdb->get_results( $wpdb->prepare( $secondquerystr, $album->artist ) );
				}
				else
				{
					if ($album->source == "artist")
					{
						$secondquerystr = "SELECT distinct album FROM " . $wpdb->get_blog_prefix() . "tracks WHERE artist = '%s' and (artist = albumartist or albumartist is NULL) order by album";
						$albumslists = $wpdb->get_results( $wpdb->prepare( $secondquerystr, $album->artist ) );
					}
					else
					{
						$secondquerystr ="SELECT distinct album FROM " . $wpdb->get_blog_prefix() . "tracks WHERE albumartist = '%s' order by album";
						$albumslists = $wpdb->get_results( $wpdb->prepare ( $secondquerystr, $album->artist ) );
					}
					
									
				}
				
				if ($albumslists) {
				
					$output .= "\t\t<div class=AlbumListHeader id=Set" . $artistnumber . " style='display:none'>\n";

				
					foreach ($albumslists as $albumlist){
						$output .= "\t\t\t<div class=AlbumTitle\">\n\t\t\t<a href=\"javascript:showLevel('Album" . $albumnumber . "','imgAlbum" . $albumnumber . "');\">\n\t\t\t<img border=0 id=imgAlbum" . $albumnumber . " class=subImage src=\"" . $tlpluginpath;
						
						if ($options['iconcolor'] == 'black' || $options['iconcolor'] == '')
							$output .= "/plusbl.gif\">";
						else if ($options['iconcolor'] == 'white')
							$output .= "/plusbl-white.gif\">";
						
						$output .= "</a><a href=\"javascript:showLevel('Album" . $albumnumber. "','imgAlbum" . $albumnumber . "');\"><b> " . $albumlist->album . "</b></a><br>\n";

						$tracklists = '';
						
						if (!$options['albumartistpriority']) {
							$thirdquerystr = "SELECT tracknum, title, artist, \"\" as albumartist from " . $wpdb->get_blog_prefix() . "tracks where album = '%s' order by tracknum";
							$tracklists = $wpdb->get_results( $wpdb->prepare( $thirdquerystr, $albumlist->album ) );
						}

						else
						{
							if ($album->source == "artist") {
								$thirdquerystr = "SELECT tracknum, title, artist, albumartist from " . $wpdb->get_blog_prefix() . "tracks where album = '%s' and artist = '%s' order by tracknum";
								$tracklists = $wpdb->get_results( $wpdb->prepare( $thirdquerystr, $albumlist->album, $album->artist ) );
							} else {
								$thirdquerystr = "SELECT tracknum, title, artist, albumartist from " . $wpdb->get_blog_prefix() . "tracks where album = '%s' and albumartist = '%s' order by tracknum";
								$tracklists = $wpdb->get_results( $wpdb->prepare( $thirdquerystr, $albumlist->album, $album->artist ) );
							}
						}

						if ($tracklists) {
						
							$output .= "\t\t\t\t<div class=TrackList id=Album" . $albumnumber . " style='position:relative;left:+15px;display:none'>\n";
							
							foreach ($tracklists as $tracklist){

								$tracknumber = ( isset( $tracklist->tracknum ) ? $tracklist->tracknum : '' );
								$tracktitle = ( isset( $tracklist->title ) ? $tracklist->title : '' );
								$trackartist = ( isset( $tracklist->artist ) ? $tracklist->artist : '' );

								if (!$options['albumartistpriority'])
								{
									$output .= "\t\t\t\t" . $tracknumber . " - " . $tracktitle . "<br />\n";
								}
								else
								{
									if ($album->source == "artist")
										$output .= "\t\t\t\t" . $tracknumber . " - " . $tracktitle . "<br />\n";
									else
										$output .= "\t\t\t\t" . $tracknumber . " - " . $trackartist  . " - " . $tracktitle . "<br />\n";
								}
							
							}
							
							$output .= "\t\t\t\t</div>\n";
						
						}
						
						$output .= "\t\t\t</div>\n";

						$albumnumber = $albumnumber + 1;
					
					}
					
					$output .= "\t\t</div>\n\t</div></div>\n";
				} 
				
			
			}
			
			$artistnumber = $artistnumber + 1;
			
			}
			
       }
	   
	 $output .= '</ul>'; 
	     
	 $output .= "</div>";
	   
	 $output .= "<!-- Tune Library Output -->";
	 
	 return $output;
	 
}

$version = "1.0";

$options  = get_option('TuneLibraryPP',"");

if ($options == "") {
	$options['filename'] = 'iTunes Music Library.xml';
	$options['albumartistpriority'] = false;
	$options['iconcolor'] = 'black';
	$options['oneletter'] = false;
	$options['useDHTML'] = false;
	$options['loadingicon'] = 'Ajax-loader.gif';
	$options['buildmenufromitems'] = false;
	$options['displayshowall'] = false;
	$options['defaultlettertodisplay'] = '';
	$options['groupnonalphaentries'] = false;
	
	update_option('TuneLibraryPP',$options);
}
else
if ($options['loadingicon'] == '')
{
	$options['filename'] = $options['filename'];
	$options['albumartistpriority'] = $options['albumartistpriority'];
	$options['iconcolor'] = $options['iconcolor'];
	$options['oneletter'] = $options['oneletter'];
	$options['useDHTML'] = $options['useDHTML'];
	$options['loadingicon'] = 'Ajax-loader.gif';
	$options['buildmenufromitems'] = $options['buildmenufromitems'];
	$options['displayshowall'] = $options['displayshowall'];
	$options['defaultlettertodisplay'] = $options['defaultlettertodisplay'];
	$options['groupnonalphaentries'] = $options['groupnonalphaentries'];
	
	update_option('TuneLibraryPP', $options);
}


function tune_library_queryvars( $qvars )
{
  $qvars[] = 'artistletter';
  $qvars[] = 'showallartists';
  return $qvars;
}

function tune_library_header() {
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/tune-library/css/folder-tree-static.css"/>';
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/tune-library/css/context-menu.css"/>';
		
}

function tune_library_init() {
	add_action( 'wp_enqueue_scripts', 'tune_library_enqueue_scripts' );
	add_action( 'admin_enqueue_scripts', 'tune_library_admin_scripts' );

	add_action( 'wp_ajax_tune_library_ajax', array( $this, 'tune_library_ajax' ) );
	add_action( 'wp_ajax_nopriv_tune_library_ajax', array( $this, 'tune_library_ajax' ) );
}

function tune_library_enqueue_scripts() {
	wp_enqueue_script('ajax', get_bloginfo('wpurl') . '/wp-content/plugins/tune-library/js/ajax.js', 'jquery' );
	wp_enqueue_script('folder-tree-static', get_bloginfo('wpurl') . '/wp-content/plugins/tune-library/js/folder-tree-static.js.php');
}

function tune_library_admin_scripts() {
	wp_enqueue_script('tiptip', get_bloginfo('wpurl').'/wp-content/plugins/tune-library/tiptip/jquery.tipTip.minified.js', "jQuery", "1.0rc3");
	wp_enqueue_style('tiptipstyle', get_bloginfo('wpurl').'/wp-content/plugins/tune-library/tiptip/tipTip.css');
}

function tune_library_ajax() {
	global $wpdb;

	$options  = get_option('TuneLibraryPP');

	$artistletter = substr($_GET['letter'], 0, 1);

	if (!$options['albumartistpriority'])
	{
		if ($options['oneletter'] == false || $showallartists == true)
			$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' order by artist";
		else
		{
			if ($artistletter == '#')
			{
				$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z') order by artist";
			}
			else
			{
				$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and artist like '" .$artistletter . "%' order by artist";
			}
		}
		$albums = $wpdb->get_results($querystr);
	}

	else
	{
		if ($options['oneletter'] == false || $showallartists == true)
			$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (albumartist is NULL or artist = albumartist)) UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->prefix . "tracks where albumartist is not NULL and artist != albumartist) order by artist";
		else
		{
			if ($artistletter == '#')
			{
				$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z')) UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->prefix . "tracks where albumartist is not NULL and artist != albumartist and (substring(albumartist, 1, 1) < 'A' and substring(albumartist, 1, 1) > 'Z')) order by artist";
			}
			else
			{
				$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) and artist like '" . $artistletter . "%') UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->prefix . "tracks where albumartist is not NULL and artist != albumartist and albumartist like '" . $artistletter .  "%') order by artist";
			}
		}
		$albums = $wpdb->get_results($querystr);

	}

	echo '<ul id="dhtmlgoodies_tree" class="dhtmlgoodies_tree" style="display: block;">';

	$currentletter = '';

	if ($albums){
		$count=1;
		foreach ($albums as $album){

			if ($currentletter != substr($album->artist, 0, 1))
				echo '<a name="' . substr($album->artist, 0, 1) . '">';

			echo "<li><a href='#' id='".urlencode('node_'.$count)."'> ".$album->artist."</a>";
			if ($album->source == "artist")
				echo "<ul><li parentId=\"artist::".urlencode($album->artist)."\"><a href='#' id='node_2'><img src=\"" . WP_PLUGIN_URL . "/tune-library/" . $options['loadingicon'] . "\" style=\"float: left;\" alt=\"Loading data, please wait...\"></a></li></ul></li>";
			else
				echo "<ul><li parentId=\"albumartist::".urlencode($album->artist)."\"><a href='#' id='node_2'><img src=\"" . WP_PLUGIN_URL . "/tune-library/" . $options['loadingicon'] . "\" style=\"float: left;\" alt=\"Loading data, please wait...\"></a></li></ul></li>";

			$count++;


		}
	}else{
		echo "<li><h2> Not Found</h2></li>";
	}
	echo "</ul>";

	exit;
}

function tl_install() {
	global $wpdb;

	$wpdb->tltracks = $wpdb->get_blog_prefix() .'tracks';

	$result = $wpdb->query("
			CREATE TABLE IF NOT EXISTS `$wpdb->tltracks` (
				`title` text NOT NULL,
				  `artist` text,
				  `albumartist` text,
				  `album` text,
				  `trackid` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `tracknum` int(10) unsigned default NULL,				
				PRIMARY KEY  (`trackid`)
				)");
				
	$result = $wpdb->query("ALTER TABLE `" . $wpdb->get_blog_prefix() . "tracks` CHANGE `trackid` `trackid` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT");
}

global $my_tune_library_admin;
$my_tune_library_admin = new TL_Admin();

add_filter('query_vars', 'tune_library_queryvars' );

add_action('wp_head', 'tune_library_header');

add_shortcode('tune-library', 'tune_library_func');

add_action('init', 'tune_library_init');

register_activation_hook(__FILE__, 'tl_install');
?>