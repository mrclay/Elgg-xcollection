<?php

require __DIR__ . '/../../../engine/start.php';
ini_set('display_errors', 1);

$user = elgg_get_logged_in_user_entity();

$collection = new ElggXCollection(null, $user, 'faves');

var_export($collection);