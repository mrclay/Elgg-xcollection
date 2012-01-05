<?php
/**
 * A Collection entity.
 */
class ElggXCollection extends ElggEntity {

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

		$this->attributes['type'] = "xcollection";
		$this->attributes['name'] = NULL;
		$this->attributes['description'] = NULL;
		$this->attributes['key'] = NULL;
        $this->attributes['items_type'] = 'entity';
		$this->attributes['tables_split'] = 2;
        $this->attributes['access_id'] = ACCESS_PUBLIC;
	}

	/**
	 * Load or create a new ElggCollection.
     *
     * The constructor is protected to simplify the API; otherwise, when passing in container
     * and key, the constructor would have to throw an exception if a duplicate would be
     * created. This way, the
     *
	 * @param mixed $guid If an int, load that GUID.  If a db row then will attempt
	 * to load the rest of the data. If not given, $container and $key must be set
     * and the collection will be immediately saved.
     * @param ElggEntity $container ignored if $guid is given
     * @param string $key ignored if $guid is given
	 *
	 * @throws IOException If passed an incorrect guid
	 * @throws InvalidParameterException If passed an Elgg* Entity that isn't an ElggxCollection
	 */
	function __construct($guid = null, ElggEntity $container = null, $key = null, $items_type = null) {
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
            $this->attributes['key'] = $key;
            if (! empty($items_type)) {
                $this->attributes['items_type'] = trim($items_type);
            }
            $this->save();
        }
	}

	/**
	 * Loads the full collection when given a guid.
	 *
	 * @param int $guid Guid of collection entity
	 *
	 * @return bool
	 * @throws InvalidClassException
	 */
	protected function load($guid) {
		// Test to see if we have the generic stuff
		if (!parent::load($guid)) {
			return false;
		}

		// Check the type
		if ($this->attributes['type'] != 'xcollection') {
			$msg = elgg_echo('InvalidClassException:NotValidElggStar', array($guid, get_class()));
			throw new InvalidClassException($msg);
		}

		// Load missing data
		$row = get_xcollection_entity_as_row($guid);
		if (($row) && (!$this->isFullyLoaded())) {
			// If $row isn't a cached copy then increment the counter
			$this->attributes['tables_loaded']++;
		}

		// Now put these into the attributes array as core values
		$objarray = (array) $row;
		foreach ($objarray as $key => $value) {
			$this->attributes[$key] = $value;
		}

		// guid needs to be an int  http://trac.elgg.org/ticket/4111
		$this->attributes['guid'] = (int)$this->attributes['guid'];

		return true;
	}

	/**
	 * Saves collection-specific attributes.
	 *
	 * @internal Collection attributes are saved in the collections_entity table.
	 *
	 * @return bool
	 */
	public function save() {
		global $CONFIG;

		// Save generic stuff
		if (!parent::save()) {
			return false;
		}

		return create_xcollection_entity($this->get('guid'), $this->get('name'),
			$this->get('description'), $this->get('key'), $this->get('items_type'));
	}

	/**
	 * Delete the collection (and its items)
	 *
	 * @return bool
	 * @throws SecurityException
	 */
	public function delete() {
        global $CONFIG;

		$delete_successful = delete_xcollection_entity($this->attributes['guid']);
        if ($delete_successful) {
            delete_data("DELETE FROM {$CONFIG->dbprefix}xcollection_items
                         WHERE guid = {$this->attributes['guid']}");
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
    function canEdit($user_guid = 0) {
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
            // if saved, don't allow changing these
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
}
