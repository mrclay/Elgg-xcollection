<?php

elgg_register_event_handler('init', 'system', 'xcollection_init');

function xcollection_init() {

}

/**
 * Get a raw (directly modifiable) collection object
 *
 * @param int|ElggEntity $container
 * @param string $key
 * @return ElggXCollection|false
 */
function elgg_get_xcollection($container, $key) {
    if (is_numeric($container)) {
        $container = get_entity($container);
    }
    if ($container) {
        $collection_guid = find_xcollection_guid($container->get('guid'), $key);
        if ($collection_guid) {
            // it exists, but can the user see it?
            return get_entity($collection_guid);
        }
    }
    return false;
}

/**
 * Get an object used to implement sticky items
 *
 * @param int|ElggEntity $container
 * @param string $key
 * @return ElggXQueryModifier
 */
function elgg_xcollection_get_sticky_modifier($container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    $application = new ElggXQueryModifier($collection);
    return $application->useStickyModel();
}

/**
 * Get an object used to filter out collection items
 *
 * @param int|ElggEntity $container
 * @param string $key
 * @return ElggXQueryModifier
 */
function elgg_xcollection_get_filter_modifier($container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    $application = new ElggXQueryModifier($collection);
    return $application->useAsFilter();
}

/**
 * Get an object used to select only items from a collection
 *
 * @param int|ElggEntity $container
 * @param string $key
 * @return ElggXQueryModifier
 */
function elgg_xcollection_get_selector_modifier($container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    return new ElggXQueryModifier($collection);
}

/**
 * Create a collection
 *
 * @param int|ElggEntity $container
 * @param string $key
 * @param string|null $items_type
 * @return ElggXCollection|false
 */
function elgg_create_xcollection($container, $key, $items_type = null) {
    if (is_numeric($container)) {
        $container = get_entity($container);
    }
    if ($container) {
        $collection_guid = find_xcollection_guid($container->get('guid'), $key);
        if ($collection_guid) {
            // already exists
            return false;
        }
        try {
            return new ElggXCollection(null, $container, $key, $items_type);
        } catch (Exception $e) {}
    }
    return false;
}

/**
 * This is a shim to support a 'collections' key in $options for elgg_get_entities, etc.
 * Call this on $options to convert 'collections' into other keys that those functions
 * already support.
 *
 * @param array $options
 */
function apply_xcollections_to_options(&$options) {
    if (empty($options['xcollections'])) {
        return;
    }
    if (! is_array($options['xcollections'])) {
        $options['xcollections'] = array($options['xcollections']);
    }
    foreach ($options['xcollections'] as $app) {
        if ($app instanceof ElggXCollection) {
            $app = new ElggXQueryModifier($app);
        }
        if ($app instanceof ElggXQueryModifier) {
            $options = $app->prepareOptions($options);
        }
    }
    ElggXQueryModifier::resetCounter();
    unset($options['xcollections']);
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