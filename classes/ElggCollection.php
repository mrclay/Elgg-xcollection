<?php
/**
 * Elgg Collection API
 *
 * @note entity_relationships does not have a priority column, so this implementation
 * uses `id` and allows limited re-ordering (swapItems).
 *
 *
 * @property int $owner_guid GUID of the metadata owner. setting persists this property immediately.
 * @property int $access_id Access ID of the metadata. Setting persists this property immediately.
 */
class ElggCollection {

	/**
	 * @var ElggEntity
	 */
	protected $entity;

	/**
	 * @var int
	 */
	protected $entity_guid;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $relationship_key;

	/**
	 * @var string
	 */
	protected $relationship_table;

	/**
	 * @var bool
	 */
	protected $is_deleted = false;

	/**
	 * @var bool
	 */
	protected $logged_in_user_can_edit = null;

	/**
	 * @var int
	 */
	protected $logged_in_user_guid = null;

	const TABLE_UNPREFIXED = 'entity_relationships';
	const COL_PRIORITY = 'id';
	const COL_ITEM = 'guid_one';
	const COL_ENTITY_GUID = 'guid_two';
	const COL_KEY = 'relationship';

	/**
	 * @param ElggEntity $entity
	 * @param string $name
	 */
	protected function __construct(ElggEntity $entity, $name) {
		$this->entity = $entity;
		$this->entity_guid = $entity->guid;
		$this->name = $name;
		$hash = base64_encode(sha1($this->entity_guid . '|' . $this->name));
		$this->relationship_key = "in_collection:$hash";
		$this->relationship_table = elgg_get_config('dbprefix') . self::TABLE_UNPREFIXED;
	}

	/**
	 * Determines whether or not the user can edit this collection
	 *
	 * @param int $user_guid The GUID of the user (defaults to currently logged in user)
	 *
	 * @return bool Depending on permissions
	 */
	public function canEdit($user_guid = 0) {
		if (! $user_guid) {
			$user_guid = elgg_get_logged_in_user_guid();
		}
		// cache permission of current user internally because this may get called a lot
		// by the item modification methods
		if ($user_guid === $this->logged_in_user_guid) {
			return $this->logged_in_user_can_edit;
		}
		$this->logged_in_user_guid = $user_guid;
		$this->logged_in_user_can_edit = false;
		if (!$this->is_deleted && $this->entity->canEdit($user_guid)) {
			$this->logged_in_user_can_edit = true;
		}
		return $this->logged_in_user_can_edit;
	}

	/**
	 * @return string
	 *
	 * @access private
	 */
	public function getRelationshipKey() {
		return $this->relationship_key;
	}

	/**
	 * @return int
	 */
	public function getEntityGuid() {
		return $this->entity_guid;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Delete the collection (and its items)
	 *
	 * @return bool
	 */
	public function delete() {
		if ($this->canEdit()) {
			$this->removeAll();
			elgg_delete_metadata(array(
				'guid' => $this->entity_guid,
				'metadata_name' => "collection_exists:$this->name",
			));
			$this->is_deleted = true;
			$this->logged_in_user_can_edit = false;
			return true;
		}
		return false;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {
		if (in_array($name, array('owner_guid', 'access_id'))) {
			$prefix = elgg_get_config('dbprefix');
			$name_id = get_metastring_id("collection_exists:$this->name");
			$row = get_data_row("
				SELECT owner_guid, access_id FROM {$prefix}metadata
				WHERE name_id = $name_id AND entity_guid = $this->entity_guid
				LIMIT 1
			");
			if ($row) {
				return $row->{$name};
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @param int $value
	 */
	public function __set($name, $value) {
		$value = (int)$value;
		if ($this->canEdit() && in_array($name, array('owner_guid', 'access_id'))) {
			// if the user can edit the entity, she must be allowed to
			// alter the owner/access level, regardless of the metadata's access.
			$prefix = elgg_get_config('dbprefix');
			$name_id = get_metastring_id("collection_exists:$this->name");
			update_data("
				UPDATE {$prefix}metadata SET $name = $value
				WHERE name_id = $name_id AND entity_guid = $this->entity_guid
			");
		}
	}

	/**
	 * Add item(s) to the end of the collection
	 *
	 * @param array|int|ElggEntity $items
	 * @return bool
	 */
	public function push($items) {
		// note: this is a separate function because pushMultiple may eventually
		// serve for unshifting as well
		return $this->pushMultiple($items);
	}


	/**
	 * Get number of items
	 *
	 * @return int|bool
	 */
	public function count() {
		return $this->fetchItems(true, '', 0, null, true);
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
	public function slice($offset = 0, $length = null) {
		if ($length !== null) {
			if ($length == 0) {
				return array();
			}
			$length = (int)$length;
		}
		$offset = (int)$offset;
		if ($offset == 0) {
			if ($length === null) {
				return $this->fetchItems();
			} elseif ($length > 0) {
				return $this->fetchItems(true, '', 0, $length);
			} else {
				// length < 0
				return array_reverse($this->fetchItems(false, '', - $length), true);
			}
		} elseif ($offset > 0) {
			if ($length === null) {
				return $this->fetchItems(true, '', $offset);
			} elseif ($length > 0) {
				return $this->fetchItems(true, '', $offset, $length);
			} else {
				// length < 0
				$rows = get_data($this->preprocessSql("
					SELECT {PRIORITY}, {ITEM} FROM (
						SELECT {PRIORITY}, {ITEM} FROM {TABLE}
						WHERE {IN_COLLECTION}
						ORDER BY {PRIORITY} DESC
						LIMIT -($length), 18446744073709551615
					)
					ORDER BY {PRIORITY}
					LIMIT $offset, 18446744073709551615
				"));
			}
		} else {
			// offset < 0
			if ($length === null) {
				return array_reverse($this->fetchItems(false, '', - $offset), true);
			} elseif ($length > 0) {
				$rows = get_data($this->preprocessSql("
					SELECT {PRIORITY}, {ITEM} FROM (
						SELECT {PRIORITY}, {ITEM} FROM {TABLE}
						WHERE {IN_COLLECTION}
						ORDER BY {PRIORITY} DESC
						LIMIT -($offset), 18446744073709551615
					)
					ORDER BY {PRIORITY}
					LIMIT $length
				"));
			} else {
				// length < 0
				$rows = get_data($this->preprocessSql("
					SELECT {PRIORITY}, {ITEM} FROM (
						SELECT {PRIORITY}, {ITEM} FROM {TABLE}
						WHERE {IN_COLLECTION}
						ORDER BY {PRIORITY} DESC
						LIMIT -($offset), 18446744073709551615
					)
					ORDER BY {PRIORITY} DESC
					LIMIT -($length), 18446744073709551615
				"));
				if ($rows) {
					$rows = array_reverse($rows);
				}
			}
		}
		$items = array();
		if ($rows) {
			foreach ($rows as $row) {
				$items[$row->{self::COL_PRIORITY}] = (int)$row->{self::COL_ITEM};
			}
		}
		return $items;
	}

	/**
	 * @return int|bool
	 */
	public function removeAll() {
		if (! $this->canEdit()) {
			return false;
		}
		return delete_data($this->preprocessSql("
			DELETE FROM {TABLE}
			WHERE {IN_COLLECTION}
		"));
	}

	/**
	 * Remove items
	 *
	 * @param array|int|ElggEntity $items
	 * @return int|bool
	 */
	public function remove($items) {
		if (! $this->canEdit()) {
			return false;
		}
		if (! $items) {
			return true;
		}
		$items = $this->castPositiveInt($this->castArray($items));
		return delete_data($this->preprocessSql("
			DELETE FROM {TABLE}
			WHERE {IN_COLLECTION} AND {ITEM} IN (" . implode(',', $items) . ")
		"));
	}

	/**
	 * Remove items by priority
	 *
	 * @param array $priorities
	 * @return int|bool
	 * @access private
	 */
	public function removeByPriority($priorities) {
		if (! $this->canEdit()) {
			return false;
		}
		if (! $priorities) {
			return true;
		}
		$priorities = $this->castPositiveInt((array)$priorities);
		return delete_data($this->preprocessSql("
			DELETE FROM {TABLE}
			WHERE {IN_COLLECTION}
			  AND {PRIORITY} IN (" . implode(',', $priorities) . ")
		"));
	}

	/**
	 * Remove item(s) from the beginning.
	 *
	 * @param int $num
	 * @return int|bool num rows removed
	 */
	public function removeFromBeginning($num = 1) {
		return $this->removeMultipleFrom($num, true);
	}

	/**
	 * Remove item(s) from the end.
	 *
	 * @param int $num
	 * @return int|bool num rows removed
	 */
	public function removeFromEnd($num = 1) {
		return $this->removeMultipleFrom($num, false);
	}

	/**
	 * Do any of the provided items appear in the collection?
	 *
	 * @param array|int|ElggEntity $items
	 * @return bool
	 */
	public function hasAnyOf($items) {
		return (bool) $this->intersect($items);
	}

	/**
	 * Return only items that also appear in the collection (and in the order they
	 * appear in the collection)
	 *
	 * @param array|int|ElggEntity $items
	 * @return array
	 */
	public function intersect($items) {
		if (! $items) {
			return array();
		}
		$items = $this->castPositiveInt($this->castArray($items));
		return $this->fetchItems(true, '{ITEM} IN (' . implode(',', $items) . ')');
	}

	/**
	 * Swap the position of two items (the first instance of each)
	 *
	 * @param int|ElggEntity $item1
	 * @param int|ElggEntity $item2
	 * @return bool success
	 */
	public function swapItems($item1, $item2) {
		if (! $this->canEdit()) {
			return false;
		}
		list($item1, $item2) = $this->castPositiveInt(array($item1, $item2));
		$rows = get_data($this->preprocessSql("
			SELECT {PRIORITY}, {ITEM} FROM {TABLE}
			WHERE {IN_COLLECTION}
			  AND {ITEM} IN ($item1, $item2)
		"));
		if (count($rows) === 2 && ($rows[0]->{self::COL_ITEM} != $rows[1]->{self::COL_ITEM})) {
			$row1_item = (int)$rows[0]->{self::COL_ITEM};
			$row2_item = (int)$rows[1]->{self::COL_ITEM};
			$row1_priority = (int)$rows[0]->{self::COL_PRIORITY};
			$row2_priority = (int)$rows[1]->{self::COL_PRIORITY};
			update_data($this->preprocessSql("
				UPDATE {TABLE} SET {ITEM} = $row1_item
				WHERE {PRIORITY} = $row2_priority
			"));
			update_data($this->preprocessSql("
				UPDATE {TABLE} SET {ITEM} = $row2_item
				WHERE {PRIORITY} = $row1_priority
			"));
			return true;
		}
		return false;
	}

	/**
	 * Get an item by index (can be negative!)
	 *
	 * @param int $index
	 * @return int|null
	 */
	public function get($index) {
		$item = $this->fetchItems(true, '', $index, 1);
		return $item ? array_pop($item) : null;
	}

	/**
	 * @param int|ElggEntity $item
	 * @return bool|int
	 *
	 * @access private
	 */
	public function indexOf($item) {
		$item = $this->castPositiveInt($item);
		$row = get_data_row($this->preprocessSql("
			SELECT COUNT(*) AS cnt
			FROM {TABLE}
			WHERE {IN_COLLECTION}
			  AND {PRIORITY} <=
				(SELECT {PRIORITY} FROM {TABLE}
				WHERE {IN_COLLECTION} AND {ITEM} = $item
				ORDER BY {PRIORITY}
				LIMIT 1)
			ORDER BY {PRIORITY}
		"));
		return ($row->cnt == 0) ? false : (int)$row->cnt - 1;
	}

	/**
	 * @param int|ElggEntity $item
	 * @return int|bool false if not found
	 *
	 * @access private
	 */
	public function priorityOf($item) {
		$item = $this->castPositiveInt($item);
		$rows = get_data($this->preprocessSql("
			SELECT {PRIORITY} FROM {TABLE}
			WHERE {IN_COLLECTION} AND {ITEM} = $item
			ORDER BY {PRIORITY}
			LIMIT 1
		"));
		return $rows ? $rows[0]->{self::COL_PRIORITY} : false;
	}



	/**
	 * Add item(s) to the end of the collection. Already existing items are not added/moved.
	 *
	 * @param array|int|ElggEntity $new_items
	 * @return bool success
	 */
	protected function pushMultiple($new_items) {
		if (! $this->canEdit()) {
			return false;
		}
		if (! $new_items) {
			return true;
		}
		$new_items = $this->castPositiveInt($this->castArray($new_items));

		// remove existing from new list
		$existing_items = $this->intersect($new_items);
		$new_items = array_diff($new_items, $existing_items);

		$rows = array();
		$time = time();
		if ($new_items) {
			foreach ($new_items as $item) {
				$rows[] = "($item, '$this->relationship_key', $this->entity_guid, $time)";
			}
			insert_data($this->preprocessSql("
				INSERT INTO {TABLE}
				({ITEM}, {KEY}, {ENTITY_GUID}, time_created)
				VALUES " . implode(', ', $rows) . "
			"));
		}
		return true;
	}

	/**
	 * Fetch items by query
	 *
	 * @param bool $ascending
	 * @param string $where
	 * @param int $offset
	 * @param int|null $limit
	 * @param bool $count_only if true, return will be number of rows
	 * @return array|int|bool
	 */
	protected function fetchItems($ascending = true, $where = '', $offset = 0,
								  $limit = null, $count_only = false) {
		$where_clause = "WHERE {ENTITY_GUID} = $this->entity_guid";
		if (! empty($where)) {
			$where_clause .= " AND ($where)";
		}
		$asc_desc = $ascending ? '' : 'DESC';
		$order_by_clause = "ORDER BY {PRIORITY} $asc_desc";

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
		$columns = '{PRIORITY}, {ITEM}';
		if ($count_only) {
			$columns = 'COUNT(*) AS cnt';
			$order_by_clause = '';
		}
		$rows = get_data($this->preprocessSql("
			SELECT $columns FROM {TABLE}
			$where_clause $order_by_clause $limit_clause
		"));
		if ($count_only) {
			return isset($rows[0]->cnt) ? (int)$rows[0]->cnt : false;
		}
		$items = array();
		if ($rows) {
			foreach ($rows as $row) {
				$items[$row->{self::COL_PRIORITY}] = (int)$row->{self::COL_ITEM};
			}
		}
		return $items;
	}

	/**
	 * Remove several from the beginning/end
	 *
	 * @param int $num
	 * @param bool $from_beginning remove from the beginning of the collection?
	 * @return int|bool num rows removed
	 */
	protected function removeMultipleFrom($num, $from_beginning) {
		if (! $this->canEdit()) {
			return false;
		}
		$num = (int)max($num, 0);
		$asc_desc = $from_beginning ? 'ASC' : 'DESC';
		return delete_data($this->preprocessSql("
			DELETE FROM {TABLE}
			WHERE {IN_COLLECTION}
			ORDER BY {PRIORITY} $asc_desc
			LIMIT $num
		"));
	}

	/**
	 * Cast a single value/entity to an int (or an array of values to an array of ints)
	 *
	 * @param mixed|array $i
	 * @return int|array
	 * @throws InvalidParameterException
	 */
	protected function castPositiveInt($i) {
		$is_array = is_array($i);
		if (! $is_array) {
			$i = array($i);
		}
		foreach ($i as $k => $v) {
			if (! is_int($v) || $v <= 0) {
				if (! is_numeric($v)) {
					if ($v instanceof ElggEntity) {
						/* @var ElggEntity $v */
						$v = $v->guid;
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

	/**
	 * Cast to array without fear of breaking objects
	 *
	 * @param mixed
	 * @return array
	 */
	protected function castArray($i) {
		return is_array($i) ? $i : array($i);
	}

	/**
	 * @param string $sql
	 * @return string
	 */
	protected function preprocessSql($sql) {
		return strtr($sql, array(
			'{TABLE}' => $this->relationship_table,
			'{PRIORITY}' => self::COL_PRIORITY,
			'{ITEM}' => self::COL_ITEM,
			'{ENTITY_GUID}' => self::COL_ENTITY_GUID,
			'{IN_COLLECTION}' => "(" . self::COL_ENTITY_GUID . " = $this->entity_guid "
								. "AND " . self::COL_KEY . " = '$this->relationship_key')",
		));
	}

	/**
	 * Makes sure only one instance is handed out of each possible collection
	 *
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return ElggCollection
	 *
	 * @access private
	 */
	static public function factory(ElggEntity $entity, $name) {
		static $cache = array();
		$key = $entity->guid . '|' . $name;
		if (!isset($cache[$key])) {
			$cache[$key] = new self($entity, $name);
		}
		return $cache[$key];
	}

	/**
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return ElggCollection|null
	 */
	static public function fetch(ElggEntity $entity = null, $name) {
		if (!$entity || !is_string($name) || empty($name)) {
			return null;
		}
		if ($entity->getMetaData("collection_exists:$name")) {
			return self::factory($entity, $name);
		}
		if (!$entity->canEdit()) {
			return null;
		}
		// user who can edit should always find an existing collection
		if (self::exists($entity, $name)) {
			return self::factory($entity, $name);
		}
		return null;
	}

	/**
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return ElggCollection|bool false if user is not permitted to create
	 */
	static public function create(ElggEntity $entity, $name) {
		if ($entity->canEdit()) {
			$coll = self::fetch($entity, $name);
			// create metadata if missing, unowned, PUBLIC
			if (!$coll) {
				create_metadata($entity->guid, "collection_exists:$name", '1', 'integer', 0, ACCESS_PUBLIC);
				$coll = self::factory($entity, $name);
			}
			return $coll;
		}
		return false;
	}

	/**
	 * Tell whether the collection exists, regardless of the current user
	 *
	 * @param ElggEntity|int $entity entity or GUID
	 * @param $name
	 * @return bool
	 */
	static public function exists($entity, $name) {
		$ia = elgg_set_ignore_access(true);
		if (! $entity instanceof ElggEntity) {
			$entity = get_entity($entity);
		}
		$exists = ($entity && $entity->getMetaData("collection_exists:$name"));
		elgg_set_ignore_access($ia);
		return $exists;
	}
}
