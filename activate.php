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
$row = get_data_row("
    SELECT 1 AS has_it FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = '{$CONFIG->dbprefix}entities'
      AND COLUMN_NAME = 'type'
      AND COLUMN_TYPE LIKE '%\\'xcollection\\'%'
");
if (empty($row->has_it)) {
    run_sql_script(__DIR__ . '/sql/alter_entities.sql');
    system_message("table altered: {prefix}entities now supports 'xcollection' in `type`");
}