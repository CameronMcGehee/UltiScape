<?php

    // Get a full list of calendarEvents within a date span (start and end dates are included) (overdue calendarEvents do not have to be within the date span!)

		function getCompletedCalendarEvents($startDateTime, $endDateTime, $queryParams = '') {
			
            require_once dirname(__DIR__).'../../database.php';
            $db = new database();
            
            $startDateTimeSan = $db->sanitize($startDateTime);
			$endDateTimeSan = $db->sanitize($endDateTime);

			$calendarEvents = [];

            if ($queryParams != '') {
                $queryParams = ' '.$queryParams;
            }

            // Get all overdue calendarEvents
            $fetch = $db->select('completedCalendarEvent', '*', "WHERE workspaceId = '".$_SESSION['lifems_workspaceId']."' AND (startDateTime < '".$startDateTimeSan."' AND completedDate = NULL)". $queryParams);
            if ($fetch) {
                $calendarEvents = array_merge($calendarEvents, $fetch);
            }

            // Get all calendarEvents from the calendarEvents table that match the date range
            $fetch = $db->select('completedCalendarEvent', '*', "WHERE workspaceId = '".$_SESSION['lifems_workspaceId']."' 
            AND (
                (startDateTime >= '".$startDateTimeSan."' AND startDateTime <= '".$endDateTimeSan."')
            OR (endDateTime >= '".$startDateTimeSan."' AND endDateTime <= '".$endDateTimeSan."') 
            OR (startDateTime <= '".$endDateTimeSan."' AND endDateTime IS NULL) 
            OR (frequencyInterval != 'none' AND startDateTime <= '".$startDateTimeSan."' AND endDateTime <= '".$endDateTimeSan."' )
            )". $queryParams);
            if ($fetch) {
                $calendarEvents = array_merge($calendarEvents, $fetch);
            }

			if ($calendarEvents) {
                foreach ($calendarEvents as $key => $calendarEvent) {
                    if ($calendarEvent['instanceDate'] == NULL) {
                        $instanceDate = new DateTime($calendarEvent['startDateTime']);
                        $calendarEvents[$key]['instanceDate'] = $instanceDate->format('Y-m-d');
                    } else {
                        $instanceDate = new DateTime($calendarEvent['instanceDate']);
                        $calendarEvents[$key]['instanceDate'] = $instanceDate->format('Y-m-d');
                    }
                }
                return $calendarEvents;
            } else {
                return [];
            }
            
		}

?>
