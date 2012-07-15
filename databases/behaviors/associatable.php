<?php
/**
 * User: Oli Griffiths
 * Date: 02/07/2012
 * Time: 19:11
 */

class KDatabaseBehaviorAssociatable extends KDatabaseBehaviorAbstract
{
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
	 * @var array
	 */
	static protected $_tables;
	protected $table;
	protected $_associations = array();
	protected $_associations_processed;

	/**
	 * Constructor.
	 * Sets up the associations
	 *
	 * @param 	object 	An optional KConfig object with configuration options
	 */
	public function __construct(KConfig $config)
	{
		parent::__construct($config);

		//Set the table
		$this->table = $config->mixer;
	}


	/**
	 * Create the associations between the source table and other tables
	 * Associations are determined using the following rules.
	 *
	 * One to one associations:
	 * These are determined using the columns from this table. Any column ending _id is created as a association minus the _id
	 *
	 * One to many associations:
	 * These are determined following a naming convention. Any tables in the DB that belong to the same package that match the
	 * singular of the source table followed by an underscore. Also any tables that contain a column of the source table singular followed by _id.
	 * E.g. package_users is associated to package_user_groups. Also, package_users is associated to package_posts if posts contains a user_id column

	 * Many to many associations:
	 * These are determined following a naming conventions. Any tables in the DB that belong to the same packages that match the
	 * plural of the source table followed by or preceded by an underscore. Also any tables that contain a column of the source table singular followed by _id.
	 * Also, if a association is defined for the table matched above, then an identifier for the source model is created by removing the plural suffix.
	 * E.g. package_posts is associated to package_posts_categories which is in turn associated to package_posts. So package_posts > package_posts_categories > package_categories
	 *
	 * @return mixed
	 */
	protected function detectAssociations()
	{
		if($this->_associations_processed) return $this->_associations;

		//Check APC cache for associations
		if(extension_loaded('apc')){
			if($associations = apc_fetch('koowa-cache-identifier-'.((string) $this->table->getIdentifier()).'.associations'))
			{
				$this->_associations = $associations;
				return $this->_associations;
			}
		}

		$tables         = $this->getTables();
		$identifier     = clone $this->table->getIdentifier();
		$identifier->path = array('model');
		$package        = $identifier->package;
		$name           = $this->table->getName();
		$stub_singular  = KInflector::singularize($identifier->name);
		$stub_plural    = KInflector::pluralize($identifier->name);
		$primary_keys   = array();
		$columns        = $this->table->getColumns();


		//1:1 Associations
		foreach($columns AS $id => $column)
		{
			//Find all columns ending _id but not the id column
			if(preg_match('#_id$#', $column->name) && $id != 'id')
			{
				//Create table name
				$model_name = KInflector::pluralize(preg_replace('#_id$#','',$column->name));

				//If this association is already defined, move on.
				if(isset($this->_associations[KInflector::singularize($model_name)])) continue;

				//Ensure the table actually exists
				if(isset($tables[$package.'_'.$model_name]))
				{
					$id = clone $identifier;
					$id->name = $model_name;

					//Ensure the model & table exists
					if($this->getService($id)->isConnected())
					{
						$this->_associations[KInflector::singularize($model_name)] = array('type' => 'one_one', 'model' => $id, 'keys' => array('id' => $column->name));
					}
				}
			}elseif($column->primary)
			{
				$primary_keys[$column->name] = $id;
			}
		}

		//1:N and N:N Associations
		foreach($tables AS $table)
		{
			//Ensure the table is part of this package
			if(preg_match('#^'.$package.'_#', $table) && $table != $name)
			{
				$table          = preg_replace('#^'.$package.'_#', '', $table);
				$is_one_many    = preg_match('#^'.preg_quote($stub_singular).'_#', $table);
				$is_many_many   = !$is_one_many ? (preg_match('#^'.preg_quote($stub_plural).'_#', $table) || preg_match('#_'.preg_quote($stub_plural).'$#', $table)) : false;

				//If no association by name, try to determine association from table schema by comparing primary keys
				if(!$is_one_many && !$is_many_many)
				{
					try{
						//Get DB table and columns
						$id = clone $this->table->getIdentifier();
						$id->path = array('database','table');
						$id->name = $table;
						$dbtable    = $this->getService($id);
						$cols       = $dbtable->getColumns();

						$columns    = array();
						foreach($cols AS $column)
						{
							$columns[] = $column->name;
						}
						$keys = array_keys($primary_keys);

						//Ensure all this tables primarys keys exist within the "associated" table
						if(!array_intersect($keys, $columns) == $keys) continue;

						//Check if table is a one_many or many_many
						$parts = explode('_', $table);

						//Detect relationship
						$is_one_many = $is_many_many = true;
						if(count($parts) == 1){
							$is_many_many = false;
						}
						else
						{
							foreach($parts AS $part)
							{
								if(KInflector::isSingular($part)) $is_many_many = false;
							}
						}
					}catch(Exception $e){}
				}

				//Validate 1:N association
				if($is_one_many)
				{
					$association_table = preg_replace('#^'.$stub_singular.'_#','', $table);

					//If this association is already defined, move on.
					if(isset($this->_associations[$association_table])) continue;

					//Construct the model identifier
					$id = clone $identifier;
					$id->name = $table;
					$this->_associations[$association_table] = array('type' => 'one_many', 'model' => $id, 'keys' => $primary_keys);
				}
				//Validate N:N association
				elseif($is_many_many)
				{
					$association_table = preg_replace('#^'.$stub_plural.'_#','', $table);

					//If this association is already defined, move on.
					if(isset($this->_associations[$association_table])) continue;

					//Construct the model identifier
					$id = clone $identifier;
					$id->name = $association_table;
					$keys = array();
					foreach($primary_keys AS $column => $pk)
					{
						$keys[$pk] = $column;
					}

					//Construct the association model identifier
					$association = clone $identifier;
					$association->name = $table;
					$this->_associations[$association_table] = array('type' => 'many_many', 'model' => $id,	'keys' => $primary_keys, 'through' => $association);
				}
			}
		}

		//Store associations in apc
		if(extension_loaded('apc')){
			apc_store('koowa-cache-identifier-'.((string) $this->table->getIdentifier()).'.associations', $this->_associations);
		}

		$this->_associations_processed = true;
		return $this->_associations;
	}


	/**
	 * Retrieves all the tables from the DB, strips off the table prefix and returns
	 * @return array
	 */
	protected function getTables()
	{
		//Check if tables have been cached previously
		if(!isset(self::$_tables))
		{
			//Get the tables from the DB
			$dbtables = $this->table->getDatabase()->select('SHOW TABLES', KDatabase::FETCH_FIELD_LIST);
			$prefix = $this->table->getDatabase()->getTablePrefix();
			self::$_tables = array();
			foreach($dbtables AS $table)
			{
				if(preg_match('#^'.preg_quote($prefix).'#',$table))
				{
					$table = preg_replace('#^'.preg_quote($prefix).'#','', $table);
					self::$_tables[$table] = $table;
				}
			}
		}

		return self::$_tables;
	}


	/**
	 * Chceks for a association for the passed property
	 * @param $property
	 * @return bool
	 */
	public function hasAssociation($property)
	{
		//Get the associations
		$this->detectAssociations();

		return isset($this->_associations[$property]);
	}


	/**
	 * Return the association for the given key if it exists
	 * @param $property
	 * @return KConfig|null
	 */
	public function getAssociation($property)
	{
		//Get the associations
		$this->detectAssociations();

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
	public function getAssociated($property, $state = array())
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
				return $this->getOneToOneAssociated($property);
				break;

			case 'one_many':
				return $this->getOneToManyAssociated($property, $state);
				break;

			case 'many_many':
				return $this->getManyToManyAssociated($property, $state);
				break;
		}

		return null;
	}


	/**
	 * Collects data for a one to one association for this record
	 * @param string $property
	 * @return KDatabaseRow|null
	 */
	protected function getOneToOneAssociated($property)
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

			//Get the mixer for the data
			$mixer = $this->getMixer();

			//Map the states foreign keys
			$state = array();
			foreach($association->keys->toArray() AS $fk => $lk)
			{
				if(!isset($state[$fk]) && $mixer->$lk !== null)	$state[$fk] = $mixer->$lk;
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
	protected function getOneToManyAssociated($property, $state = array())
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

			//Get the mixer for the data
			$mixer = $this->getMixer();

			//Map the states foreign keys
			foreach($association->keys->toArray() AS $fk => $lk)
			{
				if(!isset($state[$fk]) && $mixer->$lk !== null) $state[$fk] = $mixer->$lk;
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
	protected function getManyToManyAssociated($property, $state = array())
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

			//Get the mixer for the data
			$mixer = $this->getMixer();

			//Check for a through model
			if($association->through)
			{
				$associated_model = $this->getService($association->through);
				if(!$associated_model instanceof KModelAbstract) return null;

				//Map the states foreign keys
				$through_state = array();
				foreach($association->keys->toArray() AS $fk => $lk)
				{
					if(!isset($state[$fk]) && $mixer->$lk !== null) $through_state[$fk] = $mixer->$lk;
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
					$state[$fk] = $mixer->$lk;
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