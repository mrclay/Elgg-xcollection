<?php

if (get_subtype_id('object', 'xcollection')) {
	update_subtype('object', 'xcollection', 'ElggXCollection');
} else {
	add_subtype('object', 'xcollection', 'ElggXCollection');
}

// create tables if not exist
$prefix = elgg_get_config('dbprefix');
$tables = get_db_tables();
if (! in_array("{$prefix}xcollection_items", $tables)) {
    run_sql_script(__DIR__ . '/sql/create_tables.sql');
    system_message("Table created: {$prefix}xcollection_items");
}
