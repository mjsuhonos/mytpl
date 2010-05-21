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

	// load ISO language codes (globally available)
	include_once('iso.php');

	// re-useable functions here

	function dist($lat_from, $long_from, $lat_to, $long_to) {
		$unit = 6371;	// km
	 	$degreeRadius = deg2rad(1);

		/*** convert longitude and latitude to radians ***/
		$lat_from  *= $degreeRadius;
		$long_from *= $degreeRadius;
		$lat_to    *= $degreeRadius;
		$long_to   *= $degreeRadius;

		/*** apply the Great Circle Distance Formula ***/
		$dist = sin($lat_from) * sin($lat_to) + cos($lat_from)
		* cos($lat_to) * cos($long_from - $long_to);

		/*** radius of earth * arc cosine ***/
		return ($unit * acos($dist));
	}

	// take a MARCXML DOM document and return an array of detail info (via MODS)
	// @full (bool): whether to return full information (holdings, unabbreviated, etc)
	function getdetails($DOM, $full = false) {

		// get a holdings from MARC data
		$xpath = new DOMXPath($DOM);
		$xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

		if ($full) {
			// this particular response is TPL-specific (MARC 926), but can be modified for other ILS
			$holdings_n = $xpath->query("marc:datafield[@tag='926']");

			// load branch names and locations
			include_once('codes.php');

			// get distance of each branch with holdings
			$holdings = array();
			foreach ($holdings_n as $holding) {
				$branch = strtolower($xpath->evaluate("string(marc:subfield[@code='a'])", $holding));
				$status = $xpath->evaluate("string(marc:subfield[@code='b'])", $holding);

				// if lat/long are populated, calculate distance for branch
				if (empty($holdings[$branch]['dist']) && !empty($_GET['lat']) && !empty($_GET['long'])
					&& in_array($branch, array_keys($codes))) {  // NB: otherwise it doesn't exist (?)
					$dist = dist($_GET['lat'], $_GET['long'], $codes[$branch]['lat'], $codes[$branch]['lon']);
					$holdings[$branch]['dist'] = $dist;

					// for sending directions to google maps
					$holdings[$branch]['lat'] = $codes[$branch]['lat'];
					$holdings[$branch]['long'] = $codes[$branch]['lon'];
				} else {
					$holdings[$branch]['loc'] = $codes[$branch]['loc'];
				}

				$holdings[$branch]['name'] = $codes[$branch]['name'];
				$holdings[$branch]['status'][] = $status;
			}
			// sort by closest branch
			array_multisort($holdings);
			unset($codes);	// free a little memory

		} else {
			$holdings = $xpath->evaluate("count(marc:datafield[@tag='926'])");
		}

		// transform MARCXML to MODS
		$xsl = new DOMDocument;
		$proc = new XSLTProcessor;
		$xsl->load('marc2mods.xsl');
		$proc->importStyleSheet($xsl);
		$mods = $proc->transformToDoc($DOM);

		// set up new xpath
		$xpath = new DOMXPath($mods);
		$xpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');

		// get title; TODO: add subtitle / multilingual handling
		$title_n = $xpath->query("mods:titleInfo[not(@type)]/*");
		$title = array();
		foreach ($title_n as $part) $title[] = ucfirst($part->nodeValue);
		$title = preg_replace('/\s+/', ' ', rtrim(implode(' ', $title), ' .-'));

		// trim title for brief view
		if (!$full && strlen($title) > 75) $title = substr($title, 0, strpos($title, ' ', 65)).'...';

		// get authors
		$authors_n = $xpath->query("mods:name/mods:namePart[count(@*)=0]");
		$authors = array();

		// limit to 4 for brief view
		if (!$full) $max = 4;
		else $max = $authors_n->length;
		for ($i = 0; $i < $max; $i++) {
			if ($authors_n->item($i)) {
				$author = trim($authors_n->item($i)->nodeValue, '. ');

				// remove stuff like "(musical group)"
				$author = preg_replace(array('/\s[\d-]/', '/\s*\([^\(^\)]+\)\s*$/'), array('', ''), $author);

				// rewrite names to firstname surname
				$test = preg_match('/([^,]+), ([^\(]+)(\(([^\)]+)\))?/', $author, $matches);
				if (!empty($matches[4])) $author = $matches[4].' '.$matches[1];
				elseif (!empty($matches)) $author = $matches[2].' '.$matches[1];

				$authors[] = $author;
			}
		}
		// keep first author separate for cover art lookups
		$firstauthor = $authors[0];
		$authors = array_unique($authors);
		$authors = implode('; ', $authors);

		// trim authors for brief view
		if (!$full && $authors_n->length > 4) $authors .= ' (more)';

		// get language (not @code-specific)
		global $iso;

		$language = $xpath->evaluate("string(mods:language)");
		if (!empty($language)) if (in_array($language, array_keys($iso))) $language = $iso[$language];

		// get year of publication
		$year = $xpath->evaluate("string(mods:originInfo/mods:dateIssued[@encoding = 'marc'])");
		$year = preg_replace('/[^\d]/', '', $year);
		if (empty($year)) $year = $xpath->evaluate("string(mods:originInfo/mods:dateIssued[count(@encoding)=0])");
		$year = preg_replace('/[^\d]/', '', $year);

		// get isbns for books
		$isbn_n = $xpath->query("mods:identifier[@type='isbn']");
		$isbns = array();
		foreach ($isbn_n as $isbn) $isbns[] = preg_replace('/[^\d^X]/', '', $isbn->nodeValue);
		$isbns = array_unique($isbns);

		// get abstract if available
		$abstract = $xpath->evaluate("string(//mods:abstract)");

		// get edition if available
		$edition = $xpath->evaluate("string(//mods:edition)");
		$edition = trim($edition, ' /');

		// determine type of resource
		$type = $xpath->evaluate("string(mods:typeOfResource)");
		switch($type) {
			case 'text':
			case 'notated music':
				$type = 'Text';
				break;
			case 'sound recording-musical':
			case 'sound recording-nonmusical':
				$type = 'Audio';	// could be, eg. audiocassette; check mods:physicalDescription
				break;
			case 'moving image':
			case 'videorecording':
				$type = 'Video';	// could be, eg. videocassette; check mods:physicalDescription
				break;
			case 'software, multimedia':
			$type = 'Interactive';	// could be, eg. DVD-ROM, etc.
			break;
		}

		// link to cover art
		$url = 'image.php?type='.$type;
		if ($full) $url .= '&size=m';
		
		if ($type == 'Audio' && count($isbns) == 0) $url .= '&artist='.urlencode($firstauthor).'&album='.urlencode($title);
		elseif ($type == 'Video') $url .= '&title='.urlencode($title).'&year='.$year;
		else $url .= '&isbns='.implode(',', $isbns);

		// assemble array
		$details['title'] = $title;
		$details['authors'] = $authors;
		$details['firstauthor'] = $firstauthor;
		$details['edition'] = $edition;
		$details['type'] = $type;
		$details['language'] = $language;
		$details['year'] = $year;
		$details['abstract'] = $abstract;
		$details['url'] = $url;
		$details['isbns'] = $isbns;
		$details['holdings'] = $holdings;	// (mixed)

		$mods->formatOutput = true;
		$details['mods'] = $mods->saveXML();	// FOR TROUBLESHOOTING

		return $details;
	}
?>