<?php

	########################################################################

	loadlib("http_codes");

	#################################################################

	function api_output_ok($rsp=array(), $more=array()){

		$defaults = array(
			"geocoding" => 0,
			"query" => array(),
			"key" => "results",
			"is_singleton" => 0,
		);

		$more = array_merge($defaults, $more);

		$features = array();

		# we assume that someone (or code) earlier up the stack has assigned:
		# ?extras:lbl:latitude,lbl:longitude,geom:latitude,geom:longitude
		# see also: api_whosonfirst_utils_ensure_geojson_extras()

		$swlat = 0.0;
		$swlon = 0.0;		
		$nelat = 0.0;
		$nelon = 0.0;		

		$k = $more["key"];
		$possible = $rsp[ $k ];

		if ($more["is_singleton"]){
			$possible = array($possible);
		}

		foreach ($possible as $pl){

			if (isset($pl["lbl:latitude"]) && isset($pl["lbl:longitude"]) && $pl["lbl:latitude"] && $pl["longitude"]){
				$lat = $pl["lbl:latitude"];
				$lon = $pl["lbl:longitude"];			
			}

			else {
				$lat = $pl["geom:latitude"];
				$lon = $pl["geom:longitude"];			
			}

			$coords = array($lon, $lat);

			$geom = array(
				"type" => "Point",
				"coordinates" => $coords
			);

			$props = $pl;


			if ($more["geocoding"]){		

				# these are required by the mapzen.js geocoder widget

				$props["name"] = $props["wof:name"];
				$props["label"] = $props["wof:name"];
				$props["layer"] = $props["wof:placetype"];
			}

			$feature = array(
				"type" => "Feature",
				"geometry" => $geom,
				"properties" => $props,
			);

			$features[] = $feature;

			if (($swlat == 0.0) || ($lat < $swlat)){
				$swlat = $lat;
			}

			if (($swlon == 0.0) || ($lon < $swlon)){
				$swlon = $lon;
			}

			if (($nelat == 0.0) || ($lat > $nelat)){
				$nelat = $lat;
			}

			if (($nelon == 0.0) || ($lon > $nelon)){
				$nelon = $lon;
			}
		}

		$bbox = array(
			$swlon, $swlat,
			$nelon, $nelat,
		);
		
		$collection = array(
			"type" => "FeatureCollection",
			"features" => $features,
			"bbox" => $bbox,
		);

		if (isset($rsp["next_query"])){

			$pagination = $rsp;
			unset($pagination["places"]);

			$collection["pagination"] = $pagination;
		}
		
		if ($more["geocoding"]){

			$now = time();

			$engine = array(
				"name" => "Who's On First",
				"author" => "Mapzen",
				"version" => "0.1"
			);

			$geocoding = array(
				"version" => "0.2",
				"attribution" => "https://github.com/whosonfirst/whosonfirst-data/blob/master/LICENSE.md",
				"query" => $more["query"],
				"engine" => $engine,
				"timestamps" => $now,
			);

			$collection["geocoding"] = $geocoding;
		}

		api_output_send($collection, $more);
	}

	#################################################################

	function api_output_error($code=450, $msg='', $more=array()){

		$more['is_error'] = 1;

		$err = api_errors_build_error($code, $msg);
		$out = array('error' => $err);

		api_log($out);

		api_output_send($out, $more);
	}

	#################################################################

	function api_output_send($rsp, $more=array()){

		$stat = (isset($more['is_error'])) ? 'error' : 'ok';
		api_log(array('stat' => $stat), 'write');

		api_output_utils_start_headers($rsp, $more);

		if (features_is_enabled("api_cors")){

			if ($origin = $GLOBALS['cfg']['api_cors_allow_origin']){
				header("Access-Control-Allow-Origin: " . htmlspecialchars($origin));
			}
		}

		if (! request_isset("inline")){
			header("Content-Type: text/json");
		}

		$json = json_encode($rsp);
		header("Content-Length: " . strlen($json));

		echo $json;
		exit();
	}

	########################################################################

	# the end
