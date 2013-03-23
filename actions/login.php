<?php

// set forward url
if (isset($_SESSION['last_forward_from']) && $_SESSION['last_forward_from']) {
	$forward_url = $_SESSION['last_forward_from'];
	unset($_SESSION['last_forward_from']);
} elseif (get_input('returntoreferer')) {
	$forward_url = REFERER;
} else {
	// forward to main index page
	$forward_url = '';
}

$username = get_input('username');
$password = get_input('password', null, false);
$persistent = get_input("persistent", false);
$result = false;

if (empty($username) || empty($password)) {
	register_error(elgg_echo('login:empty'));
	forward();
}

// check if logging in with email address
if (strpos($username, '@') !== false && ($users = get_user_by_email($username))) {
	$username = $users[0]->username;
}

$result = elgg_authenticate($username, $password);

// Open log
openlog("elgg({$_SERVER['HTTP_HOST']})", LOG_PID, LOG_AUTH);

if ($result !== true) {
        // Log authentication error, in a format almost identical to the SSH rule (for compatibility)
        syslog(LOG_NOTICE,"Authentication failure for $username from {$_SERVER['REMOTE_ADDR']}");
      
	register_error($result);
	forward(REFERER);
}

// We got here, so login was successful
syslog(LOG_INFO,"Accepted password for $username from {$_SERVER['REMOTE_ADDR']}");

closelog();

$user = get_user_by_username($username);
if (!$user) {
	register_error(elgg_echo('login:baduser'));
	forward(REFERER);
}

try {
	login($user, $persistent);
	// re-register at least the core language file for users with language other than site default
	register_translations(dirname(dirname(__FILE__)) . "/languages/");
} catch (LoginException $e) {
	register_error($e->getMessage());
	forward(REFERER);
}

// elgg_echo() caches the language and does not provide a way to change the language.
// @todo we need to use the config object to store this so that the current language
// can be changed. Refs #4171
if ($user->language) {
	$message = elgg_echo('loginok', array(), $user->language);
} else {
	$message = elgg_echo('loginok');
}

system_message($message);
forward($forward_url);
