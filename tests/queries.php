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
testInfo("Collection GUID = " . $collection->get('guid'));
$plugins = array_slice(elgg_get_plugins(), 0, 3);
testShowEntities($plugins, "natural order");
$collection->pushItems($plugins);

// begin test
$items = array_values($collection->sliceItems());

$collection->swapItems($items[0], $items[1]);

testInfo("Collection order: " . implode(',', $collection->sliceItems()));

$options = array(
    'limit' => 9999,
    'type' => 'object',
    'subtype' => 'plugin',
    'order_by' => "e.guid",
);

$collApp = new ElggXCollectionApplication($collection);

$collApp->isReversed = false;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "no others (default)");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "no others, reversed");

$collApp->includeOthers = true;

$collApp->isReversed = false;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "collection first (sticky)");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "collection first, reversed");

$collApp->collectionItemsFirst = false;

$collApp->isReversed = false;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "others first");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "others first, collection reversed");

$collApp->includeCollection = false;

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "no collection, others (filter)");

$collApp->includeOthers = false;

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "no collection, no others");

$collection->deleteAllItems();

$collApp = new ElggXCollectionApplication(null);

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: no others (default)");

$collApp->useAsFilter();

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: used as filter");

$collApp->useStickyModel();

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testShowEntities($fetchedEntities, "missing collection: sticky model");

// cleanup
$collection->delete();
