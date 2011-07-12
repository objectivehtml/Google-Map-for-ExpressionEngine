<?php
/**
 * Plugin - Google Maps for ExpressionEngine
 *
 * @package			Google Maps for ExpressionEngine
 * @version			2.2.1
 * @author			Justin Kimbrell <http://objectivehtml.com>
 * @copyright 		Copyright (c) 2011 Justin Kimbrell <http://objectivehtml.com>
 * @license 		Creative Commons Attribution 3.0 Unported License -
 					please see LICENSE file included with this distribution
 * @link			http://objectivehtml.com/documentation/google-maps-for-expressionengine
 */

$plugin_info = array(
	'pi_name'			=> 'Google Maps for ExpressionEngine',
	'pi_version'		=> '2.2.1',
	'pi_author'			=> 'Justin Kimbrell',
	'pi_author_url'		=> 'http://objectivehtml.com/documentation/google-maps-for-expressionengine',
	'pi_description'	=> 'Creates static and dynamic maps from content channels.',
	'pi_usage'			=>  Gmap::usage()
);
				

Class Gmap {
	
	private $reserved_terms = array('', '_min', '_max', '_like');
	
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
			'center', 'channel', 'hide_markers', 'open_windows', 'map_type', 'id', 'class', 'style',
			'style_link', 'style_obj', 'extend_bounds', 'show_one_window', 'icon', 'show_coordinate',
			'add_title_to_dropdown', 'metric', 'offset', 'distance', 'cache_post'
		),
		
		/* Dynamic and Static fields */
		'fields' => array(
			'address_field', 'city_field', 'state_field', 'zipcode_field', 'country_field', 
			'latitude_field', 'longitude_field', 'zoom_field', 'address', 'latitude', 'longitude',
			'gmap_field', 'lat_lng'
		)
		
	);
	
	public $geocoder_response;
	
	public function Gmap()
	{
		$this->EE =& get_instance();
			
		$this->EE->load->config('gmap');
		
		$this->_fetch_params();
		
		$this->return_data = $this->init();
	}
	
	public function init()
	{	
		if(!$this->args['plugin']['id'])
			show_error('You must assign a unique <code>id</code> to every map. This <code>id</code> should only contain alphabetical and numerical characters with the exception of an underscore.');
		
		return $this->_init_map();
	}
	
	public function center()
	{
		$data  = '<script type="text/javascript">';
		
		$manual_zoom = FALSE;
		
		if($this->EE->TMPL->fetch_param('zoom'))
			$manual_zoom = $this->args['map']['zoom'];
				
		if($this->EE->TMPL->fetch_param('address'))
		{
			foreach($this->args['fields']['address'] as $address)
				$data .= $this->_geocode($address, FALSE, $manual_zoom, $this->EE->TMPL->tagdata);	
		}
		elseif($this->EE->TMPL->fetch_param('latitude') && $this->EE->TMPL->fetch_param('longitude'))
		{
			$data .=  $this->args['plugin']['id'].'_center = new google.maps.LatLng('.$this->args['fields']['latitude'].', '.$this->args['fields']['longitude'].');		
			'.$this->args['plugin']['id'].'_canvas.setCenter('.$this->args['plugin']['id'].'_center);';
		}
			
		$data .= '</script>';
		
		return $data;
	}
	
	public function dropdown()
	{
		$limit 	  = $this->args['channel']['limit'] ? $this->args['channel']['limit'] : 'false';
		 
		$dropdown = '
		
		<select name="'.$this->args['plugin']['id'].'_dropdown" id="'.$this->args['plugin']['id'].'_dropdown" class="'.$this->args['plugin']['class'].'" style="'.$this->args['plugin']['style'].'" onchange="'.$this->args['plugin']['id'].'_showMarker(this)">
			<option>--Select a location--</option>
		</select>
				
		<script type="text/javascript">
			var '.$this->args['plugin']['id'].'_dropdown = document.getElementById("'.$this->args['plugin']['id'].'_dropdown");
			
			var '.$this->args['plugin']['id'].'_dropdownLimit  = '.$limit.';';
			
			$dropdown .= '
			function '.$this->args['plugin']['id'].'_showMarker(obj) {
				var index = obj.selectedIndex - 1;
				
				if(index >= 0) {
					var marker = '.$this->args['plugin']['id'].'_markers[index];
					var position = marker.position;
					var window = '.$this->args['plugin']['id'].'_windows[index];
					
					
					for(i = 0; i < '.$this->args['plugin']['id'].'_count; i++) {
						'.$this->args['plugin']['id'].'_windows[i].close();
					}
					
					'.$this->args['plugin']['id'].'_canvas.setCenter(position);
					window.open('.$this->args['plugin']['id'].'_canvas, marker);
				}
			}
			
			for(i = 0; i < '.$this->args['plugin']['id'].'_count; i++) {
				if(i < '.$this->args['plugin']['id'].'_dropdownLimit || !'.$this->args['plugin']['id'].'_dropdownLimit) {
					var marker = '.$this->args['plugin']['id'].'_markers[i];
					
					if(marker) {
						var html = '.$this->args['plugin']['id'].'_html[i];
						';
						
						if($this->args['plugin']['show_coordinate'])
							$dropdown .= '
							html +=  \' :: \'+marker.position.lat()+\', \'+marker.position.lng();';
							
					$dropdown .= '
					'.$this->args['plugin']['id'].'_dropdown.innerHTML += \'<option id="\'+i+\'">\'+html+\'</option>\';
					}
				}
			}
			
		</script>
		';
		
		return $dropdown;
	
	}
	
	public function marker($manual_zoom = FALSE)
	{
		$tagdata	 = $this->EE->TMPL->tagdata;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;		
		
		$map  = 
		'<script type="text/javascript">' .

			$this->_geocode_center_map($this->args['plugin']['center'], $tagdata)	.			

			$this->_plot_coords($manual_zoom) .
		
		'</script>';

		return $map;
	}
	
	public function results()
	{
		$this->EE->load->model(array(
			'channel_model', 
			'field_model', 
			'channel_entries_model', 
			'category_model'
		));
		
		if($this->args['plugin']['cache_post'])
		{		
			if($this->EE->input->post('init_gmap_search') == 'y')
			{			
				$this->EE->functions->set_cookie('gmap_last_post', serialize($_POST), strtotime('+1 year'));
			}
			else
			{
				$cookie = $this->EE->input->cookie('gmap_last_post');
				
				if($cookie)
					$_POST = unserialize($cookie);
			}
		}
		
		$tagdata			 = empty($this->EE->TMPL->tagdata) ? FALSE : $this->EE->TMPL->tagdata;
		$metric		   		 = $this->EE->TMPL->fetch_param('metric');
		$metric				 = $metric ? $metric : 'miles';
		$geocode_field		 = $this->EE->TMPL->fetch_param('geocode_field');
		$location			 = $this->EE->input->post($geocode_field);
		$channels			 = explode('|', $this->args['channel']['channel']);
		$distance_field 	 = $this->EE->TMPL->fetch_param('distance_field');
		$distance			 = $this->EE->input->post($distance_field);
		$categories			 = $this->EE->input->post('category');
		
		/*
		$page				 = 1;
		$limit				 = 0;
		$offset			 	 = $page * $limit - $limit;
		*/
				
		if($this->EE->input->post('init_gmap_search') == 'y')
		{
			if($location)
			{
				$response = $this->_geocode_location($location);			
				$vars[0]  = $this->_geocode_response($response);
				
				$lat = $vars[0]['geocoder:latitude'];
				$lng = $vars[0]['geocoder:longitude'];
				
				if($lat !== FALSE && $lng !== FALSE && !empty($distance))
				{
					if($distance === FALSE)
					{
						show_error('The distance field is not defined in the template tag. The distance_field parameter value should be the same as the name of your distance field in the DOM.');
					}				
					
					$lat_field = $this->EE->TMPL->fetch_param('latitude_field');
					$lng_field = $this->EE->TMPL->fetch_param('longitude_field');
					
					$lat_field_name = $this->_prep_sql_fieldname($lat_field, FALSE, FALSE);	
					$lat_field_name = $lat_field_name[0];
									
					$lng_field_name = $this->_prep_sql_fieldname($lng_field, FALSE, FALSE);
					$lng_field_name = $lng_field_name[0];
					
					$vars[0]['distance'] = $distance;
					$vars[0]['metric'] = $metric;
				}
				else
				{
					$vars[0]['metric'] = '';
					$vars[0]['distance'] = 'any distance';
				}
			}
		
			$prep_fields = $this->_prep_sql_fieldname($_POST, FALSE);
			
			$sql 	= 'SELECT `exp_channel_data`.`entry_id`, `exp_category_posts`.`cat_id`';
			$where  = '';
			$having = '';
			
			if($distance && $location)
			{
				$sql .= ', (((ACOS(SIN('.$lat.' * PI() / 180) * SIN('.$lat_field_name.' * PI() / 180) + COS('.$lat.' * PI() / 180) * COS('.$lat_field_name.' * PI() / 180) * COS(('.$lng.' - '.$lng_field_name.') * PI() / 180)) * 180 / PI()) * 60 * 1.1515) * '.$this->_convert_metric($metric).') AS distance';
				
				$having = ' HAVING `distance` '.$this->_prep_value($distance_field, $distance);
			}
					
			$sql .= ' FROM `exp_channel_data` LEFT JOIN `exp_category_posts` ON `exp_channel_data`.`entry_id` = `exp_category_posts`.`entry_id`';
										
			//Loops through the defined channels
			foreach($channels as $channel_name)
			{		
				$channel_data = $this->EE->channel_model->get_channels(NULL, array('channel_id, field_group'), 
					array(array('channel_name' => $channel_name))
				)->row();
				
				if(count($channel_data) > 0)
				{	
					//$this->EE->db->or_where('channel_id', $channel_data->channel_id);
					$where .= '`channel_id` = \''.$channel_data->channel_id.'\' AND ';
					
					if(is_array($categories))
					{			
						foreach($categories as $category)
							$where .= '`cat_id` = \''.$category.'\' AND ';
						
						$where = rtrim($where, ' AND ') . ' OR ';
					}
					else
					{
						if(is_array($prep_fields))
						{				
							foreach($prep_fields as $prep_index => $prep_value)
								$where .= $prep_value .' AND ';
						}
						
						$where = rtrim($where, ' AND ') . ' OR ';
					}
				}
			}
			
			$where = 'WHERE '.rtrim(trim($where), ' OR');
			$sql   = rtrim(trim($sql), ' OR') . $where . ' GROUP BY `exp_channel_data`.`entry_id`' . $having;
			
			//echo $sql;
			
			/*
			if($limit) 
				$sql .= ' LIMIT '.$limit.', '.$offset;
			*/
				
			$results = $this->EE->db->query($sql);
			$total_results = $results->num_rows();
			$entry_ids = $this->_create_id_string($results->result());
					
			if($tagdata)
			{	
				if($total_results == 0)
				{
					return $this->EE->TMPL->no_results($vars);
				}
				
				$vars[0]['entry_ids'] = $entry_ids;
				$vars[0]['total_results'] = $total_results;
				
				return $this->EE->TMPL->parse_variables($tagdata, $vars);
			}
			else
			{
				return $entry_ids;
			}		
		}
	}
	
	private function _create_id_string($results)
	{		
		$id = NULL;
		
		foreach($results as $row)
			$id .= $row->entry_id . '|';
		
		return rtrim($id, '|');
	}
	
	private function _convert_metric($metric = 'miles')
	{
		$metrics = array(
			'miles' 	 => 1,
			'feet'  	 => 5280,
			'kilometers' => 1.609344,
			'meters'	 => 1609.344
		);	
		
		$return = isset($metrics[$metric]) ? $metrics[$metric] : $metrics['miles'];
		
		return isset($metrics[$metric]) ? $metrics[$metric] : $metrics['miles'];
	}
	
	private function _prep_fields($prep_fields)
	{
		if(is_array($prep_fields))
		{				
			foreach($prep_fields as $prep_index => $prep_value)
			{
				$where .= $prep_value .' AND ';
			}
		}
		
		$where = rtrim($where, ' AND ') . ' OR ';
		
		return $where;
	}
	
	private function _prep_sql_fieldname($field_array, $user_value = FALSE, $to_append = TRUE)
	{	
		$return = FALSE;
		$string = array();
		
		//Converts a single field to an array
		$field_array = is_array($field_array) ? $field_array : array($field_array => '');
		
		//Loops through the field array
		foreach($field_array as $field_name => $field_value)
		{	
			$value = FALSE;
			
			//Fallsback to the post variable if no value is passed
			$value = !empty($field_value) ? $field_value : $user_value;			
			$value = $value ? $value : $this->EE->input->post($field_name);
												
			//Creates the SQL field name by removed the reserved terms
			$sql_field_name = str_replace($this->reserved_terms, '', $field_name);
			
			//Gets the field data and if the field exists, the sql statement is created
			$field_data = $this->EE->field_model->get_fields('', array('field_name' => $sql_field_name));
						
			if($field_data->num_rows() > 0)
			{	
				//Validates that a value is not FALSE
				if($value !== FALSE && !empty($value) || $to_append == FALSE)
				{
					//If to_append is TRUE, then the operator is appended
					if($to_append == TRUE)
					{			
						//Converts a value string to a variable
						$values = is_array($value) ? $value : array($value);
						
						//Loops through the values array and creates the SQL conditions
						foreach($values as $value)
						{
							$operator = $this->_prep_value($field_name, $value);
														
							$string[] = '`field_id_'.$field_data->row('field_id').'` '.$operator;
						}
					}
					else
					{					
						$string[] = '`field_id_'.$field_data->row('field_id').'`';
					}
				}
			}			
		}
		
		return $string;
	}
	
	private function _prep_value($field_name, $value)
	{
		//Preps conditional statement by testing the field_name for keywords
		if(strpos($field_name, '_min'))
			$operator = ' >= \''.$value.'\'';
		else if(strpos($field_name, '_max'))
			$operator = ' <= \''.$value.'\'';
		else if(strpos($field_name, '_like'))
			$operator = ' LIKE \'%'.$value.'%\'';
		else
			$operator = ' = \''.$value.'\' ';
	
		return $operator;
	}
	
	public function search()
	{
		$this->EE->load->helper('form');
		$this->EE->load->model(array(
			'channel_model', 
			'field_model', 
			'channel_entries_model', 
			'category_model'
		));
		
		if($this->args['plugin']['cache_post'])
		{		
			if($this->EE->input->post('init_gmap_search') == 'y')
			{			
				$this->EE->functions->set_cookie('gmap_last_post', serialize($_POST), strtotime('+1 year'));
			}
			else
			{
				$cookie = $this->EE->input->cookie('gmap_last_post');
				
				if($cookie) $_POST = unserialize($cookie);
			}
		}
		
		$checked_true  		 = 'checked="checked"';
		$selected_true 		 = 'selected="selected"';
		$metric		   		 = $this->EE->TMPL->fetch_param('metric');
		$geocode_field		 = $this->EE->TMPL->fetch_param('geocode_field');
		$location			 = $this->EE->input->post($geocode_field);
		$distance	   		 = $this->EE->input->post('distance');		
		$channels 	   		 = explode('|', $this->args['channel']['channel']);
		$field_loop 		 = $this->reserved_terms;
		$vars 		   		 = array(array());
				
		foreach($field_loop as $append)
		{
			$vars[0]['distance'.$append] = $this->EE->input->post('distance'.$append) ? 
										   $this->EE->input->post('distance'.$append) : '';
		}
		
		$vars[0][$geocode_field] = $this->EE->input->post($geocode_field);
		$vars[0]['metric']   	 = $metric ? $metric : 'miles';
				
		//Loops through the defined channels
		foreach($channels as $channel)
		{
			$channel_data = $this->EE->channel_model->get_channels(NULL, array('*'), array(
				array(
					'channel_name' => $channel
				)
			))->row();
			
			$channel_fields = $this->EE->field_model->get_fields($channel_data->field_group)->result();
			$available_categories = explode('|', $channel_data->cat_group);
						
			//Loops throught the fields in each channel
			foreach($channel_fields as $index => $channel_field)
			{
				$field = $this->EE->field_model->get_field($channel_field->field_id)->row();
				$field_name = str_replace(array('_min', '_max'), array('', ''), $field->field_name);
								
				$fields = array();
				
				//Loops throught the standard, min, and max fields
				foreach($field_loop as $append)
				{
					$field_appendage = $field_name . $append;
										
					$input = $this->EE->input->post($field_appendage) ?
					    	 $this->EE->input->post($field_appendage) : '';
					    										
					//If list items exist, it build the option:field_name array
					if(!empty($field->field_list_items))
					{					
						$list_items = explode("\n", $field->field_list_items);
								
						if(count($list_items) > 0)
						{
							//Loops through the list items for the fieldtype
							foreach($list_items as $item)
							{
								$checked = '';
								$selected = '';
								
								//Checks to see if the entry should be checked or selected
								if($this->EE->input->post($field_appendage) !== FALSE)
								{
									$post = $this->EE->input->post($field_appendage);
																		
									if($this->_is_checked_or_selected($post, $item))
									{
										$checked = $checked_true;
										$selected = $selected_true;
									}
								}
								
								//Adds all the data to the template variable
								$vars[0]['options:'.$field_appendage][] = array(
									'option_name'  => ucfirst($item),
									'option_value' => $item,
									'selected'	   => $selected,
									'checked'	   => $checked
								);
							}
						}		
					}
					
					$fields['label:'.$field_appendage] = $field->field_label;
					$fields['instructions:'.$field_appendage] = $field->field_instructions;
					$fields[$field_appendage] = $input;
				}
				
				$vars[0] = array_merge($fields, $vars[0]);
			}
				
		}		
		
		//Loops through the channel categories and assigns them to template
		//variable in a linear fasion, similar to the steps above
		foreach($available_categories as $cat_id)
		{
			$cat_data = $this->EE->category_model->get_channel_categories($cat_id, array('*'))->result();
			
			foreach($cat_data as $category)
			{
				$selected = '';
				$checked  = '';
				
				if($this->_is_checked_or_selected($this->EE->input->post('category'), $category->cat_id))
				{
					$selected = $selected_true;
					$checked  = $checked_true;
				}
				
				$vars[0]['categories'][] = array(
					'category_id'   		  => $category->cat_id,
					'category_name' 		  => $category->cat_name,
					'category_url_title'      => $category->cat_url_title,
					'category_description'    => $category->cat_description,
					'category_image'		  => $category->cat_image,
					'selected'				  => $selected,
					'checked'				  => $checked
				);	
			}
		}
		
		$return    	   = $this->EE->TMPL->fetch_param('return');
		
		$attributes    = array('method' => 'post');
		$hidden_fields = array(
			'init_gmap_search' 	=> 'y',
			'channel'		   	=> $channels,
			'distance'		   	=> $distance,
			'metric'		   	=> $metric,
			'geocode_field'	   	=> $geocode_field,
			'location'		  	=> $location
		);
		
		$vars[0]['has_searched']     = $this->EE->input->post('init_gmap_search') ? TRUE : FALSE;
		$vars[0]['has_not_searched'] = $vars[0]['has_searched'] ? FALSE : TRUE;
		
		$tagdata   	   		   = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $vars);
		$form 		   		   = form_open($return, $attributes, $hidden_fields).$tagdata.form_close();
				
		if(isset($response))
		{
			$this->EE->functions->set_cookie('gmap_last_search', serialize($response), strtotime('+1 year'));
		}
		
		return $form;
	}
	
	private function _geocode_location($location)
	{ 
		$sensor  = 'true';
		$url 	 = 'http://maps.googleapis.com/maps/api/geocode/json?&sensor=' . $sensor .
				   '&address='.urlencode($location);
		$timeout = 5000;
		
		$curl_handle = curl_init();
       	curl_setopt($curl_handle, CURLOPT_URL, $url);
       	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, $timeout);
       	curl_setopt($curl_handle, CURLOPT_TIMEOUT, $timeout);
       	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);

       	$response = curl_exec($curl_handle);
       	curl_close($curl_handle);
       	
       	return json_decode($response);
	}
	
	private function _geocode_response($response_array, $depth = 1)
	{
		$return = array();
		
		foreach($response_array->results as $index => $response)
		{
			if($index < $depth || $depth == 0)
			{
				foreach($response->address_components as $component)
				{
					$return[$index]['geocoder:address_component'][] = array(
						'long_name'  => $component->long_name,
						'short_name' => $component->short_name,
						'types'		 => implode(', ', $component->types)
					);			
				}
			} 
		}		
		
		if(is_object($response))
		{
			$return['geocoder:formatted_address'] = $response->formatted_address;
			$return['geocoder:latitude']  		  = $response->geometry->location->lat;
			$return['geocoder:longitude'] 		  = $response->geometry->location->lng;
			$return['geocoder:status'] 			  = $response_array->status;
		}
		
		return $return;
	}
	
	private function _is_checked_or_selected($post, $item)
	{
		if(is_array($post))
		{
			foreach($post as $post_index => $post_value)
			{											
				if($item == $post_value)
				{
					return TRUE;
				}
			}									
		}
		else
		{
			if($item == $post)
			{	
				return TRUE;								
			}
		}
		
		return FALSE;
	}
	
	public function radius()
	{
		$this->EE->load->model(array('channel_model', 'field_model', 'channel_entries_model'));
		
		$channels = explode('|', $this->args['channel']['channel']);
		$channels[0];		
		
		$channel_data = $this->EE->channel_model->get_channels(NULL, array('channel_id, field_group'), array(
			array(
				'channel_name' => $this->args['channel']['channel']
			)
		))->row();
		
		$field_data = $this->EE->field_model->get_fields($channel_data->field_group)->result_array();
		
		$lat_field = $this->EE->field_model->get_fields($channel_data->field_group, array(
			'field_name' => $this->args['fields']['latitude_field']
		))->row();
		
		$lng_field = $this->EE->field_model->get_fields($channel_data->field_group, array(
			'field_name' => $this->args['fields']['longitude_field']
		))->row();
		
		$lat_field_name = '`field_id_'.$lat_field->field_id.'`';
		$lng_field_name = '`field_id_'.$lng_field->field_id.'`';
		
		/*
		$this->EE->db->select('channel_titles.*');
		
		foreach($field_data as $field)
			$this->EE->db->select('channel_data.field_id_'.$field['field_id'].' as '.$field['field_name']);
		
		SELECT ((ACOS(SIN($lat * PI() / 180) * SIN(`lat` * PI() / 180) + COS($lat * PI() / 180) * COS(`lat` * PI() / 180) * COS(($lon – `lon`) * PI() / 180)) * 180 / PI()) * 60 * 1.1515) AS distance 
		
		FROM `members` HAVING distance<=’10′ ORDER BY distance ASC
		
		*/
		
		$lat = 40;
		$lng = -86;
		
		$this->EE->db->select('entry_id');
		$this->EE->db->select('(((ACOS(SIN('.$lat.' * PI() / 180) * SIN('.$lat_field_name.' * PI() / 180) + COS('.$lat.' * PI() / 180) * COS('.$lat_field_name.' * PI() / 180) * COS(('.$lng.' - '.$lng_field_name.') * PI() / 180)) * 180 / PI()) * 60 * 1.1515) * '.$this->args['plugin']['offset'].') AS distance');
		
		//$this->EE->db->join('channel_data', 'channel_titles.entry_id = channel_data.entry_id');
		
		$this->EE->db->where($lat_field_name.' != ', '');
		
		if($this->args['plugin']['distance'])
		{
			$distance = $this->args['plugin']['distance'];
			
			$polarity = strstr($distance, '+') == TRUE ? ' >= ' : ' <= ';
					
			$this->EE->db->having('distance '.$polarity.' '. str_replace('+', '', $this->args['plugin']['distance']), NULL, FALSE);
		}
		
		$entries = $this->EE->db->get('channel_data')->result_array();
		
		$entry_id = array();
	
		foreach($entries as $entry)
			$entry_id[] = $entry['entry_id'];
		
		$this->args['channel']['entry_id'] = implode('|', $entry_id);
		
		if(!empty($this->args['channel']['entry_id']))
			return $this->_init_map();
		
		$verbage = strstr($distance, '+') ? 'outside' : 'within';
		
		return 'No coordinates found '.$verbage.' '.(float)$distance.' '.$this->args['plugin']['metric'];
	}
	
	public function zoom()
	{
		$data = NULL;
		
		if($this->args['map']['zoom'] !== FALSE)
		{
			$data = 
			'<script type="text/javascript">' .
				$this->_set_zoom($this->args['map']['zoom']) . 
			'</script>';
		}
				
		return $data;		
	}
	
	private function _init_map($plot_coords = TRUE)
	{
		$tagdata = $this->EE->TMPL->tagdata;
		
		$map = NULL;
		
		if($this->args['plugin']['style_link'])
			$map .= '<script src="'.$this->args['plugin']['style_link'].'"></script>';
		
		$map .= '
		<div class="'.$this->args['plugin']['class'].'" id="'.$this->args['plugin']['id'].'" style="'.$this->args['plugin']['style'].'"></div>

		<script type="text/javascript">
	
		var '.$this->args['plugin']['id'].' = document.getElementById(\''.$this->args['plugin']['id'].'\');
		var '.$this->args['plugin']['id'].'_bounds = new google.maps.LatLngBounds(); 
		var '.$this->args['plugin']['id'].'_center = new google.maps.LatLng('.$this->args['fields']['latitude'].', '.$this->args['fields']['longitude'].');
		var '.$this->args['plugin']['id'].'_geocoder = new google.maps.Geocoder();
		var '.$this->args['plugin']['id'].'_window;
		var '.$this->args['plugin']['id'].'_markers = [];
		var '.$this->args['plugin']['id'].'_windows = [];
		var '.$this->args['plugin']['id'].'_html = [];
		var '.$this->args['plugin']['id'].'_count = 0;
		
			
		'.$this->_gmap_options($this->args['map']).'
		
		var '.$this->args['plugin']['id'].'_canvas = new google.maps.Map('.$this->args['plugin']['id'].', '.$this->args['plugin']['id'].'_options);';
		
		if($this->args['plugin']['style_link'] || $this->args['plugin']['style_obj'])
		{
			$json_obj =  $this->args['plugin']['style_obj'] ?  $this->args['plugin']['style_obj'] : 'stylez';
			
			$map .= '
			var '.$this->args['plugin']['id'].'_styleType = new google.maps.StyledMapType('.$json_obj.', '.$this->args['plugin']['id'].'_options);
			
			'.$this->args['plugin']['id'].'_canvas.mapTypes.set(\''.$this->args['plugin']['id'].'_styleType\', '.$this->args['plugin']['id'].'_styleType);
			 
			'.$this->args['plugin']['id'].'_canvas.setMapTypeId(\''.$this->args['plugin']['id'].'_styleType\');			
			';
		}
		
		$map .= $this->_geocode_center_map($this->args['plugin']['center'], $tagdata);			
		
		if($plot_coords)
			$map .= $this->_plot_coords();
		
		$map .= '
					
		</script>';
		
		return $map;
	}
	
	private function _plot_coords($manual_zoom = FALSE)
	{	
		$coords 	 = NULL;
		$manual_zoom = $manual_zoom || $this->args['map']['zoom'] !== FALSE ? TRUE : FALSE;
		
			
		/* Prevents the marks from being added if the hide_markers parameter is set */
		if(!$this->args['plugin']['hide_markers'])
		{
			$coords = $this->_get_points_by_coordinate($manual_zoom) .
			
					  $this->_get_point_by_lat_lng_field($manual_zoom) .
			
					  $this->_get_points_by_address($manual_zoom) .
					
					  $this->_get_point_by_address($manual_zoom);
					  
			
			if($this->args['fields']['gmap_field'])
			{			
				
				$coords = $this->_get_points_by_lat_lng('{'.$this->args['fields']['gmap_field'].'}', $manual_zoom, TRUE);
			}
			
			if($this->args['fields']['lat_lng'])
			{
				$coords = $this->_get_points_by_lat_lng($this->args['fields']['lat_lng'], $manual_zoom);
			}
		}
		
		return $coords;
	}
	
	private function _center_map($manual_zoom = FALSE, $latitude = FALSE, $longitude = FALSE)
	{
		$marker = NULL;
		
		if(!$this->args['plugin']['center'])
    	{
    		if($latitude !== FALSE && $longitude !== FALSE)
    			$marker .= '
	    			if(new_location) {';
	    			
    		if($manual_zoom || !$this->args['plugin']['extend_bounds'] || $this->EE->TMPL->fetch_param('zoom'))
    		{		
    			$default_zoom = $manual_zoom ? $manual_zoom : 15;
						
				if($manual_zoom !== FALSE)	
    			{
    				$marker .= '
    				'.$this->_set_zoom($manual_zoom).'
	    			'.$this->_set_center('new_location').'
	    			';		
    			}
    		}
    		elseif($this->args['plugin']['extend_bounds'])
    		{    		
			   	$marker .= '
	    		'.$this->args['plugin']['id'].'_bounds.extend(new_location);
	    		'.$this->args['plugin']['id'].'_canvas.fitBounds('.$this->args['plugin']['id'].'_bounds);
	    		';
    		}
    			
	    	if($latitude !== FALSE && $longitude !== FALSE)
		    	$marker .= '
	    		}';
    	}
    	
    	return $marker;
	}
	
	
	private function _geocode_center_map($center, $tagdata, $manual_zoom = FALSE)
	{
		$data 		 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
		
		/* Centers map to a set location */
		if($this->args['plugin']['center'])
			$data = $this->_geocode($center, FALSE, $manual_zoom, $tagdata);
		
		return $data;
	}
	
	private function _get_points_by_coordinate($manual_zoom = FALSE)
	{
		$tagdata 	 = $this->EE->TMPL->tagdata;
		$data 	 	 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
		
		/* Creates a point from the latitude and longitude parameters */
		if($this->EE->TMPL->fetch_param('latitude') !== FALSE && $this->EE->TMPL->fetch_param('longitude') !== FALSE)
		{    		
			$data .= $this->_add_marker($this->args['fields']['latitude'], $this->args['fields']['longitude'], $manual_zoom, $tagdata);				
		}
		
		return $data;
	}
	
	private function _get_points_by_lat_lng($field = FALSE, $manual_zoom = FALSE, $add_channel_tag = FALSE)
	{			
		$data 		 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
				
		if($field == '{}') return $data;
		
		//Loops through all the LatLng fields for larger datasets (set by the fieldtype)
		if($field)
		{				
			$data = $this->_add_lat_lng($field, TRUE, $manual_zoom, $this->EE->TMPL->tagdata);
			
			if($add_channel_tag)
				$data = $this->_create_ee_tag($data);
			
		}
			
		return $data;
	}
	
	private function _get_point_by_address($manual_zoom = FALSE)
	{
		$data 		 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
		
		/* Map multiple static address points */
		if($this->args['fields']['address'])
		{
			foreach($this->args['fields']['address'] as $address)
				$data .= $this->_geocode($address, TRUE, $this->EE->TMPL->fetch_param('zoom'), $this->EE->TMPL->tagdata);
		}
		
		return $data;
	}
	
	private function _get_point_by_lat_lng_field($manual_zoom = FALSE)
	{
		$data 		 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
		
		/* Adds the markers from latitude and longitude fields */
		if($this->args['channel']['channel'] && $this->args['fields']['latitude_field'] && $this->args['fields']['longitude_field'])
		{
			$latitude_field  = '{'. $this->args['fields']['latitude_field'] .'}';
			$longitude_field = '{'. $this->args['fields']['longitude_field'].'}';
			
			if($this->args['fields']['zoom_field'])
				$manual_zoom = '{'.$this->args['fields']['zoom_field'].'}';
			
			$function = $this->_add_marker($latitude_field, $longitude_field, $manual_zoom, $this->EE->TMPL->tagdata);
			
			$data .= $this->_create_ee_tag($function);
		}
		
		return $data;
	}
	
	private function _get_points_by_address($manual_zoom = FALSE)
	{
		$data 		 = NULL;
		$manual_zoom = $manual_zoom ? $this->args['map']['zoom'] : FALSE;
		
		/* Adds the marks from address fields */
		if($this->args['channel']['channel'] && $this->args['fields']['address_field'] || $this->args['channel']['channel'] && $this->args['fields']['city_field'] || $this->args['channel']['channel'] && $this->args['fields']['state_field'] || $this->args['channel']['channel'] && $this->args['fields']['zipcode_field'] || $this->args['channel']['channel'] && $this->args['fields']['country_field'])
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
			
			$function = $this->_geocode($address, TRUE, $manual_zoom, $this->EE->TMPL->tagdata);
			$data .= $this->_create_ee_tag($function);
		}
		
		return $data;
	}


	private function _add_lat_lng($gmap_field, $marker = TRUE, $manual_zoom = FALSE, $tagdata = FALSE)
	{
		$marker = '
			coord_string = \''.$gmap_field.'\';
			coords = coord_string.split(\')(\');
			
			if(coord_string != \'\') {
			
				for(i = 0; i < coords.length; i++) {
				
					var coord = coords[i].split(\', \');
															
					var new_latitude 	= coord[0].replace(\'(\', \'\').replace(\')\', \'\');
					var new_longitude 	= coord[1].replace(\')\', \'\').replace(\'(\', \'\');
					
					(function () {  /* Fixes a known bug with Google\'s API with displaying the InfoWindow(s) */					
						new_location = false;
					
						if(new_latitude != \'\' && new_longitude != \'\') {
						
						new_location = new google.maps.LatLng(new_latitude, new_longitude);
									       	
				    	var marker = new google.maps.Marker({
				        	map: '.$this->args['plugin']['id'].'_canvas, 
				       	 	position: new_location
				    	});
						
						'
						.$this->_add_to_dropdown($tagdata);
						
						if($icon = $this->args['plugin']['icon'])
						{
							$marker .= '
							marker.setIcon("'.$icon.'");
							';
						}		
						
						$marker .= $this->_center_map($manual_zoom);
				    	
				    	$marker .= '}
				    	';
				    	
				    	$marker .= $this->_infowindow();
				    	
				$marker .= '})(); /* End bug fix */			
				}
			}	
		';
		
		return $marker;
	}
	
	private function _geocode($address, $marker = TRUE, $manual_zoom = FALSE, $tagdata = FALSE)
	{
		$location = '
		address = \''.$address.'\';
		address = address.replace(/^\s\s*/, \'\').replace(/\s\s*$/, \'\');
		
		if(address != \'\') {		
			/* Gets a location using Google\'s Geocode */
			'.$this->args['plugin']['id'].'_geocoder.geocode(
				{ 
					\'address\': address
				}, function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) { 
						var location = results[0].geometry.location;
						';
											
						$zoom = $manual_zoom;
						
						if($this->args['fields']['zoom_field'])
							$zoom = '{'.$this->args['fields']['zoom_field'].'}';
						elseif($this->args['map']['zoom'] && $manual_zoom)
							$zoom = $this->args['map']['zoom'];
						
						if($marker)
						{
							$location .= $this->_add_marker(FALSE, FALSE, $zoom, $tagdata);
			        	}
			        	else
			        	{
			        		if($manual_zoom)
								$location .= '
								'.$this->_set_zoom($this->args['map']['zoom']);
							
							$location .= '
							'.$this->_set_center('location');
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
	
	private function _add_marker($latitude = FALSE, $longitude = FALSE, $manual_zoom = FALSE, $tagdata)
	{	
		$open_if 	= '';
		$close_if	= '';
		$marker 	= '';
		
		$marker .= '
		(function () {  /* Fixes a known bug with Google\'s API with displaying the InfoWindow(s) */
		
		new_location = false;';
		
		if($latitude !== FALSE && $longitude !== FALSE)
			$marker .= '
			new_latitude = \''.$latitude.'\';
			new_longitude = \''.$longitude.'\';
			
			if(new_latitude != \'\' && new_longitude != \'\') {
			
			new_location = new google.maps.LatLng(new_latitude, new_longitude);';
		else
			$marker .= '
			new_location = location;';
				
		$marker .= '					       	
    	var marker = new google.maps.Marker({
        	map: '.$this->args['plugin']['id'].'_canvas, 
       	 	position: new_location
    	});
		
		'
		.$this->_add_to_dropdown($tagdata);

		if($icon = $this->args['plugin']['icon'])
		{
			$marker .= '
			marker.setIcon("'.$icon.'");
			';
		}

		if($latitude !== FALSE && $longitude !== FALSE)
			$marker .= '
			}';
					
		$marker .= $this->_center_map($manual_zoom, $latitude, $longitude);
    	
    	$marker .= $this->_infowindow();
			
		$marker .= '})(); /* End bug fix */'; 
		
    	return $open_if . $marker . $close_if;
	}
	
	function _infowindow()
	{
		$marker = NULL;
		$tagdata = $this->EE->TMPL->tagdata;
		
		if($tagdata != '')
		{
			$marker .= '
			if(new_location) {
				
				var html = "'.preg_replace("/[\n\r\t]/","",str_replace("\"", "\\\"", $this->EE->TMPL->tagdata)).'"		
				if('.$this->args['plugin']['id'].'_html['.$this->args['plugin']['id'].'_count-1] == "" || '.$this->args['plugin']['id'].'_html['.$this->args['plugin']['id'].'_count] == "{title}")
					'.$this->args['plugin']['id'].'_html['.$this->args['plugin']['id'].'_count-1] = html;
				
				var infowindow = new google.maps.InfoWindow({
					content: html
				});
				
				'.$this->args['plugin']['id'].'_windows['.$this->args['plugin']['id'].'_count-1] = infowindow;';

				if($this->args['plugin']['open_windows'])
					$marker .= 'infowindow.open('.$this->args['plugin']['id'].'_canvas, marker);';
													
				$marker .= '				
				google.maps.event.addListener(marker, \'click\', function() {';
				
					if($this->args['plugin']['show_one_window'])
						$marker .= '
						if('.$this->args['plugin']['id'].'_window)
							'.$this->args['plugin']['id'].'_window.close()';
					
					$marker .= '
					infowindow.open('.$this->args['plugin']['id'].'_canvas, marker);
					'.$this->args['plugin']['id'].'_window = infowindow;
				});
			}';
		}
		
		return $marker;
	}
	
	function _add_to_dropdown($tagdata = FALSE)
	{
		if(!$tagdata || $this->args['plugin']['add_title_to_dropdown'])
			$script = '
			'.$this->args['plugin']['id'].'_html['.$this->args['plugin']['id'].'_count] = "{title}";';
		else
			$script = '
			'.$this->args['plugin']['id'].'_html['.$this->args['plugin']['id'].'_count] = "'.preg_replace("/[\n\r\t]/","",str_replace("\"", "\\\"", $tagdata)).'";';
		
		$script .= '
		'.$this->args['plugin']['id'].'_markers['.$this->args['plugin']['id'].'_count] = marker;
    	'. $this->args['plugin']['id'].'_count++;';
    	
    	return $script;
	}
	
	private function _set_center($center)
	{
		return $this->args['plugin']['id'].'_canvas.setCenter('.$center.')';
	}
	
	private function _set_zoom($zoom)
	{
		return $this->args['plugin']['id'].'_canvas.setZoom('.$zoom.')';
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
		// Loops through the defined channels and checks for custom fields and 
		$this->EE->load->model(array('channel_model', 'field_model'));
		
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
		$this->args['fields']['latitude'] = $this->args['fields']['latitude'] !== FALSE ? $this->args['fields']['latitude'] : 0;
		
		$this->args['fields']['longitude'] = $this->args['fields']['longitude'] !== FALSE ? $this->args['fields']['longitude'] : 0;
		
		$this->args['plugin']['offset'] = 1;
		
		switch($this->args['plugin']['metric'])
		{
			case 'kilometers':
				$this->args['plugin']['offset'] = 1.609344;
				break;
				
			case 'meters':
				$this->args['plugin']['offset'] = 1609.344;
				break;
			
			case 'feet':
				$this->args['plugin']['offset'] = 5280;				
				break;
		}
		
		
		//$this->args['map']['zoom'] = $this->args['map']['zoom'] !== FALSE ? $this->args['map']['zoom'] : 15;
				
		if($this->args['plugin']['extend_bounds'] == "yes" || $this->args['plugin']['extend_bounds'] == "true" || !$this->args['plugin']['extend_bounds'])
			$this->args['plugin']['extend_bounds'] = TRUE;		
		else
			$this->args['plugin']['extend_bounds'] = FALSE;
			
		
		if($this->args['plugin']['show_one_window'] == "yes" || $this->args['plugin']['show_one_window'] == "true")
			$this->args['plugin']['show_one_window'] = TRUE;
		else
			$this->args['plugin']['show_one_window'] = FALSE;
						
			
		if($this->args['plugin']['show_coordinate'] == "yes" || $this->args['plugin']['show_coordinate'] == "true")
			$this->args['plugin']['show_coordinate'] = TRUE;
		else
		
			$this->args['plugin']['show_coordinate'] = FALSE;
			
			
		if($this->args['plugin']['add_title_to_dropdown'] == "yes" || $this->args['plugin']['add_title_to_dropdown'] == "true")
			$this->args['plugin']['add_title_to_dropdown'] = TRUE;			
		else
			$this->args['plugin']['add_title_to_dropdown'] = FALSE;
						
		
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