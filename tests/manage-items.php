<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}

$key = "xcollection_test1";
$user = elgg_get_logged_in_user_entity();

$collection = elgg_get_xcollection($user, $key, true);

if (! assertTrue($collection instanceof ElggXCollection, 'User test collection couldn\'t be created')) {
    die();
}

testInfo("Collection GUID = " . $collection->get('guid'));

$testArray = array();

$collection->pushItems(30);
$testArray[] = 30;

$collection->pushItems(array(40, '50', 60, '70'));
array_push($testArray, 40, 50, 60, 70);

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

$collection->removeItemsFromEnd(2);
$testArray = array_slice($testArray, 0, -2);

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

$collection->unshiftItems(10);
array_unshift($testArray, 10);

$collection->insertBefore(20, 30);
$collection->insertBefore(15, 20);

$collection->insertBefore(array(22, 24, 26, 28), 30);
$testArray = $testArray2 = array(10, 15, 20, 22, 24, 26, 28, 30, 40, 50);

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

$collection->insertBefore(range(200, 300), 30);
array_splice($testArray, array_search(30, $testArray), 0, range(200, 300));

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

$collection->removeItems(range(200, 300));
$testArray = $testArray2;

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

$collection->insertAfter(range(32, 38, 2), 30);
array_splice($testArray, array_search(30, $testArray) + 1, 0, range(32, 38, 2));

assertTrue(array_values($collection->sliceItems()) === $testArray, "");

assertTrue(count($testArray) === $collection->countItems(), "Counts match");

var_export($collection->sliceItems());

$collection->delete();

