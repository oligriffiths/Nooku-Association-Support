<?php
/**
 * Created By: Oli Griffiths
 * Date: 01/06/2012
 * Time: 10:16
 */
defined('KOOWA') or die('Protected resource');

class KDatabaseTableAssociations extends KDatabaseTableDefault
{
	public function __construct(KConfig $config)
	{
		if($config->get('load_associations',true))
		{
			$config->append(array('behaviors' => array('associatable')));
		}
		parent::__construct($config);
	}
}