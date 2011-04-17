<?php
/**
* Google Maps for ExpressionEngine
* Author: Justin Kimbrell (SaucePan Creative)
* Build: 2.0 - April 16, 2011
* Copyright 2011 - All rights reserved
* http://objectivehtml.com, http://inthesaucepan.com
*/

$plugin_info = array(
	'pi_name'			=> 'Google Map Channel Plugin',
	'pi_version'		=> '2.0',
	'pi_author'			=> 'Justin Kimbrell',
	'pi_author_url'		=> 'http://objectivehtml.com/plugins/google-map-channel-plugin',
	'pi_description'	=> 'Creates static and dynamic maps from content channels.',
	'pi_usage'			=> Gmap::usage()
);
					

Class Gmap {

	/* Channel Parameters */
	
	var $params = array(
	    'author_id', 'backspace', 'cache', 'refresh', 'cat_limit', 'category', 
	    'category_group', 'isable','channel', 'display_by', 'dynamic', 'dynamic_start',
   		'entry_id', 'entry_id_from', 'entry_id_to', 'fixed_order', 'group_id', 'limit', 
   		'month_limit', 'offset', 'orderby', 'paginate', 'paginate_base', 'paginate_type', 		'related_categories_mode', 'relaxed_categories', 'require_entry', 'show_current_week', 
   		'show_expired', 'show_future_entries', 'show_pages', 'sort', 'start_day', 'start_on', 
   		'status', 'stop_before', 'sticky', 'track_views', 'uncategorized_entries', 'url_title', 
   	 	'username', 'week_sort', 'year', 'month', 'day'
   	 );
   	 
   	 var $options = array('backgroundColor', 'disableDefaultUI', 'disableDoubleClickZoom', 'draggable', 'draggableCursor', 'heading', 'keyboardShortcuts', 'mapTypeControl', 'mapTypeControlOptions', 'mapTypeId', 'maxZoom', 'minZoom', 'noClear', 'overviewMapControl', 'overviewMapControlOptions', 'panControl', 'panControlOptions', 'rotateControl', 'rotateControlOptions', 'scaleControl', 'scaleControlOptions', 'scrollwheel', 'streetView', 'streetViewControl', 'streetViewControlOptions', 'tilt', 'title', 'zoomControl', 'zoomControlOptions', 'zoom');
   	 
   	 var $map = array('center', 'address_field', 'city_field', 'state_field', 'zipcode_field', 
   	 'country_field', 'channel', 'address', 'latitude', 'longitude', 'map_type', 'id', 'class', 'style');
	
	
	/* Map parameters */
	
	var $address;
	
	private function _fetch_params()
	{
		foreach($this->map as $field)
			$this->map[$field] = $this->EE->TMPL->fetch_param($field);
		
		foreach($this->options as $field)
			$options[$field] = $this->EE->TMPL->fetch_param($field);
		
		$this->options = $options;
		
		$address = $this->map['address_field'] ? '{'.$this->map['address_field'].'} ' : '';
		$address .= $this->map['city_field'] ? '{'.$this->map['city_field'].'} ' : '';
		$address .= $this->map['state_field'] ? '{'.$this->map['state_field'].'} ' : '';
		$address .= $this->map['zipcode_field'] ? '{'.$this->map['zipcode_field'].'} ' : '';
		$address .= $this->map['country_field'] ? '{'.$this->map['country_field'].'} ' : '';
		
		$this->address = $address;
					
		/* Sets the default if any param is not set */
			
		//$this->map['zoom'] = !$this->map['zoom'] ? 12 : $this->map['zoom'];
		$this->map['latitude'] = !$this->map['latitude'] ? 0 : $this->map['latitude'];
		$this->map['longitude'] = !$this->map['longitude'] ? 0 : $this->map['longitude'];
		$this->map['id'] = !$this->map['id'] ? FALSE : $this->map['id'];
		$this->map['class'] = !$this->map['class'] ? '' : $this->map['class'];
		$this->map['style'] = !$this->map['style'] ? '' : $this->map['style'];
		
		if($this->map['map_type'])
			$map_type = $this->map['map_type'];
		else
			$map_type = $this->options['mapTypeId'];
		
		$map_type = !$map_type || !isset($map_type) ? 'google.maps.MapTypeId.ROADMAP' : 'google.maps.MapTypeId.'.strtoupper($map_type);
		
		$this->options['mapTypeId'] = $map_type;
		
		return $this->map;
	}
	
	private function _init_gmap()
	{
	
		$string = '
		<div id="'.$this->map['id'].'" class="'.$this->map['class'].'" style="'.$this->map['style'].'"></div>
		
		<script type="text/javascript">
		
			var geocoder = new google.maps.Geocoder();';
			
			if($this->map['address'])
			{
				$this->address = $this->map['address'];
				$exp_open_tag = '';
				$exp_close_tag = '';
			}
			else
			{
				$address = $this->map['address_field'] ? '{if '.$this->map['address_field'].'}{'.$this->map['address_field'].'}{/if}' : '';
				$address .= $this->map['city_field'] ? '{if '.$this->map['city_field'].'}{'.$this->map['city_field'].'},{/if}' : '';
				$address .= $this->map['state_field'] ? '{if '.$this->map['state_field'].'}{'.$this->map['state_field'].'}{/if}' : '';
				$address .= $this->map['zipcode_field'] ? '{if '.$this->map['zipcode_field'].'}{'.$this->map['zipcode_field'].'}{/if}' : '';
				$address .= $this->map['country_field'] ? '{if '.$this->map['country_field'].'}{'.$this->map['country_field'].'}{/if}' : '';
				
				
				$exp_open_tag = '{exp:channel:entries ';
				
				foreach($this->params as $param)
					if($this->EE->TMPL->fetch_param($param))
						$exp_open_tag .= $param.'="'.$this->EE->TMPL->fetch_param($param).'" ';
			
				$exp_open_tag .= '}';
						
				$exp_close_tag = '{/exp:channel:entries}';
			}
			
			if($this->map['center'])
			{
				$string .= '
				geocoder.geocode({ address: "'.$this->map['center'].'"}, function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) {
						var location = results[0].geometry.location;
						';
						
						if(!$this->options['zoom'])
							$string .='
							'.$this->map['id'].'.setZoom(10);
							';
							
						$string .= '
						'.$this->map['id'].'.setCenter(location);
					}
				});
				';
			}			
				$string .= '
				var latlng = new google.maps.LatLng('.$this->map['latitude'].', '.$this->map['longitude'].');
				
				var myOptions = {
					center: latlng,
					';
					$tmp_string = '';
					
					foreach($this->options as $index => $value)
					{
						if($value)
						{
							$tmp_string .= $index .': '.$value.',';
						}
					};
					
					$tmp_string = substr($tmp_string, 0, strlen($tmp_string) - 1);
					
					$string .= $tmp_string.'
				}
				
				'.$this->map['id'].' = new google.maps.Map(document.getElementById("'.$this->map['id'].'"), myOptions);
				
				var '.$this->map['id'].'_bounds = new google.maps.LatLngBounds(); 
				';
				
				$string .= $exp_open_tag.'
					var address = "'.trim($this->address).'";
					
					geocoder.geocode({ address: address}, function(results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							var location = results[0].geometry.location;
							';
							
							if(!$this->map['center'] && !$this->options['zoom'])
							{
								$string .= '
								'.$this->map['id'].'_bounds.extend(location);
								'.$this->map['id'].'.fitBounds('.$this->map['id'].'_bounds);
								';
							}
								
							if($this->options['zoom'] && !$this->map['center']) {
								
									$string .= '
									'.$this->map['id'].'.setCenter(location);
									';
							}
							
							$string .= '								
							var marker = new google.maps.Marker({
								map: '.$this->map['id'].',
								position: location,
								title: "'.$this->address.'"
							});
							';
							
							if($this->EE->TMPL->tagdata != '')
							{
								$string .= '
								var html = "'.preg_replace("/[\n\r\t]/","",$this->EE->TMPL->tagdata).'";
								';
										
								$string .='
								var infowindow = new google.maps.InfoWindow({
									content: html
								});
								
								google.maps.event.addListener(marker, \'click\', function() {
									infowindow.close();
									infowindow.open('.$this->map['id'].', marker);
								});';
							}
							
						$string .= '
						}
					});
				'.$exp_close_tag.'
		</script>';
		
		return $string;

	}
	
	function Gmap()
	{
		$this->EE =& get_instance();
		
		$params = $this->_fetch_params();
		
		if(!$this->map['address'] && !$this->map['channel'])
			show_error('You must define an address or a channel.');
		
		if(!$this->map['id'])
			show_error('You must assign a unique <code>id</code> to every map. This <code>id</code> sould only contain alphabetical and numerical characters with the exception of an underscore.');
			
		$this->return_data = $this->_init_gmap();
	}
		 
	function usage()
	{
	ob_start(); 
	?>
	
	For a complete tutorial, go to http://objectivehtml.com/examples/gmap
	
	And the plugin reference, http://objectivehtml.com/plugins/google-maps-for-expressionengine


	<?php
	$buffer = ob_get_contents();
	
	ob_end_clean(); 

	return $buffer;
	}
} 