<?php

elgg_register_event_handler('init', 'system', 'xcollection_init');

function xcollection_init() {

}

/**
 * @param ElggEntity $container
 * @param string $key
 * @param bool $create_if_missing
 * @param string $items_type
 *
 * @return bool|ElggEntity
 * @throws InvalidParameterException
 */
function elgg_get_xcollection(ElggEntity $container, $key, $create_if_missing = false, $items_type = null) {
    global $CONFIG;

    $collection_guid = find_xcollection_guid($container->get('guid'), $key);
    if ($collection_guid) {
        // it exists, but can the user see it?
        return xcollection_get_entity($collection_guid);
    }
    if ($create_if_missing) {
        try {
            return new ElggXCollection(null, $container, $key, $items_type);
        } catch (Exception $e) {}
    }
    return false;
}

function find_xcollection_guid($container_guid, $key) {
    global $CONFIG;

    $container_guid = (int)$container_guid;
    $key_id = get_metastring_id($key);
    if (! $key_id) {
        return false;
    }
    $row = get_data_row("
        SELECT e.guid
        FROM {$CONFIG->dbprefix}entities e
        JOIN {$CONFIG->dbprefix}xcollections_entity c ON e.guid = c.guid
        WHERE e.container_guid = {$container_guid}
          AND c.key_id = {$key_id}
    ");
    if (isset($row->guid)) {
        return (int)$row->guid;
    } else {
        return false;
    }
}

function get_xcollection_entity_as_row($guid) {
    global $CONFIG;

    $guid = (int)$guid;
    $row = get_data_row("SELECT * FROM {$CONFIG->dbprefix}xcollections_entity WHERE guid = {$guid}");

    $row->key = get_metastring($row->key_id);
    unset($row->key_id);
    $row->items_type = get_metastring($row->items_type_id);
    unset($row->items_type_id);

    return $row;
}

function create_xcollection_entity($guid, $name, $description, $key, $items_type) {
    global $CONFIG;

    $guid = (int)$guid;
    $name = sanitise_string($name);
    $description = sanitise_string($description);
    $key_id = add_metastring($key);
    $items_type_id = add_metastring($items_type);

    $row = get_entity_as_row($guid);

    if ($row) {
        // Core entities row exists and we have access to it
        $query = "SELECT guid from {$CONFIG->dbprefix}xcollections_entity where guid = {$guid}";
        if ($exists = get_data_row($query)) {
            $query = "
                UPDATE {$CONFIG->dbprefix}xcollections_entity
                SET `name`='{$name}',
                    description='{$description}',
                    key_id={$key_id},
                    items_type_id='{$items_type_id}'
                WHERE guid={$guid}";

            $result = update_data($query);
            if ($result != false) {
                // Update succeeded, continue
                $entity = xcollection_get_entity($guid);
                if (elgg_trigger_event('update', $entity->type, $entity)) {
                    return $guid;
                } else {
                    $entity->delete();
                }
            }
        } else {
            // Update failed, attempt an insert.
            $query = "
                INSERT INTO {$CONFIG->dbprefix}xcollections_entity
                       (guid, `name`, description, key_id, items_type_id)
                VALUES ($guid, '{$name}','{$description}', {$key_id}, '{$items_type_id}')";

            $result = insert_data($query);
            if ($result !== false) {
                $entity = xcollection_get_entity($guid);
                if (elgg_trigger_event('create', $entity->type, $entity)) {
                    return $guid;
                } else {
                    $entity->delete();
                }
            }
        }
    }
}


/**
 * Delete an entity.
 *
 * Removes an entity and its metadata, annotations, relationships, river entries,
 * and private data.
 *
 * Optionally can remove entities contained and owned by $guid.
 *
 * @tip Use ElggEntity::delete() instead.
 *
 * @warning If deleting recursively, this bypasses ownership of items contained by
 * the entity.  That means that if the container_guid = $guid, the item will be deleted
 * regardless of who owns it.
 *
 * @param int  $guid      The guid of the entity to delete
 * @param bool $recursive If true (default) then all entities which are
 *                        owned or contained by $guid will also be deleted.
 *
 * @return bool
 * @access private
 */
function delete_xcollection_entity($guid, $recursive = true) {
	global $CONFIG, $ENTITY_CACHE;

	$guid = (int)$guid;
	if ($entity = xcollection_get_entity($guid)) {
		if (elgg_trigger_event('delete', $entity->type, $entity)) {
			if ($entity->canEdit()) {

				// delete cache
				if (isset($ENTITY_CACHE[$guid])) {
					invalidate_cache_for_entity($guid);
				}

				// If memcache is available then delete this entry from the cache
				static $newentity_cache;
				if ((!$newentity_cache) && (is_memcache_available())) {
					$newentity_cache = new ElggMemcache('new_entity_cache');
				}
				if ($newentity_cache) {
					$newentity_cache->delete($guid);
				}

				// Delete contained owned and otherwise releated objects (depth first)
				if ($recursive) {
					// Temporary token overriding access controls
					// @todo Do this better.
					static $__RECURSIVE_DELETE_TOKEN;
					// Make it slightly harder to guess
					$__RECURSIVE_DELETE_TOKEN = md5(elgg_get_logged_in_user_guid());

					$entity_disable_override = access_get_show_hidden_status();
					access_show_hidden_entities(true);
					$ia = elgg_set_ignore_access(true);
					$sub_entities = get_data("SELECT * from {$CONFIG->dbprefix}entities
						WHERE container_guid=$guid
							or owner_guid=$guid
							or site_guid=$guid", 'entity_row_to_elggstar');
					if ($sub_entities) {
						foreach ($sub_entities as $e) {
							// check for equality so that an entity that is its own
							// owner or container does not cause infinite loop
							if ($e->guid != $guid) {
								$e->delete(true);
							}
						}
					}

					access_show_hidden_entities($entity_disable_override);
					$__RECURSIVE_DELETE_TOKEN = null;
					elgg_set_ignore_access($ia);
				}

				// Now delete the entity itself

				// All the following call get_entities, which we can't use :(
				//$entity->deleteMetadata();
				//$entity->deleteOwnedMetadata();
				//$entity->deleteAnnotations();
				//$entity->deleteOwnedAnnotations();
				//$entity->deleteRelationships();

				//elgg_delete_river(array('subject_guid' => $guid));
				//elgg_delete_river(array('object_guid' => $guid));
				//remove_all_private_settings($guid);

				$res = delete_data("DELETE from {$CONFIG->dbprefix}entities where guid={$guid}");
				if ($res) {
                    delete_data("DELETE FROM {$CONFIG->dbprefix}xcollections_entity
                                 WHERE guid = {$guid}");

                }

				return (bool)$res;
			}
		}
	}
	return false;

}

/**
 * NOTE: only needed until 'collection' type added to core.
 *
 * Loads and returns an entity object from a guid.
 *
 * @param int $guid The GUID of the entity
 *
 * @return ElggEntity The correct Elgg or custom object based upon entity type and subtype
 * @link http://docs.elgg.org/DataModel/Entities
 */
function xcollection_get_entity($guid) {
	static $newentity_cache;
	$new_entity = false;

	// We could also use: if (!(int) $guid) { return FALSE },
	// but that evaluates to a false positive for $guid = TRUE.
	// This is a bit slower, but more thorough.
	if (!is_numeric($guid) || $guid === 0 || $guid === '0') {
		return FALSE;
	}

	if ((!$newentity_cache) && (is_memcache_available())) {
		$newentity_cache = new ElggMemcache('new_entity_cache');
	}

	if ($newentity_cache) {
		$new_entity = $newentity_cache->load($guid);
	}

	if ($new_entity) {
		return $new_entity;
	}

	return xcollection_entity_row_to_elggstar(get_entity_as_row($guid));
}

/**
 * NOTE: only needed until 'collection' type added to core.
 *
 * Create an Elgg* object from a given entity row.
 *
 * Handles loading all tables into the correct class.
 *
 * @param stdClass $row The row of the entry in the entities table.
 *
 * @return object|false
 * @link http://docs.elgg.org/DataModel/Entities
 * @see get_entity_as_row()
 * @see add_subtype()
 * @see get_entity()
 * @access private
 */
function xcollection_entity_row_to_elggstar($row) {
	if (!($row instanceof stdClass)) {
		return $row;
	}

	if ((!isset($row->guid)) || (!isset($row->subtype))) {
		return $row;
	}

	$new_entity = false;

	// Create a memcache cache if we can
	static $newentity_cache;
	if ((!$newentity_cache) && (is_memcache_available())) {
		$newentity_cache = new ElggMemcache('new_entity_cache');
	}
	if ($newentity_cache) {
		$new_entity = $newentity_cache->load($row->guid);
	}
	if ($new_entity) {
		return $new_entity;
	}

	if (!$new_entity) {
		$new_entity = new ElggXCollection($row);
	}

	// Cache entity if we have a cache available
	if (($newentity_cache) && ($new_entity)) {
		$newentity_cache->save($new_entity->guid, $new_entity);
	}

	return $new_entity;
}