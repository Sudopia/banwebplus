<?php

require_once(dirname(__FILE__)."/globals.php");
require_once(dirname(__FILE__)."/db_query.php");

if (isset($_GET["action"])) {
		executeAction($_GET["action"]);
} else {
		drawOptions();
}

function executeAction($s_action) {
	if ($s_action == "save") {
			saveTables();
	} else {
			loadTables();
	}
}

function drawOptions() {
	echo "<form action='' method='GET'><input type='hidden' name='action' value='save'></input><input type='submit' value='Save Tables'></input></form>
<form action='' method='GET'><input type='hidden' name='action' value='load'></input><input type='submit' value='Load Tables'></input></form>";
}

function saveTables() {
	$a_tables = getTables();
	$s_tables = serialize($a_tables);
	$filename = dirname(__FILE__)."/../database_desc.txt";
	file_put_contents($filename, $s_tables);
	echo "<pre>saved to file ".realpath($filename).":\n\nmodtime:\n".date("Y-m-d H:i:s",filemtime($filename))." (current time ".date("Y-m-d H:i:s").")\n\ncontents:\n".file_get_contents($filename)."</pre>";
}

function loadTables() {
	$filename = dirname(__FILE__)."/../database_desc.txt";
	$a_file_tables = unserialize(file_get_contents($filename));
	$a_tables = getTables();
	updateTables($a_tables, $a_file_tables);
}

function updateTables($a_old_tables, $a_new_tables) {

	global $maindb;
	echo "<pre>";
	
	// index current tables by name,
	// and their columns by name,
	// and add a "visited" marker so as to know if the column needs to be deleted
	$a_tables = array();
	foreach($a_old_tables as $a_table) {
			foreach($a_table["columns"] as $k=>$a_column) {
					unset($a_table["columns"][$k]);
					$a_table["columns"][$a_column["name"]] = array_merge($a_column, array("visited"=>0));
			}
			$a_tables[$a_table["Table"]] = $a_table;
	}
	
	// check for non-existant tables
	foreach($a_new_tables as $k=>$a_table) {
			if (!isset($a_tables[$a_table["Table"]])) {
					db_query(replace("CREATE TABLE ", "CREATE TABLE `{$maindb}` ", $a_table["Create Table"]), 1);
					echo "\n";
					unset($a_new_tables[$k]);
			}
	}

	// all other tables are either the same or need to be updated
	// check for tables that need to be updated
	foreach($a_new_tables as $a_table) {
			$s_tablename = $a_table["Table"];
			$a_curr_table = $a_tables[$s_tablename];
			$a_curr_cols = $a_curr_table["columns"];
			
			// check for columns that need to be updated
			// or columns that don't need to be updated
			foreach($a_table["columns"] as $col_key=>$a_column) {
					$s_colname = $a_column["name"];
					if (isset($a_curr_cols[$s_colname])) {
							if ($a_curr_cols[$s_colname]["desc"] != $a_column["desc"]) {
									db_query("ALTER TABLE `{$maindb}`.`[table]` MODIFY COLUMN `[colname]` [desc]", array("table"=>$s_tablename, "colname"=>$s_colname, "desc"=>$a_column["desc"]), 1);
									echo "\n";
							}
							unset($a_table["columns"][$col_key]);
							unset($a_curr_cols[$s_colname]);
					}
			}

			// check for columns that need to be deleted
			foreach($a_curr_cols as $a_curr_column) {
					$b_found = FALSE;
					$s_colname = $a_curr_column["name"];
					foreach($a_table["columns"] as $col_key=>$a_column) {
							if ($s_colname == $a_column["name"]) {
									$b_found = TRUE;
									break;
							}
					}
					if (!$b_found) {
							db_query("ALTER TABLE `{$maindb}`.`[table]` DROP COLUMN [colname]", array("table"=>$s_tablename, "colname"=>$s_colname), 1);
							echo "\n";
					}
			}
			
			// check for columns that need to be created
			$s_after = "";
			foreach($a_table["columns"] as $col_key=>$a_column) {
					$s_colname = $a_column["name"];
					db_query("ALTER TABLE ADD COLUMN [colname] [desc] [after]", array("colname"=>$s_colname, "desc"=>$a_column["desc"], "after"=>$s_after), 1);
					echo "\n";
					$s_after = "AFTER {$s_colname}";
			}

			// check for keys to modify
			foreach($a_table["keys"] as $k=>$s_key) {
					$b_found = FALSE;
					
					echo "{$s_key}\n";
					
					// does the key already exist?
					foreach($a_curr_table["keys"] as $s_curr_key) {
							if ($s_curr_key == $s_key) {
									$b_found = TRUE;
									break;
							}
					}
					
					// it doesn't! Create it!
					if (!$b_found) {
							$s_keytype = (strpos($s_key, "PRIMARY") === 0) ? "PRIMARY KEY" : "KEY";
							$a_keyparts = explode("`", $s_key);
							$s_keyname = $a_keyparts[1];
							db_query("ALTER TABLE `{$maindb}`.`[table]` ADD {$s_keytype} '[keyname]'", array("table"=>$s_tablename, "keyname"=>$s_keyname), 1);
							echo "\n";
					}
			}
	}

	echo "</pre>";
}

function getTables() {
	global $maindb;
	$a_tables = db_query("SHOW TABLES IN `[maindb]`", array("maindb"=>$maindb));
	$a_retval = array();
	for($i = 0; $i < count($a_tables); $i++) {
			$s_tablename = $a_tables[$i]["Tables_in_{$maindb}"];
			$a_retval[] = getTableDescription($s_tablename);
	}
	return $a_retval;
}

function getTableDescription($s_tablename) {
	global $maindb;
	$a_create = db_query("SHOW CREATE TABLE `[maindb]`.`[table]`", array("maindb"=>$maindb, "table"=>$s_tablename));
	$a_create = $a_create[0];
	$a_desc = explode("\n", $a_create["Create Table"]);
	$a_vals = array("columns"=>array(), "keys"=>array());
	foreach($a_desc as $k=>$s_desc) {
			$s_line = trim($s_desc);
			if (strpos($s_line, "CREATE TABLE ") === 0 || strpos($s_line, ") ENGINE=MyISAM ") === 0) {
					unset($a_desc[$k]);
					continue;
			}
			if (strpos($s_line, "KEY ") !== FALSE) {
					$a_vals["keys"][] = trim(str_replace(",", "", $s_line));
			} else {
					$a_column = explode("`", $s_line);
					$s_colname = trim($a_column[1]);
					$s_coldesc = trim(str_replace(",", "",$a_column[2]));
					$a_vals["columns"][] = array("name"=>$s_colname, "desc"=>$s_coldesc);
			}
	}
	$a_create = array_merge($a_create, $a_vals);
	return $a_create;
}

?>