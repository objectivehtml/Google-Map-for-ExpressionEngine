var fieldOpts 		= EE.gmaps.'.$this->field_name.'.settings;
var location 		= new google.maps.LatLng(fieldOpts.latitude, fieldOpts.longitude);
	var gmap_geocoder	= new google.maps.Geocoder();

var myOptions = {
	zoom: fieldOpts.zoom,
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

function gmap_add_marker(location) {							
	
	var element = gmap_marker_count;
					
	var marker = new google.maps.Marker({
		map: gmap,
		position: location,
		draggable: true,
		raiseOnDrag: true
	});
	
	
	gmap_bounds.extend(location);
	gmap_markers.push(marker);
	
	var html = "<div style=\"width:275px;height:100px:\">" + location + "<br><br>" + "<a href=\"#\" id=\""+element+"\" title=\""+location+"\" class=\"gmap-remove\">Remove from Map</a></div>";
			
	var infowindow = new google.maps.InfoWindow({
		content: html
	});
					
	google.maps.event.addListener(marker, 'dragstart', function(event) {
		gmap_coords.val(gmap_coords.val().replace(String(event.latLng), ''));
	});
	
	google.maps.event.addListener(marker, 'dragend', function(event) {
		var new_location = event.latLng;
		
		infowindow.setContent("<div style=\"width:275px;height:100px:\">" + new_location + "<br><br>" + "<a href=\"#\" id=\""+element+"\" title=\""+new_location+"\" class=\"gmap-remove\">Remove from Map</a></div>");
		
		gmap_coords.val(new_location + gmap_coords.val());
	});
					
	google.maps.event.addListener(marker, 'click', function(event) {
		infowindow.open(gmap, marker);					
	});
	
	gmap_coords.val(location + gmap_coords.val());
	
	gmap_marker_count++;
}

google.maps.event.addListener(gmap, 'dblclick', function(event) {
			       	
	gmap_add_marker(event.latLng);
	
});
			
$(".gmap-remove").live("click", function() {
	
	var $t 		= $(this);
	var id 		= String($t.attr("id"));
	var title 	= $t.attr("title");
					
	gmap_markers[id].setMap(null);
	
	gmap_coords.val(gmap_coords.val().replace(title, ""));
	
	return false;
});
		
$("#gmap_address").keypress(function(event) {
	if(event.keyCode == 13)	{	
		$("#gmap_submit").click();
		return false;
	}
});
						
$("#gmap_submit").click(function() {
	(function () {  /* Fixes a known bug with Google's API with displaying the InfoWindow(s) */
		var address = $("#gmap_address").val();
		
		gmap_geocoder.geocode( { 'address': address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				
				var coord = results[0].geometry.location;
				
	    		gmap_bounds.extend(coord);
	    		gmap.fitBounds(gmap_bounds);
		
				gmap_add_marker(coord);
				
			} else {
				alert("Geocode was not successful for the following reason: " + status);
			}
		});
	})();
});