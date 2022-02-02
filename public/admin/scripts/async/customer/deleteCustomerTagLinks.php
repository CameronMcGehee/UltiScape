<?php

    // This script takes an array as input: [['customerId1', 'customerTagId1'], ['customerId2', 'customerTagId2'], etc.]. If not in this format, error will be thrown.

    require_once '../../../../php/startSession.php';

    // Make sure that an admin is logged in

    if (!isset($_SESSION['ultiscape_adminId']) || !isset($_SESSION['ultiscape_businessId'])) {
        echo 'unauthorized';
        exit();
    }

    // Verify that variables are set

    if (!isset($_POST['authToken']) || !isset($_POST['customerTagLinks']) || gettype($_POST['customerTagLinks']) != 'array') {
        echo 'inputInvalid';
        die();
    }

    foreach ($_POST['customerTagLinks'] as $tagLink) {
        if (empty($tagLink[0]) || empty($tagLink[1])) {
            echo 'inputInvalid';
            die();
        }
    }

    // Validate the auth token
	require_once '../../../../../lib/etc/authToken/validateAuthToken.php';
	if (!validateAuthToken($_POST['authToken'], 'deleteCustomerTagLinks')) {
		echo 'tokenInvalid';
		exit();
	}

    require_once '../../../../../lib/database.php';

    foreach ($_POST['customerTagLinks'] as $tagLink) {
        // Make sure that the bridge entry exists
        $db = new database();
        $select = $db->select('customerCustomerTagBridge', 'customerCustomerTagId', "WHERE customerId = '".$db->sanitize($tagLink[0])."' AND customerTagId = '".$db->sanitize($tagLink[1])."'");
        if (!$select) {
            echo 'noLink';
            die();
        } else if (count($select) !== 1) {
            echo 'unknown: '.$db->getLastError();
            die();
        }
        // delete the link
        if ($db->delete('customerCustomerTagBridge', "WHERE businessId = '".$_SESSION['ultiscape_businessId']."' AND customerId = '".$db->sanitize($tagLink[0])."' AND customerTagId = '".$db->sanitize($tagLink[1])."'", 1)) {
            echo 'success';
        } else {
            echo 'deleteError';
            die();
        }
    }

    // Use the auth token
	require_once '../../../../../lib/etc/authToken/useAuthToken.php';
	useAuthToken($_POST['authToken'], 'deleteCustomerTagLinks');

?>