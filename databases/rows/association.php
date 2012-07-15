<?php

class KDatabaseRowAssociations extends KDatabaseRowDefault
{
	/**
	 * Get a value by key or a associated
	 *
	 * @param   string  The key name.
	 * @return  string|KDatabaseRow|KDatabaseRowset  The corresponding value.
	 */
	public function __get($property)
	{
		//Check if this is an associatable property
		if( !isset($this->_data[$property]) &&
			$this->isAssociatable() &&
			$this->hasAssociation($property)){
			return $this->getAssociated($property);
		}

		return parent::__get($property);
	}


	/**
	 * Overridden __call method to allow for retrieving associated data and pass filters
	 * @param $property
	 * @param array $args
	 * @return KDatabaseRowset|mixed
	 */
	public function __call($method, $args = array())
	{
		$property = $method;

		//Check if its an associated property
		if(KInflector::isPlural($method) && !in_array($method, $this->_mixed_methods))
		{
			//Lazy load mixin
			//If associatable mixin loaded, look for association
			if($this->isAssociatable())
			{
				$state = array_shift($args);

				//Check if we've previously retrieved this property
				if(isset($this->_data[$property]) && empty($state)) return $this->_data[$property];

				//Ensure association exists
				if($this->hasAssociation($property))
				{
					//Retrieve association and check type is plural
					if($association = $this->getAssociation($property))
					{
						if(in_array($association->type, array('one_many','many_many')))
						{
							//Get the data
							$data = $this->getAssociated($property, $state);

							//Only merge in data with no state info
							if(empty($state)) $this->_data[$property] = $data;

							return $data;
						}
					}
				}
			}
		}

		return parent::__call($method, $args);
	}
}