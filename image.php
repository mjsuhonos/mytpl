<?php
/*
MyTPL - Location-aware Mobile Search using PHP-YAZ and IUI
Copyright (C) 2010  MJ Suhonos  <mj@robotninja.com>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

	error_reporting(E_ERROR);

	// ******* some configuration settings here
	//
	$scrobbler_key = ''; //audioscrobbler API key; for cover art and music summaries
	//
	// ******* end configuration settings

	// retrieve an image given an ISBN and size
	ini_set('default_socket_timeout', 30);	// try REALLY hard

	// default to thumbnail unless medium is specified
	if (empty($_GET['size'])) $size = 's';
	else $size = $_GET['size'];

	$type = strtolower($_GET['type']);

	if (!empty($_GET['isbns'])) $isbns = explode(',', $_GET['isbns']);
	$artist = stripslashes($_GET['artist']);
	$album = stripslashes($_GET['album']);
	$title = stripslashes($_GET['title']);

	// TODO: clean this logic up!  It's a mess...
	// Perhaps a plugin/interface pattern would be a better implementation
	if (!empty($title) && $type == 'video') {

		// see if we have a cached copy
		if (file_exists('covers/video-'.$title.'-'.$year.'-'.$size)) {
			readfile('covers/video-'.$title.'-'.$year.'-'.$size);
			exit;
		}

		$url = 'http://www.movieposterdb.com/embed.inc.php?movie_title='.urlencode($title);
		if (!empty($year)) $url .= '['.$year.']';

		// extract image URL from JSON response
		$json = file_get_contents($url);
		if (!$json) { readfile('img/no-image-dvd.png'); exit; }
		$image_url = preg_replace('/.*(http:.*jpg).*/', '$1', $json);

		// have to use CURL for this one because the API is under development
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $image_url);
		$image = curl_exec( $ch );

		if (strlen($image) > 900) {
			file_put_contents('covers/dvd-'.$title.'-'.$year.'-'.$size, $image);
			echo $image;
			exit;
		}

	} elseif (!empty($artist) && !empty($album)) {

		// see if we have a cached copy
		if (file_exists('covers/cd-'.$artist.'-'.$album.'-'.$size)) {
			readfile('covers/cd-'.$artist.'-'.$album.'-'.$size);
			exit;
		}

		// do some munging to make the query more friendly
		$album = preg_replace('/(original.*)?soundtrack.*$/i', '', $album);

		$url = 'http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key='.$scrobbler_key.'&artist='.urlencode($artist).'&album='.urlencode($album);

		$xml = file_get_contents($url);
		if (!$xml) { readfile('img/no-image-cd.png'); exit; }

		// load result into DOM
		$DOM = new DOMDocument;
		$DOM->loadXML($xml);
		$xpath = new DOMXPath($DOM);

		$summary = $xpath->evaluate("string(//summary)");
		if ($summary != '') file_put_contents('records/cdxml-'.$artist.'-'.$album, $xml);

		if ('s' == $size) $image_url = $xpath->evaluate("string(//image[@size = 'medium' or @size = ''])");
		else $image_url = $xpath->evaluate("string(//image[@size = 'large' or @size = ''])");
		$image = file_get_contents($image_url);
		if (strlen($image) > 900) {
			file_put_contents('covers/cd-'.$artist.'-'.$album.'-'.$size, $image);
			echo $image;
			exit;
		}

	} else do {
		
		// assume book; not so bad thanks to ISBN
		if (empty($isbns)) { readfile('img/no-image-'.$type.'.png'); exit; }

		// see if we have a cached copy
		if (file_exists('covers/'.current($isbns).'-'.$size)) {
			readfile('covers/'.current($isbns).'-'.$size);
			exit;
		}

		// clear url/image for next lookup
		$url = ''; $image = '';

		if ('s' == $size) $url = 'http://covers.openlibrary.org/b/isbn/'.current($isbns).'-S.jpg';
		else $url = 'http://covers.openlibrary.org/b/isbn/'.current($isbns).'-M.jpg';
		$image = file_get_contents($url);
		if (strlen($image) > 900) {
			file_put_contents('covers/'.current($isbns).'-'.$size, $image);
			echo $image;
			exit;
		}

		/*
		*	DEFINITELY DO NOT UNCOMMENT THE SECTION BELOW UNLESS YOU WANT TO GET
		*	HIGH-QUALITY COVER ART FROM WONDERFUL COMPANIES THAT LOVE LIBRARIES
		*	(That would be bad.  Right?)
		*/
		/*
		if ('s' == $size) $url = 'http://covers.librarything.com/devkey/KEY/small/isbn/'.current($isbns);
		else $url = 'http://covers.librarything.com/devkey/KEY/medium/isbn/'.current($isbns);
		$image = file_get_contents($url);
		if (strlen($image) > 900) {
			file_put_contents('covers/'.current($isbns).'-'.$size, $image);
			echo $image;
			exit;
		}

		if ('s' == $size) $url = 'http://images.amazon.com/images/P/'.current($isbns).'.01.THUMBZZZ.jpg';
		else $url = 'http://images.amazon.com/images/P/'.current($isbns).'.01.MZZZZZZZ.jpg';
		$image = file_get_contents($url);
		if (strlen($image) > 900) {
			file_put_contents('covers/'.current($isbns).'-'.$size, $image);
			echo $image;
			exit;
		}

		if ('s' == $size) $url = 'http://syndetics.com/index.aspx?isbn='.current($isbns).'/SC.gif';
		else $url = 'http://syndetics.com/index.aspx?isbn='.current($isbns).'/MC.gif';
		$image = file_get_contents($url);
		if (strlen($image) > 900) {
			file_put_contents('covers/'.current($isbns).'-'.$size, $image);
			echo $image;
			exit;
		}
		*/
	} while (next($isbns));

	readfile('img/no-image-'.$type.'.png');
?>