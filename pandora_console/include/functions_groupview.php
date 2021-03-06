<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2011 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

include_once ("include/functions_groups.php");
include_once ("include/functions_tags.php");

function groupview_get_all_data ($id_user = false, $user_strict = false, $acltags, $returnAllGroup = false, $agent_filter = array(), $module_filter = array()) {
	global $config;
	if ($id_user == false) {
		$id_user = $config['id_user'];
	}

	$user_groups = array();
	$groups_without_tags = array();
	foreach ($acltags as $group => $tags) {
		if ($user_strict) { //Remove groups with tags
			$groups_without_tags[$group] = $group;
		}
	}

	if ($user_strict) {
		$user_groups_ids = implode(',', array_keys($groups_without_tags));
	}
	else {
		$user_groups_ids = implode(',', array_keys($acltags));
	}

	if (!empty($user_groups_ids)) {
		switch ($config["dbtype"]) {
			case "mysql":
				$list_groups = db_get_all_rows_sql("
					SELECT *
					FROM tgrupo
					WHERE id_grupo IN (" . $user_groups_ids . ")
					ORDER BY nombre COLLATE utf8_general_ci ASC");
				break;
			case "postgresql":
				$list_groups = db_get_all_rows_sql("
					SELECT *
					FROM tgrupo
					WHERE id_grupo IN (" . $user_groups_ids . ")
					ORDER BY nombre ASC");
				break;
			case "oracle":
				$list_groups = db_get_all_rows_sql("
					SELECT *
					FROM tgrupo
					WHERE id_grupo IN (" . $user_groups_ids . ")
					ORDER BY nombre ASC");
				break;
		}
	}
	$list = array();
	$list['_monitors_critical_'] = 0;
	$list['_monitors_warning_'] = 0;
	$list['_monitors_unknown_'] = 0;
	$list['_monitors_not_init_'] = 0;
	$list['_monitors_ok_'] = 0;
	if ($list_groups == false) {
		$list_groups = array();
	}

	/*
	 * Agent cache for metaconsole.
	 * Retrieve the statistic data from the cache table.
	 */
	if (!$user_strict && is_metaconsole() && !empty($list_groups)) {
		$cache_table = 'tmetaconsole_agent';

		$sql_stats = "SELECT id_grupo, COUNT(id_agente) AS agents_total,
						SUM(total_count) AS monitors_total,
						SUM(normal_count) AS monitors_ok,
						SUM(warning_count) AS monitors_warning,
						SUM(critical_count) AS monitors_critical,
						SUM(unknown_count) AS monitors_unknown,
						SUM(notinit_count) AS monitors_not_init,
						SUM(fired_count) AS alerts_fired
					  FROM $cache_table
					  WHERE disabled = 0
					  	AND id_grupo IN ($user_groups_ids)
					  GROUP BY id_grupo";

		$data_stats = db_get_all_rows_sql($sql_stats);

		$sql_stats_unknown = "SELECT id_grupo, COUNT(id_agente) AS agents_unknown
							  FROM $cache_table
							  WHERE disabled = 0
							  	AND id_grupo IN ($user_groups_ids)
							  	AND critical_count = 0
							  	AND warning_count = 0
							  	AND unknown_count > 0
							  GROUP BY id_grupo";
		$data_stats_unknown = db_get_all_rows_sql($sql_stats_unknown);

		$sql_stats_not_init = "SELECT id_grupo, COUNT(id_agente) AS agents_not_init
							  FROM $cache_table
							  WHERE disabled = 0
							  	AND id_grupo IN ($user_groups_ids)
							  	AND (total_count = 0 OR total_count = notinit_count)
							  GROUP BY id_grupo";
		$data_stats_not_init = db_get_all_rows_sql($sql_stats_not_init);

		$stats_by_group = array();
		if (!empty($data_stats)) {
			foreach ($data_stats as $value) {
				$group_id = (int) $value['id_grupo'];

				$stats = array();
				$stats['agents_total'] = (int) $value['agents_total'];
				$stats['monitors_total'] = (int) $value['monitors_total'];
				$stats['monitors_ok'] = (int) $value['monitors_ok'];
				$stats['monitors_warning'] = (int) $value['monitors_warning'];
				$stats['monitors_critical'] = (int) $value['monitors_critical'];
				$stats['monitors_unknown'] = (int) $value['monitors_unknown'];
				$stats['monitors_not_init'] = (int) $value['monitors_not_init'];
				$stats['alerts_fired'] = (int) $value['alerts_fired'];
				$stats_by_group[$group_id] = $stats;
			}

			if (!empty($stats_by_group)) {
				if (!empty($data_stats_unknown)) {
					foreach ($data_stats_unknown as $value) {
						$group_id = (int) $value['id_grupo'];
						if (isset($stats_by_group[$group_id])) {
							$stats_by_group[$group_id]['agents_unknown'] = (int) $value['agents_unknown'];
						}
					}
				}
				if (!empty($data_stats_not_init)) {
					foreach ($data_stats_not_init as $value) {
						$group_id = (int) $value['id_grupo'];
						if (isset($stats_by_group[$group_id])) {
							$stats_by_group[$group_id]['agents_not_init'] = (int) $value['agents_not_init'];
						}
					}
				}
				if (!empty($data_stats_ok)) {
					foreach ($data_stats_ok as $value) {
						$group_id = (int) $value['id_grupo'];
						if (isset($stats_by_group[$group_id])) {
							$stats_by_group[$group_id]['agents_ok'] = (int) $value['agents_ok'];
						}
					}
				}
				if (!empty($data_stats_warning)) {
					foreach ($data_stats_warning as $value) {
						$group_id = (int) $value['id_grupo'];
						if (isset($stats_by_group[$group_id])) {
							$stats_by_group[$group_id]['agents_warning'] = (int) $value['agents_warning'];
						}
					}
				}
				if (!empty($data_stats_critical)) {
					foreach ($data_stats_critical as $value) {
						$group_id = (int) $value['id_grupo'];
						if (isset($stats_by_group[$group_id])) {
							$stats_by_group[$group_id]['agents_critical'] = (int) $value['agents_critical'];
						}
					}
				}
			}
		}
	}

	if (!$user_strict && is_metaconsole()) { // Agent cache
		$group_stat = array();
		foreach ($list_groups as $value) {
			$id = (int) $value['id_grupo'];
			if (isset($stats_by_group[$id]))
				$group_stat = $stats_by_group[$id];

			$list['_agents_unknown_'] += isset($group_stat['agents_unknown']) ? $group_stat['agents_unknown'] : 0;
			$list['_monitors_alerts_fired_'] += isset($group_stat['alerts_fired']) ? $group_stat['alerts_fired'] : 0;
			$list['_total_agents_'] += isset($group_stat['agents_total']) ? $group_stat['agents_total'] : 0;

			// This fields are not in database
			$list['_monitors_ok_'] = isset($group_stat['monitors_ok']) ? $group_stat['monitors_ok'] : 0;
			$list['_monitors_critical_'] += isset($group_stat['monitors_critical']) ? $group_stat['monitors_critical'] : 0;
			$list['_monitors_warning_'] += isset($group_stat['monitors_warning']) ? $group_stat['monitors_warning'] : 0;
			$list['_monitors_unknown_'] += isset($group_stat['monitors_unknown']) ? $group_stat['monitors_unknown'] : 0;
			$list['_monitors_not_init_'] += isset($group_stat['monitors_not_init']) ? $group_stat['monitors_not_init'] : 0;
			$list['_agents_not_init_'] += isset($group_stat['agents_not_init']) ? $group_stat['agents_not_init'] : 0;

			$list['_agents_ok_'] = isset($group_stat['agents_ok']) ? $group_stat['agents_ok'] : 0;
			$list['_agents_warning_'] += isset($group_stat['agents_warning']) ? $group_stat['agents_warning'] : 0;
			$list['_agents_critical_'] += isset($group_stat['agents_critical']) ? $group_stat['agents_critical'] : 0;
			$list['_monitors_alerts_'] +=  isset($group_stat['alerts']) ? $group_stat['alerts'] : 0;;

			$list["_monitor_alerts_fire_count_"] = $group_stat["alerts_fired"];
			$list["_total_checks_"] += $group_stat["modules"];
			$list["_total_alerts_"] += $group_stat["alerts"];
		}
		// Get total count of monitors for this group, except disabled.
		$list["_monitor_checks_"] = $list["_monitors_not_init_"] + $list["_monitors_unknown_"] + $list["_monitors_warning_"] + $list["_monitors_critical_"] + $list["_monitors_ok_"];

		// Calculate not_normal monitors
		$list["_monitor_not_normal_"] = $list[$i]["_monitor_checks_"] - $list["_monitors_ok_"];

		if ($list["_monitor_not_normal_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_monitor_health_"] = format_numeric (100 - ($list["_monitor_not_normal_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_monitor_health_"] = 100;
		}

		if ($list["_monitors_not_init_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_module_sanity_"] = format_numeric (100 - ($list["_monitors_not_init_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_module_sanity_"] = 100;
		}

		if (isset($list[$i]["_alerts_"])) {
			if ($list["_monitors_alerts_fired_"] > 0 && $list["_alerts_"] > 0) {
				$list["_alert_level_"] = format_numeric (100 - ($list["_monitors_alerts_fired_"] / ($list["_alerts_"] / 100)), 1);
			}
			else {
				$list["_alert_level_"] = 100;
			}
		}
		else {
			$list["_alert_level_"] = 100;
			$list["_alerts_"] = 0;
		}

		$list["_monitor_bad_"] = $list["_monitors_critical_"] + $list["_monitors_warning_"];

		if ($list["_monitor_bad_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_global_health_"] = format_numeric (100 - ($list["_monitor_bad_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_global_health_"] = 100;
		}

		$list["_server_sanity_"] = format_numeric (100 - $list["_module_sanity_"], 1);
	}
	else if (($config["realtimestats"] == 0) && !$user_strict) {

		$group_stat = db_get_all_rows_sql ("SELECT
			SUM(ta.normal_count) as normal, SUM(ta.critical_count) as critical,
			SUM(ta.warning_count) as warning,SUM(ta.unknown_count) as unknown,
			SUM(ta.notinit_count) as not_init, SUM(fired_count) as alerts_fired
			FROM tagente ta
			WHERE id_grupo IN ($user_groups_ids)");

		$list['_agents_unknown_'] = $group_stat[0]["unknown"];
		$list['_monitors_alerts_fired_'] = $group_stat[0]["alerts_fired"];

		$list['_monitors_ok_'] = $group_stat[0]["normal"];
		$list['_monitors_warning_'] = $group_stat[0]["warning"];
		$list['_monitors_critical_'] = $group_stat[0]["critical"];
		$list['_monitors_unknown_'] = $group_stat[0]["unknown"];
		$list['_monitors_not_init_'] = $group_stat[0]["not_init"];
		$total_agentes = agents_get_agents (false, array('count(*) as total_agents'), 'AR',false, false);
		$list['_total_agents_'] = $total_agentes[0]['total_agents'];
		$list["_monitor_alerts_fire_count_"] = $group_stat[0]["alerts_fired"];

		$list['_monitors_alerts_'] = groupview_monitor_alerts (explode(',',$user_groups_ids), $user_strict,explode(',',$user_groups_ids));
		// Get total count of monitors for this group, except disabled.
		$list["_monitor_checks_"] = $list["_monitors_not_init_"] + $list["_monitors_unknown_"] + $list["_monitors_warning_"] + $list["_monitors_critical_"] + $list["_monitors_ok_"];

		// Calculate not_normal monitors
		$list["_monitor_not_normal_"] = $list["_monitor_checks_"] - $list["_monitors_ok_"];

		if ($list["_monitor_not_normal_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_monitor_health_"] = format_numeric (100 - ($list["_monitor_not_normal_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_monitor_health_"] = 100;
		}

		if ($list["_monitors_not_init_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_module_sanity_"] = format_numeric (100 - ($list["_monitors_not_init_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_module_sanity_"] = 100;
		}

		if (isset($list["_alerts_"])) {
			if ($list["_monitors_alerts_fired_"] > 0 && $list["_alerts_"] > 0) {
				$list["_alert_level_"] = format_numeric (100 - ($list["_monitors_alerts_fired_"] / ($list["_alerts_"] / 100)), 1);
			}
			else {
				$list["_alert_level_"] = 100;
			}
		}
		else {
			$list["_alert_level_"] = 100;
			$list["_alerts_"] = 0;
		}

		$list["_monitor_bad_"] = $list["_monitors_critical_"] + $list["_monitors_warning_"];

		if ($list["_monitor_bad_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_global_health_"] = format_numeric (100 - ($list["_monitor_bad_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_global_health_"] = 100;
		}

		$list["_server_sanity_"] = format_numeric (100 - $list["_module_sanity_"], 1);
	}
	else {
		if ($user_strict) {
			$_tag_condition = 'AND ' . tags_get_acl_tags_module_condition($acltags,'tae');
		}
		else
			$_tag_condition = '';

		$result_list = db_get_all_rows_sql("SELECT COUNT(*) as contado, estado
					FROM tagente_estado tae INNER JOIN tagente ta
						ON tae.id_agente = ta.id_agente
							AND ta.disabled = 0
							AND ta.id_grupo IN ( $user_groups_ids )
					INNER JOIN tagente_modulo tam
						ON tae.id_agente_modulo = tam.id_agente_modulo
							AND tam.disabled = 0
					$_tag_condition
					GROUP BY estado");

		foreach ($result_list as $result) {
			switch ($result['estado']) {
				case AGENT_MODULE_STATUS_CRITICAL_ALERT:
					break;
				case AGENT_MODULE_STATUS_CRITICAL_BAD:
					$list['_monitors_critical_'] += (int)$result['contado'];
					break;
				case AGENT_MODULE_STATUS_WARNING_ALERT:
					break;
				case AGENT_MODULE_STATUS_WARNING:
					$list['_monitors_warning_'] += (int)$result['contado'];
					break;
				case AGENT_MODULE_STATUS_UNKNOWN:
					$list['_monitors_unknown_'] += (int)$result['contado'];
					break;
				case AGENT_MODULE_STATUS_NO_DATA:
				case AGENT_MODULE_STATUS_NOT_INIT:
					$list['_monitors_not_init_'] += (int)$result['contado'];
					break;
				case AGENT_MODULE_STATUS_NORMAL_ALERT:
					break;
				case AGENT_MODULE_STATUS_NORMAL:
					$list['_monitors_ok_'] += (int)$result['contado'];
					break;
			}
		}

		$list['_monitors_alerts_fired_'] = groupview_monitor_fired_alerts (explode(',',$user_groups_ids), $user_strict,explode(',',$user_groups_ids));
		$list['_monitors_alerts_'] = groupview_monitor_alerts (explode(',',$user_groups_ids), $user_strict,explode(',',$user_groups_ids));

		$total_agentes = agents_get_agents (false, array('count(*) as total_agents'), 'AR',false, false);
		$list['_total_agents_'] = $total_agentes[0]['total_agents'];

		$list["_monitor_checks_"] = $list["_monitors_not_init_"] + $list["_monitors_unknown_"] + $list["_monitors_warning_"] + $list["_monitors_critical_"] + $list["_monitors_ok_"];

		// Calculate not_normal monitors
		$list["_monitor_not_normal_"] = $list["_monitor_checks_"] - $list["_monitors_ok_"];
	}

	return $list;
}

function groupview_status_modules_agents($id_user = false, $user_strict = false, $access = 'AR', $force_group_and_tag = true) {
	global $config;

	if ($id_user == false) {
		$id_user = $config['id_user'];
	}

	$acltags = tags_get_user_module_and_tags ($id_user, $access, $user_strict);

	// If using metaconsole, the strict users will use the agent table of every node
	if (is_metaconsole() && $user_strict) {
		$servers = metaconsole_get_servers();

		$result_list = array ();
		foreach ($servers as $server) {

			$server_list = groupview_get_all_data($id_user, $user_strict,
				$acltags);

			foreach ($server_list as $server_item) {
				if (! isset ($result_list[$server_item['_name_']])) {

					$result_list[$server_item['_name_']] = $server_item;
				}
				else {
					$result_list[$server_item['_name_']]['_monitors_ok_'] += $server_item['_monitors_ok_'];
					$result_list[$server_item['_name_']]['_monitors_critical_'] += $server_item['_monitors_critical_'];
					$result_list[$server_item['_name_']]['_monitors_warning_'] += $server_item['_monitors_warning_'];
					$result_list[$server_item['_name_']]['_agents_unknown_'] += $server_item['_agents_unknown_'];
					$result_list[$server_item['_name_']]['_total_agents_'] += $server_item['_total_agents_'];
					$result_list[$server_item['_name_']]['_monitors_alerts_fired_'] += $server_item['_monitors_alerts_fired_'];

					$result_list[$server_item['_name_']]['_agents_ok_'] += $server_item['_agents_ok_'];
					$result_list[$server_item['_name_']]['_agents_critical_'] += $server_item['_agents_critical_'];
					$result_list[$server_item['_name_']]['_agents_warning_'] += $server_item['_agents_warning_'];
					$result_list[$server_item['_name_']]['_monitors_alerts_'] += $server_item['_monitors_alerts_'];

					$result_list[$server_item['_name_']]["_monitor_checks_"] += $server_item["_monitor_checks_"];
					$result_list[$server_item['_name_']]["_monitor_not_normal_"] += $server_item["_monitor_not_normal_"];
					$result_list[$server_item['_name_']]["_monitor_health_"] += $server_item["_monitor_health_"];
					$result_list[$server_item['_name_']]["_module_sanity_"] += $server_item["_module_sanity_"];
					$result_list[$server_item['_name_']]["_alerts_"] += $server_item["_alerts_"];
					$result_list[$server_item['_name_']]["_alert_level_"] += $server_item["_alert_level_"];
					$result_list[$server_item['_name_']]["_monitor_bad_"] += $server_item["_monitor_bad_"];
					$result_list[$server_item['_name_']]["_global_health_"] += $server_item["_global_health_"];
					$result_list[$server_item['_name_']]["_server_sanity_"] += $server_item["_server_sanity_"];
					$result_list[$server_item['_name_']]["_monitor_alerts_fire_count_"] += $server_item["_monitor_alerts_fire_count_"];
					$result_list[$server_item['_name_']]["_total_checks_"] += $server_item["_total_checks_"];
					$result_list[$server_item['_name_']]["_total_alerts_"] += $server_item["_total_alerts_"];
					}
				}
		}

		return $result_list;
	}
	else {

		$result_list = groupview_get_all_data ($id_user, $user_strict,
				$acltags);
		return $result_list;
	}
}

function groupview_monitor_alerts ($group_array, $strict_user = false, $id_group_strict = false) {

	// If there are not groups to query, we jump to nextone

	if (empty ($group_array)) {
		return 0;

	}
	else if (!is_array ($group_array)) {
		$group_array = array($group_array);
	}

	$group_clause = implode (",", $group_array);
	$group_clause = "(" . $group_clause . ")";

	if ($strict_user) {
		$group_clause_strict = implode (",", $id_group_strict);
		$group_clause_strict = "(" . $group_clause_strict . ")";
		$sql = "SELECT COUNT(talert_template_modules.id)
			FROM talert_template_modules, tagente_modulo, tagente_estado, tagente
			WHERE tagente.id_grupo IN $group_clause_strict AND tagente_modulo.id_agente = tagente.id_agente
				AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo
				AND talert_template_modules.id_agent_module = tagente_modulo.id_agente_modulo";
		$count = db_get_sql ($sql);
		return $count;
	} else {
		//TODO REVIEW ORACLE AND POSTGRES
		return db_get_sql ("SELECT COUNT(talert_template_modules.id)
			FROM talert_template_modules, tagente_modulo, tagente_estado, tagente
			WHERE tagente.id_grupo IN $group_clause AND tagente_modulo.id_agente = tagente.id_agente
				AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo
				AND talert_template_modules.id_agent_module = tagente_modulo.id_agente_modulo");
	}
}

function groupview_monitor_fired_alerts ($group_array, $strict_user = false, $id_group_strict = false) {

	// If there are not groups to query, we jump to nextone

	if (empty ($group_array)) {
		return 0;

	}
	else if (!is_array ($group_array)) {
		$group_array = array($group_array);
	}

	$group_clause = implode (",", $group_array);
	$group_clause = "(" . $group_clause . ")";


	if ($strict_user) {
		$group_clause_strict = implode (",", $id_group_strict);
		$group_clause_strict = "(" . $group_clause_strict . ")";
		$sql = "SELECT COUNT(talert_template_modules.id)
		FROM talert_template_modules, tagente_modulo, tagente_estado, tagente
		WHERE tagente.id_grupo IN $group_clause_strict AND tagente_modulo.id_agente = tagente.id_agente
			AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo
			AND talert_template_modules.id_agent_module = tagente_modulo.id_agente_modulo
			AND times_fired > 0 ";

		$count = db_get_sql ($sql);
		return $count;
	} else {
		//TODO REVIEW ORACLE AND POSTGRES
		return db_get_sql ("SELECT COUNT(talert_template_modules.id)
			FROM talert_template_modules, tagente_modulo, tagente_estado, tagente
			WHERE tagente.id_grupo IN $group_clause AND tagente_modulo.id_agente = tagente.id_agente
				AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo
				AND talert_template_modules.id_agent_module = tagente_modulo.id_agente_modulo
				AND times_fired > 0");
	}

}

function groupview_get_groups_list($id_user = false, $user_strict = false, $access = 'AR', $force_group_and_tag = true, $returnAllGroup = false) {
	global $config;

	if ($id_user == false) {
		$id_user = $config['id_user'];
	}

	$acltags = tags_get_user_module_and_tags ($id_user, $access, $user_strict);

	// If using metaconsole, the strict users will use the agent table of every node
	if (is_metaconsole() && $user_strict) {
		$servers = metaconsole_get_servers();

		$result_list = array ();
		foreach ($servers as $server) {

			if (metaconsole_connect($server) != NOERR) {
				continue;
			}
			$server_list = groupview_get_data ($id_user, $user_strict,
				$acltags, $returnAllGroup);

			foreach ($server_list as $server_item) {
				if (! isset ($result_list[$server_item['_name_']])) {

					$result_list[$server_item['_name_']] = $server_item;
				}
				else {
					$result_list[$server_item['_name_']]['_monitors_ok_'] += $server_item['_monitors_ok_'];
					$result_list[$server_item['_name_']]['_monitors_critical_'] += $server_item['_monitors_critical_'];
					$result_list[$server_item['_name_']]['_monitors_warning_'] += $server_item['_monitors_warning_'];
					$result_list[$server_item['_name_']]['_agents_unknown_'] += $server_item['_agents_unknown_'];
					$result_list[$server_item['_name_']]['_total_agents_'] += $server_item['_total_agents_'];
					$result_list[$server_item['_name_']]['_monitors_alerts_fired_'] += $server_item['_monitors_alerts_fired_'];
				}
			}
			metaconsole_restore_db();

		}

		return $result_list;
	}
	// If using metaconsole, the not strict users will use the metaconsole's agent cache table
	else {
		$result_list = groupview_get_data ($id_user, $user_strict, $acltags,
			$returnAllGroup);

		return $result_list;
	}
}

function groupview_get_data ($id_user = false, $user_strict = false, $acltags, $returnAllGroup = false, $agent_filter = array(), $module_filter = array()) {
	global $config;
	if ($id_user == false) {
		$id_user = $config['id_user'];
	}

	$user_groups = array();
	$user_tags = array();
	$groups_without_tags = array();
	foreach ($acltags as $group => $tags) {
		if ($user_strict) { //Remove groups with tags
			if ($tags == '') {
				$groups_without_tags[$group] = $group;
			}
		}
		$user_groups[$group] = groups_get_name($group);
		if ($tags != '') {
			$tags_group = explode(',', $tags);

			foreach ($tags_group as $tag) {
				$user_tags[$tag] = tags_get_name($tag);
			}
		}
	}

	$user_groups_ids = '0,';

	if ($user_strict) {
		$user_groups_ids .= implode(',', array_keys($groups_without_tags));
	}
	else {
		$user_groups_ids .= implode(',', array_keys($acltags));
	}
	if (!empty($user_groups_ids)) {
		if (is_metaconsole()) {
			switch ($config["dbtype"]) {
				case "mysql":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tmetaconsole_agent)
						ORDER BY nombre COLLATE utf8_general_ci ASC");
					break;
				case "postgresql":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tmetaconsole_agent)
						ORDER BY nombre ASC");
					break;
				case "oracle":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tmetaconsole_agent)
						ORDER BY nombre ASC");
					break;
			}
		}
		else {
			switch ($config["dbtype"]) {
				case "mysql":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tagente)
						ORDER BY nombre COLLATE utf8_general_ci ASC");
					break;
				case "postgresql":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tagente)
						ORDER BY nombre ASC");
					break;
				case "oracle":
					$list_groups = db_get_all_rows_sql("
						SELECT *
						FROM tgrupo
						WHERE id_grupo IN (" . $user_groups_ids . ")
						AND id_grupo IN (SELECT id_grupo FROM tagente)
						ORDER BY nombre ASC");
					break;
			}
		}
	}
	//Add the group "All" at first
	$group_all = array('id_grupo'=>0, 'nombre'=>'All', 'icon'=>'', 'parent'=>'', 'propagate'=>0, 'disabled'=>0,
						'custom_id'=>'', 'id_skin'=>0, 'description'=>'', 'contact'=>'', 'other'=>'', 'password'=>'');
	array_unshift($list_groups, $group_all);
	//Takes the parents even without agents, first ids
	$fathers_id = '';
	$list_father_groups = array();
	foreach ($list_groups as $group) {
		if ($group['parent'] != '') {
			$grup = $group['parent'];
			while ($grup != 0) {
				$recursive_fathers = db_get_row_sql ("SELECT parent FROM tgrupo
														WHERE id_grupo = " . $grup);
				$grup = $recursive_fathers['parent'];
				if (!strpos($fathers_id, $grup)) {
					$fathers_id .= ',' . $grup;
				}
			}
			if (!strpos($fathers_id, $group['parent'])) {
				$fathers_id .= ',' . $group['parent'];
			}
		}
	}

	//Eliminate the first comma
	$fathers_id = substr($fathers_id, 1);
	//Takes the parents even without agents, complete groups
	$list_father_groups = db_get_all_rows_sql("
				SELECT *
				FROM tgrupo
				WHERE id_grupo IN (" . $fathers_id . ")
				ORDER BY nombre COLLATE utf8_general_ci ASC");

	//Merges the arrays and eliminates the duplicates groups
	$list_groups = array_merge($list_groups, $list_father_groups);
    $list_groups = groupview_array_unique_multidim($list_groups, 'id_grupo');

	//Order groups (Father-children)
	$ordered_groups = groupview_order_groups_for_parents($list_groups);
	$ordered_ids = array();
	$ordered_ids = groupview_order_group_ids($ordered_groups, $ordered_ids);
	$final_list = array();
	array_push($final_list, $group_all);

	foreach ($ordered_ids as $key) {
		if ($key == 'All') {
			continue;
		}
		$complete_group = db_get_row_sql("
					SELECT *
					FROM tgrupo
					WHERE nombre = '" . $key . "'");
		array_push($final_list, $complete_group);
	}

	$list_groups = $final_list;
	$list = array();
	foreach ($list_groups as $group) {
		$list[$group['id_grupo']]['_name_'] = $group['nombre'];
		$list[$group['id_grupo']]['_id_'] = $group['id_grupo'];
		$list[$group['id_grupo']]['_monitors_critical_'] = 0;
		$list[$group['id_grupo']]['_monitors_warning_'] = 0;
		$list[$group['id_grupo']]['_monitors_unknown_'] = 0;
		$list[$group['id_grupo']]['_monitors_not_init_'] = 0;
		$list[$group['id_grupo']]['_monitors_ok_'] = 0;
	}

	if ($list_groups == false) {
		$list_groups = array();
	}

	if (!$user_strict && is_metaconsole()) { // Agent cache
		foreach ($list_groups as $group) {
			$group_agents = db_get_row_sql("SELECT SUM(warning_count) AS _monitors_warning_,
														SUM(critical_count) AS _monitors_critical_,
														SUM(normal_count) AS _monitors_ok_,
														SUM(unknown_count) AS _monitors_unknown_,
														SUM(notinit_count) AS _monitors_not_init_,
														SUM(fired_count) AS _monitors_alerts_fired_,
														COUNT(*) AS _total_agents_, id_grupo, intervalo,
														ultimo_contacto, disabled
									FROM tmetaconsole_agent WHERE id_grupo = " . $group['id_grupo'] . " GROUP BY id_grupo");
			$list[$group['id_grupo']]['_monitors_critical_'] = (int)$group_agents['_monitors_critical_'];
			$list[$group['id_grupo']]['_monitors_warning_'] = (int)$group_agents['_monitors_warning_'];
			$list[$group['id_grupo']]['_monitors_unknown_'] = (int)$group_agents['_monitors_unknown_'];
			$list[$group['id_grupo']]['_monitors_not_init_'] = (int)$group_agents['_monitors_not_init_'];
			$list[$group['id_grupo']]['_monitors_ok_'] = (int)$group_agents['_monitors_ok_'];

			$list[$group['id_grupo']]['_monitors_alerts_fired_'] = (int)$group_agents['_monitors_alerts_fired_'];

			$list[$group['id_grupo']]['_total_agents_'] = (int)$group_agents['_total_agents_'];

			$list[$group['id_grupo']]["_monitor_checks_"] = $list[$group['id_grupo']]["_monitors_not_init_"] + $list[$group['id_grupo']]["_monitors_unknown_"] + $list[$group['id_grupo']]["_monitors_warning_"] + $list[$group['id_grupo']]["_monitors_critical_"] + $list[$group['id_grupo']]["_monitors_ok_"];

			// Calculate not_normal monitors
			$list[$group['id_grupo']]["_monitor_not_normal_"] = $list["_monitor_checks_"] - $list["_monitors_ok_"];

			$total_agents = $list[$group['id_grupo']]['_total_agents_'];

			if (($group['id_grupo'] != 0) && ($total_agents > 0)) {
				$agents = db_get_all_rows_sql("SELECT warning_count,
													critical_count,
													normal_count,
													unknown_count,
													notinit_count,
													fired_count,
													disabled
												FROM tmetaconsole_agent
												WHERE id_grupo = " . $group['id_grupo'] );
				foreach ($agents as $agent) {
					$total_monitors_init = $agent['warning_count'] + $agent['critical_count'] + $agent['normal_count'];
					if ($total_monitors_init == $agent['notinit_count']) {
						$list[$group['id_grupo']]['_agents_not_init_'] += 1;
					}
					if (($agent['critical_count'] == 0) && ($agent['warning_count'] == 0) && ($group_agents['disabled'] == 0)) {
						if ($agent['unknown_count'] > 0) {
							$list[$group['id_grupo']]['_agents_unknown_'] += 1;
						}
					}
				}
			}
		}
	}
	else if (($config["realtimestats"] == 0) && !$user_strict) {

		$group_stat = db_get_all_rows_sql ("SELECT
			SUM(ta.normal_count) as normal, SUM(ta.critical_count) as critical,
			SUM(ta.warning_count) as warning,SUM(ta.unknown_count) as unknown,
			SUM(ta.notinit_count) as not_init, SUM(fired_count) as alerts_fired
			FROM tagente ta
			WHERE id_grupo IN ($user_groups_ids)");

		$list['_agents_unknown_'] = $group_stat[0]["unknown"];
		$list['_monitors_alerts_fired_'] = $group_stat[0]["alerts_fired"];

		$list['_monitors_ok_'] = $group_stat[0]["normal"];
		$list['_monitors_warning_'] = $group_stat[0]["warning"];
		$list['_monitors_critical_'] = $group_stat[0]["critical"];
		$list['_monitors_unknown_'] = $group_stat[0]["unknown"];
		$list['_monitors_not_init_'] = $group_stat[0]["not_init"];
		$total_agentes = agents_get_agents (false, array('count(*) as total_agents'), 'AR',false, false);
		$list['_total_agents_'] = $total_agentes[0]['total_agents'];
		$list["_monitor_alerts_fire_count_"] = $group_stat[0]["alerts_fired"];

		$list['_monitors_alerts_'] = groupview_monitor_alerts (explode(',',$user_groups_ids), $user_strict,explode(',',$user_groups_ids));
		// Get total count of monitors for this group, except disabled.
		$list["_monitor_checks_"] = $list["_monitors_not_init_"] + $list["_monitors_unknown_"] + $list["_monitors_warning_"] + $list["_monitors_critical_"] + $list["_monitors_ok_"];

		// Calculate not_normal monitors
		$list["_monitor_not_normal_"] = $list["_monitor_checks_"] - $list["_monitors_ok_"];

		if ($list["_monitor_not_normal_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_monitor_health_"] = format_numeric (100 - ($list["_monitor_not_normal_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_monitor_health_"] = 100;
		}

		if ($list["_monitors_not_init_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_module_sanity_"] = format_numeric (100 - ($list["_monitors_not_init_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_module_sanity_"] = 100;
		}

		if (isset($list["_alerts_"])) {
			if ($list["_monitors_alerts_fired_"] > 0 && $list["_alerts_"] > 0) {
				$list["_alert_level_"] = format_numeric (100 - ($list["_monitors_alerts_fired_"] / ($list["_alerts_"] / 100)), 1);
			}
			else {
				$list["_alert_level_"] = 100;
			}
		}
		else {
			$list["_alert_level_"] = 100;
			$list["_alerts_"] = 0;
		}

		$list["_monitor_bad_"] = $list["_monitors_critical_"] + $list["_monitors_warning_"];

		if ($list["_monitor_bad_"] > 0 && $list["_monitor_checks_"] > 0) {
			$list["_global_health_"] = format_numeric (100 - ($list["_monitor_bad_"] / ($list["_monitor_checks_"] / 100)), 1);
		}
		else {
			$list["_global_health_"] = 100;
		}

		$list["_server_sanity_"] = format_numeric (100 - $list["_module_sanity_"], 1);
	}
	else {
		if ($user_strict) {
			$_tag_condition = 'AND ' . tags_get_acl_tags_module_condition($acltags,'tae');
		}
		else{
			$_tag_condition = '';
		}
		foreach ($list_groups as $group) {
			$group_agents = db_get_row_sql("SELECT SUM(warning_count) AS _monitors_warning_,
														SUM(critical_count) AS _monitors_critical_,
														SUM(normal_count) AS _monitors_ok_,
														SUM(unknown_count) AS _monitors_unknown_,
														SUM(notinit_count) AS _monitors_not_init_,
														SUM(fired_count) AS _monitors_alerts_fired_,
														COUNT(*) AS _total_agents_, id_grupo, intervalo,
														ultimo_contacto, disabled
									FROM tagente WHERE id_grupo = " . $group['id_grupo'] . $_tag_condition . " GROUP BY id_grupo");
			$list[$group['id_grupo']]['_monitors_critical_'] = (int)$group_agents['_monitors_critical_'];
			$list[$group['id_grupo']]['_monitors_warning_'] = (int)$group_agents['_monitors_warning_'];
			$list[$group['id_grupo']]['_monitors_unknown_'] = (int)$group_agents['_monitors_unknown_'];
			$list[$group['id_grupo']]['_monitors_not_init_'] = (int)$group_agents['_monitors_not_init_'];
			$list[$group['id_grupo']]['_monitors_ok_'] = (int)$group_agents['_monitors_ok_'];

			$list[$group['id_grupo']]['_monitors_alerts_fired_'] = (int)$group_agents['_monitors_alerts_fired_'];

			$list[$group['id_grupo']]['_total_agents_'] = (int)$group_agents['_total_agents_'];

			$list[$group['id_grupo']]["_monitor_checks_"] = $list[$group['id_grupo']]["_monitors_not_init_"] + $list[$group['id_grupo']]["_monitors_unknown_"] + $list[$group['id_grupo']]["_monitors_warning_"] + $list[$group['id_grupo']]["_monitors_critical_"] + $list[$group['id_grupo']]["_monitors_ok_"];

			// Calculate not_normal monitors
			$list[$group['id_grupo']]["_monitor_not_normal_"] = $list["_monitor_checks_"] - $list["_monitors_ok_"];

			$total_agents = $list[$group['id_grupo']]['_total_agents_'];

			if (($group['id_grupo'] != 0) && ($total_agents > 0)) {
				$agents = db_get_all_rows_sql("SELECT warning_count,
													critical_count,
													normal_count,
													unknown_count,
													notinit_count,
													fired_count,
													disabled
												FROM tagente
												WHERE id_grupo = " . $group['id_grupo'] );
				foreach ($agents as $agent) {
					$total_monitors_init = $agent['warning_count'] + $agent['critical_count'] + $agent['normal_count'];
					if ($total_monitors_init == $agent['notinit_count']) {
						$list[$group['id_grupo']]['_agents_not_init_'] += 1;
					}
					if (($agent['critical_count'] == 0) && ($agent['warning_count'] == 0) && ($group_agents['disabled'] == 0)) {
						if ($agent['unknown_count'] > 0) {
							$list[$group['id_grupo']]['_agents_unknown_'] += 1;
						}
					}
				}
			}
		}

	}

	if ($user_strict) {
		foreach ($user_tags as $group_id => $tag_name) {
			$id = db_get_value('id_tag', 'ttag', 'name', $tag_name);

			$list[$i]['_id_'] = $id;
			$list[$i]['_name_'] = $tag_name;
			$list[$i]['_iconImg_'] = html_print_image ("images/tag_red.png", true, array ("style" => 'vertical-align: middle;'));
			$list[$i]['_is_tag_'] = 1;

			$list[$i]['_total_agents_'] = (int) tags_get_total_agents ($id, $acltags, $agent_filter, $module_filter, $config["realtimestats"]);
			$list[$i]['_agents_unknown_'] = (int) tags_get_unknown_agents ($id, $acltags, $agent_filter, $module_filter, $config["realtimestats"]);
			$list[$i]['_agents_not_init_'] = (int) tags_get_not_init_agents ($id, $acltags, $agent_filter, $module_filter, $config["realtimestats"]);
			$list[$i]['_monitors_ok_'] = (int) tags_get_normal_monitors ($id, $acltags, $agent_filter, $module_filter);
			$list[$i]['_monitors_critical_'] = (int) tags_get_critical_monitors ($id, $acltags, $agent_filter, $module_filter);
			$list[$i]['_monitors_warning_'] = (int) tags_get_warning_monitors ($id, $acltags, $agent_filter, $module_filter);
			$list[$i]['_monitors_not_init_'] = (int) tags_get_not_init_monitors ($id, $acltags, $agent_filter, $module_filter);
			$list[$i]['_monitors_unknown_'] = (int) tags_get_unknown_monitors ($id, $acltags, $agent_filter, $module_filter);
			$list[$i]['_monitors_alerts_fired_'] = tags_monitors_fired_alerts($id, $acltags);

			if ($returnAllGroup) {
				$list[0]['_agents_unknown_'] += $list[$i]['_agents_unknown_'];
				$list[0]['_monitors_alerts_fired_'] += $list[$i]['_monitors_alerts_fired_'];
				$list[0]['_total_agents_'] += $list[$i]['_total_agents_'];
				$list[0]['_monitors_ok_'] += $list[$i]['_monitors_ok_'];
				$list[0]['_monitors_critical_'] += $list[$i]['_monitors_critical_'];
				$list[0]['_monitors_warning_'] += $list[$i]['_monitors_warning_'];
				$list[0]['_monitors_unknown_'] += $list[$i]['_monitors_unknown_'];
				$list[0]['_agents_not_init_'] += $list[$i]['_agents_not_init_'];
				$list[0]['_monitors_not_init_'] += $list[$i]['_monitors_not_init_'];
			}
			if (! defined ('METACONSOLE')) {
				if (($list[$i]['_agents_unknown_'] == 0) && ($list[$i]['_monitors_alerts_fired_'] == 0) && ($list[$i]['_total_agents_'] == 0) && ($list[$i]['_monitors_ok_'] == 0) && ($list[$i]['_monitors_critical_'] == 0) && ($list[$i]['_monitors_warning_'] == 0) && ($list[$i]['_monitors_unknown_'] == 0) && ($list[$i]['_monitors_not_init_'] == 0) && ($list[$i]['_agents_not_init_'] == 0)) {
					unset($list[$i]);
				}
			}
			else {
				if (($list[$i]['_agents_unknown_'] == 0) && ($list[$i]['_monitors_alerts_fired_'] == 0) && ($list[$i]['_total_agents_'] == 0) && ($list[$i]['_monitors_ok_'] == 0) && ($list[$i]['_monitors_critical_'] == 0) && ($list[$i]['_monitors_warning_'] == 0)) {
					unset($list[$i]);
				}
			}
			$i++;
		}
	}

	return $list;
}

//Order the groups by parents
function groupview_order_groups_for_parents ($view_groups) {
	$final_groups = array();
	// Index the groups
	$groups = array();
	foreach ($view_groups as $item) {
		$groups[$item['id_grupo']] = $item;
	}
	// Build the group hierarchy
	foreach ($groups as $id => $group) {
		if (!isset($groups[$id]['parent']))
			continue;
		$parent = $groups[$id]['parent'];
		// Parent exists
		if (isset($groups[$parent])) {
			if (!isset($groups[$parent]['children']))
				$groups[$parent]['children'] = array();
			// Store a reference to the group into the parent
			$groups[$parent]['children'][] = &$groups[$id];

			// This group was introduced into a parent
			$groups[$id]['have_parent'] = true;
		}
	}

	// Sort the children groups
	for ($i = 0; $i < count($groups); $i++) {
		if (isset($groups[$i]['children']))
			usort($groups[$i]['children'], function ($a, $b) {
				return strcmp($a["nombre"], $b["nombre"]);
			});
	}
	// Extract the root groups
	foreach ($groups as $group) {
		if (!$group['have_parent'])
			$final_groups[] = $group;
	}
	// Sort the root groups
	usort($final_groups, function ($a, $b) {
		return strcmp($a["name"], $b["name"]);
	});

	return $final_groups;
}

function groupview_order_group_ids($groups, $ordered_ids){
	foreach ($groups as $group) {
		if (!empty($group['children'])) {
			$ordered_ids[$group['id_grupo']] = $group['nombre'];
			$ordered_ids = groupview_order_group_ids($group['children'], $ordered_ids);
		}
		else {
			$ordered_ids[$group['id_grupo']] = $group['nombre'];
		}
	}
	return $ordered_ids;
}

//Function to eliminate duplicates groups in multidimensional array
function groupview_array_unique_multidim($groups, $key){
    $temp_group = array();
    $i = 0;
    $key_group = array();
    foreach($groups as $group){
        if(!in_array($group[$key],$key_group)){
            $key_group[$i] = $group[$key];
            $temp_group[$i] = $group;
        }
        $i++;
    }
    return $temp_group;
}

?>
