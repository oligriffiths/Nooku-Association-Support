<?php
/**
 * Created By: Oli Griffiths
 * Date: 21/06/2012
 * Time: 13:56
 */
defined('KOOWA') or die('Protected resource');

KService::get('com://site/base.initialize');

echo KService::get('com://site/test.dispatcher', array('request'=> array('view' => KRequest::get('request.view','cmd','tests'))))->dispatch();