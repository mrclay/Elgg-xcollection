<?php
/**
 * A Collection entity.
 */
class ElggXCollection extends ElggObject {

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
	function __construct($guid = null, ElggEntity $container = null, $key = null, $items_type = 'entity') {
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
            $this->save();
        }
	}

	/**
	 * Delete the collection (and its items)
	 *
	 * @return bool
	 * @throws SecurityException
	 */
	public function delete() {
        global $CONFIG;

		$delete_successful = parent::delete();
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
}
