<?php

class KDatabaseRowActiverecord extends KDatabaseRowDefault{

	/**
	 * Association information.
	 * Associations must be specified in the following format:
	 *
	 *
	 *  $_associations = array(
	 *      property_name => array(
	 *          'type' => (string) one_one|one_many|many_many
	 *           'model' => 'a nooku model identifier string or object',
	 *              this is the identifer for the source model where the data is being retrieved from
	 *
	 *          'keys' => array('foreign_key' => 'local_key'),
	 *              foreign key is the key for the associated table, local key is the column in this table that contains the value.
	 *              when creating a many to many association this refers to the key within the association table defined below
	 *
	 *          'through' => 'a nooku model identifier' (optional)
	 *              only required for many to many association. This is the ID for the association table
	 *      )
	 * )
	 *
	 * Within the appropriate association type
	 *
	 * @var array
	 */
	protected $_associations = array();


	/**
	 * Object constructor
	 *
	 * @param   object  An optional KConfig object with configuration options.
	 */
	public function __construct(KConfig $config)
	{
		parent::__construct($config);

		//Store associations
		$this->_associations = KConfig::unbox($config->associations);
	}


	/**
	 * Initializes the options for the object, adding associateds key
	 *
	 * @param 	object 	An optional KConfig object with configuration options.
	 * @return void
	 */
	protected function _initialize(KConfig $config)
	{
		parent::_initialize($config);
		$config->append(
			array('associations' => $this->_associations
		));
	}


	/**
	 * Get a value by key or a associated
	 *
	 * @param   string  The key name.
	 * @return  string|KDatabaseRow|KDatabaseRowset  The corresponding value.
	 */
	public function __get($property)
	{
		if(!isset($this->_data[$property]) && $this->hasAssociation($property)){
			return $this->getAssociates($property);
		}

		return parent::__get($property);
	}


	/**
	 * Overridden __call method to allow for retrieving associated data and pass filters
	 * @param $property
	 * @param array $args
	 * @return KDatabaseRowset|mixed
	 */
	public function __call($property, $args = array())
	{
		//Ensure this property is relatable
		if($this->hasAssociation($property) &&
			($association = $this->getAssociation($property)) &&
			in_array($association->type, array('one_many','many_many')))
		{
			$state = array_shift($args);

			//Get the data
			$data = $this->getAssociates($property, $state);

			//Only merge in data with no state info
			if(empty($state)) $this->_data[$property] = $data;

			return $data;
		}else{
			return parent::__call($property, $args);
		}
	}


	/**
	 * Chceks for a association for the passed property
	 * @param $property
	 * @return bool
	 */
	public function hasAssociation($property)
	{
		return isset($this->_associations[$property]);
	}


	/**
	 * Return the association for the given key if it exists
	 * @param $property
	 * @return KConfig|null
	 */
	public function getAssociation($property)
	{
		//Attempt to find association
		if(!isset($this->_associations[$property]) ||
			!isset($this->_associations[$property]['type']) ||
			!isset($this->_associations[$property]['model']) ||
			!isset($this->_associations[$property]['keys']) ||
			!in_array($this->_associations[$property]['type'], array('one_one','one_many','many_many')))
		{
			return null;
		}

		return new KConfig($this->_associations[$property]);
	}


	/**
	 * Retrieves the associated data for a property
	 * @param $property
	 * @param array $args
	 * @param null $type
	 * @return KDatabaseRow|KDatabaseRowSet|null
	 */
	public function getAssociates($property, $state = array())
	{
		//Attempt to find association
		if(!($association = $this->getAssociation($property)))
		{
			return null;
		}

		//Return data for the relevant type
		switch($association->type)
		{
			case 'one_one':
				return $this->getOneToOneAssociates($property);
				break;

			case 'one_many':
				return $this->getOneToManyAssociates($property, $state);
				break;

			case 'many_many':
				return $this->getManyToManyAssociates($property, $state);
				break;
		}

		return null;
	}


	/**
	 * Collects data for a one to one association for this record
	 * @param string $property
	 * @return KDatabaseRow|null
	 */
	protected function getOneToOneAssociates($property)
	{
		//Ensure associated exists and is complete
		if(!($association = $this->getAssociation($property)))
		{
			return null;
		}

		//Try to retrieve the data from the model
		try{
			//Check the identifier is a model
			$model = $this->getService($association->model);
			if(!$model instanceof KModelAbstract) return null;

			//Map the states foreign keys
			$state = array();
			foreach($association->keys->toArray() AS $fk => $lk)
			{
				if(!isset($state[$fk]))	$state[$fk] = $this->$lk;
			}

			//Can't have empty state
			if(empty($state)) return false;

			return $model->set($state)->getItem();
		}
		catch (Exception $e)
		{
			return null;
		}
	}


	/**
	 * Collects data for a one to many association for this record
	 * @param string $property
	 * @param array $state - Optional state data
	 * @return KDatabaseRowSet|null
	 */
	protected function getOneToManyAssociates($property, $state = array())
	{
		//Ensure associated exists and is complete
		if(!($association = $this->getAssociation($property)))
		{
			return null;
		}

		//Try to retrieve the data from the model
		try{
			//Check the identifier is a model
			$model = $this->getService($association->model);
			if(!$model instanceof KModelAbstract) return null;

			//Map the states foreign keys
			foreach($association->keys->toArray() AS $fk => $lk)
			{
				$state[$fk] = $this->$lk;
			}

			//Ensure we have a key
			if(empty($state)) return null;

			//Ensure that each key is a state in the relevant model
			return $model->set($state)->getList();
		}
		catch (Exception $e)
		{
			return null;
		}
	}


	/**
	 * Collects data for a many to many association with this record
	 * @param string $property
	 * @param array $state - Optional state data
	 * @return KDatabaseRow|null
	 */
	protected function getManyToManyAssociates($property, $state = array())
	{
		//Ensure associated exists and is complete
		if(!($association = $this->getAssociation($property)))
		{
			return null;
		}

		//Try to retrieve the data from the model
		try
		{
			//Check the identifier is a model
			$model = $this->getService($association->model);
			if(!$model instanceof KModelAbstract) return null;

			//Check for a through model
			if($association->through)
			{
				$associated_model = $this->getService($association->through);
				if(!$associated_model instanceof KModelAbstract) return null;

				//Map the states foreign keys
				$through_state = array();
				foreach($association->keys->toArray() AS $fk => $lk)
				{
					$through_state[$fk] = $this->$lk;
				}

				//Ensure we have a key
				if(empty($through_state)) return null;

				//Ensure that each key is a state in the relevant model
				$associations = $associated_model->set($through_state)->getList();

				//Compile associated items state
				$property_singular = KInflector::singularize($this->getIdentifier($association->model)->name);
				if(!isset($state['id'])) $state['id'] = array();
				foreach($associations AS $association)
				{
					$state['id'][] = $association->{$property_singular.'_id'};
				}
			}else
			{
				//Map the states foreign keys
				foreach($association->keys->toArray() AS $fk => $lk)
				{
					$state[$fk] = $this->$lk;
				}
			}

			//Get the associated items
			return $model->set($state)->getList();
		}
		catch (Exception $e)
		{
			return null;
		}
	}
}