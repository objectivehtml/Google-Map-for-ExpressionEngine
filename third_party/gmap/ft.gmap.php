<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Fieldtype - Google Maps for ExpressionEngine
 *
 * @package			Google Maps for ExpressionEngine
 * @version			2.2.1
 * @author			Justin Kimbrell <http://objectivehtml.com>
 * @copyright 		Copyright (c) 2011 Justin Kimbrell <http://objectivehtml.com>
 * @license 		Creative Commons Attribution 3.0 Unported License -
 					please see LICENSE file included with this distribution
 * @link			http://objectivehtml.com/documentation/google-maps-for-expressionengine
 */

class Gmap_ft extends EE_Fieldtype {

	var $info = array(
		'name'		=> 'Google Maps for ExpressionEngine',
		'version'	=> '2.2'
	);
	
	// --------------------------------------------------------------------
	
	
	function install()
	{
		return array(
			'gmap_map_height'		=> '500px',
			'gmap_latitude'			=> '0',
			'gmap_longitude'		=> '0',
			'gmap_zoom'				=> 0,
			'gmap_total_points'		=> 0,
			'gmap_latitude_field'	=> NULL,
			'gmap_longitude_field'	=> NULL
		);
	}
	
	function display_field($coords)
	{	
		$this->EE->load->config('gmap');
		
		$field_id = $this->settings['field_id'];
		$field = $this->EE->db->get_where('channel_fields', array('field_id' => $field_id))->row_array();
		
		$this->settings = unserialize(base64_decode($field['field_settings']));
		
		$coord_string = $coords;
		$coords = explode('|', str_replace(')', '', str_replace('(', '', str_replace(')(', '|', $coords))));
		
		foreach($coords as $index => $value)
		{
			$coord = explode(', ', $value);
			
			if(count($coord) > 1)
			{
				$coords[$index] = array(
					'lat' 	=> $coord[0],
					'lng' 	=> $coord[1]
				);
			}			
		}
		
		$options = array(
			'gmap_map_height'	   => $this->settings['gmap_map_height'],
			'gmap_latitude'		   => $this->settings['gmap_latitude'],
			'gmap_longitude'	   => $this->settings['gmap_longitude'],
			'gmap_zoom'			   => (int) $this->settings['gmap_zoom'],
			'gmap_total_points'    => (int) $this->settings['gmap_total_points'],
			'gmap_latitude_field'  => $this->settings['gmap_latitude_field'],
			'gmap_longitude_field' => $this->settings['gmap_longitude_field']
		);
			
		if($options['gmap_latitude_field'] || $options['gmap_longitude_field'])
		{
			$this->EE->load->model('field_model');
			
			$lat_field = $this->EE->field_model->get_fields('', array(
				'field_name' => $options['gmap_latitude_field']
			))->row_array();
			
			$lng_field = $this->EE->field_model->get_fields('', array(
				'field_name' => $options['gmap_longitude_field']
			))->row_array();
		}
		
		
		$this->EE->cp->add_to_head('<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>');
		
		//$this->EE->javascript->set_global('gmap.'.$this->field_name.'.settings', $options);
		
		
		$script = '';
		foreach($options as $option => $value)
			$script .= $option.': \''.$value.'\', ';
		
		$script = '{'.rtrim(trim($script), ',').'}';
		
		$center = '';
		$populate_lat_lng = '';
		$populate_lat_lng_fn = '';
		
		if(isset($lat_field) && count($lat_field) > 0 && isset($lng_field) && count($lng_field) > 0)
		{
			$populate_lat_lng = '
			fieldOpts.gmap_total_points = 1;
			populate_lat_lng(location.lat(), location.lng());
			';
			
			$populate_lat_lng_fn = '
			function populate_lat_lng(lat, lng) {
				$("#field_id_'.$lat_field['field_id'].', input[name=\''.$lat_field['field_name'].'\']").val(lat);
				$("#field_id_'.$lng_field['field_id'].', input[name=\''.$lng_field['field_name'].'\']").val(lng);
			}';
		}
		
		$this->EE->javascript->output('
			var fieldOpts 		= '.$script.';			
			
			var location 		= new google.maps.LatLng(fieldOpts.gmap_latitude, fieldOpts.gmap_longitude);
	 		var gmap_geocoder	= new google.maps.Geocoder();
	 		
			var myOptions = {
				zoom: parseInt(fieldOpts.gmap_zoom),
				center: location,
				mapTypeId: google.maps.MapTypeId.ROADMAP,
				disableDoubleClickZoom: true
			}
						
			var gmap_canvas = $("#gmap_canvas").get(0);
			var gmap_coords = $("#gmap_coords");
			var gmap_button = document.getElementById("#gmap_submit");
					
			var gmap = new google.maps.Map(gmap_canvas, myOptions);
			var gmap_bounds = new google.maps.LatLngBounds(); 
			var gmap_markers = [];
			var gmap_marker_count = 0;
			
			'.$populate_lat_lng_fn.'
			
			$("#hold_field_'.$field_id.' .hide_field span").click(function() {
				google.maps.event.trigger(gmap, "resize");
				gmap.setCenter(location)
			});
					
			function gmap_remove_all_markers() {
				$(gmap_markers).each(function(i) {
					remove_marker(i);
				});
				
				gmap_coords.val("");
			}
			
			function remove_marker(id, title)
			{
				if(title) gmap_coords.val(gmap_coords.val().replace(title, ""));
				
				gmap_markers[id].setMap(null);
				
				if(gmap_marker_count > 0)
					gmap_marker_count--;
				else
					gmap_marker_count = 0;			
			}
			
			function gmap_add_marker(location, cancelCenter) {
				var element = gmap_marker_count;
				
				if(fieldOpts.gmap_total_points == 0 || fieldOpts.gmap_total_points > gmap_marker_count) {						
					'.$populate_lat_lng.'
					
					var marker = new google.maps.Marker({
			    		map: gmap,
			    		position: location,
			    		draggable: true,
			    		raiseOnDrag: true
					});
					
					gmap_markers[gmap_marker_count] = marker;
					
					if(!cancelCenter) {
						gmap_bounds.extend(location);
				    	gmap.fitBounds(gmap_bounds);
				    }
				    
					var html = "<div style=\"width:275px;height:100px:\">" + location + "<br><br>" + "<a href=\"#\" id=\""+element+"\" title=\""+location+"\" class=\"gmap-remove\">Remove from Map</a></div>";
							
					var infowindow = new google.maps.InfoWindow({
						content: html
					});
									
					google.maps.event.addListener(marker, \'dragstart\', function(event) {
						gmap_coords.val(gmap_coords.val().replace(String(event.latLng), \'\'));
					});
					
					google.maps.event.addListener(marker, \'dragend\', function(event) {
						var new_location = event.latLng;
						
						infowindow.setContent("<div style=\"width:275px;height:100px:\">" + new_location + "<br><br>" + "<a href=\"#\" id=\""+element+"\" title=\""+new_location+"\" class=\"gmap-remove\">Remove from Map</a></div>");
						
						gmap_coords.val(new_location + gmap_coords.val());
					});
									
					google.maps.event.addListener(marker, \'click\', function(event) {
						infowindow.open(gmap, marker);					
					});
					
					gmap_coords.val(location + gmap_coords.val());
					
					gmap_marker_count++;
				} else {
					alert("You can only add "+fieldOpts.gmap_total_points+" point(s) on the map.");
				}
			}
			
			google.maps.event.addListener(gmap, \'dblclick\', function(event) {
		    	gmap_add_marker(event.latLng, true);
				
			});
						
			$(".gmap-remove").live("click", function() {
				var $t 		= $(this);
				var id 		= String($t.attr("id"));
				var title 	= $t.attr("title");
				
				remove_marker(id, title);
					
				return false;
			});
			
			$(".gmap-remove-all").click(function() {
				gmap_remove_all_markers();
				
				return false;
			});
					
			$("#gmap_address").keypress(function(event) {
				if(event.keyCode == 13)	{	
					$("#gmap_submit").click();
					return false;
				}
			});
									
			$("#gmap_submit").click(function() {
				(function () {  /* Fixes a known bug with Google\'s API with displaying the InfoWindow(s) */
					var address = $("#gmap_address").val();
					
					gmap_geocoder.geocode( { \'address\': address}, function(results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							
							var coord = results[0].geometry.location;
							
							gmap_add_marker(coord);
							
							 $("#gmap_address").val("").focus();
						} else {
							alert("Geocode was not successful for the following reason: " + status);
						}
					});
				})();
			});
			
			gmap_coords.blur(function() {
				coord_string = $(this).val();
				coords = coord_string.split(\')(\');
				
				gmap_remove_all_markers();
					
				if(coord_string != \'\') {					
					for(i = 0; i < coords.length; i++) {
						var coord = coords[i].split(\', \');
						
						if(coord.length >= 2) {							
							var new_latitude 	= coord[0].replace(\'(\', \'\').replace(\')\', \'\');
							var new_longitude 	= coord[1].replace(\')\', \'\').replace(\'(\', \'\');
							
							var location = new google.maps.LatLng(new_latitude, new_longitude);
							
							gmap_add_marker(location);
						} else {
							alert("There is an error in the coordinate formatting: "+coord);
						}
					}
				}
				
			});
		');
		
		foreach($coords as $coord)
		{
			if(is_array($coord))
			{
				$this->EE->javascript->output('
					location = new google.maps.LatLng('.$coord['lat'].', '.$coord['lng'].');
					
					gmap_add_marker(location);
		    		gmap_bounds.extend(location);
		    		gmap.fitBounds(gmap_bounds);
				');
			}
		}
		
		$vars = array(
			'height' 	 => $options['gmap_map_height'],
			'coords' 	 => $coord_string,
			'field_name' => $this->field_name
		);
		
		
		return $this->EE->load->view('map', $vars, TRUE);
	}
		
	function display_settings($data)
	{		
		// load the language file
		$this->EE->lang->loadfile('gmap');
		
		$data = array(			
			'gmap_latitude' 		=> isset($data['gmap_longitude'])       ? $data['gmap_latitude'] :
																	   $this->settings['gmap_latitude'],				'gmap_longitude' 		=> isset($data['gmap_longitude'])       ? $data['gmap_longitude'] :
																	   $this->settings['gmap_longitude'],				'gmap_zoom' 			=> isset($data['gmap_zoom'])            ? $data['gmap_zoom'] : 
																       $this->settings['gmap_zoom'],
			'gmap_total_points' 	=> isset($data['gmap_total_points'])    ? $data['gmap_total_points'] :
		 															   $this->settings['gmap_total_points'],
			'gmap_map_height' 		=> isset($data['gmap_map_height'])      ? $data['gmap_map_height'] : 
																   	   $this->settings['gmap_map_height'],
			'gmap_latitude_field' 	=> isset($data['gmap_latitude_field'])  ? $data['gmap_latitude_field'] : 
																	   $this->settings['gmap_latitude_field'],
			'gmap_longitude_field' 	=> isset($data['gmap_longitude_field']) ? $data['gmap_longitude_field'] : 
																	   $this->settings['gmap_longitude_field']
		);
				
		if(empty($data['gmap_latitude']))
			$data['gmap_latitude'] = 0;
		
		if(empty($data['gmap_longitude']))
			$data['gmap_longitude'] = 0;
							
		$this->EE->cp->add_to_head('<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>');
		
		$this->EE->javascript->output('

            var timer = setInterval(shouldInit, 1000);  // check if map should be inited
            var mapInited = false;

            var gmap_canvas;
            var gmap;

            function shouldInit()
            {
                if(mapInited) return;

                if($("#gmap_wrapper").is(":visible")) {

                    mapInited = true;
                    clearInterval(timer);

                    gmap_canvas = $("#gmap_canvas").get(0);

                    var location 		= new google.maps.LatLng('.$data['gmap_latitude'].', '.$data['gmap_longitude'].');
                    var gmap_geocoder	= new google.maps.Geocoder();

                    var myOptions = {
                        zoom: '.$data['gmap_zoom'].',
                        center: location,
                        mapTypeId: google.maps.MapTypeId.ROADMAP
                    }

                    gmap = new google.maps.Map(gmap_canvas, myOptions);

                    google.maps.event.addListener(gmap, \'center_changed\', function() {
                        var center = gmap.getCenter();
                        $("#gmap_latitude").val(center.lat());
                        $("#gmap_longitude").val(center.lng());
                    });

                    google.maps.event.addListener(gmap, \'zoom_changed\', function() {
                        var zoom = gmap.getZoom();
                        $("#gmap_zoom").val(zoom);
                    });
                }
            }

			$("#gmap_latitude, #gmap_longitude").blur(function() {
				var lat = parseFloat($("#gmap_latitude").val());
				var lng = parseFloat($("#gmap_longitude").val());
				
				if(!isNaN(lat) && !isNaN(lat)) {
					var new_location = new google.maps.LatLng(lat, lng);
				
					gmap.setCenter(new_location);
					
				} else {
					alert("Invalid latitude or longitude: "+lat+", "+lng);
				}
			});
			
			$("#gmap_zoom").blur(function() {
				var zoom = parseInt($("#gmap_zoom").val());
				
				if(zoom <= 20 && zoom >= 0) {
					gmap.setZoom(zoom);
				} else {
					alert("Invalid zoom: "+zoom+". The zoom must be between 0 and 20.");
				}
			});

			$("#gmap_map_height").blur(function() {
				$("#gmap_wrapper").css("height", $(this).val());
			});
		');
				
		$this->EE->table->add_row(
			lang('gmap_latitude', 'gmap_latitude').'<br>'.lang('gmap_latitude_description'),
			form_input(array(
				'name' 	=> 'gmap_latitude',
				'id'   	=> 'gmap_latitude',
				'value'	=> $data['gmap_latitude']
			))
		);
		
		$this->EE->table->add_row(
			lang('gmap_longitude', 'gmap_longitude').'<br>'.lang('gmap_longitude_description'),
			form_input(array(
				'name' 	=> 'gmap_longitude', 
				'id' 	=> 'gmap_longitude',
				'value'	=>	$data['gmap_longitude']
			))
		);
				
		$this->EE->table->add_row(
			lang('gmap_zoom', 'gmap_zoom').'<br>'.lang('gmap_zoom_description'),
			form_input(array(
				'name'	=> 'gmap_zoom', 
				'id'	=> 'gmap_zoom',
				'value' => $data['gmap_zoom']
			))
		);	
		
		$this->EE->table->add_row(
			lang('gmap_total_points', 'gmap_total_points').'<br>'.lang('gmap_total_points_description'),
			form_input(array(
				'name'	=> 'gmap_total_points',
				'id'	=> 'gmap_total_points',
				'value'	=> $data['gmap_total_points']
			))
		);	
		
		$this->EE->table->add_row(
			lang('gmap_latitude_field', 'gmap_latitude_field').'<br>'.lang('gmap_latitude_field_description'),
			form_input(array(
				'name' 	=> 'gmap_latitude_field',
				'id'	=> 'gmap_latitude_field',
				'value'	=> 	$data['gmap_latitude_field']
			))
		);
		
		$this->EE->table->add_row(
			lang('gmap_longitude_field', 'gmap_longitude_field').'<br>'.lang('gmap_longitude_field_description'),
			form_input(array(
				'name' 	=> 'gmap_longitude_field',
				'id'	=> 'gmap_longitude_field',
				'value'	=> 	$data['gmap_longitude_field']
			))
		);
		
		$this->EE->table->add_row(
			lang('gmap_map_height', 'gmap_map_height').'<br>'.lang('gmap_map_height_description'),
			form_input(array(
				'name' 	=> 'gmap_map_height',
				'id'	=> 'gmap_map_height',
				'value'	=> 	$data['gmap_map_height']
			))
		);
		
		$this->EE->table->add_row(
			lang('gmap_preview', 'gmap_preview').'<br>'.
			lang('gmap_preview_description').'<br>'.'<br>'.
			'<i>'.lang('gmap_google_map_render_bug').'</i>',
			'<div id="gmap_wrapper" style="height: '.$data['gmap_map_height'].'"><div id="gmap_canvas" style="width: 100%; height: 100%"></div></div>'
		);
		
	}
	
	/*function display_global_settings()
	{
		
		$val = array_merge($this->settings, $_POST);
	
		return $this->EE->load->view('field_type/settings', $val, TRUE);
	}
	
	function save_global_settings()
	{		
		return array_merge($this->settings, $_POST);
	}*/
	
	function save_settings()
	{
		return array_merge($this->settings, $_POST);
	}
	
	function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
		return $data;
	}
	
}
// END Google_maps_ft class

/* End of file ft.google_maps.php */
/* Location: ./system/expressionengine/third_party/google_maps/ft.google_maps.php */