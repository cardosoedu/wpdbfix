<?php

require_once("wp-load.php");
global $wpdb;

$wptables_original = array(
	"commentmeta",
	"comments",
	"links",
	"options",
	"postmeta",
	"posts",
	"terms",
	"termmeta",
	"term_taxonomy",
	"usermeta",
	"users");

function fixZeroId($args) {
	global $wpdb;
	$args = func_get_args();
	$vals = array_values($args);
	$nome_campos = array_keys($vals[1]);
	$tabela = $vals[0];

	$querytotal = "SELECT max($nome_campos[0]) AS last FROM $tabela";
        if($result = $wpdb->get_row($querytotal, ARRAY_A))
		$total = $result['last'];
	else die('Algo deu errado.');
	
	$campo_id = $nome_campos[0];
	$campo_sec = $nome_campos[1];
	$campo_terc = $nome_campos[2];
	
	print("<p>Corrigindo o registro $tabela($campo_id, $campo_sec) com id 0...</p>");
	$query = "UPDATE $tabela SET $campo_id=%d WHERE $campo_id=0 AND $campo_sec=%s AND $campo_terc=%s";
	if($rs = $wpdb->query($wpdb->prepare($query, $total+1, $vals[1][$campo_sec], $vals[1][$campo_terc]))) {
		if($rs == 1) print "$campo_id corrigido. <br>";
	}
}

function fixAIPK($table, $idcol) {
	global $wpdb;
	print("<p>Corrigindo tabela $table... ");
	$sql = "SHOW COLUMNS IN $table LIKE '$idcol'";
        $rs = $wpdb->get_row($sql, ARRAY_A);
        if(is_array($rs)) {
	        $null = $rs['Null'] == "NO" ? "NOT NULL" : "NULL";
        	$datatype = strtoupper($rs['Type']);
        	$query = $rs['Key'] == 'PRI' ? "ALTER TABLE $table MODIFY $idcol $datatype $null AUTO_INCREMENT" : "ALTER TABLE $table MODIFY $idcol $datatype $null PRIMARY KEY AUTO_INCREMENT";
			$exe = $wpdb->query($query);
			if($exe == 1) 
				print "Corrigido</p>";
			else
				print "Algo deu errado!";
	} else die("Algo deu errado.");
	
}

// Creates a new array containing the default table names with the user chosen prefix
$wptables = array();
foreach($wptables_original as $tb) {
	array_push($wptables, $table_prefix.$tb);
}

$fixtables = Array();

// Select tables using the prefix of the local Wordpress and that the auto_increment is null
$checkPk = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME LIKE CONCAT(%s, '%') AND AUTO_INCREMENT IS NULL";

// Select the table and column names, where the column is in the ordinal position 1 or is a primary key
$checksql = "SELECT t.TABLE_NAME, c.COLUMN_NAME 
			FROM INFORMATION_SCHEMA.TABLES t 
			JOIN INFORMATION_SCHEMA.COLUMNS c 
			ON (t.TABLE_NAME=%s AND t.TABLE_NAME=c.TABLE_NAME AND (c.ORDINAL_POSITION='1' OR c.COLUMN_KEY='PRI'))";

if($tables = $wpdb->get_results($wpdb->prepare($checkPk, DB_NAME, $table_prefix), ARRAY_A)) {
	foreach($tables as $tb) {
		// Insert into the fixtables array only the native wordpress tables
		in_array($tb['TABLE_NAME'], $wptables) ? $fixtables[] = $tb['TABLE_NAME'] : null;
	}

	// If there is none, we stop.
	if(empty($fixtables))
		die("<h3>Nenhuma tabela encontrada.</h3>");

	// We iterate over the fixtables array
	foreach($fixtables as $tb) {
		$col = $wpdb->get_row($wpdb->prepare($checksql, $tb), ARRAY_N);
		if($col) {
			// Selecting the columns that have a 0 ID
			$sql = "SELECT $col[1] FROM $tb WHERE $col[1]=0";
			$results = $wpdb->get_results($sql, ARRAY_A);
			// If it has a 0 ID, we fix it
			if(count($results) > 0) {
				$sql = "SELECT * FROM $col[0] WHERE $col[1]=0";
				if($results = $wpdb->get_results($sql, ARRAY_A)) {
					foreach($results as $result) {
						fixZeroId($tb, $result);
					}
				}
			}
			// Finally we fix the table
			fixAIPK($tb, $col[1]);
		} else die("Nenhuma tabela encontrada.");
	}

}

