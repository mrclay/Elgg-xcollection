<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

// prepare test
if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}
$key = "xcollection_test_api_usage";
$key2 = "xcollection_test_api_usage2";
$container = elgg_get_logged_in_user_entity();
$collection = elgg_get_xcollection($container, $key);
if (! $collection) {
    $collection = elgg_create_xcollection($container, $key);
}
if (! $collection instanceof ElggXCollection) {
    die('Could not get/create test collection.');
}
$collection2 = elgg_get_xcollection($container, $key2);
if (! $collection2) {
    $collection2 = elgg_create_xcollection($container, $key2);
}
if (! $collection2 instanceof ElggXCollection) {
    die('Could not get/create 2nd test collection.');
}

testInfo("Collection GUIDs: " . $collection->get('guid') . ", " . $collection2->get('guid'));
$plugins = elgg_get_plugins();
$collection->pushItems(array_slice($plugins, 5, 5));
$collection2->pushItems(array_slice($plugins, 0, 5));

testInfo("Collection items: " . implode(',', $collection->sliceItems()));
testInfo("Collection2 items: " . implode(',', $collection2->sliceItems()));

$options = array(
    'xcollections' => elgg_xcollection_get_item_selector($container, $key),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Item selector");

$options = array(
    'xcollections' => elgg_xcollection_get_item_selector($container, 'non-existent'),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Item selector w/ missing collection");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => elgg_xcollection_get_filter($container, $key),
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Filter items");

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
    'xcollections' => elgg_xcollection_get_sticky_modifier($container, $key),
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
        elgg_xcollection_get_sticky_modifier($container, $key),
        elgg_xcollection_get_filter($container, $key2),
    )
);
apply_xcollections_to_options($options); // eventually wouldn't be necessary!
testShowEntities(elgg_get_entities($options), "Multiple collections: Sticky + Filter");


// cleanup
$collection->delete();
$collection2->delete();
