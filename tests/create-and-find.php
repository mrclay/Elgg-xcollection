<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

// prepare test
if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}
$key = "xcollection_test_create_find";
$user = elgg_get_logged_in_user_entity();

assertTrue(elgg_get_xcollection(null, $key) === false, "No container, no collection");

$collection = elgg_get_xcollection($user, $key);

if (! assertTrue($collection === false, 'User test collection doesn\'t exist')) {
    $collection->delete();
}

try {
    $collection = new ElggXCollection(null, $user, $key);
    assertTrue($collection instanceof ElggXCollection, "Collection created");

} catch (Exception $e) {

    assertTrue(false, "Constructor threw exception: " . $e->getMessage());
    die();
}

testInfo("Collection GUID = " . $collection->get('guid'));

assertTrue($collection->key === $key, "Has right key");

assertTrue($collection->container_guid === $user->get('guid'), "Has right container_guid");

$collection2 = elgg_get_xcollection($user->get('guid'), $key);

assertTrue($collection2 instanceof ElggXCollection, "Collection found by container_guid");

assertTrue($collection->get('guid') == $collection2->get('guid'), "GUIDs match");

try {
    $collection2 = new ElggXCollection($collection->get('guid'));
    assertTrue($collection2 instanceof ElggXCollection, "Collection loaded from guid");
} catch (Exception $e) {
    assertTrue(false, "Constructor threw exception: " . $e->getMessage());
    die();
}

assertTrue($collection->get('guid') == $collection2->get('guid'), "GUIDs match");

$collection->delete();

$collection = elgg_get_xcollection($user, $key);

assertTrue($collection === false, 'Test collection was deleted');

$collection = elgg_create_xcollection($user, $key);

assertTrue($collection instanceof ElggXCollection, "elgg_create_xcollection()");

testInfo("2nd collection GUID = " . $collection->get('guid'));

try {
    $collection3 = new ElggXCollection(null, $user, $key);
    assertTrue(false, "Constructor prohibits creating duplicates");
} catch (Exception $e) {
    assertTrue(true, "Constructor prohibits creating duplicates");
}

// cleanup
$collection->delete();
