<?php

    // This script takes an array as input: [['contactId1', 'contactTagId1'], ['contactId2', 'contactTagId2'], etc.]. If not in this format, error will be thrown.

    require_once '../../../../php/startSession.php';

    // Make sure that an admin is logged in

    if (!isset($_SESSION['lifems_adminId']) || !isset($_SESSION['lifems_workspaceId'])) {
        echo 'unauthorized';
        exit();
    }

    // Verify that variables are set

    if (!isset($_POST['authToken']) || !isset($_POST['contactTagLinks']) || gettype($_POST['contactTagLinks']) != 'array') {
        echo 'inputInvalid';
        die();
    }

    foreach ($_POST['contactTagLinks'] as $tagLink) {
        if (empty($tagLink[0]) || empty($tagLink[1])) {
            echo 'inputInvalid';
            die();
        }
    }

    // Validate the auth token
	require_once '../../../../../lib/etc/authToken/validateAuthToken.php';
	if (!validateAuthToken($_POST['authToken'], 'createContactTagLinks')) {
		echo 'tokenInvalid';
		exit();
	}

    require_once '../../../../../lib/database.php';

    foreach ($_POST['contactTagLinks'] as $tagLink) {
        // Make sure that the bridge entry doesn't already exist
        $db = new database();
        $select = $db->select('contactContactTagBridge', 'contactContactTagId', "WHERE contactId = '".$db->sanitize($tagLink[0])."' AND contactTagId = '".$db->sanitize($tagLink[1])."'");
        if ($select) {
            echo 'alreadyExists';
            die();
        }
        // create the link
        if ($db->insert('contactContactTagBridge', ['workspaceId' => $_SESSION['lifems_workspaceId'], 'contactId' => $db->sanitize($tagLink[0]), 'contactTagId' => $db->sanitize($tagLink[1]))) {
            echo 'success';
        } else {
            echo 'insertError';
            die();
        }
    }

    // Use the auth token
	require_once '../../../../../lib/etc/authToken/useAuthToken.php';
	useAuthToken($_POST['authToken'], 'createContactTagLinks');

?>
