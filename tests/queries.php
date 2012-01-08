<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

function testPluginList($plugins, $msg = '') {
    $names = array();
    foreach ($plugins as $plugin) {
        /* @var ElggPlugin $plugin */
        $names[] = $plugin->get('guid');
    }
    testInfo("[" . implode(',', $names) . "] : {$msg}");
}

if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}

$key = "xcollection_queries_test";
$user = elgg_get_logged_in_user_entity();
$collection = elgg_get_xcollection($user, $key, true);

if (! assertTrue($collection instanceof ElggXCollection, 'Creating test collection')) {
    die();
}

testInfo("Collection GUID = " . $collection->get('guid'));

$plugins = array_slice(elgg_get_plugins(), 0, 3);

testPluginList($plugins, "natural order");

$collection->pushItems($plugins);

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
testPluginList($fetchedEntities, "no others (default)");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "no others, reversed");

$collApp->includeOthers = true;

$collApp->isReversed = false;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "collection first (sticky)");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "collection first, reversed");

$collApp->collectionItemsFirst = false;

$collApp->isReversed = false;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "others first");

$collApp->isReversed = true;
$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "others first, collection reversed");

$collApp->includeCollection = false;

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "no collection, others (filter)");

$collApp->includeOthers = false;

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "no collection, no others");

$collection->deleteAllItems();

$collApp = new ElggXCollectionApplication(null);

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "missing collection: no others (default)");

$collApp->useAsFilter();

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "missing collection: used as filter");

$collApp->useStickyModel();

$fetchedEntities = elgg_get_entities($collApp->prepareOptions($options));
testPluginList($fetchedEntities, "missing collection: sticky model");

