<?
/*
Plugin Name: Tune Library
Plugin URI: http://yannickcorner.nayanna.biz/wordpress-plugins/
Description: A plugin that can be used to import an iTunes Library into a MySQl database and display the contents of the collection on a Wordpress Page.
Version: 1.0
Author: Yannick Lefebvre
Author URI: http://yannickcorner.nayanna.biz
*/

/*  Copyright 2009  Yannick Lefebvre  (email : ylefebvre@gmail.com)
	Part of XML Loading Code based on Musiker (http://code.google.com/p/musiker/) by Jarvis Badgley, re-used with permission


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

		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_submenu_page('plugins.php', 'Tune Library for Wordpress', 'Tune Library', 9, basename(__FILE__), array('TL_Admin','config_page'));
				add_filter( 'plugin_action_links', array( 'TL_Admin', 'filter_plugin_actions'), 10, 2 );
				add_filter( 'ozh_adminmenu_icon', array( 'TL_Admin', 'add_ozh_adminmenu_icon' ) );				
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
				$settings_link = '<a href="plugins.php?page=tune-library.php">' . __('Settings') . '</a>';
				array_unshift( $links, $settings_link ); // before other links
			}
			return $links;
		}
		
		function config_page() {
			global $dlextensions;
			global $wpdb;
			
			if ( isset($_GET['reset']) && $_GET['reset'] == "true") {
					$options['filename'] = 'iTunes Music Library.xml';
					$options['albumartistpriority'] = false;
				update_option('TuneLibraryPP',$options);
			}
			if ( isset($_GET['flush']) && $_GET['flush'] == "true") {
				$droptrackstable = "DROP TABLE ". $wpdb->prefix . "tracks";
				
				$wpdb->get_results($droptrackstable);

				echo "Music Data and table deleted";
			}
			if ( isset($_GET['import']) && $_GET['import'] == "true") {
			
				
				echo "<div class=\"wrap\">";
				$options  = get_option('TuneLibraryPP');

				echo "Connecting to MySQL...";
				
					// Pre-2.6 compatibility
					if ( !defined('WP_CONTENT_URL') )
						define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
					if ( !defined('WP_CONTENT_DIR') )
						define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

					// Guess the location
						$tlpluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';


				echo "Loading iTunes library file...(" . $tlpluginpath . $options['filename'] . ")<br />\n";
				$filetoload = $tlpluginpath . $options['filename'];
 				$xmlDoc = new DOMDocument();
				$xmlDoc->load($filetoload);

				$root = $xmlDoc->documentElement->firstChild;

				while ($root->nodeName == "#text") $root = $root->nextSibling;

				//load root tree structure, this contains lib info, the songs database, and the playlists array
				$docRootValues = parseValue($root,1);

				//get itunes music folder location
				$libraryRootFolder = $docRootValues['Music Folder'];
		
				$droptrackstable = "DROP TABLE ". $wpdb->prefix . "tracks";
				
				$wpdb->get_results($droptrackstable);
				
				//remake the new tables
				$schemafilename = $tlpluginpath . "table_schema.sql";
				$queryF = file_get_contents($schemafilename);
				$queryF = str_replace('\t',' ', $queryF);
				$queryF = str_replace('\n','', $queryF);
				$queryF = str_replace('\r','', $queryF);
				$queryF = str_replace('PREFIX',$wpdb->prefix, $queryF);
				$queryF = explode(";",$queryF);
				foreach($queryF as $query) {
					$query = trim($query);
					if ($query) {
						$wpdb->get_results($query);
					}
				}

				//load the track list
				$songsDict = parseValue($docRootValues['Tracks'],1);
				$songCount = count($songsDict);
				$i=0;
				foreach ($songsDict as $trackElement) {
					$track = parseValue($trackElement);
					
					$track['Location'] = str_replace($libraryRootFolder, '', $track['Location']); //trim file path to root of music folder
					
					//QUERY STRING: Title, Artist, Album, TrackID, FilePath, FileSize, Total Time, Rating, track number, track count, disc number, disc count, play count, date last played, date added
					
					$query = "INSERT INTO ".$wpdb->prefix."tracks VALUES (";
					$query.= '"'.mysql_real_escape_string($track['Name']).'", ';
					$query.= (isset($track['Artist']) ? '"'.mysql_real_escape_string($track['Artist']).'"' : 'NULL') .", ";
					$query.= (isset($track['Album Artist']) ? '"'.mysql_real_escape_string($track['Album Artist']).'"' : 'NULL') .", ";
					$query.= (isset($track['Album']) ? '"'.mysql_real_escape_string($track['Album']).'"'  : 'NULL') .", ";
					$query.= $track['Track ID'] .", ";
					$query.= (isset($track['Track Number']) ? $track['Track Number'] : 'NULL') . " ";
					$query.= ");";
					
					$wpdb->get_results($query);
				
				}

				echo "...done\n";
				
				echo "</div>";

			}
			if ( isset($_POST['submit']) ) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Tune Library for WordPress options.'));
				check_admin_referer('tunelibrarypp-config');
				
				foreach (array('filename') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = $_POST[$option_name];
					}
				}
				
				foreach (array('albumartistpriority') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = true;
					} else {
						$options[$option_name] = false;
					}
				}

				
				update_option('TuneLibraryPP', $options);
			}

			$options  = get_option('TuneLibraryPP');
			?>
			<div class="wrap">
				<h2>Tune Library Configuration</h2>
				<form action="" method="post" id="analytics-conf">
					<table class="form-table" style="width:100%;">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('tunelibrarypp-config');
					?>
					<tr>
						<th scope="row" valign="top">
							<label for="filename">Name of iTunes Library to import (file should be in same folder as plugin)</label>
						</th>
						<td>
							<input type="text" id="filename" name="filename" size="40" value="<?php echo strval($options['filename']); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;"/>
						</td>
					</tr>	
					<tr>
						<th scope="row" valign="top">
							<label for="albumartistpriority">Use Album Artist instead of Artist when present</label>
						</th>
						<td>
							<input type="checkbox" id="albumartistpriority" name="albumartistpriority" <?php if ($options['albumartistpriority']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							<label for="filename"><a href="?page=tune-library.php&amp;flush=true">Delete imported library</a></label>
						</th>
						<td>
						</td>
					</tr>	
					<tr>
						<th scope="row" valign="top">
							<label for="filename"><a href="?page=tune-library.php&amp;import=true">Import iTunes library</a></label>
						</th>
						<td>
						</td>
					</tr>					
					<tr>
						<th scope="row" valign="top">
							<label for="filename"><a href="?page=tune-library.php&amp;reset=true">Reset Settings</a></label>
						</th>
						<td>
						</td>
					</tr>										
					</table>
									
					<p style="border:0;" class="submit"><input type="submit" name="submit" value="Update Settings &raquo;" /></p>
					
				</form>
			</div>
			<?php

		} // end config_page()

		function restore_defaults() {
				$options['filename'] = 'iTunes Music Library.xml';
	
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
	
 	if (!$options['albumartistpriority'])
	{ 
		$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' order by artist";
		$albums = $wpdb->get_results($querystr);
	}
	else
	{
		$querystr ="SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) order by artist";
		$firstartists = $wpdb->get_results($querystr);
		
		$albumartistsquery = "SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->prefix . "tracks where albumartist is not NULL and artist != albumartist order by albumartist";
		$albumartists = $wpdb->get_results($albumartistsquery);
		
		$albums = array_merge($firstartists, $albumartists);
		
		sort($albums);
	
	} 
	
	// Pre-2.6 compatibility
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

	// Guess the location
		$tlpluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';


    if ($albums) {
	
		$output .= "<!-- Tune Library Output -->";
	
		$output .= "<SCRIPT LANGUAGE=\"JavaScript\">\n";
		$output .= "var plusImg = new Image();\n";
		$output .= "\tplusImg.src = \"" . $tlpluginpath . "/plusbl.gif\"\n";
		$output .= "var minusImg = new Image()\n";
		$output .= "\tminusImg.src = \"" . $tlpluginpath . "/minusbl.gif\"\n\n";
		
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
		
		$output .= "</SCRIPT>\n\n";
	
	
       foreach ($albums as $album){
			$output .= "\t<div class=ArtistHeader>\n<a href=\"javascript:showLevel('Set" . $artistnumber . "','imgSet" . $artistnumber . "');\">\n";
			$output .= "\t\t<img id=imgSet" . $artistnumber . " border=0 src=\"". $tlpluginpath . "/plusbl.gif\"><b> " . $album->artist . "</b></a><br>\n\n";
						
			if (!$options['albumartistpriority'])
			{
				$secondquerystr ="SELECT distinct album FROM " . $wpdb->prefix . "tracks WHERE artist = '" . mysql_real_escape_string($album->artist) . "' order by album";
				$albumslists = $wpdb->get_results($secondquerystr); 
			}
			else
			{
				if ($album->source == "artist")
				{
					$secondquerystr = "SELECT distinct album FROM " . $wpdb->prefix . "tracks WHERE artist = '" . mysql_real_escape_string($album->artist) . "' and (artist = albumartist or albumartist is NULL) order by album";
					$albumslists = $wpdb->get_results($secondquerystr); 
				}
				else
				{
					$secondquerystr ="SELECT distinct album FROM " . $wpdb->prefix . "tracks WHERE albumartist = '" . mysql_real_escape_string($album->artist) . "' order by album";
					$albumslists = $wpdb->get_results($secondquerystr); 
				}
				
								
			}
			
			if ($albumslists) {
			
				$output .= "\t\t<div class=AlbumListHeader id=Set" . $artistnumber . " style='display:none'>\n";

			
				foreach ($albumslists as $albumlist){
					$output .= "\t\t\t<div class=AlbumTitle\">\n\t\t\t<a href=\"javascript:showLevel('Album" . $albumnumber . "','imgAlbum" . $albumnumber . "');\">\n\t\t\t<img border=0 id=imgAlbum" . $albumnumber . " class=subImage src=\"" . $tlpluginpath . "/plusbl.gif\"></a><a href=\"javascript:showLevel('Album" . $albumnumber. "','imgAlbum" . $albumnumber . "');\"><b> " . $albumlist->album . "</b></a><br>\n";
					
					if (!$options['albumartistpriority'])
						$thirdquerystr = "SELECT tracknum, title, artist, \"\" as albumartist from " . $wpdb->prefix . "tracks where album = '" . mysql_real_escape_string($albumlist->album) . "' order by tracknum";
					else
					{
						if ($album->source == "artist")
							$thirdquerystr = "SELECT tracknum, title, artist, albumartist from " . $wpdb->prefix . "tracks where album = '" . mysql_real_escape_string($albumlist->album) . "' and artist = '" . $album->artist . "' order by tracknum";
						else
							$thirdquerystr = "SELECT tracknum, title, artist, albumartist from " . $wpdb->prefix . "tracks where album = '" . mysql_real_escape_string($albumlist->album) . "' and albumartist = '" . $album->artist . "' order by tracknum";
					}
						
					$tracklists = $wpdb->get_results($thirdquerystr); 
					
					if ($tracklists) {
					
						$output .= "\t\t\t\t<div class=TrackList id=Album" . $albumnumber . " style='position:relative;left:+15px;display:none'>\n";
						
						foreach ($tracklists as $tracklist){
							if (!$options['albumartistpriority'])
							{
								$output .= "\t\t\t\t" . $tracklist->tracknum . " - " . $tracklist->title . "<br />\n";
							}
							else
							{
								if ($album->source == "artist")
									$output .= "\t\t\t\t" . $tracklist->tracknum . " - " . $tracklist->title . "<br />\n";
								else
									$output .= "\t\t\t\t" . $tracklist->tracknum . " - " . $tracklist->artist  . " - " . $tracklist->title . "<br />\n";
							}
						
						}
						
						$output .= "\t\t\t\t</div>\n";
					
					}
					
					$output .= "\t\t\t</div>\n";

					$albumnumber = $albumnumber + 1;
				
				}
				
				$output .= "\t\t</div>\n\t</div>\n";
			} 
			
			$artistnumber = $artistnumber + 1;
		}
       }
	   
	 $output .= "<!-- Tune Library Output -->";
	   
	return $output;
}

$version = "1.0";

$options  = get_option('TuneLibraryPP',"");

if ($options == "") {
	$options['filename'] = 'iTunes Music Library.xml';
	$options['albumartistpriority'] = false;
	
	update_option('TuneLibraryPP',$options);
} 


// adds the menu item to the admin interface
add_action('admin_menu', array('TL_Admin','add_config_page'));

add_shortcode('tune-library', 'tune_library_func');
?>