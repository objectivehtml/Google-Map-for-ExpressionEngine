<?php

$plugin_info = array(
	'pi_name'			=> 'Google Map Channel Plugin',
	'pi_version'		=> '2.1.3',
	'pi_author'			=> 'Justin Kimbrell',
	'pi_author_url'		=> 'http://objectivehtml.com/plugins/google-map-channel-plugin',
	'pi_description'	=> 'Creates static and dynamic maps from content channels.',
	'pi_usage'			=> Gmap::usage()
);
				

Class Gmap {
	
	private $args = array(
		
		/* Google Map Parameters */
		'map' => array(
			'backgroundColor', 'disableDefaultUI', 'disableDoubleClickZoom', 'draggable', 
			'draggableCursor', 'heading', 'keyboardShortcuts', 'mapTypeControl', 
			'mapTypeControlOptions', 'mapTypeId', 'maxZoom', 'minZoom', 'noClear', 
			'overviewMapControl', 'overviewMapControlOptions', 'panControl', 'panControlOptions', 			'rotateControl', 'rotateControlOptions', 'scaleControl', 'scaleControlOptions', 
			'scrollwheel', 'streetView', 'streetViewControl', 'streetViewControlOptions', 
			'tilt', 'title', 'zoomControl', 'zoomControlOptions', 'zoom'
		),
		
		/* EE Channel Parameters */
		'channel' => array(
		    'author_id', 'backspace', 'cache', 'refresh', 'cat_limit', 'category', 
		    'category_group', 'isable','channel', 'display_by', 'dynamic', 'dynamic_start',
	   		'entry_id', 'entry_id_from', 'entry_id_to', 'fixed_order', 'group_id', 'limit', 
	   		'month_limit', 'offset', 'orderby', 'paginate', 'paginate_base', 'paginate_type', 			'related_categories_mode', 'relaxed_categories', 'require_entry', 'show_current_week', 
	   		'show_expired', 'show_future_entries', 'show_pages', 'sort', 'start_day', 'start_on', 
	   		'status', 'stop_before', 'sticky', 'track_views', 'uncategorized_entries', 'url_title', 
	   	 	'username', 'week_sort', 'year', 'month', 'day'
	   	 ),
		
		/* Plugin & Convenience Parameters */
		'plugin' => array(
			'center', 'channel', 'hide_markers', 'map_type', 'id', 'class', 'style'
		),
		
		/* Dynamic and Static fields */
		'fields' => array(
			'address_field', 'city_field', 'state_field', 'zipcode_field', 'country_field', 
			'latitude_field', 'longitude_field', 'zoom_field', 'address', 'latitude', 'longitude'	
		)
		
	);
	
	function Gmap()
	{
		$this->EE =& get_instance();
		
		$this->_fetch_params();
				
		if(!$this->args['plugin']['id'])
			show_error('You must assign a unique <code>id</code> to every map. This <code>id</code> should only contain alphabetical and numerical characters with the exception of an underscore.');
		
		
		// Loops through the defined channels and checks for custom fields and 
		$this->EE->load->model(array('channel_model', 'field_model'));
		
		$channel_name = explode('|', $this->args['channel']['channel']);
		
		foreach($channel_name as $name)
		{			
			$channel = $this->EE->channel_model->get_channels(NULL, array('*'), array(array('channel_name' => $name)))->row();
			
			if(isset($channel->field_group))
			{			
				$fields = $this->EE->field_model->get_fields($channel->field_group)->result();
				
				foreach($fields as $field)
				{			
					$field_name = 'search:'.$field->field_name;
					
					if($this->EE->TMPL->fetch_param($field_name))
						$this->args['channel'][$field_name] = $this->EE->TMPL->fetch_param($field_name);
				}
			}
		}
		
		$this->return_data = $this->_init_map();
	}
		
	private function _init_map()
	{
		$map = '
		<div class="'.$this->args['plugin']['class'].'" id="'.$this->args['plugin']['id'].'" style="'.$this->args['plugin']['style'].'"></div>

		<script type="text/javascript">
	
		var '.$this->args['plugin']['id'].' = document.getElementById(\''.$this->args['plugin']['id'].'\');
		var '.$this->args['plugin']['id'].'_bounds = new google.maps.LatLngBounds(); 
		var '.$this->args['plugin']['id'].'_center = new google.maps.LatLng('.$this->args['fields']['latitude'].', '.$this->args['fields']['longitude'].');
		var '.$this->args['plugin']['id'].'_geocoder = new google.maps.Geocoder();
			
		'.$this->_gmap_options($this->args['map']).'
		
		var '.$this->args['plugin']['id'].'_canvas = new google.maps.Map('.$this->args['plugin']['id'].', '.$this->args['plugin']['id'].'_options);';
			
		/* Centers map to a set location */
		if($this->args['plugin']['center'])
			$map .= $this->_geocode($this->args['plugin']['center'], FALSE);
		
		/* Prevents the marks from being added if the hide_markers parameter is set */
		if(!$this->args['plugin']['hide_markers'])
		{
			/* Creates a point from the latitude and longitude parameters */
			if($this->args['fields']['latitude'] && $this->args['fields']['longitude'])
				$map .= $this->_add_marker($this->args['fields']['latitude'], $this->args['fields']['longitude'], $this->args['map']['zoom']);
			
			/* Map multiple static address points */
			if($this->args['fields']['address'])
				foreach($this->args['fields']['address'] as $address)
					$map .= $this->_geocode($address, TRUE, $this->EE->TMPL->fetch_param('zoom'));
					
			/* Adds the markers from latitude and longitude fields */
			if($this->args['plugin']['channel'] && $this->args['fields']['latitude_field'] && $this->args['fields']['longitude_field'])
			{
				$latitude_field  = '{'. $this->args['fields']['latitude_field'] .'}';
				$longitude_field = '{'. $this->args['fields']['longitude_field'].'}';
				
				if($this->args['fields']['zoom_field'])
					$this->args['fields']['zoom_field'] = '{'.$this->args['fields']['zoom_field'].'}';
				
				$function = $this->_add_marker($latitude_field, $longitude_field, $this->args['fields']['zoom_field']);
				
				$map .= $this->_create_ee_tag($function);
			}
			
			/* Adds the marks from address fields */
			if($this->args['plugin']['channel'] && $this->args['fields']['address_field'] || $this->args['plugin']['channel'] && $this->args['fields']['city_field'] || $this->args['plugin']['channel'] && $this->args['fields']['state_field'] || $this->args['plugin']['channel'] && $this->args['fields']['zipcode_field'] || $this->args['plugin']['channel'] && $this->args['fields']['country_field'])
			{
				if($this->args['fields']['address_field'])
					$fields[] = '{'.$this->args['fields']['address_field'].'}';
					
				if($this->args['fields']['city_field'])
					$fields[] = '{'.$this->args['fields']['city_field'].'}';
					
				if($this->args['fields']['state_field'])
					$fields[] = '{'.$this->args['fields']['state_field'].'}';
				
				if($this->args['fields']['zipcode_field'])
					$fields[] = '{'.$this->args['fields']['zipcode_field'].'}';
				
				if($this->args['fields']['country_field'])
					$fields[] = '{'.$this->args['fields']['country_field'].'}';
				
				$address = trim(implode(' ', $fields));
				
				$function = $this->_geocode($address, TRUE, FALSE);
				$map .= $this->_create_ee_tag($function);
			}
		}
		$map .= '
					
		</script>';
		
		return $map;
	}
	
	private function _geocode($address, $marker = TRUE, $manual_zoom = FALSE)
	{
		$location = '
		address = \''.$address.'\';
		address = address.replace(/^\s\s*/, \'\').replace(/\s\s*$/, \'\');
		
		if(address != \'\') {		
		/* Gets a location using Google\'s Geocode */
		'.$this->args['plugin']['id'].'_geocoder.geocode(
			{ 
				\'address\': \''.$address.'\'
			}, function(results, status) {
				if (status == google.maps.GeocoderStatus.OK) { 
					var location = results[0].geometry.location;';
					
					$zoom = $manual_zoom;
					
					if($this->args['fields']['zoom_field'])
						$zoom = '{'.$this->args['fields']['zoom_field'].'}';
					elseif($this->args['map']['zoom'] && $manual_zoom)
						$zoom = $this->args['map']['zoom'];
										
					if($marker)
					{
						$location .= $this->_add_marker(FALSE, FALSE, $zoom);
		        	}
		        	else
		        	{	
						$location .= '
						'.$this->args['plugin']['id'].'_canvas.setZoom('.$this->args['map']['zoom'].');
						'.'
						'.$this->args['plugin']['id'].'_canvas.setCenter(location);';
		        	}
		        	
		        	$location .= '
		     		} else {
		       			alert("Geocode was not successful for the following reason: " + status);
		      		}
			}
		);
		}';
		
		return $location;
	}
	
	private function _add_marker($latitude = FALSE, $longitude = FALSE, $manual_zoom = FALSE)
	{
		$open_if 	= '';
		$close_if	= '';
		$marker 	= '';
		
		$marker .= '
		(function () {  /* Fixes a known bug with Google\'s API with displaying the InfoWindow(s) */
		
		new_location = false;';
		
		if($latitude && $longitude)
			$marker .= '
			new_latitude = \''.$latitude.'\';
			new_longitude = \''.$longitude.'\';
			
			if(new_latitude != \'\' && new_longitude != \'\') {
			
			new_location = new google.maps.LatLng(new_latitude, new_longitude);';
		else
			$marker .= '
			new_location = location';
		
		$marker .= '					       	
    	var marker = new google.maps.Marker({
        	map: '.$this->args['plugin']['id'].'_canvas, 
       	 	position: new_location
    	});';
		
		if($latitude && $longitude)
			$marker .= '
			}';
		
		if(!$this->args['plugin']['center'])
    	{
    		if($latitude && $longitude) {
    			$marker .= '
	    			if(new_location) {';	    		   	
	    	}
	    	   		        		
    		if($manual_zoom)
    		{
    			$marker .= '
    			'.$this->args['plugin']['id'].'_canvas.setZoom('.$manual_zoom.');
    			'.$this->args['plugin']['id'].'_canvas.setCenter(new_location)';   			
    		}
    		else
    		{    		
			   	$marker .= '
	    		'.$this->args['plugin']['id'].'_bounds.extend(new_location);
	    		'.$this->args['plugin']['id'].'_canvas.fitBounds('.$this->args['plugin']['id'].'_bounds);
	    		';
    		}
    			
	    	if($latitude && $longitude)
		    	$marker .= '
	    		}';	
    	}
    	
    	if($this->EE->TMPL->tagdata != '')
		{
			$marker .= '
			if(new_location) {
			
				var html = "'.preg_replace("/[\n\r\t]/","",str_replace("\"", "\\\"", $this->EE->TMPL->tagdata)).'"
			
				var infowindow = new google.maps.InfoWindow({
					content: html
				});
				
				google.maps.event.addListener(marker, \'click\', function() {
					infowindow.open('.$this->args['plugin']['id'].'_canvas, marker);
				});
			}';
		}
			
		$marker .= '})(); /* End bug fix */'; 
		
    	return $open_if . $marker . $close_if;
	}
	
	private function _gmap_options()
	{
		$newline = "\r\t\t\t";
		
		$options = '
		var '.$this->args['plugin']['id'].'_options = {
			center: '.$this->args['plugin']['id'].'_center,
			';
			
			foreach($this->args['map'] as $option => $value)
				if($value) $options .= $option.': '.$value.', '.$newline;
						
			$options = rtrim($options, ', '.$newline);
			
		$options .= $newline.'}';
					
		return $options;
	}
	
	private function _create_ee_tag($content)
	{				
		$exp_open_tag = '{exp:channel:entries ';
		
		foreach($this->args['channel'] as $param => $value)
		{
			if($value) $exp_open_tag .= $param.'="'.$value.'" ';
		}
		
		$exp_open_tag .= '}';
		
		$exp_close_tag = '{/exp:channel:entries}';
		
		return $exp_open_tag . $content . $exp_close_tag;
	}
	
	private function _fetch_params()
	{
		$params = array();
		
		/* Loops through the arguments and initializes the array */
		foreach($this->args as $group => $fields)
		{
			$tmp = $fields;
			
			unset($this->args[$group]);
			
			foreach($tmp as $param)
				$this->args[$group][$param] = $this->EE->TMPL->fetch_param($param);
		}
		
		/* Sets the default values */
		$this->args['fields']['latitude'] = $this->args['fields']['latitude'] ? $this->args['fields']['latitude'] : 0;
		$this->args['fields']['longitude'] = $this->args['fields']['longitude'] ? $this->args['fields']['longitude'] : 0;
		$this->args['map']['zoom'] = $this->args['map']['zoom'] ? $this->args['map']['zoom'] : 15;
		
		/* Breaks the address' down into an array */
		if($this->args['fields']['address'])
			$this->args['fields']['address'] = explode('|', $this->args['fields']['address']);
		
		/* Sets a default mapTypeId if one isn't set, and makes the map_type alias */
		if($this->args['plugin']['map_type'])
			$map_type = $this->args['plugin']['map_type'];
		else
			$map_type = $this->args['map']['mapTypeId'];
		
		$map_type = !$map_type || !isset($map_type) ? 'google.maps.MapTypeId.ROADMAP' : 'google.maps.MapTypeId.'.strtoupper($map_type);
		
		$this->args['map']['mapTypeId'] = $map_type;
		
		return $this->args;
	}
	
	function usage()
	{
	ob_start(); 
	?>
	
	{exp:gmap id="map_canvas" latitude="40" longitude="-86" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" latitude="40" longitude="-86" zoom="8" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" center="Alaska" zoom="5" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" address="Yellow Stone National Park" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" address="Florida|Texas" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" channel="locations" address_field="map_address" city_field="map_city" state_field="map_state" zipcode_field="map_zipcode" country_field="map_country" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" channel="locations" address_field="map_address" city_field="map_city" state_field="map_state" zipcode_field="map_zipcode" country_field="map_country" dynamic="no" limit="3" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" channel="locations" address_field="map_address" city_field="map_city" state_field="map_state" zipcode_field="map_zipcode" country_field="map_country" url_title="some-url-title" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" channel="locations" latitude_field="map_latitude" longitude_field="map_longitude" style="width:400px;height:300px"}{/exp:gmap}
	
	{exp:gmap id="map_canvas" channel="locations" latitude_field="map_latitude" longitude_field="map_longitude" zoom_field="map_zoom" limit="1" style="width:400px;height:300px"}{/exp:gmap}
	
	For a complete break down of the plugin API and working examples visit http://objectivehtml.com/plugins/google-maps-for-expressionengine.
	
	<?php
	$buffer = ob_get_contents();
	
	ob_end_clean(); 

	return $buffer;
	}
}