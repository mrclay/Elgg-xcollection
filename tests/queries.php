<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

// prepare test
if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}
$key = "xcollection_test_queries";
$user = elgg_get_logged_in_user_entity();
$collection = elgg_get_xcollection($user, $key);
if (! $collection) {
    $collection = elgg_create_xcollection($user, $key);
}
if (! $collection instanceof ElggXCollection) {
    die('Could not get/create test collection.');
}
$plugins = array_slice(elgg_get_plugins(), 0, 3);
testShowEntities($plugins, "natural order");
$collection->pushItems($plugins);

// begin test
$items = array_values($collection->sliceItems());

$collection->swapItems($items[0], $items[1]);

testInfo("Collection ({$collection->guid}) order: " . implode(',', $collection->sliceItems()));

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
);

$modifier = new ElggXQueryModifier($collection);

$modifier->isReversed = false;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "no others (default)");

$modifier->isReversed = true;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "no others, reversed");

$modifier->includeOthers = true;

$modifier->isReversed = false;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "collection first (sticky)");

$modifier->isReversed = true;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "collection first, reversed");

$modifier->collectionItemsFirst = false;

$modifier->isReversed = false;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "others first");

$modifier->isReversed = true;
$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "others first, collection reversed");

$modifier->includeCollection = false;

$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "no collection, others (filter)");

$modifier->includeOthers = false;

$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "no collection, no others");

$collection->deleteAllItems();

$modifier = new ElggXQueryModifier(null);

$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: no others (default)");

$modifier->useAsFilter();

$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: used as filter");

$modifier->useStickyModel();

$fetchedEntities = elgg_get_entities($modifier->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: sticky model");

// cleanup
$collection->delete();
