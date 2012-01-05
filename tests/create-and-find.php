<?php

require __DIR__ . '/_inc.php';

//delete_data("DELETE FROM elgg_entities WHERE guid = 271");

header('Content-Type: text/plain');

$user = elgg_get_logged_in_user_entity();

$key = "xcollection_test1";

$collection = elgg_get_xcollection($user, $key);

if (assertTrue($collection === false, 'User test collection doesn\'t exist')) {
    $collection = new ElggXCollection(null, $user, $key);

    assertTrue($collection instanceof ElggXCollection, "Collection created!");
}

assertTrue($collection->key === $key, "Has right key");

assertTrue($collection->container_guid === $user->get('guid'), "Has right container_guid");

$collection->delete();

$collection = elgg_get_xcollection($user, $key);

assertTrue($collection === false, 'Test collection was deleted');


