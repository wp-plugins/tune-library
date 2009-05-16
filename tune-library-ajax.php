<?php
	require_once('../../../wp-load.php');
	global $wpdb;
	
	$options  = get_option('TuneLibraryPP');
	
	$artistletter = $_GET['letter'];
	
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
					$querystr ="(SELECT distinct artist, 'artist' as source FROM " . $wpdb->prefix . "tracks where artist != '' and (albumartist is NULL or artist = albumartist) and (substring(artist, 1, 1) < 'A' or substring(artist, 1, 1) > 'Z') UNION (SELECT distinct albumartist as artist, 'albumartist' as source FROM " . $wpdb->prefix . "tracks where albumartist is not NULL and artist != albumartist and (substring(albumartist, 1, 1) >= 'A' and substring(albumartist, 1, 1) <= 'Z')) order by artist";			
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
		
?>
