<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2011 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

global $config;

if (is_ajax()) {

	if (! check_acl ($config["id_user"], 0, "ER")) {
		db_pandora_audit("ACL Violation", "Trying to access event viewer");
		return;
	}

	$get_last_events = (bool) get_parameter("get_last_events");
	if ($get_last_events) {
		
		require_once ('include/functions_io.php');
		require_once ('include/functions_tags.php');

		$limit = (int) get_parameter("limit", 5);

		$tags_condition = tags_get_acl_tags($config['id_user'], 0, 'ER', 'event_condition', 'AND');
		$filter = "estado<>1 $tags_condition";

		switch ($config["dbtype"]) {
			case "mysql":
			case "postgresql":
					$sql = sprintf ("SELECT id_agente, evento, utimestamp
									FROM tevento
									WHERE %s
									ORDER BY utimestamp DESC LIMIT %d",
									$filter, $limit);
				break;
			case "oracle":
					$sql = sprintf ("SELECT *
									FROM tevento
									WHERE %s
										AND rownum <= %d
									ORDER BY utimestamp DESC",
									$filter, $limit);
				break;
		}
		$result = db_get_all_rows_sql ($sql);

		$events = array();
		if (! empty($result)) {
			foreach ($result as $key => $value) {
				$event = array();
				$event['agent'] = (empty($value['id_agente'])) ? "System" : agents_get_name($value['id_agente']);
				$event['text'] = io_safe_output($value['evento']);
				$event['date'] = date(io_safe_output($config['date_format']), $value['utimestamp']);
				$events[] = $event;
			}
		} else {
			sleep(5);
		}

		echo json_encode($events);
		return;
	}

	return;
}

?>