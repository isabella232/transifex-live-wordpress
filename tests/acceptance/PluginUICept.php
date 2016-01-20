<?php 
date_default_timezone_set('America/New_York');

$I = new AcceptanceTester($scenario);

$I->amOnPage('/wp-login.php');
$I->fillField('Username', 'test');
$I->fillField('Password','test');
$I->click('Log In');
$I->see('Dashboard');
$I->click('Settings');
$I->click('Transifex Live');
$I->see('Transifex Live Translation Plugin Settings','h2');
$I->seeInFormFields('#settings_form',[ 
	'transifex_live_settings[api_key]' => ''
	]);