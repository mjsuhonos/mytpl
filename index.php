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
?>
<!DOCTYPE html>
<html>
	<head>
		<title>My TPL</title>
		<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
		<meta name="apple-mobile-web-app-capable" content="yes" />

		<link rel="apple-touch-icon" href="img/apple-touch-icon.png" />
		<link rel="stylesheet" href="iui/iui.css" type="text/css" />
		<link rel="stylesheet" href="mytpl.css" type="text/css" />
		<link rel="stylesheet" title="Default" href="iui/t/default/default-theme.css"  type="text/css"/>
		<script type="application/x-javascript" src="iui/iui.js"></script>
</head>

<body>
    <div class="toolbar">
        <h1 id="pageTitle"></h1>
        <a id="backButton" class="button" href="#"></a>
    </div>

    <form id="home" class="panel" action="search.php" method="GET" title="My TPL" selected="true"
			style="background-image: none; background-color: #FFFFFF; min-height: 420px; max-width: 320px;">
        <fieldset id="search">
            <div class="row">
                <input style="float: right; padding-left: 0px; width: 98%;" type="text" id="q" name="q"/>
            </div>
        </fieldset>
        <a class="whiteButton" type="submit" href="#" onclick="this.parent.submit()">Search</a>
		<div style="text-align: center; font-style: italic; color: gray;">11,000,000 items at your fingertips</div>
        <div class="spinner"></div>

		<input type="hidden" id="lat" name="lat"/>
		<input type="hidden" id="long" name="long"/>
		<input type="hidden" id="acc" name="acc"/>

		<img src="img/mytpl-alpha.png" style="position:absolute; bottom:0; left: 0; margin-bottom: 10px;"/>
    </form>

	<script>
	function handler(location) {
		document.getElementById('lat').value = location.coords.latitude;
		document.getElementById('long').value = location.coords.longitude;
		document.getElementById('acc').value = location.coords.accuracy;
	}
	function fail(location) {
		alert('Location Services are turned off.\nMy TPL cannot determine your location.');
	}
	function toggle(obj) {
		var el = document.getElementById(obj);
		el.style.display = (el.style.display != 'none' ? 'none' : '' );
	}

	navigator.geolocation.getCurrentPosition(handler, fail);
	</script>

</body>
</html>