<p>These settings only affect how the Google Map will display in the backend. These settings have no effect on how the map will be reproduced on the front-end at this time.</p>


<table class="mainTable padTable">
	
	<thead>
		<tr>
			<th style="width:40%">Preference</th>
			<th>Setting</th>
		</tr>
	</thead>
	
	<tbody>
		<tr>
			<td>
				<label for="latitude"><?=lang('latitude')?></label>
			
				<div class="subtext">You can set the default map center by entering a latitude & longitude. </div>		
			</td>
			<td>
				<input type="text" name="latitude" id="latitude" value="<?=$latitude?>" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="longitude"><?=lang('gmap_longitude')?></label>
			
				<div class="subtext">You can set the default map center by entering a latitude & longitude. </div>		
			</td>
			<td>
				<input type="text" name="longitude" id="longitude" value="<?=$longitude?>" />
			</td>
		</tr>
		<tr>
			<td>	
				<label for="center">Center</label>
			
				<div class="subtext">Alternatively, enter an location (address, city, state, zip code, etc) that will be use Google's geocoder to get the coordinate to be used as the center.</div>
			</td>
			<td>
				<input type="text" name="center" id="center" value="<?=$center?>" />
			</td>
		</tr>
		<tr>
			<td>	
				<label for="zoom">Zoom</label>
			
				<div class="subtext">Alternatively, enter an location (address, city, state, zip code, etc) that will be use Google's geocoder to get the coordinate to be used as the center.</div>
			</td>
			<td>
				<select name="zoom">
				<? for($x = 1; $x <= 20; $x++): ?>
					<option value="<?=$x?>" <? if($zoom == $x):?>selected="selected"<? endif; ?>><?=$x?></option>
				<? endfor; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td>	
				<label for="total_points">Total Points</label>
			
				<div class="subtext">You can limit users to a maximum number of points. If you enter zero, the user can enter an unlimited number of points on the map. (0 = Unlimited Markers)</div>
			</td>
			<td>
				<input type="text" name="total_points" id="total_points" value="<?=$total_points?>" />
			</td>
		</tr>
	</tbody>
	
</table>