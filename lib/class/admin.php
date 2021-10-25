<?php

	class admin {

		private string $setType;
		private databaseManager $db;

		public string $originalAdminId; // Used when updating the table incase the adminId has been changed after instantiation
		public bool $existed; // Can be used to see whether the given entity existed already at the time of instantiation

		public $adminId;
		public $username;
		public $password;
		public $email;
		public $surname;
		public $firstName;
		public $lastName;
		public $profilePicture;
		public $allowSignIn;
		public $dateTimeJoined;
		public $dateTimeLeft;

		public $loginAttempts;
		public $savedLogins;

		private bool $setLoginAttempts;
		private bool $setSavedLogins;

		function __construct(string $adminId = '') {

			// Connect to the database
			require_once dirname(__FILE__)."/../manager/databaseManager.php";
			$this->db = new databaseManager;

			// Empty arrays for extra fetch data
			$this->loginAttempts = array();
			$this->savedLogins = array();

			// Set the loginAttempt and savedLogin set bools to false (these get set to true when the get functions are called to tell the set() function whether it should push these arrays to the database)
			$ths->$setLoginAttempts = false;
			$ths->$setSavedLogins = false;

			// Fetch from database
			$fetch = $this->db->select('admin', '*', "WHERE adminId ='$adminId'");

			// If adminId already exists then set the set method type to UPDATE and fetch the values for the admin
			if ($fetch) {
				$this->setType = 'UPDATE';
				$this->adminId = $adminId;
				$this->existed = true;

				$this->username = $fetch[0]['username'];
				$this->password = $fetch[0]['password'];
				$this->email = $fetch[0]['email'];
				$this->surname = $fetch[0]['surname'];
				$this->firstName = $fetch[0]['firstName'];
				$this->lastName = $fetch[0]['lastName'];
				$this->profilePicture = $fetch[0]['profilePicture'];
				$this->allowSignIn = $fetch[0]['allowSignIn'];
				$this->dateTimeJoined = $fetch[0]['dateTimeJoined'];
				$this->dateTimeLeft = $fetch[0]['dateTimeLeft'];

			// If adminId does not exist then set the set method type to INSERT and inititialize default values
			} else {
				$this->setType = 'INSERT';
				$this->existed = false;

				// Make a new adminId
				require_once dirname(__FILE__)."/uuid.php";
				$uuid = new uuid('table', 'admin', 'adminId');
				$this->adminId = $uuid->generatedId;

				$this->username = '';
				$this->password = '';
				$this->email = '';
				$this->surname = NULL;
				$this->firstName = '';
				$this->lastName = '';
				$this->profilePicture = NULL;
				$this->allowSignIn = '1';
				// Default dateTimeJoined to now since it is likely going to be inserted at this time
				$currentDateTime = new DateTime();
				$this->dateTimeJoined = $currentDateTime->format('Y-m-d H:i:s');

				$this->dateTimeLeft = NULL;
			}

			$this->$originalAdminId = $this->adminId;
			
		}

		public function getLoginAttempts () {
			// If there are entries for adminloginAttempt then push them to the array. load last 5 by default, use method loadAllLoginAttempts to load all.
			$fetch = $this->db->select('adminLoginAttempt', '*', "WHERE adminId ='$adminId' ORDER BY dateTimeAdded DESC LIMIT 5");
			if ($fetch) {
				$loginAttempts = array();
				foreach ($fetch as $row) {
					array_push($loginAttempts, array('adminLoginAttemptId' =>$row['adminLoginAttemptId'], 'dateTimeUsed' => $row['dateTimeUsed'], 'clientIp' => $row['clientIp'], 'enteredUsername' => $row['enteredUsername'], 'result' => $row['result']));
				}
			}

			$setLoginAttempts = true;
		}

		public function getSavedLogins() {
			// If there are entries for adminsavedLogin then push them to the array
			$fetch = $this->db->select('adminSavedLogin', '*', "WHERE adminId ='$adminId' ORDER BY dateTimeAdded DESC LIMIT 5");
			if ($fetch) {
				$savedLogins = array();
				foreach ($fetch as $row) {
					array_push($savedLogins, $row['adminSavedLoginId']);
				}
			}
			$setSavedLogins = true;
		}

		// Adds the admin to the database or updates the values
		public function set() {

			$attributes = array(
				'adminId' => $this->db->sanitize($this->adminId),
				'username' => $this->db->sanitize($this->username),
				'password' => $this->db->sanitize($this->password),
				'email' => $this->db->sanitize($this->email),
				'surname' => $this->db->sanitize($this->surname),
				'firstName' => $this->db->sanitize($this->firstName),
				'lastName' => $this->db->sanitize($this->lastName),
				'profilePicture' => $this->db->sanitize($this->profilePicture),
				'allowSignIn' => $this->db->sanitize($this->allowSignIn),
				'dateTimeJoined' => $this->db->sanitize($this->dateTimeJoined),
				'dateTimeLeft' => $this->db->sanitize($this->dateTimeLeft)
			);

			if ($this->setType == 'UPDATE') {

				// Update the values in the database after sanitizing them
				if ($this->db->update('admin', $attributes, "WHERE adminId = ".$this->db->sanitize($this->originalAdminId), 1)) {
					return true;
				} else {
					return $this->db->getLastError();
				}

			} else {

				// Insert the values to the database after sanitizing them
				if ($this->db->insert('admin', $attributes)) {
					return true;
				} else {
					return $this->db->getLastError();
				}

			}

		}
	}

?>
