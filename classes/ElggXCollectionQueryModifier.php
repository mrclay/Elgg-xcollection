<?php

/**
 * Create a strategy for applying a collection to a query
 */
class ElggXCollectionQueryModifier {
    protected $collection = null;
    public $includeCollection = true;
    public $includeOthers = false;
    public $isReversed = false;
    public $collectionItemsFirst = true;
    static protected $counter = 0;

    const DEFAULT_ORDER = 'e.time_created DESC';

    /**
     * @param ElggXCollection|null $collection
     */
    public function __construct(ElggXCollection $collection = null) {
        $this->collection = $collection;
    }

    /**
     * @return ElggXCollection|null
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
     * @return ElggXCollectionQueryModifier
     */
    public function useStickyModel() {
        $this->includeOthers = $this->includeCollection = $this->collectionItemsFirst = true;
        $this->isReversed = false;
        return $this;
    }

    /**
     * @return ElggXCollectionQueryModifier
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
        $guid = $this->collection ? $this->collection->get('guid') : 0;
        if (empty($options['order_by'])) {
            $options['order_by'] = self::DEFAULT_ORDER;
        }
        global $CONFIG;
        $join = "JOIN {$CONFIG->dbprefix}xcollection_items {$tableAlias} "
              . "ON ({$joinOnColumn} = {$tableAlias}.item AND {$tableAlias}.guid = $guid)";
        if ($this->includeOthers) {
            $join = "LEFT {$join}";
        }
        $options['joins'][] = $join;
        if ($this->includeCollection) {
            $order = "{$tableAlias}.priority";
            if ($this->collectionItemsFirst != $this->isReversed) {
                $order = "- $order";
            }
            if ($this->collectionItemsFirst) {
                $order .= " DESC";
            }
            $options['order_by'] = "{$order}, {$options['order_by']}";
        } else {
            $options['wheres'][] = "({$tableAlias}.item IS NULL)";
        }
        return $options;
    }
}
