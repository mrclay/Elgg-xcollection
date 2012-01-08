<?php
/**
 * A Collection entity.
 */
class ElggXCollection extends ElggObject {

    /**
     * Spreading apart priority values allows us to more frequently insert
     * values without rebuilding the entire set of rows.
     */
    const STEP = 100;

    /**
	 * Initialise the attributes array.
	 * This is vital to distinguish between metadata and base parameters.
	 *
	 * Place your base parameters here.
	 *
	 * @return void
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();

		$this->attributes['subtype'] = "xcollection";
		$this->attributes['access_id'] = ACCESS_PUBLIC;
	}

	/**
	 * Load or create a new ElggXCollection.
     *
	 * @param mixed $guid If an int, load that GUID.  If a db row then will attempt
	 * to load the rest of the data. If not given, $container and $key must be set
     * and the collection will be immediately saved.
     * @param ElggEntity $container ignored if $guid is given
     * @param string $key ignored if $guid is given
     * @param string $items_type
	 *
	 * @throws IOException If passed an incorrect guid
	 * @throws InvalidParameterException If passed an Elgg* Entity that isn't an ElggObject
	 */
	public function __construct($guid = null, ElggEntity $container = null, $key = null, $items_type = 'entity') {
		$this->initializeAttributes();

		// compatibility for 1.7 api.
		//$this->initialise_attributes(false);

		if (!empty($guid)) {
			// Is $guid is a DB row - either a entity row, or a collection table row.
			if ($guid instanceof stdClass) {
				// Load the rest
				if (!$this->load($guid->guid)) {
					$msg = elgg_echo('IOException:FailedToLoadGUID', array(get_class(), $guid->guid));
					throw new IOException($msg);
				}

			} else if (is_numeric($guid)) {
				if (!$this->load($guid)) {
					throw new IOException(elgg_echo('IOException:FailedToLoadGUID', array(get_class(), $guid)));
				}
			} else {
				throw new InvalidParameterException(elgg_echo('InvalidParameterException:UnrecognisedValue'));
			}
		} else {
            // verify that this object would have a unique key on the container
            // and that the user can edit the container
            if (! is_string($key) || empty($key)) {
                throw new InvalidParameterException(elgg_echo('InvalidParameterException:Collection:KeyInvalid'));
            }
            if (! $container) {
                throw new InvalidParameterException(elgg_echo('InvalidParameterException:Collection:ContainerInvalid'));
            }
            $container_guid = $container->get('guid');
            if (find_xcollection_guid($container_guid, $key)) {
                throw new InvalidParameterException(elgg_echo('InvalidParameterException:Collection:AlreadyExists'));
            }
            if (! $container->canEdit(elgg_get_logged_in_user_guid())) {
                throw new SecurityException(elgg_echo('SecurityException:Collection:CannotEditContainer'));
            }
            $this->attributes['container_guid'] = $container_guid;
            $this->save();
            $this->setMetaData('key', $key);
            $this->setMetaData('items_type', trim($items_type));
        }
	}

	/**
	 * Delete the collection (and its items)
	 *
	 * @return bool
	 * @throws SecurityException
	 */
	public function delete() {
        $delete_successful = parent::delete();
        if ($delete_successful) {
            $this->deleteAllItems();
        }
        return $delete_successful;
	}

    /**
     * Determines whether or not the user can edit this collection
     *
     * @param int $user_guid The GUID of the user (defaults to currently logged in user)
     *
     * @return true|false Depending on permissions
     */
    public function canEdit($user_guid = 0) {
        if ($entity = get_entity($this->get('container_guid'))) {
            return $entity->canEdit($user_guid);
        }
        return false;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function set($name, $value) {
        if ($this->attributes['guid']) {
            // if saved, don't allow changing these. When this is core, we'll really be able
            // to protect key and items_type, but they're metadata in this implementation :(
            if ($name === 'container_guid' || $name === 'key' || $name === 'items_type') {
                return false;
            }
        }
        return parent::set($name, $value);
    }

	/*
	 * EXPORTABLE INTERFACE
	 */

	/**
	 * Return an array of fields which can be exported.
	 *
	 * @return array
	 */
	public function getExportableValues() {
		return array_merge(parent::getExportableValues(), array(
			'name',
			'description',
			'key',
            'items_type'
		));
	}

    /**
     * Add item(s) to the end of the collection
     *
     * @param array|int|ElggEntity|ElggExtender $items
     * @return bool
     */
    public function pushItems($items) {
        return $this->pushMultiple($items);
    }

    /**
     * Add item(s) to the beginning of the collection
     *
     * @param array|int|ElggEntity|ElggExtender $items
     * @return bool
     */
    public function unshiftItems($items) {
        return $this->pushMultiple($items, -1);
    }

    /**
     * Get number of items
     *
     * @return int|false
     */
    public function countItems() {
        return $this->queryItems(true, '', 0, null, true);
    }

    /**
     * Similar behavior as array_slice (w/o the first param)
     *
     * Note: the large numbers in these queries is to make up for MySQL's lack of
     * support for offset without limit: http://stackoverflow.com/a/271650/3779
     *
     * @param int $offset
     * @param int|null $length
     * @return array
     */
    public function sliceItems($offset = 0, $length = null) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $items = array();
        if ($length !== null && $length == 0) {
            return $items;
        }
        $offset = (int)$offset;
        $rows = false;
        if ($offset == 0) {
            if ($length === null) {
                $items = $this->queryItems();
            } elseif ($length > 0) {
                $items = $this->queryItems(true, '', 0, $length);
            } else {
                // length < 0
                $items = array_reverse($this->queryItems(false, '', - $length));
            }
        } elseif ($offset > 0) {
            if ($length === null) {
                $items = $this->queryItems(true, '', $offset);
            } elseif ($length > 0) {
                $items = $this->queryItems(true, '', $offset, $length);
            } else {
                // length < 0
                $rows = get_data("
                    SELECT id, item FROM (
                        SELECT id, item, priority FROM $CONFIG->dbprefix}xcollection_items
                        WHERE guid = $guid
                        ORDER BY priority DESC
                        LIMIT " . (- (int)$length) . ", 18446744073709551615
                    )
                    ORDER BY priority
                    LIMIT " . (int)$offset . ", 18446744073709551615
                ");
            }
        } else {
            // offset < 0
            if ($length === null) {
                $items = array_reverse($this->queryItems(false, '', - $offset));
            } elseif ($length > 0) {
                $rows = get_data("
                    SELECT id, item FROM (
                        SELECT id, item, priority FROM $CONFIG->dbprefix}xcollection_items
                        WHERE guid = $guid
                        ORDER BY priority DESC
                        LIMIT " . (- (int)$offset) . ", 18446744073709551615
                    )
                    ORDER BY priority
                    LIMIT " . (int)$length . "
                ");
            } else {
                // length < 0
                $rows = get_data("
                    SELECT id, item FROM (
                        SELECT id, item, priority FROM $CONFIG->dbprefix}xcollection_items
                        WHERE guid = $guid
                        ORDER BY priority DESC
                        LIMIT " . (- (int)$offset) . ", 18446744073709551615
                    )
                    ORDER BY priority DESC
                    LIMIT " . (- (int)$length) . ", 18446744073709551615
                ");
                if ($rows) {
                    $rows = array_reverse($rows);
                }
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $items[$row->id] = (int)$row->item;
            }
        }
        return $items;
    }

    /**
     * @return false|int
     */
    public function deleteAllItems() {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        return delete_data("
            DELETE FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
        ");
    }

    /**
     * Remove items (even if they appear multiple times)
     *
     * @param array|int|ElggEntity|ElggExtender $items
     * @return false|int
     */
    public function deleteItems($items) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        if (! is_array($items)) {
            $items = array($items);
        }
        $items = $this->toPositiveInt($items);
        return delete_data("
            DELETE FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
              AND item IN (" . implode(',', $items) . ")
        ");
    }

    /**
     * Remove items by ID
     *
     * @param array $ids
     * @return false|int
     */
    public function deleteItemsById($ids) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $ids = (array)$ids;
        return delete_data("
            DELETE FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
              AND id IN (" . implode(',', $ids) . ")
        ");
    }

    /**
     * Remove item(s) from the beginning. Unlike array_shift(), the item(s) is/are not returned.
     *
     * @param int $num
     * @return false|int num rows removed
     */
    public function shiftItems($num = 1) {
        return $this->removeMultipleFrom($num, true);
    }

    /**
     * Remove item(s) from the end. Unlike array_pop(), the item(s) is/are not returned.
     *
     * @param int $num
     * @return false|int num rows removed
     */
    public function popItems($num = 1) {
        return $this->removeMultipleFrom($num, false);
    }

    /**
     * Get the IDs of items. Each key has an array of IDs
     *
     * @param array|int|ElggEntity|ElggExtender $items
     * @param int|null $limit
     * @return array
     */
    public function findItemIds($items, $limit = null) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        if (! is_array($items)) {
            $items = array($items);
        }
        $items = $this->toPositiveInt($items);
        $limit_clause = ($limit > 0) ? ("LIMIT " . (int)$limit) : "";
        $rows = get_data("
            SELECT id, item FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
              AND item IN (" . implode(',', $items) . ")
            ORDER BY priority
            $limit_clause
        ");
        $ids = array();
        if ($rows) {
            foreach ($rows as $row) {
                $ids[$row->item][] = (int)$row->id;
            }
        }
        return $ids;
    }

    /**
     * Move the first instance of item to the beginning
     *
     * @param int|ElggEntity|ElggExtender $item
     * @return bool
     */
    public function moveItemToBeginning($item) {
        $ids = $this->findItemIds($item, 1);
        if ($ids) {
            $ids = array_shift($ids);
            $this->deleteItemsById($ids[0]);
            $this->unshiftItems($item);
            return true;
        }
        return false;
    }

    /**
     * Move the first instance of item to the end
     *
     * @param int|ElggEntity|ElggExtender $item
     * @return bool
     */
    public function moveItemToEnd($item) {
        $ids = $this->findItemIds($item, 1);
        if ($ids) {
            $ids = array_shift($ids);
            $this->deleteItemsById($ids[0]);
            $this->pushItems($item);
            return true;
        }
        return false;
    }

    /**
     * Swap the position of two items (the first instance of each)
     *
     * @param int|ElggEntity|ElggExtender $item1
     * @param int|ElggEntity|ElggExtender $item2
     * @return bool success
     */
    public function swapItems($item1, $item2) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        list($item1, $item2) = $this->toPositiveInt(array($item1, $item2));
        $rows = get_data("
            SELECT id, item FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
              AND item IN ($item1, $item2)
        ");
        if (count($rows) === 2 && ($rows[0]->item != $rows[1]->item)) {
            return update_data("
                UPDATE {$CONFIG->dbprefix}xcollection_items SET item = " . (int)$rows[0]->item . "
                WHERE id = " . (int)$rows[1]->id . "
            ") && update_data("
                UPDATE {$CONFIG->dbprefix}xcollection_items SET item = " . (int)$rows[1]->item . "
                WHERE id = " . (int)$rows[0]->id . "
            ");
        }
        return false;
    }

    /**
     * Insert items directly before an item in the collection
     *
     * @param array|int|ElggEntity|ElggExtender $new_items
     * @param int|ElggEntity|ElggExtender $existing_item
     * @return bool
     */
    public function insertBefore($new_items, $existing_item = null) {
        global $CONFIG;
        if (! $new_items) {
            return false;
        }
        if (! is_array($new_items)) {
            $new_items = array($new_items);
        } else {
            // make sure zero-indexed
            $new_items = array_values($new_items);
        }
        if (! $existing_item) {
            return $this->pushMultiple($new_items);
        }
        $existing_item = $this->toPositiveInt($existing_item);
        $item_priorities = $this->prioritiesOf($existing_item, 1);
        if (! $item_priorities) {
            return false;
        }
        $priority2 = $item_priorities[0];
        // find next lowest priority
        $row = get_data_row("
            SELECT priority FROM {$CONFIG->dbprefix}xcollection_items
            WHERE priority < $priority2
            ORDER BY priority DESC
            LIMIT 1
        ");
        if (! isset($row->priority)) {
            // ref was first, place at beginning
            return $this->pushMultiple($new_items, -1);
        }
        $priority1 = $row->priority;
        $this->insertBetween($priority1, $priority2, $existing_item, $new_items);
    }

    /**
     * Insert items directly after an item in the collection
     *
     * @param array|int|ElggEntity|ElggExtender $new_items
     * @param int|ElggEntity|ElggExtender $existing_item
     * @return bool
     */
    public function insertAfter($new_items, $existing_item = null) {
        global $CONFIG;
        if (! $new_items) {
            return false;
        }
        if (! is_array($new_items)) {
            $new_items = array($new_items);
        } else {
            // make sure zero-indexed
            $new_items = array_values($new_items);
        }
        if (! $existing_item) {
            return $this->pushMultiple($new_items);
        }
        $item_priorities = $this->prioritiesOf($existing_item, 1);
        if (! $item_priorities) {
            return false;
        }
        $priority1 = $item_priorities[0];
        // find next highest priority
        $row = get_data_row("
            SELECT priority, item FROM {$CONFIG->dbprefix}xcollection_items
            WHERE priority > $priority1
            ORDER BY priority
            LIMIT 1
        ");
        if (! isset($row->priority)) {
            // ref was last, place at end
            return $this->pushMultiple($new_items);
        }
        $priority2 = $row->priority;
        $item2 = $row->item;
        return $this->insertBetween($priority1, $priority2, $item2, $new_items);
    }

    /**
     * @param index $index
     * @return int|null
     */
    public function itemAt($index) {
        $items = $this->queryItems(true, '', $index, 1);
        return $items ? $items[0] : null;
    }

    /**
     * @param int|ElggEntity|ElggExtender $item
     * @param int $offset
     * @return bool|int
     */
    public function indexOf($item, $offset = 0) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $item = $this->toPositiveInt($item);
        if ($offset == 0) {
            $row = get_data_row("
                SELECT COUNT(*) AS cnt FROM {$CONFIG->dbprefix}xcollection_items
                WHERE guid = $guid
                  AND priority <=
                    (SELECT priority FROM {$CONFIG->dbprefix}xcollection_items
                    WHERE guid = $guid AND item = $item
                    ORDER BY priority
                    LIMIT 1)
                ORDER BY priority
            ");
            return ($row->cnt == 0) ? false : (int)$row->cnt - 1;
        }
    }

    /**
     * @param int|ElggEntity|ElggExtender $item
     * @param int|null $limit
     * @return array
     */
    public function prioritiesOf($item, $limit = null) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $item = $this->toPositiveInt($item);
        $limit_clause = ($limit > 0) ? ("LIMIT " . (int)$limit) : "";
        $rows = get_data("
            SELECT priority FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid AND item = $item
            ORDER BY priority
            $limit_clause
        ");
        $priorities = array();
        if ($rows) {
            foreach ($rows as $row) {
                $priorities[] = $row->priority;
            }
        }
        return $priorities;
    }

    /**
     * @param int $priority1
     * @param int $priority2
     * @param int $item2
     * @param array $new_items
     * @return bool
     */
    protected function insertBetween($priority1, $priority2, $item2, array $new_items) {
        global $CONFIG;
        if ($priority1 >= $priority2) {
            return false;
        }
        $new_items = $this->toPositiveInt($new_items);
        $gap = $priority2 - $priority1;
        $step = floor($gap / (count($new_items) + 1));
        if ($step > 0) {
            // new items can fit between two existing items
            $value_sets = array();
            $guid = (int)$this->attributes['guid'];
            foreach ($new_items as $i => $item) {
                $value_sets[] = "($guid, $item, " . ($priority1 + ($step * ($i + 1))) . ")";
            }
            return (bool) insert_data("
                INSERT INTO {$CONFIG->dbprefix}xcollection_items (guid, item, priority)
                VALUES " . implode(',', $value_sets) . "
            ");
        } else {
            // must rebuild whole set to make room :(
            $existing_items = array_values($this->sliceItems());
            $this->deleteAllItems();
            $ref_index = array_search($item2, $existing_items);
            array_splice($existing_items, $ref_index, 0, $new_items);
            return $this->pushMultiple($existing_items);
        }
    }

    /**
     * Add item(s) to the end of the collection (or beginning if $dir = -1)
     *
     * @param array|int|ElggEntity|ElggExtender $new_items
     * @param int $dir
     * @return bool success
     */
    protected function pushMultiple($new_items, $dir = 1) {
        global $CONFIG;
        if (! $new_items) {
            return true;
        }
        // you can't cast because objects might be passed in
        if (! is_array($new_items)) {
            $new_items = array($new_items);
        } else {
            // make sure array is zero-indexed
            $new_items = array_values($new_items);
        }
        /*if (count($new_items) === 1) {
            // special case with no race condition
            return $this->pushOne($new_items[0], $dir);
        }*/
        $guid = (int)$this->attributes['guid'];
        if ($dir > 0) {
            $func = 'MAX';
            $step = self::STEP;
        } else {
            $func = 'MIN';
            $step = - self::STEP;
            $new_items = array_reverse($new_items);
        }
        $row = get_data_row("
            SELECT COALESCE({$func}(priority) + $step, 0) AS `first`
            FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
        ");
        // race condition! hope no one else alters before next query
        if (! isset($row->first)) {
            return false;
        }
        $value_sets = array();
        $new_items = $this->toPositiveInt($new_items);
        foreach ($new_items as $i => $item) {
            $value_sets[] = "($guid, $item, " . ($row->first + ($i * $step))  . ")";
        }
        return (bool) insert_data("
            INSERT INTO {$CONFIG->dbprefix}xcollection_items (guid, item, priority)
            VALUES " . implode(',', $value_sets) . "
        ");
    }

    /**
     * Add one item to the end (or beginning if $dir = -1)
     *
     * @param int|ElggEntity|ElggExtender $new_item
     * @param int $dir
     * @return bool
     */
    /*protected function pushOne($new_item, $dir = 1) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $new_item = $this->toPositiveInt($new_item);
        if ($dir > 0) {
            $func = 'MAX';
            $step = self::STEP;
        } else {
            $func = 'MIN';
            $step = - self::STEP;
        }
        return (bool) insert_data("
            INSERT INTO {$CONFIG->dbprefix}xcollection_items (guid, item, priority)
            VALUES ($guid, $new_item, (SELECT COALESCE({$func}(priority) + $step, 0)
                                       FROM {$CONFIG->dbprefix}xcollection_items
                                       WHERE guid = $guid
                                      ))
        ");
    }*/

    /**
     * Fetch items by query
     *
     * @param bool $ascending
     * @param string $where
     * @param int $offset
     * @param int|null $limit
     * @param bool $count_only if true, return will be number of rows
     * @return array|int|false
     */
    protected function queryItems($ascending = true, $where = '', $offset = 0, $limit = null, $count_only = false) {
        global $CONFIG;
        $guid = (int)$this->attributes['guid'];
        $where_clause = "WHERE guid = $guid";
        if (! empty($where)) {
            $where_clause .= " AND ($where)";
        }
        $order_by_clause = "ORDER BY priority " . ($ascending ? '' : 'DESC');
        if ($offset == 0 && $limit === null) {
            $limit_clause = "";
        } elseif ($offset == 0) {
            $limit_clause = "LIMIT $limit";
        } else {
            if ($limit === null) {
                // http://stackoverflow.com/a/271650/3779
                $offset = "18446744073709551615";
            }
            $limit_clause = "LIMIT $offset, $limit";
        }
        $columns = 'id, item';
        if ($count_only) {
            $columns = 'COUNT(*) AS cnt';
            $order_by_clause = '';
        }
        $rows = get_data("
            SELECT $columns FROM {$CONFIG->dbprefix}xcollection_items
            $where_clause $order_by_clause $limit_clause
        ");
        if ($count_only) {
            return isset($rows[0]->cnt) ? (int)$rows[0]->cnt : false;
        }
        $items = array();
        if ($rows) {
            foreach ($rows as $row) {
                $items[$row->id] = (int)$row->item;
            }
        }
        return $items;
    }

    /**
     * Remove several from the beginning/end
     *
     * @param int $num
     * @param bool $from_beginning remove from the beginning of the collection?
     * @return false|int num rows removed
     */
    protected function removeMultipleFrom($num, $from_beginning) {
        global $CONFIG;
        $num = (int)max($num, 0);
        $guid = (int)$this->attributes['guid'];
        $priority_order = $from_beginning ? 'ASC' : 'DESC';
        return delete_data("
            DELETE FROM {$CONFIG->dbprefix}xcollection_items
            WHERE guid = $guid
            ORDER BY priority $priority_order
            LIMIT $num
        ");
    }

    /**
     * Convert a single value to an int (or an array of values to an array of ints)
     *
     * @param mixed|array $i
     * @return int|array
     * @throws InvalidParameterException
     */
    protected function toPositiveInt($i) {
        $is_array = is_array($i);
        if (! $is_array) {
            $i = array($i);
        }
        foreach ($i as $k => $v) {
            if (! is_int($v) || $v <= 0) {
                if (! is_numeric($v)) {
                    if ($v instanceof ElggEntity) {
                        $v = $v->get('guid');
                    } elseif ($v instanceof ElggExtender) {
                        $v = $v->get('id');
                    }
                }
                $v = (int)$v;
                if ($v < 1) {
                    throw new InvalidParameterException(elgg_echo('InvalidParameterException:UnrecognisedValue'));
                }
                $i[$k] = $v;
            }
        }
        return $is_array ? $i : $i[0];
    }
}