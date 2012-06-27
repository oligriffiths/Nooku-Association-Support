<?php
/**
 * Created By: Oli Griffiths
 * Date: 21/06/2012
 * Time: 14:04
 */
defined('KOOWA') or die('Protected resource');

$time = microtime(true);

$user = $users->top();

echo '<pre>
User:
';
var_dump($user->getData());

echo "\n\n";

echo '
User->profile:
';
var_dump($user->profile->getData());


echo "\n\n";

echo '
User->documents:

';
foreach($user->documents() AS $document)
{
	echo "User->document:\n";
	var_dump($document->getData());
	echo "User->document->user:\n";
	var_dump($document->user->getData());
	echo "\n";
}


echo "\n\n";

echo '
User->groups:
';
var_dump($user->groups->getData());


echo "\n\n";

echo '
User->posts:
';
var_dump($user->posts->getData());


echo '</pre>';


echo 'Runtime: '.(microtime(true) - $time);

exit;