<?php

elgg_register_event_handler('init', 'system', 'xcollection_init');

function xcollection_init() {

}

/**
 * Get a modifiable collection object (if the current user can access it)
 *
 * @param ElggEntity $container
 * @param string $key
 * @return ElggXCollection|null
 */
function elgg_get_xcollection(ElggEntity $container, $key) {
    $collection_guid = find_xcollection_guid($container->get('guid'), $key);
    if ($collection_guid) {
        // it exists, but can the user see it?
        return get_entity($collection_guid);
    }
    return null;
}

/**
 * Get an object used to implement sticky items
 *
 * @param ElggEntity $container
 * @param string $key
 * @return ElggXCollectionQueryModifier
 */
function elgg_xcollection_get_sticky_modifier(ElggEntity $container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    $application = new ElggXCollectionQueryModifier($collection);
    return $application->useStickyModel();
}

/**
 * Get an object used to filter out collection items
 *
 * @param ElggEntity $container
 * @param string $key
 * @return ElggXCollectionQueryModifier
 */
function elgg_xcollection_get_filter_modifier(ElggEntity $container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    $application = new ElggXCollectionQueryModifier($collection);
    return $application->useAsFilter();
}

/**
 * Get an object used to select only items from a collection
 *
 * @param ElggEntity $container
 * @param string $key
 * @return ElggXCollectionQueryModifier
 */
function elgg_xcollection_get_selector_modifier(ElggEntity $container, $key) {
    $collection = elgg_get_xcollection($container, $key);
    return new ElggXCollectionQueryModifier($collection);
}

/**
 * Create a collection
 *
 * @param ElggEntity $container
 * @param string $key
 * @return ElggXCollection|bool
 */
function elgg_create_xcollection(ElggEntity $container, $key) {
    $collection_guid = find_xcollection_guid($container->get('guid'), $key);
    if ($collection_guid) {
        // already exists
        return false;
    }
    try {
        return new ElggXCollection(null, $container, $key);
    } catch (Exception $e) {
        // likely can't edit container
    }
    return false;
}

/**
 * Tell if a collection of exists regardless of the current user.
 *
 * @param int $container_guid
 * @param string $key
 * @return bool
 */
function elgg_xcollection_exists($container_guid, $key) {
    return (bool) find_xcollection_guid($container_guid, $key);
}

/**
 * Trigger the ("apply", "xcollection") plugin hook to apply collections to an elgg_get_entities
 * query.
 *
 * $params will, by default, contain:
 *    query_name : name that hook handlers will look for to apply their collections.
 *                 e.g. "pages_group_widget_list"
 *    options    : a copy of the $options array (but without the "xcollections" key)
 *    function   : "elgg_get_entities"
 *
 * $returnValue will contain a (possibly empty) array of ElggXCollectionQueryModifier objects to
 * which the handler should push their own ElggXCollectionQueryModifier object(s), or alter those
 * already added.
 *
 * @param array $options to be passed into elgg_get_entities
 * @param string $query_name a name that hook handlers can recognize the query by
 * @param array $params to be passed to the hook handler
 */
function elgg_xcollection_alter_entities_query(&$options, $query_name, array $params = array()) {
    _elgg_xcollection_trigger_hooks($options, $query_name, $params, 'elgg_get_entities');
}

/**
 * Trigger the ("apply", "xcollection") plugin hook to apply collections to an elgg_get_river
 * query.
 *
 * $params will, by default, contain:
 *    query_name : name that hook handlers will look for to apply their collections.
 *                 e.g. "activity_stream"
 *    options    : a copy of the $options array (but without the "xcollections" key)
 *    function   : "elgg_get_river"
 *
 * $returnValue will contain a (possibly empty) array of ElggXCollectionQueryModifier objects to
 * which the handler should push their own ElggXCollectionQueryModifier object(s), or alter those
 * already added.
 *
 * @param array $options to be passed into elgg_get_river
 * @param string $query_name a name that hook handlers can recognize the query by
 * @param array $params to be passed to the hook handler
 */
function elgg_xcollection_alter_river_query(&$options, $query_name, array $params = array()) {
    _elgg_xcollection_trigger_hooks($options, $query_name, $params, 'elgg_get_river');
}

/**
 * @param array $options passed by reference
 * @param string $query_name
 * @param array $params
 * @param string $func
 */
function _elgg_xcollection_trigger_hooks(&$options, $query_name, $params, $func = 'elgg_get_entities') {
    $params = array_merge($params, array(
        'query_name' => $query_name,
        'function' => $func,
        'options' => $options,
    ));
    unset($params['options']['xcollections']);
    if (empty($options['xcollections'])) {
        $options['xcollections'] = array();
    }
    $options['xcollections'] = elgg_trigger_plugin_hook('apply', 'xcollection', $params, $options['xcollections']);
    apply_xcollections_to_options($options, _elgg_xcollection_get_join_column($func));
}


/**
 * Get the column expression to join the items column to. e.g. "rv.id"
 *
 * @param string $query_function name of Elgg query function (e.g. elgg_get_entities)
 * @return string
 */
function _elgg_xcollection_get_join_column($query_function) {
    if (false !== strpos($query_function, 'river')) {
        return 'rv.id';
    }
    return 'e.guid';
}

/**
 * This is a shim to support a 'collections' key in $options for elgg_get_entities, etc.
 * Call this on $options to convert 'collections' into other keys that those functions
 * already support.
 *
 * @param array $options
 * @param string $join_column (e.g. set to "rv.id" to order river items)
 */
function apply_xcollections_to_options(&$options, $join_column = 'e.guid') {
    if (empty($options['xcollections'])) {
        return;
    }
    if (! is_array($options['xcollections'])) {
        $options['xcollections'] = array($options['xcollections']);
    }
    foreach ($options['xcollections'] as $app) {
        if ($app instanceof ElggXCollection) {
            $app = new ElggXCollectionQueryModifier($app);
        }
        if ($app instanceof ElggXCollectionQueryModifier) {
            $options = $app->prepareOptions($options, $join_column);
        }
    }
    ElggXCollectionQueryModifier::resetCounter();
    unset($options['xcollections']);
}

/**
 * This is a shim to support a 'collections' key in $options for elgg_get_river.
 * Call this on $options to convert 'collections' into other keys that get_river
 * already supports.
 *
 * @param $options
 */
function apply_xcollections_to_river_options(&$options) {
    apply_xcollections_to_options($options, 'rv.id');
}

/**
 * @param int $container_guid
 * @param string $key
 * @return int|bool
 */
function find_xcollection_guid($container_guid, $key) {
    $prefix = elgg_get_config('dbprefix');

    // find the GUID (w/o access control)
    $md_name_id = (int) get_metastring_id('key');
    $md_value_id = (int) get_metastring_id($key);
    $subtype_id = (int) get_subtype_id('object', 'xcollection');
    $container_guid = (int) $container_guid;
    $row = get_data_row("
        SELECT e.guid
        FROM {$prefix}entities e
        JOIN {$prefix}metadata md ON (e.guid = md.entity_guid)
        WHERE e.container_guid = {$container_guid}
          AND e.type = 'object'
          AND e.subtype = {$subtype_id}
          AND md.name_id = {$md_name_id}
          AND md.value_id = {$md_value_id}
    ");
    if (isset($row->guid)) {
        return (int) $row->guid;
    } else {
        return false;
    }
}
