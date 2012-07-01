<?php

/**
 * Create a strategy for applying a collection to a query
 */
class ElggCollectionQueryModifier {

    protected $collection = null;
    public $includeCollection = true;
    public $includeOthers = false;
    public $isReversed = false;
    public $collectionItemsFirst = true;
    static protected $counter = 0;

    const DEFAULT_ORDER = 'e.time_created DESC';

    /**
     * @param ElggCollection|null $collection
     */
    public function __construct(ElggCollection $collection = null) {
        $this->collection = $collection;
    }

    /**
     * @return ElggCollection|null
     */
    public function getCollection() {
        return $this->collection;
    }

    /**
     * Reset the collection_items table alias counter (call after each query to optimize
     * use of the query cache)
     */
    static public function resetCounter() {
        self::$counter = 0;
    }

    /**
     * Get the next collection_items table alias
     * @return int
     */
    static public function getTableAlias() {
        self::$counter++;
        return "ci" . self::$counter;
    }

    /**
     * @return ElggCollectionQueryModifier
     */
    public function useStickyModel() {
        $this->includeOthers = $this->includeCollection = $this->collectionItemsFirst = true;
        $this->isReversed = false;
        return $this;
    }

    /**
     * @return ElggCollectionQueryModifier
     */
    public function useAsFilter() {
        $this->includeOthers = true;
        $this->includeCollection = false;
        return $this;
    }

    /**
     * Prepare the options array for elgg_get_entities/etc. so that the collection is
     * applied to the query
     *
     * Note: temporary proof-of-concept API
     *
     * @param array $options
     * @param string $joinOnColumn
     * @return array
     */
    public function prepareOptions(array $options = array(), $joinOnColumn = 'e.guid') {
        if (! $this->includeCollection && ! $this->includeOthers) {
            // return none
            $options['wheres'][] = "(1 = 2)";
            return $options;
        }
        $tableAlias = self::getTableAlias();
		$guid = 0;
		$key = '';
		if ($this->collection) {
			$guid = $this->collection->getEntityGuid();
			$key = $this->collection->getRelationshipKey();
		}
        if (empty($options['order_by'])) {
            $options['order_by'] = self::DEFAULT_ORDER;
        }

		$table           = elgg_get_config('dbprefix') . ElggCollection::TABLE_UNPREFIXED;
		$col_item        = ElggCollection::COL_ITEM;
		$col_entity_guid = ElggCollection::COL_ENTITY_GUID;
		$col_key         = ElggCollection::COL_KEY;
		$col_priority    = ElggCollection::COL_PRIORITY;

        $join = "JOIN $table $tableAlias "
              . "ON ($joinOnColumn = {$tableAlias}.{$col_item} "
			  . "    AND {$tableAlias}.{$col_entity_guid} = $guid "
			  . "    AND {$tableAlias}.{$col_key} = '$key') ";
        if ($this->includeOthers) {
            $join = "LEFT {$join}";
        }
        $options['joins'][] = $join;
        if ($this->includeCollection) {
            $order = "{$tableAlias}.{$col_priority}";
            if ($this->collectionItemsFirst != $this->isReversed) {
                $order = "- $order";
            }
            if ($this->collectionItemsFirst) {
                $order .= " DESC";
            }
            $options['order_by'] = "{$order}, {$options['order_by']}";
        } else {
            $options['wheres'][] = "({$tableAlias}.{$col_item} IS NULL)";
        }
        return $options;
    }

	/**
	 * This is a shim to support a 'collections' key in $options for elgg_get_entities, etc.
	 * Call this on $options to convert 'collections' into other keys that those functions
	 * already support.
	 *
	 * @param array $options
	 * @param string $join_column (e.g. set to "rv.id" to order river items)
	 */
	static public function applyToOptions(&$options, $join_column = 'e.guid') {
		if (empty($options['xcollections'])) {
			return;
		}
		if (! is_array($options['xcollections'])) {
			$options['xcollections'] = array($options['xcollections']);
		}
		foreach ($options['xcollections'] as $app) {
			if ($app instanceof ElggCollection) {
				$app = new self($app);
			}
			if ($app instanceof ElggCollectionQueryModifier) {
				$options = $app->prepareOptions($options, $join_column);
			}
		}
		self::resetCounter();
		unset($options['xcollections']);
	}
}
