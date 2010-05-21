<?php
	error_reporting(E_ERROR);

	// load helper functions
	include_once('functions.php');

	// load the cached record
	$rec = file_get_contents('records/'.$_GET['id']);

	// load the MARCXML record into DOM, and process into an array of details
	$DOM = new DOMDocument;
	$DOM->loadXML($rec);
	$details = getdetails($DOM, true);

	// get summary info for CD if cached
	if (file_exists('records/cdxml-'.$details['firstauthor'].'-'.$details['title'])) {
		$xml = file_get_contents('records/cdxml-'.$details['firstauthor'].'-'.$details['title']);

		$DOM->loadXML($xml);
		$xpath = new DOMXPath($DOM);
		$summary = $xpath->evaluate("string(//summary)");
		$summary = htmlspecialchars_decode(preg_replace('/<[^>]+>/', '', $summary));

		if (empty($details['abstract'])) $details['abstract'] = $summary;
	}
	
?>
<div id="<?php echo $_GET['id']; ?>" title="<?php echo $details['title']; ?>" class="panel">
	<fieldset class="detail">
		<img src="<?php echo $details['url']; ?>" class="detail"/>
		<p class="title"><?php echo $details['title']; ?></p>
		<?php if ($details['edition'] != '') { ?>
		<p class="edition"><?php echo $details['edition']; ?></p>
		<?php } ?>
		<p class="authors"><?php echo $details['authors']; ?></p>
		<p class="type"><?php
			echo $details['type'];
			echo ($details['language'] != '') ? ', '.$details['language'] : '';
			echo ($details['year'] != '') ? '; '.$details['year'] : '';
			echo ': <span style="white-space: nowrap;">'.count($details['holdings']).' copies</span>';
			?>
		</p>
		<div style="clear: left;">
		<?php if ($details['abstract'] != '') { ?>
			<p class="abstract"><?php echo $details['abstract']; ?></p>
		<?php } ?>
		<a href="#_<?php echo $_GET['id']; ?>" onClick="toggle('mods_<?php echo $_GET['id']; ?>');">MODSXML</a>

	<?php
	// TEMPORARY
	$mods->formatOutput = true; printf ("<pre id='mods_%s' style='width: 280px; background-color: ghostwhite; border: 1px solid #EEEEEE; font-size: 0.75em; font-weight: bold; display: none;'>%s</pre>", $_GET['id'], htmlentities($details['mods']));
	?>
		</div>
	</fieldset>

    <ul id="holdings" title="holdings" selected="true" style="list-style-type: none;">
	<?php
	foreach ($details['holdings'] as $branch) {
		if ($branch['dist'] > 0) $dist = ': '.round($branch['dist'], 1).' km';
		$branch['status'] = array_unique($branch['status']);
		$directions = 'http://maps.google.com/maps?daddr='.$branch['lat'].','.$branch['long'].'&saddr='.$_GET['lat'].','.$_GET['long'];
		?>
		
		<li class="branch" style="text-align: left;">
			<p class="branch"><?php echo $branch['name']; echo $dist;?>
				<?php if ($branch['dist'] > 0) { ?>
				<span style="float: right; vertical-align: top;"><a target="_webapp" href="<?php echo $directions; ?>"><img src="/img/maps.gif" class="status"/></a></span>
				<?php } else { ?>
				<span style="float: right; vertical-align: top;"><?php echo $branch['loc'] ?></span>
				<?php } ?>
				<br/><span style="color: gray;"><?php echo implode('/',$branch['status']); ?></span>
			</p>
		</li>
	<?php
	}
	?>
	</ul>

</div>