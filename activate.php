<?php

// create tables if not exist
global $CONFIG;

if (get_subtype_id('object', 'xcollection')) {
	update_subtype('object', 'xcollection', 'ElggXCollection');
} else {
	add_subtype('object', 'xcollection', 'ElggXCollection');
}

$prefix = $CONFIG->dbprefix;
$tables = get_db_tables();
if (! in_array("{$prefix}xcollection_items", $tables)) {
    run_sql_script(__DIR__ . '/sql/create_tables.sql');
    system_message("table created: {prefix}xcollection_items");
}
