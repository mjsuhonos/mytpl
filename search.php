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
	$page_size = 20; // The number of hits to appear on each page of search results
	$connect_string = '';	// z39.50 connection string, eg. host:port/database
	//
	// ******* end configuration settings
	
	// load helper functions
	include_once('functions.php');

	// build search query
	if (!empty($_GET['q'])) $query = '@attr 1=1016 "' . $_GET['q'] . '"';

	// get lat/long if available
	if (!empty($_GET['lat'])) $lat = $_GET['lat'];
	if (!empty($_GET['long'])) $long = $_GET['long'];

	// get/set paging info
	$page = (empty($_GET['page'])) ? '1' : $_GET['page'];
	if (!isset($_GET['page'])) $start_list_at = 1;
	else $start_list_at = ($_GET['page'] - 1) * $page_size + 1;

	// perform a z39.50 search using YAZ
	$id = yaz_connect($connect_string);
	yaz_syntax($id, 'marc21');
	yaz_search($id, 'rpn', $query);
	yaz_wait();
	$hits = yaz_hits($id);

	// if we have no results, show a message and quit
	if ($hits == 0) {
		echo '<ul id="results" title="Results">';
		echo '<li class="result">No Results Found</li>';
		echo '</ul>';
		exit;
	}

	if ($page < 2) echo '<ul id="results" title="Results">';

	// instantiate XML stuff
	$DOM = new DOMDocument;

	// go through result set and retrieve records to process locally
	for ($pos = $start_list_at; $pos <= $start_list_at + $page_size - 1; $pos++) {

		$rec = yaz_record($id, $pos, "xml");
		if (empty($rec)) continue;

		// use SHA1 hash of record as the UID
		$record_id = sha1($rec);

		// NB: this is expensive, should be a DB
		if (!file_exists('records/'.$record_id)) file_put_contents('records/'.$record_id, $rec);

		// load the MARCXML record into DOM, and process into an array of details
		$DOM->loadXML($rec);
		$details = getdetails($DOM);
?>

<li class="result">
	<img src="<?php echo $details['url']; ?>" class="thumb"/>
	<a href="record.php?id=<?php echo $record_id.'&lat='.$lat.'&long='.$long; ?>">
	<?php echo $details['title']; ?>
	<div><?php echo $details['authors']; ?></div>
	<div style="color: gray;"><?php
		echo $details['type'];
		echo ($details['language'] != '') ? ', '.$details['language'] : '';
		echo ($details['year'] != '') ? '; '.$details['year'] : '';
		echo ': '.$details['holdings'].' copies';
		?>
	</div>
	</a>
</li>

<?php
		flush(); // spit out results as they're done
	}

	$remain = $hits - $pos;
	if ($remain > 0) {
		$url = "search.php?q=".$_GET['q']."&page=".($page + 1).'&lat='.$lat.'&long='.$long;
		echo '<li><a target="_replace" href="'.$url.'">'.$remain.' More Results</a></li>';
	}

	if ($page < 2) echo '</ul>';
?>