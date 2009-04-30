<?

if(isset($_GET['parentId'])){
	$incoming = urldecode($_GET['parentId']);
	list($pre, $itemData, $itemData2) =  explode('::', $incoming);
	
	require_once('../../../wp-load.php');
	global $wpdb;
	
	if($pre == 'artist'){
		$querystr = "SELECT distinct album FROM " . $wpdb->prefix . "tracks where artist = '".mysql_real_escape_string(stripslashes($itemData))."' and (artist = albumartist or albumartist is NULL) order by album";
		//print $querystr;
		$tracks = $wpdb->get_results($querystr);
		
		foreach($tracks as $track){
			echo "<li><a href='#'> ".$track->album."</a>
				<ul>
					<li parentId='album::".urlencode($itemData)."::".urlencode($track->album)."'><a href='#'>Loading...</a></li>
				</ul>			
			</li>";
		}
	}
	
	if($pre == 'albumartist'){
		$querystr = "SELECT distinct album FROM " . $wpdb->prefix . "tracks where albumartist = '".mysql_real_escape_string(stripslashes($itemData))."' order by album";
		//print $querystr;
		$tracks = $wpdb->get_results($querystr);
		
		foreach($tracks as $track){
			echo "<li><a href='#'> ".$track->album."</a>
				<ul>
					<li parentId='albumvarious::".urlencode($itemData)."::".urlencode($track->album)."'><a href='#'>Loading...</a></li>
				</ul>			
			</li>";
		}
	}	
	
	if($pre == 'album'){
		$querystr = "SELECT title, tracknum FROM " . $wpdb->prefix . "tracks where artist= '".mysql_real_escape_string(stripslashes($itemData))."' and album = '".mysql_real_escape_string(stripslashes($itemData2))."' order by tracknum";
		//print $querystr;
		$tracks = $wpdb->get_results($querystr);
	
		foreach($tracks as $track){
			if(isset($track->tracknum)){
				echo "<li class='dhtmlgoodies_sheet.gif'><a href='#' disabled></a> ".$track->tracknum." - ".$track->title."</li>";
			}else{
				echo "<li class='dhtmlgoodies_sheet.gif'><a href='#' disabled></a> ".$track->title."</li>";
			}
		}	
		
	}
	
	if($pre == 'albumvarious'){
		$querystr = "SELECT title, tracknum, artist FROM " . $wpdb->prefix . "tracks where albumartist= '".mysql_real_escape_string(stripslashes($itemData))."' and album = '".mysql_real_escape_string(stripslashes($itemData2))."' order by tracknum";
		//print $querystr;
		$tracks = $wpdb->get_results($querystr);
	
		foreach($tracks as $track){
			if(isset($track->tracknum)){
				echo "<li class='dhtmlgoodies_sheet.gif'><a href='#' disabled></a> ".$track->tracknum." - " . $track->artist . " - ".$track->title."</li>";
			}else{
				echo "<li class='dhtmlgoodies_sheet.gif'><a href='#' disabled></a> ".$track->title."</li>";
			}
		}	
		
	}
	
	
	
}

?>