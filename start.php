<?php

elgg_register_event_handler('init', 'system', 'xcollection_init');

function xcollection_init() {

}

/**
 * @param ElggEntity $container
 * @param string $key
 * @param bool $create_if_missing
 *
 * @return bool|ElggEntity
 * @throws InvalidParameterException
 */
function elgg_get_xcollection(ElggEntity $container, $key, $create_if_missing = false) {
    global $CONFIG;

    $container_guid = $container->get('guid');
    $collection_guid = find_xcollection_guid($container->get('guid'), $key);
    if ($collection_guid) {
        // it exists, but can the user see it?
        return get_entity($collection_guid);
    }
    if ($create_if_missing) {
        try {
            return new ElggXCollection(null, $container, $key);
        } catch (Exception $e) {}
    }
    return false;
}

function find_xcollection_guid($container_guid, $key) {
    global $CONFIG;

    $container_guid = (int)$container_guid;
    $key_id = get_metastring_id($key);
    if (! $key_id) {
        return false;
    }
    $row = get_data_row("
        SELECT e.guid
        FROM {$CONFIG->dbprefix}entities e
        JOIN {$CONFIG->dbprefix}xcollections_entity c ON e.guid = c.guid
        WHERE e.container_guid = {$container_guid}
          AND c.key_id = {$key_id}
    ");
    if (isset($row->guid)) {
        return (int)$row->guid;
    } else {
        return false;
    }
}

function get_xcollection_entity_as_row($guid) {
    global $CONFIG;

    $guid = (int)$guid;
    $row = get_data_row("SELECT * FROM {$CONFIG->dbprefix}xcollections_entity WHERE guid = {$guid}");

    $row->key = get_metastring($row->key_id);
    unset($row->key_id);

    return $row;
}

function create_xcollection_entity($guid, $name, $description, $key, $items_type) {
    global $CONFIG;

    $guid = (int)$guid;
    $name = sanitise_string($name);
    $description = sanitise_string($description);
    $key_id = add_metastring($key);
    $items_type = sanitise_string($items_type);

    $row = get_entity_as_row($guid);

    if ($row) {
        // Core entities row exists and we have access to it
        $query = "SELECT guid from {$CONFIG->dbprefix}objects_entity where guid = {$guid}";
        if ($exists = get_data_row($query)) {
            $query = "
                UPDATE {$CONFIG->dbprefix}xcollections_entity
                SET `name`='{$name}',
                    description='{$description}',
                    key_id={$key_id},
                    items_type='{$items_type}'
                WHERE guid={$guid}";

            $result = update_data($query);
            if ($result != false) {
                // Update succeeded, continue
                $entity = get_entity($guid);
                if (elgg_trigger_event('update', $entity->type, $entity)) {
                    return $guid;
                } else {
                    $entity->delete();
                }
            }
        } else {
            // Update failed, attempt an insert.
            $query = "
                INSERT INTO {$CONFIG->dbprefix}xcollections_entity
                       (guid, `name`, description, key_id, items_type)
                VALUES ($guid, '{$name}','{$description}', {$key_id}, '{$items_type}')";

            $result = insert_data($query);
            if ($result !== false) {
                $entity = get_entity($guid);
                if (elgg_trigger_event('create', $entity->type, $entity)) {
                    return $guid;
                } else {
                    $entity->delete();
                }
            }
        }
    }
}