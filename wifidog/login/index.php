<?php
  // $Id$
  /********************************************************************\
   * This program is free software; you can redistribute it and/or    *
   * modify it under the terms of the GNU General Public License as   *
   * published by the Free Software Foundation; either version 2 of   *
   * the License, or (at your option) any later version.              *
   *                                                                  *
   * This program is distributed in the hope that it will be useful,  *
   * but WITHOUT ANY WARRANTY; without even the implied warranty of   *
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the    *
   * GNU General Public License for more details.                     *
   *                                                                  *
   * You should have received a copy of the GNU General Public License*
   * along with this program; if not, contact:                        *
   *                                                                  *
   * Free Software Foundation           Voice:  +1-617-542-5942       *
   * 59 Temple Place - Suite 330        Fax:    +1-617-542-2652       *
   * Boston, MA  02111-1307,  USA       gnu@gnu.org                   *
   *                                                                  *
   \********************************************************************/
  /**@file
   * Login page
   * @author Copyright (C) 2004 Benoit Gr�goire et Philippe April
   */
define('BASEPATH','../');
require_once BASEPATH.'include/common.php';
require_once BASEPATH.'classes/SmartyWifidog.php';
require_once BASEPATH.'classes/Security.php';

$smarty = new SmartyWifidog;
$session = new Session;

include BASEPATH.'include/language.php';

$login_successful = false;
$login_failed_message = '';

if (!empty($_REQUEST['url'])) {
    $session = new Session();
    $session->set(SESS_ORIGINAL_URL_VAR, $_REQUEST['url']);
}


if (!empty($_REQUEST['username']) && !empty($_REQUEST['password'])) {
    $security = new Security();
    $evious_username = $db->EscapeString($_REQUEST['username']);
    $username = $db->EscapeString($_REQUEST['username']);
    $password_hash = get_password_hash($_REQUEST['password']);
    $db->ExecSqlUniqueRes("SELECT *, CASE WHEN ((NOW() - reg_date) > interval '".VALIDATION_GRACE_TIME." minutes') THEN true ELSE false END AS validation_grace_time_expired FROM users WHERE (user_id='$username' OR email='$username') AND pass='$password_hash'", $user_info, false);

    if ($user_info != null) {
	if (($user_info['account_status'] == ACCOUNT_STATUS_VALIDATION) && ($user_info['validation_grace_time_expired']=='t')) {
	    $login_successful=false;
	    $validation_grace_time = VALIDATION_GRACE_TIME;
	    $smarty->assign("error",  _("Sorry, your $validation_grace_time minutes grace period to retrieve your email and validate your account has now expired. ($validation_grace_time min grace period started on $user_info[reg_date]).  You will have to connect to the internet and validate your account from another location."));
	  } else {
	    $token = gentoken();
	    if ($_REQUEST['gw_id']) {
            $node_id = $db->EscapeString($_REQUEST['gw_id']);
	    }
	    if ($_SERVER['REMOTE_ADDR']) {
		    $node_ip = $db->EscapeString($_SERVER['REMOTE_ADDR']);
	    }
	    $db->ExecSqlUpdate("INSERT INTO connections (user_id, token, token_status, timestamp_in, node_id, node_ip, last_updated) VALUES ('{$user_info['user_id']}', '$token', '" . TOKEN_UNUSED . "', NOW(), '$node_id', '$node_ip', NOW())");
	
	    $login_successful = true;
	    $security->login($username, $password_hash);
	    header("Location: http://" . $_REQUEST['gw_address'] . ":" . $_REQUEST['gw_port'] . "/wifidog/auth?token=$token");
	    }
    } else {
	    $user_info = null;
	    /* This is only used to discriminate if the problem was a non-existent user of a wrong password. */
        $db->ExecSqlUniqueRes("SELECT * FROM users WHERE user_id='$username' OR email='$username'", $user_info, false);
	    if ($user_info == null) {
	        $smarty->assign("error",  _('Unknown username or email'));
	    } else {
	        $smarty->assign("error",  _('Incorrect password (Maybe you have CAPS LOCK on?)'));
	    }
    }
}

if ($login_successful == false) {
    isset($_REQUEST["username"]) && $smarty->assign('username', $_REQUEST["username"]);
    isset($_REQUEST["gw_address"]) && $smarty->assign('gw_address', $_REQUEST['gw_address']);
    isset($_REQUEST["gw_port"]) && $smarty->assign('gw_port', $_REQUEST['gw_port']);
    isset($_REQUEST["gw_id"]) && $smarty->assign('gw_id', $_REQUEST['gw_id']);
    
    $smarty->display("templates/".LOGIN_PAGE_NAME);
}
?>
