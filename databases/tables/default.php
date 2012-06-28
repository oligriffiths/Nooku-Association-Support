<?php
/**
 * Created By: Oli Griffiths
 * Date: 01/06/2012
 * Time: 10:16
 */
defined('KOOWA') or die('Protected resource');

class KDatabaseTableActiveRecord extends KDatabaseTableDefault
{
	protected static $_tables;
	protected $_relationships;


	/**
	 * Get an instance of a row object for this table and merges in the relations
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @return  KDatabaseRowInterface
	 */
	public function getRow(array $options = array())
	{
		$options['relationships'] = isset($options['relationships']) ? array_merge($options['relationships'], $this->getrReationships()) : $this->getrReationships();
		return parent::getRow($options);
	}



	/**
	 * Create the relationships between the source table and other tables
	 * Relationships are determined using the following rules.
	 *
	 * One to one relationships:
	 * These are determined using the columns from this table. Any column ending _id is created as a relationship minus the _id
	 *
	 * One to many relationships:
	 * These are determined following a naming convention. Any tables in the DB that belong to the same package that match the
	 * singular of the source table followed by an underscore. Also any tables that contain a column of the source table singular followed by _id.
	 * E.g. package_users is related to package_user_groups. Also, package_users is related to package_posts if posts contains a user_id column

	 * Many to many relationships:
	 * These are determined following a naming conventions. Any tables in the DB that belong to the same packages that match the
	 * plural of the source table followed by or preceded by an underscore. Also any tables that contain a column of the source table singular followed by _id.
	 * Also, if a relationship is defined for the table matched above, then an identifier for the source model is created by removing the plural suffix.
	 * E.g. package_posts is related to package_posts_categories which is in turn related to package_posts. So package_posts > package_posts_categories > package_categories
	 *
	 * @return mixed
	 */
	public function getRelations()
	{
		if(isset($this->_relationships)) return $this->_relationships;

		$tables         = $this->getTables();
		$identifier     = clone $this->getIdentifier();
		$identifier->path = array('model');
		$package        = $identifier->package;
		$name           = $this->getName();
		$stub_singular  = KInflector::singularize($identifier->name);
		$stub_plural    = KInflector::pluralize($identifier->name);
		$primary_keys   = array();
		$one_to_one     = array();
		$one_to_many    = array();
		$many_to_many   = array();
		$columns        = $this->getColumns();


		//1:1 Relations
		foreach($columns AS $id => $column)
		{
			//Find all columns ending _id but not the id column
			if(preg_match('#_id$#', $column->name) && $id != 'id')
			{
				//Create table name
				$model_name = KInflector::pluralize(preg_replace('#_id$#','',$column->name));

				//Ensure the table actually exists
				if(isset($tables[$package.'_'.$model_name]))
				{
					$id = clone $identifier;
					$id->name = $model_name;

					//Ensure the model & table exists
					if($this->getService($id)->isConnected())
					{
						$one_to_one[KInflector::singularize($model_name)] = array('model' => $id, 'keys' => array('id' => $column->name));
					}
				}
			}elseif($column->primary)
			{
				$primary_keys[$column->name] = $id;
			}
		}

		//1:N and N:N Relations
		foreach($tables AS $table)
		{
			//Ensure the table is part of this package
			if(preg_match('#^'.$package.'_#', $table) && $table != $name)
			{
				$table          = preg_replace('#^'.$package.'_#', '', $table);
				$is_one_many    = preg_match('#^'.preg_quote($stub_singular).'_#', $table);
				$is_many_many   = !$is_one_many ? (preg_match('#^'.preg_quote($stub_plural).'_#', $table) || preg_match('#_'.preg_quote($stub_plural).'$#', $table)) : false;

				//If no relationship by name, try to determine relationship from table schema by comparing primary keys
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

						//Ensure all this tables primarys keys exist within the "related" table
						if(!array_intersect($keys, $columns) == $keys) continue;

						//Check if table is a one_many or many_many
						$parts = explode('_', $table);
						$is_many_many = true;
						foreach($parts AS $part)
						{
							if(KInflector::isSingular($part))
							{
								$is_one_many = true;
								$is_many_many = false;
								break;
							}
						}
					}catch(Exception $e){}
				}


				//Validate 1:N relationship
				if($is_one_many)
				{
					$relation_table = preg_replace('#^'.$stub_singular.'_#','', $table);
					$id = clone $identifier;
					$id->name = $table;

					$one_to_many[$relation_table] = array('model' => $id, 'keys' => $primary_keys);
				}
				//Validate N:N relationship
				elseif($is_many_many)
				{
					$relation_table = preg_replace('#^'.$stub_plural.'_#','', $table);
					$id = clone $identifier;
					$id->name = $relation_table;

					$keys = array();
					foreach($primary_keys AS $column => $pk)
					{
						$keys[$pk] = $column;
					}

					$relation = clone $identifier;
					$relation->name = $table;
					$many_to_many[$relation_table] = array('model' => $id,	'keys' => $primary_keys, 'relation' => $relation);
				}
			}
		}

		$this->_relationships = array('one_one' => $one_to_one, 'one_many' => $one_to_many, 'many_many' => $many_to_many);
		return $this->_relationships;
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