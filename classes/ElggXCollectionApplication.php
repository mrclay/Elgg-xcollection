<?php

/**
 * Used to determine how a collection is to be applied to a query
 */
class ElggXCollectionApplication {
    protected $collection = null;
    public $includeCollection = true;
    public $includeOthers = false;
    public $isReversed = false;
    public $collectionItemsFirst = true;

    const DEFAULT_ORDER = 'e.time_created DESC';

    public function __construct(ElggXCollection $collection = null) {
        $this->collection = $collection;
    }

    public function useStickyModel() {
        $this->includeOthers = $this->includeCollection = $this->collectionItemsFirst = true;
        $this->isReversed = false;
    }

    public function useAsFilter() {
        $this->includeOthers = true;
        $this->includeCollection = false;
    }

    /**
     * Prepare the options array (to be passed into elgg_get_entities and friends)
     *
     * Note: temporary proof-of-concept API
     *
     * @param array $options
     * @return array
     */
    public function prepareOptions(array $options = array()) {
        static $i = 0;
        if (! $this->includeCollection && ! $this->includeOthers) {
            // return none
            $options['wheres'][] = "(1 = 2)";
            return $options;
        }
        $guid = $this->collection ? $this->collection->get('guid') : 0;
        $i++;
        $tableAlias = "ci{$i}";

        if (empty($options['order_by'])) {
            $options['order_by'] = self::DEFAULT_ORDER;
        }
        global $CONFIG;
        $join = "JOIN {$CONFIG->dbprefix}xcollection_items {$tableAlias} "
              . "ON (e.guid = {$tableAlias}.item AND {$tableAlias}.guid = $guid)";
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