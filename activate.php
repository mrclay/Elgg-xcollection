<?php

// create tables if not exist
global $CONFIG;

$prefix = $CONFIG->dbprefix;
$tables = get_db_tables();
if (! in_array("{$prefix}xcollections_entity", $tables)
    || ! in_array("{$prefix}xcollection_items", $tables)) {
    run_sql_script(__DIR__ . '/sql/create_tables.sql');
    system_message("2 tables created: {prefix}xcollections_entity and {prefix}xcollection_items");
}
