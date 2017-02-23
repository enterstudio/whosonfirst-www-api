<?php

	##############################################################################

	function api_utils_ensure_pagination_args(&$args){

		if ($page = request_int32("page")){
			$args['page'] = $page;
		}

		if ($per_page = request_int32("per_page")){
			$args['per_page'] = $per_page;
		}

		if (! $args['page']){
			$args['page'] = 1;
		}

		if (! $args['per_page']){
			$args['per_page'] = $GLOBALS['cfg']['api_per_page_default'];
		}

		else if ($args['per_page'] > $GLOBALS['cfg']['api_per_page_max']){
			$args['per_page'] = $GLOBALS['cfg']['api_per_page_max'];
		}

		if ($cursor = request_str("cursor")){
			$args['cursor'] = $cursor;
		}

		# note the pass by ref
	}

	##############################################################################

	function api_utils_ensure_pagination_results(&$out, &$pagination){

		$out['next'] = null;
		 
		$method = request_str("method");
		$method_row = $GLOBALS['cfg']['api']['methods'][$method];

		$query = array(
			"method" => $method,
		);

		foreach ($method_row["parameters"] as $p){

			$k = $p["name"];

			if ($v = request_str($k)){
				$query[$k] = $v;
			}
		}

		if ((features_is_enabled("api_extras")) && ($method_row["extras"])){

			if ($e = request_str("extras")){
				$query["extras"] = $e;
			}
		}

		if (isset($pagination['cursor'])){

			# on the one hand it would be good and nice to include these for consistency's
			# sake (with say null values) but I have a feeling their presence will just be
			# confusing... we'll see, I guess (20170222/thisisaaronland)

			# $out['total'] = null;
			# $out['page'] = null;
			# $out['pages'] = null;

			$out['per_page'] = $pagination['per_page'];
			$out['cursor'] = $pagination['cursor'];

			if ($cursor = $out['cursor']){

				$query["cursor"] = $cursor;
				$query = http_build_query($query);

				# $next = $GLOBALS['cfg']['api_abs_root_url'] . $GLOBALS['cfg']['api_endpoint'] . "?{$query}";
				$next = $query;

				$out['next_query'] = $next;
			}
		}

		else {

			$out['total'] = $pagination['total_count'];
			$out['page'] = $pagination['page'];
			$out['per_page'] = $pagination['per_page'];
			$out['pages'] = $pagination['page_count'];

			if (($out['page'] + 1) < $out['pages']){

				$query['page'] = $out['page'] + 1;
				$query = http_build_query($query);

				# $next = $GLOBALS['cfg']['api_abs_root_url'] . $GLOBALS['cfg']['api_endpoint'] . "?{$query}";
				$next = $query;

				$out['next_query'] = $next;
			}
		}

		# note the pass by ref
	}
	
	##############################################################################

	function api_utils_features_ensure_enabled($f){

		if (! features_is_enabled($f)){
			api_output_error(503, "This feature is disabled");
		}
	}

	##############################################################################

	# https://secure.php.net/manual/en/features.file-upload.php
	# https://secure.php.net/manual/en/features.file-upload.post-method.php

	function api_utils_ensure_upload($param, $more=array()){

		$rsp = api_utils_get_upload($param, $more);

		if (! $rsp['ok']){

			$code = $rsp["code"] || 450;
			api_output_error($code, $rsp['error']);
		}

		return $rsp;
	}

	########################################################################

	function api_utils_get_upload($param, $more=array()){

		$defaults = array(
			'ensure_mimetype' => array()
		);

		$more = array_merge($defaults, $more);

		if (! isset($_FILES[$param])){
			return array('ok' => 0, 'error' => "Missing upload parameter", 'code' => 453);
		}

		if (! is_array($_FILES[$param])){
			return array('ok' => 0, 'error' => "Invalid upload parameter", "code" => 454);
		}

		$upload = $_FILES[$param];

		if (! isset($upload['error'])){
			return array('ok' => 0, 'error' => "Missing upload error response", "code" => 455);
		}

		if (is_array($upload['error'])){
			return array('ok' => 0, 'error' => "Invalid upload error response", "code" => 455);
		}

		switch ($upload['error']) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				return array('ok' => 0, 'error' => "Missing body", "code" => 456);
			case UPLOAD_ERR_INI_SIZE:
				# pass
			case UPLOAD_ERR_FORM_SIZE:
				return array('ok' => 0, 'error' => "Exceeded filesize", "code" => 457);
			default:
				return array('ok' => 0, 'error' => "INVISIBLE ERROR CAT", "code" => 450);
		}

		if (! is_uploaded_file($upload['tmp_name'])){
			return array('ok' => 0, 'error' => "Invalid upload file name", "code" => 454);
		}

		if ($upload['size'] == 0){
			return array('ok' => 0, 'error' => "Invalid file size", "code" => 454);
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $upload['tmp_name']);

		if (count($more['ensure_mimetype'])){

			if (! in_array($mime, $more['ensure_mimetype'])){
				return array('ok' => 0, 'error' => "Invalid mime type", "code" => 458);
			}
		}

		return array('ok' => 1, 'upload' => $upload, 'mimetype' => $mime);
	}

	########################################################################

	# the end
