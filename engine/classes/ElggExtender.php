<?php
/**
 * The base class for \ElggEntity extenders.
 *
 * Extenders allow you to attach extended information to an
 * \ElggEntity.  Core supports two: \ElggAnnotation and \ElggMetadata.
 *
 * Saving the extender data to database is handled by the child class.
 *
 * @package    Elgg.Core
 * @subpackage DataModel.Extender
 * @see        \ElggAnnotation
 * @see        \ElggMetadata
 * 
 * @property string $type         annotation or metadata (read-only after save)
 * @property int    $id           The unique identifier (read-only)
 * @property int    $entity_guid  The GUID of the entity that this extender describes
 * @property int    $owner_guid   The GUID of the owner of this extender
 * @property int    $access_id    Specifies the visibility level of this extender
 * @property string $name         The name of this extender
 * @property mixed  $value        The value of the extender (int or string)
 * @property int    $time_created A UNIX timestamp of when the extender was created (read-only, set on first save)
 * @property string $value_type   'integer' or 'text'
 * @property string $enabled      Is this extender enabled ('yes' or 'no')
 */
abstract class ElggExtender extends \ElggData {

	/**
	 * (non-PHPdoc)
	 *
	 * @see \ElggData::initializeAttributes()
	 *
	 * @return void
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();

		$this->attributes['type'] = null;
		$this->attributes['id'] = null;
		$this->attributes['entity_guid'] = null;
		$this->attributes['owner_guid'] = null;
		$this->attributes['access_id'] = ACCESS_PRIVATE;
		$this->attributes['enabled'] = 'yes';
	}

	/**
	 * Set an attribute
	 *
	 * @param string $name  Name
	 * @param mixed  $value Value
	 * @return void
	 */
	public function __set($name, $value) {
		if ($name === 'access_id' && $this instanceof ElggMetadata && $value != ACCESS_PUBLIC) {
			elgg_deprecated_notice('Setting ->access_id to a value other than ACCESS_PUBLIC is deprecated. '
				. 'All metadata will be public in 3.0.', '2.3');
		}

		$this->attributes[$name] = $value;
		if ($name == 'value') {
			$this->attributes['value_type'] = detect_extender_valuetype($value);
		}
	}

	/**
	 * Set the value of the extender
	 * 
	 * @param mixed  $value      The value being set
	 * @param string $value_type The type of the : 'integer' or 'text'
	 * @return void
	 * @since 1.9
	 */
	public function setValue($value, $value_type = '') {
		$this->attributes['value'] = $value;
		$this->attributes['value_type'] = detect_extender_valuetype($value, $value_type);
	}

	/**
	 * Gets an attribute
	 *
	 * @param string $name Name
	 * @return mixed
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->attributes)) {
			if ($name == 'value') {
				switch ($this->attributes['value_type']) {
					case 'integer' :
						return (int)$this->attributes['value'];
						break;
					case 'text' :
						return $this->attributes['value'];
						break;
					default :
						$msg = "{$this->attributes['value_type']} is not a supported \ElggExtender value type.";
						throw new \UnexpectedValueException($msg);
						break;
				}
			}

			return $this->attributes[$name];
		}

		return null;
	}

	/**
	 * Get the GUID of the extender's owner entity.
	 *
	 * @return int The owner GUID
	 */
	public function getOwnerGUID() {
		return $this->owner_guid;
	}

	/**
	 * Get the entity that owns this extender
	 *
	 * @return \ElggEntity
	 */
	public function getOwnerEntity() {
		return get_entity($this->owner_guid);
	}

	/**
	 * Get the entity this describes.
	 *
	 * @return \ElggEntity The entity
	 */
	public function getEntity() {
		return get_entity($this->entity_guid);
	}

	/**
	 * Returns if a user can edit this entity extender.
	 *
	 * @param int $user_guid The GUID of the user doing the editing
	 *                      (defaults to currently logged in user)
	 *
	 * @return bool
	 * @see elgg_set_ignore_access()
	 */
	abstract public function canEdit($user_guid = 0);

	/**
	 * {@inheritdoc}
	 */
	public function toObject() {
		$object = new \stdClass();
		$object->id = $this->id;
		$object->entity_guid = $this->entity_guid;
		$object->owner_guid = $this->owner_guid;
		$object->name = $this->name;
		$object->value = $this->value;
		$object->time_created = date('c', $this->getTimeCreated());
		$object->read_access = $this->access_id;
		$params = array(
			$this->getSubtype() => $this, // deprecated use
			$this->getType() => $this,
		);
		if (_elgg_services()->hooks->hasHandler('to:object', $this->getSubtype())) {
			_elgg_services()->deprecation->sendNotice("Triggering 'to:object' hook by extender name '{$this->getSubtype()}' has been deprecated. "
			. "Use the generic 'to:object','{$this->getType()}' hook instead.", '2.3');
			$object = _elgg_services()->hooks->trigger('to:object', $this->getSubtype(), $params, $object);
		}
		return _elgg_services()->hooks->trigger('to:object', $this->getType(), $params, $object);
	}

	/*
	 * SYSTEM LOG INTERFACE
	 */

	/**
	 * Return an identification for the object for storage in the system log.
	 * This id must be an integer.
	 *
	 * @return int
	 */
	public function getSystemLogID() {
		return $this->id;
	}

	/**
	 * Return a type of extension.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Return a subtype. For metadata & annotations this is the 'name' and
	 * for relationship this is the relationship type.
	 *
	 * @return string
	 */
	public function getSubtype() {
		return $this->name;
	}

	/**
	 * Get a url for this extender.
	 *
	 * Plugins can register for the 'extender:url', <type> plugin hook to
	 * customize the url for an annotation or metadata.
	 *
	 * @return string
	 */
	public function getURL() {

		$url = "";
		$type = $this->getType();
		$subtype = $this->getSubtype();

		// @todo remove when elgg_register_extender_url_handler() has been removed
		if ($this->id) {
			global $CONFIG;

			$function = "";
			if (isset($CONFIG->extender_url_handler[$type][$subtype])) {
				$function = $CONFIG->extender_url_handler[$type][$subtype];
			}
			if (isset($CONFIG->extender_url_handler[$type]['all'])) {
				$function = $CONFIG->extender_url_handler[$type]['all'];
			}
			if (isset($CONFIG->extender_url_handler['all']['all'])) {
				$function = $CONFIG->extender_url_handler['all']['all'];
			}
			if (is_callable($function)) {
				$url = call_user_func($function, $this);
			}

			if ($url) {
				$url = elgg_normalize_url($url);
			}
		}

		$params = array('extender' => $this);
		$url = _elgg_services()->hooks->trigger('extender:url', $type, $params, $url);

		return elgg_normalize_url($url);
	}

}
