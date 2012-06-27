<?php

class ComBaseCommonDatabaseRowDefault extends KDatabaseRowDefault{

	/**
	 * Relationship information.
	 * Relationships must be specified in the following format:
	 *
	 * property_name => array(
	 *      'model' => 'a nooku model identifier string or object',
	 *          this is the identifer for the source model where the data is being retrieved from
	 *
	 *      'keys' => array('foreign_key' => 'local_key'),
	 *          foreign key is the key for the related table, local key is the column in this table that contains the value.
	 *          when creating a many to many relationship this refers to the key within the relationship table defined below
	 *
	 *      'relation' => 'a nooku model identifier' (optional)
	 *          only required for many to many relationship. This is the ID for the relationship table
	 * )
	 * 
	 * Within the appropriate relationship type
	 *
	 * @var array
	 */ 
	protected $_relationships = array(
		'one_one' => array(),
		'one_many' => array(),
		'many_many' => array()
	);


	/**
	 * Object constructor
	 *
	 * @param   object  An optional KConfig object with configuration options.
	 */
	public function __construct(KConfig $config)
	{
		parent::__construct($config);

		//Store relations
		$this->_relationships = $config->relationships->toArray();
	}


	/**
	 * Initializes the options for the object, adding relations key
	 *
	 * @param 	object 	An optional KConfig object with configuration options.
	 * @return void
	 */
	protected function _initialize(KConfig $config)
	{
		parent::_initialize($config);
		$config->append(array('relationships' => $this->_relationships));
	}


	/**
	 * Get a value by key or a relation
	 *
	 * @param   string  The key name.
	 * @return  string|KDatabaseRow|KDatabaseRowset  The corresponding value.
	 */
	public function __get($property)
	{
		if(!isset($this->_data[$property]) && $this->hasRelation($property)){
			return $this->getRelation($property);
		}

		return parent::__get($property);
	}


	/**
	 * Overridden __call method to allow for retrieving related data and pass filters
	 * @param $property
	 * @param array $args
	 * @return KDatabaseRowset|mixed
	 */
	public function __call($property, $args = array())
	{
		if(KInflector::isPlural($property) && $this->hasRelation($property)){
			$state = array_shift($args);
			$this->_data[$property] = $this->getRelation($property, $state);
			return $this->_data[$property];
		}else{
			return parent::__call($property, $args);
		}
	}


	/**
	 * Chceks for a relationship for the passed property
	 * @param $property
	 * @return bool
	 */
	public function hasRelation($property)
	{
		return isset($this->_relationships['one_one'][$property]) || isset($this->_relationships['one_many'][$property]) || isset($this->_relationships['many_many'][$property]);
	}


	/**
	 * Retrieves the relation data for a property
	 * @param $property
	 * @param array $args
	 * @param null $type
	 * @return KDatabaseRow|KDatabaseRowSet|null
	 */
	public function getRelation($property, $state = array(), $type = null)
	{
		//Attempt to find relationship
		if(isset($this->_relationships['one_one'][$property]))
		{
			$type = 'one_one';
		}
		elseif(isset($this->_relationships['one_many'][$property]))
		{
			$type = 'one_many';
		}
		elseif(isset($this->_relationships['many_many'][$property]))
		{
			$type = 'many_many';
		}

		//Return data for the relevant type
		switch($type)
		{
			case 'one_one':
				return $this->getOneToOneRelation($property);
				break;

			case 'one_many':
				return $this->getOneToManyRelation($property, $state);
				break;

			case 'many_many':
				return $this->getManyToManyRelation($property, $state);
				break;
		}

		return null;
	}


	/**
	 * Collects data for a one to one relationship for this record
	 * @param string $property
	 * @return KDatabaseRow|null
	 */
	protected function getOneToOneRelation($property)
	{
		//Ensure relation exists and is complete
		if( !isset($this->_relationships['one_one'][$property]) ||
			!isset($this->_relationships['one_one'][$property]['model']) ||
			!isset($this->_relationships['one_one'][$property]['keys'])
		) return null;

		//Try to retrieve the data from the model
		try{
			//Check the identifier is a model
			$model = $this->getService($this->_relationships['one_one'][$property]['model']);
			if(!$model instanceof KModelAbstract) return null;

			//Map the states foreign keys
			$state = array();
			foreach($this->_relationships['one_one'][$property]['keys'] AS $fk => $lk)
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
	 * Collects data for a one to many relationship for this record
	 * @param string $property
	 * @param array $state - Optional state data
	 * @return KDatabaseRowSet|null
	 */
	protected function getOneToManyRelation($property, $state = array())
	{
		//Ensure relation exists and is complete
		if( !isset($this->_relationships['one_many'][$property]) ||
			!isset($this->_relationships['one_many'][$property]['model']) ||
			!isset($this->_relationships['one_many'][$property]['keys'])
		) return null;

		//Try to retrieve the data from the model
		try{
			//Check the identifier is a model
			$model = $this->getService($this->_relationships['one_many'][$property]['model']);
			if(!$model instanceof KModelAbstract) return null;

			//Map the states foreign keys
			foreach($this->_relationships['one_many'][$property]['keys'] AS $fk => $lk)
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
	 * Collects data for a many to many relationship with this record
	 * @param string $property
	 * @param array $state - Optional state data
	 * @return KDatabaseRow|null
	 */
	protected function getManyToManyRelation($property, $state = array())
	{
		//Ensure relation exists and is complete
		if( !isset($this->_relationships['many_many'][$property]) ||
			!isset($this->_relationships['many_many'][$property]['model']) ||
			!isset($this->_relationships['many_many'][$property]['keys']) ||
			!isset($this->_relationships['many_many'][$property]['relation'])
		) return null;

		//Try to retrieve the data from the model
		try
		{
			//Check the identifier is a model
			$model = $this->getService($this->_relationships['many_many'][$property]['model']);
			if(!$model instanceof KModelAbstract) return null;

			//Check relation model
			$relation_model = $this->getService($this->_relationships['many_many'][$property]['relation']);
			if(!$relation_model instanceof KModelAbstract) return null;

			//Map the states foreign keys
			foreach($this->_relationships['many_many'][$property]['keys'] AS $fk => $lk)
			{
				$state[$fk] = $this->$lk;
			}

			//Ensure we have a key
			if(empty($state)) return null;

			//Ensure that each key is a state in the relevant model
			$relations = $relation_model->set($state)->getList();

			//Compile related items state
			$property_singular = KInflector::singularize($this->getIdentifier($this->_relationships['many_many'][$property]['model'])->name);
			$state = array_merge_recursive($state, array('id' => array()));
			foreach($relations AS $relation)
			{
				$state['id'][] = $relation->{$property_singular.'_id'};
			}

			//Get the related items
			return $model->set($state)->getList();
		}
		catch (Exception $e)
		{
			return null;
		}
	}
}