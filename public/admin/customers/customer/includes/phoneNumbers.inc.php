<?php

	require_once '../../../../php/startSession.php';

	// Make sure that an admin is logged in

	if (!isset($_SESSION['ultiscape_adminId']) || !isset($_SESSION['ultiscape_businessId'])) {
		echo 'error (1)';
		exit();
	}

	if (!isset($_POST['customerId'])) {
		echo 'error (2)';
		exit();
	}

	require_once '../../../../../lib/table/customer.php';
	$currentCustomer = new customer($_POST['customerId']);

	if ($currentCustomer->businessId != $_SESSION['ultiscape_businessId']) {
        echo "unauthorized";
		exit();
    }

    // Get all the phone numbers in the system
    $currentCustomer->pullPhoneNumbers('ORDER BY dateTimeAdded ASC');

	// Render the list of phone number inputs and buttons
	require_once '../../../../../lib/table/customerPhoneNumber.php';
    foreach ($currentCustomer->phoneNumbers as $phoneNumberId) {
        $phoneInfo = new customerPhoneNumber($phoneNumberId);
        echo '<input type="hidden" name="phoneNumberIds[]" value="'.htmlspecialchars($phoneNumberId).'">
		';
		echo '<input placeholder="+1" class="almostInvisibleInputNoHover" style="font-size: 1.2em; width: 2em; max-width: 10%; display: inline;" type="text" name="phoneNumberPrefixes[]" id="phoneNumberPrefix'.htmlspecialchars($phoneNumberId).'" value="'.htmlspecialchars($phoneInfo->phonePrefix).'">
		';
		echo '<input placeholder="Phone 1..." class="almostInvisibleInputNoHover" style="font-size: 1.2em; width: 13em; display: inline;" type="text" name="phoneNumbers[]" id="phoneNumber'.htmlspecialchars($phoneNumberId).'" value="'.htmlspecialchars($phoneInfo->phone1).'">
		';
		echo ' <input class="almostInvisibleInputNoHover" style="font-size: 1.2em; width: 22%; display: inline;" type="text" name="phoneNumberDescriptions[]" id="phoneNumberDescription'.htmlspecialchars($phoneNumberId).'" placeholder="Note" value="'.htmlspecialchars($phoneInfo->description).'">
		';
		echo ' <span id="deletePhoneNumber:::'.htmlspecialchars($phoneNumberId).'" class="extraSmallButtonWrapper orangeButton xyCenteredFlex" style="width: 1em; display: inline;"><img style="height: 1em;" src="../../../images/ultiscape/icons/trash.svg"></span>
		';
		echo '<span id="phoneNumber'.htmlspecialchars($phoneNumberId).'Error" class="underInputError" style="display: none;"><br><br>Enter a valid phone number (digits only).</span><br><br>
		';
	}

    // One at the end to add a new phone number
	echo '<span style="border-radius: .3em; padding: .6em;" class="defaultMainShadows">
	<input class="invisibleInputNoHover" style="font-size: 1.2em; width: 2em; max-width: 10%;" type="text" name="newPhoneNumberPrefix" id="newPhoneNumberPrefix" placeholder="+1">';
	echo '<input class="invisibleInputNoHover" style="font-size: 1.2em; width: 70%;" type="text" name="newPhoneNumber" id="newPhoneNumber" placeholder="Add Phone Number...">
	</span>';
	echo '<span id="newPhoneNumberError" class="underInputError" style="display: none;"><br><br>Enter a valid phone number.</span>';

?>
