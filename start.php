<?php

elgg_register_event_handler('init', 'system', 'xcollection_init');

function xcollection_init() {

}

/**
 * @param int|ElggEntity $container
 * @param string $key
 * @param bool $create_if_missing
 * @param string $items_type
 * @return ElggXCollection|false
 */
function elgg_get_xcollection($container, $key, $create_if_missing = false, $items_type = null) {
    global $CONFIG;

    if (is_numeric($container)) {
        $container = get_entity($container);
    }
    if ($container) {
        $collection_guid = find_xcollection_guid($container->get('guid'), $key);
        if ($collection_guid) {
            // it exists, but can the user see it?
            return get_entity($collection_guid);
        }
        if ($create_if_missing) {
            try {
                return new ElggXCollection(null, $container, $key, $items_type);
            } catch (Exception $e) {}
        }
    }
    return false;
}

/**
 * @param int $container_guid
 * @param string $key
 * @return int|false
 */
function find_xcollection_guid($container_guid, $key) {
    global $CONFIG;

    // find the GUID (w/o access control)
    $md_name_id = (int) get_metastring_id('key');
    $md_value_id = (int) get_metastring_id($key);
    $subtype_id = (int) get_subtype_id('object', 'xcollection');
    $container_guid = (int)$container_guid;
    $row = get_data_row("
        SELECT e.guid
        FROM {$CONFIG->dbprefix}entities e
        JOIN {$CONFIG->dbprefix}metadata md ON (e.guid = md.entity_guid)
        WHERE e.container_guid = {$container_guid}
          AND e.type = 'object'
          AND e.subtype = {$subtype_id}
          AND md.name_id = {$md_name_id}
          AND md.value_id = {$md_value_id}
    ");
    if (isset($row->guid)) {
        return (int)$row->guid;
    } else {
        return false;
    }
}