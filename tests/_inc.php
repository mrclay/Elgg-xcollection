<?php
die('Tests disabled. Comment out line 2 of mod/xcollection/tests/_inc.php');

require __DIR__ . '/../../../engine/start.php';
ini_set('display_errors', 1);

/**
 * pTest - PHP Unit Tester
 * @param mixed $test Condition to test, evaluated as boolean
 * @param string $message Descriptive message to output upon test
 * @url http://www.sitepoint.com/blogs/2007/08/13/ptest-php-unit-tester-in-9-lines-of-code/
 * @return bool
 */
function assertTrue($test, $message) {
    static $count;
    if (!isset($count)) $count = array('pass'=>0, 'fail'=>0, 'total'=>0);

    $mode = $test ? 'pass' : 'fail';
    $outMode = $test ? 'PASS' : '!FAIL';
    printf("%s: %s (%d of %d tests run so far have %sed)\n",
    $outMode, $message, ++$count[$mode], ++$count['total'], $mode);

    return (bool)$test;
}

function testInfo($message) {
    echo "INFO: " . trim($message) . "\n";
}

function testShowEntities($entities, $msg = '') {
    $names = array();
    foreach ($entities as $entity) {
        /* @var ElggEntity $entity */
        $names[] = $entity->get('guid');
    }
    testInfo("[" . implode(',', $names) . "] : {$msg}");
}

