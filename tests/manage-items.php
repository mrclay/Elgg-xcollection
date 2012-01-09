<?php

require __DIR__ . '/_inc.php';

header("Content-Type: text/plain");

// prepare test
if (! elgg_is_logged_in()) {
    die("Must be logged in.");
}
$key = "xcollection_test_manage_items";
$user = elgg_get_logged_in_user_entity();
$collection = elgg_create_xcollection($user, $key);
if (! $collection instanceof ElggXCollection) {
    die('Could not create test collection.');
}
testInfo("Collection GUID = " . $collection->get('guid'));
$testArray = array();

// begin test
$collection->pushItems(30);
$testArray[] = 30;

$collection->pushItems(array(40, '50', 60, '70'));
array_push($testArray, 40, 50, 60, 70);

assertTrue(array_values($collection->sliceItems()) === $testArray, "pushItems()");

$collection->popItems(2);
$testArray = array_slice($testArray, 0, -2);

assertTrue(array_values($collection->sliceItems()) === $testArray, "popItems()");

$collection->unshiftItems(10);
array_unshift($testArray, 10);

$collection->insertBefore(20, 30);
$collection->insertBefore(15, 20);

$collection->insertBefore(array(24, 27), 30);
$testArray = $testArray2 = array(10, 15, 20, 24, 27, 30, 40, 50);

assertTrue(array_values($collection->sliceItems()) === $testArray, "insertBefore()");

$collection->insertBefore(range(200, 300), 30);
array_splice($testArray, array_search(30, $testArray), 0, range(200, 300));

assertTrue(array_values($collection->sliceItems()) === $testArray, "insertBefore()");

$collection->deleteItems(range(200, 300));
$testArray = $testArray2;

assertTrue(array_values($collection->sliceItems()) === $testArray, "deleteItems()");

$collection->insertAfter(array(34, 37), 30);
array_splice($testArray, array_search(30, $testArray) + 1, 0, array(34, 37));

assertTrue(array_values($collection->sliceItems()) === $testArray, "insertAfter()");

assertTrue(count($testArray) === $collection->countItems(), "countItems()");

$collection->swapItems(10, 15);

assertTrue(array_values($collection->sliceItems())
           === array(15,10,20,24,27,30,34,37,40,50), "swapItems()");

$collection->pushItems(20);

$collection->moveItemToBeginning(20);

$collection->moveItemToEnd(27);

assertTrue(array_values($collection->sliceItems())
           === array(20,15,10,24,30,34,37,40,50,20,27), "moveItemToBeginning/End()");

assertTrue($collection->indexOf(20) === 0, "indexOf()");

assertTrue($collection->indexOf(24) === 3, "indexOf()");

assertTrue($collection->indexOf(51) === false, "indexOf()");

//echo implode(',', $collection->sliceItems()) . "\n";

$collection->delete();
