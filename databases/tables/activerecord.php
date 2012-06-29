<?php
/**
 * Created By: Oli Griffiths
 * Date: 01/06/2012
 * Time: 10:16
 */
defined('KOOWA') or die('Protected resource');

class KDatabaseTableActiverecord extends KDatabaseTableDefault
{
	protected static $_tables;
	protected $_associations;
	protected $_associations_processed = false;


	/**
	 * Get an instance of a row object for this table and merges in the associations
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @return  KDatabaseRowInterface
	 */
	public function getRow(array $options = array())
	{
		$options['associations'] = isset($options['associations']) ? array_merge($options['associations'], $this->getAssociations()) : $this->getAssociations();
		return parent::getRow($options);
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
	public function getAssociations()
	{
		if($this->_associations_processed) return $this->_associations;

		//Check APC cache for associations
		if(extension_loaded('apc')){
			$associations = apc_fetch('koowa-cache-identifier-'.((string) $this->getIdentifier()).'.associations');
			if($associations)
			{
				$this->_associations = $associations;
				return $this->_associations;
			}
		}

		$tables         = $this->getTables();
		$identifier     = clone $this->getIdentifier();
		$identifier->path = array('model');
		$package        = $identifier->package;
		$name           = $this->getName();
		$stub_singular  = KInflector::singularize($identifier->name);
		$stub_plural    = KInflector::pluralize($identifier->name);
		$primary_keys   = array();
		$columns        = $this->getColumns();


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
						$id = clone $this->getIdentifier();
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

		if(extension_loaded('apc')){
			apc_store('koowa-cache-identifier-'.((string) $this->getIdentifier()).'.associations', $this->_associations);
		}

		$this->_associations_processed = true;
		return $this->_associations;
	}


	/**
	 * Retrieves all the tables from the DB, strips off the table prefix and returns
	 * @return array
	 */
	public function getTables()
	{
		//Check if tables have been cached previously
		if(!isset(self::$_tables))
		{
			//Get the tables from the DB
			$dbtables = $this->getDatabase()->select('SHOW TABLES', KDatabase::FETCH_FIELD_LIST);
			$prefix = $this->getDatabase()->getTablePrefix();
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
}