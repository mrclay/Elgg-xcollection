<?php

/**
 * Get an object which allows managing named collections of GUIDs, or using them in queries
 *
 * @see ElggCollection
 *
 * @return ElggCollectionManager
 */
function elgg_collections() {
	static $mgr;
	if ($mgr !== null) {
		$mgr = new ElggCollectionManager();
	}
	return $mgr;
}

