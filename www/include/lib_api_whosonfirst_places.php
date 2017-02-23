<?php

	loadlib("whosonfirst_placetypes");
	
	loadlib("whosonfirst_places");
	loadlib("whosonfirst_spatial");
	loadlib("whosonfirst_pip");

	loadlib("api_whosonfirst_output");
	loadlib("api_whosonfirst_utils");

	loadlib("geo_utils");

	########################################################################

	function api_whosonfirst_places_search(){

		$q = request_str("q");

		$filters = api_whosonfirst_utils_search_filters();

		if (($q == "") && (count($filters) <= 1)){
			api_output_error(452, "E_INSUFFICIENT_QUERY");	# maybe 453?
		}

		$args = array();
		api_utils_ensure_pagination_args($args);

		$rsp = whosonfirst_places_search($q, $filters, $args);

		if (! $rsp['ok']){
			api_output_error(500, $rsp['error']);
		}

		$more = array();

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		api_whosonfirst_output_enpublicify($rsp['rows'], $more);
		$pagination = $rsp['pagination'];

		$out = array(
			'results' => $rsp['rows']
		);

		api_utils_ensure_pagination_results($out, $pagination);
		api_output_ok($out);
	}

	########################################################################

	function api_whosonfirst_places_getByLatLon(){

		api_utils_features_ensure_enabled(array(
			"pip"
		));

		$lat = request_float("latitude");
		$lon = request_float("longitude");
		$pt = request_str("placetype");

		if (! $lat){
			api_output_error(453, "Missing latitude");
		}

		if (! $lon){
			api_output_error(453, "Missing longitude");
		}

		if (! geo_utils_is_valid_latitude($lat)){
			api_output_error(454, "Invalid latitude");
		}

		if (! geo_utils_is_valid_longitude($lon)){
			api_output_error(454, "Invalid longitude");
		}
		
		$more = array();
		
		if ($pt){

			if (! whosonfirst_placetypes_is_valid_placetype($pt)){
				api_output_error(454, "Invalid placetype");
			}

			$more["placetype"] = $pt;
		}

		$rsp = whosonfirst_pip_get_by_latlon($lat, $lon, $more);

		if (! $rsp["ok"]){
			api_output_error(500, $rsp["error"]);
		}

		$more = array();

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		$results = array();

		foreach ($rsp["rows"] as $pip_row){
			
			$row = whosonfirst_places_get_by_id($pip_row["Id"]);
			$public = api_whosonfirst_output_enpublicify_single($row, $more);

			$results[] = $public;
		}

		$out = array(
			"results" => $results,
		);

		api_output_ok($out);
	}

	########################################################################

	function api_whosonfirst_places_getInfo(){

		$id = request_int64("id");

		if (! $id){
			api_output_error(453, "Missing 'id' parameter");
		}

		$place = whosonfirst_places_get_by_id($id);

		if (! $place){
			api_output_error(500, "Failed to retrieve ID");
		}

		$more = array();

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		$public = api_whosonfirst_output_enpublicify_single($place, $more);

		$out = array(
			'record' => $public 
		);

		api_output_ok($out);
	}

	########################################################################

	function api_whosonfirst_places_getIntersects(){

		api_utils_features_ensure_enabled(array(
			"spatial", "spatial_intersects"
		));

		$swlat = null;
		$swlon = null;

		$nelat = null;
		$nelon = null;

		if ($wofid = request_int64("id")){
		
			api_output_error(501, "This has not been implemented yet");
		}

		else {

			$swlat = request_float("min_latitude");
			$swlat = trim($swlat);

			if (! $swlat){
				api_output_error(453, "Missing min_latitude");
			}

			if (! geo_utils_is_valid_latitude($swlat)){
				api_output_error(454, "Invalid min_latitude");
			}

			$swlon = request_float("min_longitude");
			$swlon = trim($swlon);

			if (! $swlon){
				api_output_error(453, "Missing min_longitude");
			}

			if (! geo_utils_is_valid_longitude($swlon)){
				api_output_error(454, "Invalid min_longitude");
			}
			
			$nelat = request_float("max_latitude");
			$nelat = trim($nelat);

			if (! $nelat){
				api_output_error(453, "Missing max_latitude");
			}

			if (! geo_utils_is_valid_latitude($nelat)){
				api_output_error(454, "Invalid max_latitude");
			}

			$nelon = request_float("max_longitude");
			$nelon = trim($nelon);

			if (! $nelon){
				api_output_error(453, "Missing max_longitude");
			}

			if (! geo_utils_is_valid_longitude($nelon)){
				api_output_error(454, "Invalid max_longitude");
			}

			if ($swlat > $nelat){
				api_output_error(454, "Impossible min_latitude");
			}

			if ($swlon > $nelon){
				api_output_error(454, "Impossible min_longitude");
			}
		}

		$more = array();

		if ($cursor = request_str("cursor")){

			api_whosonfirst_places_ensure_valid_cursor($cursor);
			$more['cursor'] = $cursor;
		}

		if ($placetype = request_str("placetype")){

			api_whosonfirst_places_ensure_valid_placetype($placetype);
			$more['placetype_id'] = whosonfirst_placetypes_name_to_id($placetype);
		}

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		api_utils_ensure_pagination_args($more);

		$rsp = whosonfirst_spatial_intersects($swlat, $swlon, $nelat, $nelon, $more);

		if (! $rsp['ok']){
			api_output_error(500, $rsp['error']);
		}

		$results = whosonfirst_spatial_inflate_results($rsp);

		$pagination = $rsp['pagination'];

		$more['is_tile38'] = 1;	# because this: https://github.com/whosonfirst/whosonfirst-www-api/issues/8
		api_whosonfirst_output_enpublicify($results, $more);

		$out = array(
			'results' => $results
		);

		api_utils_ensure_pagination_results($out, $pagination);
		api_output_ok($out);
	}

	########################################################################

	function api_whosonfirst_places_getNearby(){

		api_utils_features_ensure_enabled(array(
			"spatial", "spatial_nearby"
		));

		$lat = null;
		$lon = null;

		if ($wofid = request_int64("id")){
		
			api_output_error(501, "This has not been implemented yet");
		}

		else {

			$lat = request_float("latitude");

			if (! $lat){
				api_output_error(453, "Missing latitude");
			}

			if (! geo_utils_is_valid_latitude($lat)){
				api_output_error(454, "Invalid latitude");
			}

			$lon = request_float("longitude");

			if (! $lon){
				api_output_error(453, "Missing longitude");
			}

			if (! geo_utils_is_valid_longitude($lon)){
				api_output_error(454, "Invalid longitude");
			}
		}

		if ($r = request_int32("radius")){

			if (($r < 0) || ($r > 500)){
				api_output_error(454, "Invalid radius");
			}
		}

		else {
			$r = 200;
		}

		# IMPORTANT - WE AREN'T DEALING WITH TILE38 PAGINATION AT ALL YET BECAUSE TILE38
		# USES 'cursors' AND ... YEAH, CURSORS. WE WILL NEED TO FIGURE SOMETHING OUT BUT
		# NOT TODAY (20160811/thisisaaronland)

		$more = array();

		if ($placetype = request_str("placetype")){

			api_whosonfirst_places_ensure_valid_placetype($placetype);
			$more['wof:placetype_id'] = whosonfirst_placetypes_name_to_id($placetype);
		}
		
		if ($cursor = request_str("cursor")){

			api_whosonfirst_places_ensure_valid_cursor($cursor);
			$more['cursor'] = $cursor;
		}

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		api_utils_ensure_pagination_args($more);

		$rsp = whosonfirst_spatial_nearby_latlon($lat, $lon, $r, $more);

		if (! $rsp['ok']){
			api_output_error(500, $rsp['error']);
		}

		$results = whosonfirst_spatial_inflate_results($rsp);

		$pagination = $rsp['pagination'];

		$more['is_tile38'] = 1;	# because this: https://github.com/whosonfirst/whosonfirst-www-api/issues/8
		api_whosonfirst_output_enpublicify($results, $more);

		$out = array(
			'results' => $results
		);

		api_utils_ensure_pagination_results($out, $pagination);
		api_output_ok($out);
	}

	########################################################################

	function api_whosonfirst_places_getRandom(){

		$rsp = whosonfirst_places_get_random();

		if (! $rsp['ok']){
			api_output_error(500, "Failed to retrieve random Who's On First record");
		}

		$more = array();

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		$doc = $rsp['rows'][0];
		$doc = api_whosonfirst_output_enpublicify_single($doc, $more);

		$out = array(
			'record' => $doc		     
		);

		api_output_ok($out);
	}
	
	########################################################################

	function api_whosonfirst_places_getDescendants(){

		$wofid = request_int64("id");

		if (! $wofid){
			api_output_error(453, "Missing 'id' parameter");
		}

		$filters = api_whosonfirst_utils_search_filters();
		$args = array();

		api_utils_ensure_pagination_args($args);

		$rsp = whosonfirst_places_get_descendants($wofid, $filters, $args);

		if (! $rsp['ok']){
			# $details = print_r($rsp, true);
			# error_log($details);
			api_output_error(500, $rsp['error']);
		}

		$more = array();

		if ($extras = request_str("extras")){
			$more["extras"] = $extras;
		}

		api_whosonfirst_output_enpublicify($rsp['rows'], $more);
		$pagination = $rsp['pagination'];

		$out = array(
			'results' => $rsp['rows'],
			# 'query' => $rsp['query'],
		);

		api_utils_ensure_pagination_results($out, $pagination);
		api_output_ok($out);	
	}

	########################################################################

	function api_whosonfirst_places_ensure_valid_placetype($pt){

		if (! whosonfirst_placetypes_is_valid_placetype($pt)){
			api_output_error(454, "Invalid placetype");
		}

		return 1;
	}

	########################################################################

	function api_whosonfirst_places_ensure_valid_cursor($cursor){

		if (! preg_match("/^[0-9]+$/", $cursor)){
			api_output_error(454, "Invalid cursor");
		}

		return 1;
	}
	
	########################################################################
	
	# the end
