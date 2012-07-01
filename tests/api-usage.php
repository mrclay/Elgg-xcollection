<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

// prepare test
if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}
$key1 = "xcollection_test_api_usage";
$key2 = "xcollection_test_api_usage2";
$container = elgg_get_logged_in_user_entity();
$collection1 = ElggCollection::fetch($container, $key1);
if (! $collection1) {
    $collection1 = ElggCollection::create($container, $key1);
}
if (! $collection1 instanceof ElggCollection) {
    die('Could not get/create test collection.');
}
$collection2 = ElggCollection::fetch($container, $key2);
if (! $collection2) {
    $collection2 = ElggCollection::create($container, $key2);
}
if (! $collection2 instanceof ElggCollection) {
    die('Could not get/create 2nd test collection.');
}

$plugins = elgg_get_plugins();
$collection1->push(array_slice($plugins, 5, 5));
$collection2->push(array_slice($plugins, 0, 5));

testInfo("Collection1 items: " . implode(',', $collection1->slice()));
testInfo("Collection2 items: " . implode(',', $collection2->slice()));

$options = array(
    'xcollections' => elgg_xcollection_get_selector_modifier($container, $key1),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Item selector");

$options = array(
    'xcollections' => elgg_xcollection_get_selector_modifier($container, 'non-existent'),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Item selector w/ missing collection");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => elgg_xcollection_get_filter_modifier($container, $key1),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Filter items");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => elgg_xcollection_get_sticky_modifier($container, $key1),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Sticky items");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => elgg_xcollection_get_sticky_modifier($container, 'non-existent'),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Sticky items w/ missing collection");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => array(
        elgg_xcollection_get_sticky_modifier($container, $key1),
        elgg_xcollection_get_filter_modifier($container, $key2),
    )
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Multiple collections: Sticky + Filter");

$options = array(
    'limit' => 7,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
);
elgg_xcollection_alter_entities_query($options, 'test1');
testShowEntities(elgg_get_entities($options), "Apply by hooks: no handlers");

// test applying collections by trigger hooks

elgg_register_plugin_hook_handler('apply', 'xcollection',
    function ($hook, $type, $returnvalue, $params) use (&$container, &$key1) {
        if ($params['query_name'] == 'test1') {
            $returnvalue[] = elgg_xcollection_get_sticky_modifier($container, $key1);
        }
        return $returnvalue;
    });

$options = array(
    'limit' => 7,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
);
elgg_xcollection_alter_entities_query($options, 'test1');
testShowEntities(elgg_get_entities($options), "Apply by hooks: sticky");



// cleanup
$collection1->delete();
$collection2->delete();
