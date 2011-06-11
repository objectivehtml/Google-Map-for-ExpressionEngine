<div class="geocoder" style="margin:1em 0">
	<label for="gmap_address">Enter an address, city, state, or a known location</label>
	<input type="text" name="gmap_address" id="gmap_address" value="" style="width:80%;margin-right:1em" />
	
	<button type="button" class="submit" id="gmap_submit">Add to Map</button>	
</div>

<div class="map">
	<label>Or double click to add a point to the map. To move a point, click and hold to drag.</label>
	
	<div style="height:<?=$height?>; margin-bottom:1em">
		
		<div style="height:100%;width:100%" id="gmap_canvas">
		
		</div>
		
	</div>
</div>

<div class="coordinates">
	<label for="gmap_coords">Saved Coordinates</label>
	<input type="text" name="<?=$field_name?>" id="gmap_coords" value="" style="width:100%" />
	
	<br>

	<a href="#" class="gmap-remove-all">Remove All Points</a>
</div>