<?php
// include db handler
include('include/config.php');
include(DB_FUNCTION_PATH . 'db_functions.php');
include(FUNCTION_PATH . 'functions.php');
include(FUNCTION_PATH . 'ics_class.php');


$db = new DB_Functions();

$validation = array();
//response to be converted in json
$response = array();

$discount_amount =0 ;
if(isset($_REQUEST['payment'])) {
	
	$payment = json_decode($_REQUEST['payment'], true);
	
	$discount_amount = $payment['discount'];
}
//check validation for expert_id and date
if (getValue('expert_id') == "") {
    $response["error"] = TRUE;
    $response["error_msg"] = "expert id is not set";
    $db->closedb();
    echo json_encode($response);
    unset($response);
    unset($db);
    cleanup();
    exit();
}
if (getValue('platform_used') == "") {
    $response["error"] = 1;
    $response["error_msg"] = "Invalid parameters";
    $db->closedb();
    echo json_encode($response);
    unset($response);
    unset($db);
    cleanup();
    exit();
}

if (getValue('appointment_date') == "") {
    $response["error"] = TRUE;
    $response["error_msg"] = "Date is not set";
$db->closedb();
    echo json_encode($response);
    unset($response);
    unset($db);
    cleanup();
    exit();
}

$validation_params = array('expert_id', 'appointment_date', 'service_id', 'start_time', 'email', 'name', 'country_id', 'state_id', 'city', 'address', 'zipcode');
foreach ($validation_params AS $param) {
    if (!isset($_REQUEST[$param])) {

        $response["error"] = TRUE;
        $response["error_msg"] = "Invalid parameters";
        $db->closedb();
        echo json_encode($response);
        exit();
    }
}

$appointment_data = array(
    'appointment_id' => trim(getValue('appointment_id')),
    'expert_id' => trim(getValue('expert_id')),
    'service_id' => addslashes(trim(getValue('service_id'))),
    'start_time' => addslashes(date('H:i:s', strtotime(trim(getValue('start_time'))))),
    'appointment_date' => addslashes(date('Y-m-d', strtotime(trim(getValue('appointment_date'))))),
    'email' => addslashes(trim(getValue('email'))),
    'name' => explode(' ', addslashes(trim(getValue('name'))))['0'],
    'last_name' => explode(' ', addslashes(trim(getValue('name'))))['1'],
    'contact_number' => trim(getValue('contact_number')),
    'country_id' => trim(getValue('country_id')),
    'state_id' => trim(getValue('state_id')),
    'city' => trim(getValue('city')),
    'suite' => trim(getValue('suite')),
    'address' => addslashes(trim(getValue('address'))),
    'zipcode' => trim(getValue('zipcode')),
    'platform_used' => trim(getValue('platform_used')),
	'discount_amount'=>trim($discount_amount)
);

$service_details = $db->get_service_detail_by_id($appointment_data['service_id']);
$expert_details = $db->getUserDetails($appointment_data['expert_id']);

date_default_timezone_set("UTC");
$utc_datetime = date('Y-m-d H:i:s');

$company_detail = $db->get_company_details($expert_details['company_id']);
$company_timezone = $db->get_company_timezone($expert_details['company_id']);

date_default_timezone_set($company_timezone);
$today = date('Y-m-d H:i:s');

$gap_in_min = $expert_details['unavailable_after_appointment'];
if (empty($gap_in_min)) {
    $gap_in_min = '0';
}

$end_time = strtotime("+" . $service_details['min_needed'] . " minutes", strtotime($appointment_data['start_time']));
$end_time = date('H:i:s', $end_time);

$last_time = strtotime("+" . $gap_in_min . " minutes", strtotime($end_time));
$last_time = date('H:i:s', $last_time);

$expert_available = $db->check_expert_availability($service_details['name'], $appointment_data['expert_id'], $appointment_data['start_time'], $end_time, $last_time, $appointment_data['appointment_date'], $gap_in_min, $appointment_data['appointment_id']);
//echo "$service_details[name], $appointment_data[expert_id], $appointment_data[start_time], $end_time, $last_time, $appointment_data[appointment_date], $gap_in_min, $appointment_data[appointment_id]";
if (!$expert_available['return_value']) {

    $response["error"] = TRUE;
    $response["error_msg"] = $expert_available['msg'];
$db->closedb();
    echo json_encode($response);
    exit();

} else {
    $customer_details = $db->get_customer_by_email($appointment_data['email']);
    if ($customer_details === false) {
        $new_password = $db->generateRandomString('6');
        $hashed_password = $db->create_password($new_password);

        $ref_discount_code = "RS-" . substr($appointment_data['name'], 0, 2) . substr($appointment_data['last_name'], 0, 2);

        $sql_refl_customer = mysql_query("select referral_id from company_customers where referral_id like '$ref_discount_code%'");
        $discount_code_num = mysql_num_rows($sql_refl_customer);

        if ($discount_code_num > 0) {
            while ($row_refl_customer = mysql_fetch_assoc($sql_refl_customer)) {
                $row_refl_customer_arr[] = $row_refl_customer['referral_id'];
            }
            for ($cd = 1; $cd < 1000000; $cd++) {
                $ref_discount_code_final = $ref_discount_code . $i;
                if (in_array($ref_discount_code_final, $row_refl_customer_arr)) {
                } else {
                    break;
                }
            }

        } else {
            $ref_discount_code_final = $ref_discount_code . '1';
        }
        $query = "test query";
        $result = mysql_query($query) or die(mysql_error());
        $customer_id = mysql_insert_id();

        $to = $appointment_data['email'];
        $subject = 'Welcome to dash board 24/7 Booking System!';

        $toRepArray = array('[!FIRST_NAME!]', '[!CUSTOMER_EMAIL!]', '[!PASSWORD!]');
        $fromRepArray = array($appointment_data['name'],
            $appointment_data['email'],
            $new_password,
        );


        send_mail($to, $subject, 'welcome_mail', $toRepArray, $fromRepArray, $expert_details['company_id']);

    } else {
        $query = "update query";

        $result = mysql_query($query) or die(mysql_error());
        $customer_id = $customer_details['id'];
    }
    $customer_details = $db->get_customer_by_email($appointment_data['email']);
    if ($appointment_data['appointment_id'] > 0) {
        $current_datetime = date('Y-m-d H:i:s');
        $querySession = "select";
        $resultSession = mysql_query($querySession) or die(mysql_error());
        if (!(mysql_num_rows($resultSession) > 0)) {
            $response["error"] = TRUE;
            $response["error_msg"] = "Your session has expired. Please hit the back button and start the booking process again.";
            echo json_encode($response);
            exit();
        }

        $eastern_start_time = convert_to_eastern($appointment_data['appointment_date'], $appointment_data['start_time'], $company_timezone);
        $eastern_end_time = convert_to_eastern($appointment_data['appointment_date'], $end_time, $company_timezone);

        $active = '1';
        $payment_status = '2';        
        if( $service_details['type'] == '2' ){
            if( isset($_REQUEST['payment']) ){   
                $_REQUEST['payment'] = json_decode($_REQUEST['payment'], true);                             
                if( ( isset($_REQUEST['payment']['discountPercent']) && $_REQUEST['payment']['discountPercent'] != 100 ) OR ( !isset($_REQUEST['payment']['discountPercent']) ) ){
                    $active = '0';
                    $payment_status = '0';
                }                
            }
            else{   
                $active = '0';
                $payment_status = '0';
            }
        }        

        $queryIA = "update";


        $resultIA = mysql_query($queryIA) or die(mysql_error() . $queryIA);

        $appointment_id = $appointment_data['appointment_id'];

        if ($active == '1') {

            $queryCC = "insert";
            $resultCC = mysql_query($queryCC) or die(mysql_error());

            $ics_name = 'Add-To-Calendar:Appointment_' . $appointment_id . '.ics';

            $location = (!empty($company_detail['suite'])) ? $company_detail['address'] . ', Suite ' . $company_detail['suite'] . ', ' : $company_detail['address'] . ', ';
            $location .= $company_detail['city'] . ', ' . $company_detail['state_name'] . ' ' . $company_detail['zipcode'];

            $event = new ICS($appointment_data['appointment_date'] . " " . $appointment_data['start_time'], $appointment_data['appointment_date'] . " " . $end_time, $service_details['name'], $service_details['description'], $location);
            $event->save($ics_name);

            $company_address = (!empty($company_detail['suite'])) ? $company_detail['address'] . ', Suite ' . $company_detail['suite'] . '<br/>' : $company_detail['address'] . '<br/>';
            $company_address .= $company_detail['city'] . ', ' . $company_detail['state_name'] . ' ' . $company_detail['zipcode'];

            $new_company_address = '';
            if ($service_details['outcall'] == 0) {
                $new_company_address = '<p class="content">
                <strong>Our Address</strong> <br/>
                <a target="_blank" href="http://maps.google.com/?q=' . $location . '">' . $company_address . '</a>
            </p>';
            }

            $to = $customer_details['email'];
            $subject = 'Appointment Confirmation:  ' . $service_details['name'] . ' at ' . $company_detail['name'] . '  on ' . date('F j, Y', strtotime($appointment_data['appointment_date']));

            $pre_field_form = '';
            if ($service_details['pre_field_form'] != '')
                $pre_field_form = "Note: Please fill out <a href='" . $service_details['pre_field_form'] . "' target='_blank'>this form</a> before the appointment so we can better serve you.";


            $toRepArray = array('[!FIRST_NAME!]', '[!SERVICE!]', '[!EXPERT!]', '[DATE]', '[!TIME!]', '[!COMPANY_ADDRESS!]', '[!PAYMENT!]', '[!PRE_FIELD_FORM!]');
            $fromRepArray = array($customer_details['name'],
                $service_details['name'],
                $expert_details['firstname'] . ' ' . $expert_details['lastname'],
                date('F j, Y', strtotime($appointment_data['appointment_date'])),
                date('g:i A', strtotime($appointment_data['start_time'])),
                $new_company_address,
                '',
                $pre_field_form
            );

            send_mail($to, $subject, 'add_appointment', $toRepArray, $fromRepArray, $expert_details['company_id'], $ics_name);

            /*
         * Mail to expert new code
         */

            $customer_location = (!empty($customer_details['suite'])) ? $customer_details['address'] . ', Suite ' . $customer_details['suite'] . ', ' : $customer_details['address'] . ', ';
            $customer_location .= $customer_details['city'] . ', ' . $customer_details['state_name'] . ' ' . $customer_details['zipcode'];


            $customer_address = (!empty($customer_details['suite'])) ? $customer_details['address'] . ', Suite ' . $customer_details['suite'] . '<br/>' : $customer_details['address'] . '<br/>';
            $customer_address .= $customer_details['city'] . ', ' . $customer_details['state_name'] . ' ' . $customer_details['zipcode'];


            $to = $expert_details['email'];
            $subject = 'New Appointment Added: ' . $service_details['name'] . '  on ' . date('F j, Y', strtotime($appointment_data['appointment_date']));

            $new_customer_address = '';
            if ($service_details['outcall'] == 1) {
                $new_customer_address = '<p class="content">
                    <strong>Address</strong> <br/>
                    <a target="_blank" href="http://maps.google.com/?q=' . $customer_location . '">' . $customer_address . '</a>
                    </p>';
            }


            $toRepArray = array('[!FIRST_NAME!]', '[!SERVICE!]', '[!CUSTOMER!]', '[DATE]', '[!TIME!]', '[!COMPANY_ADDRESS!]', '[!PAYMENT!]');
            $fromRepArray = array(
                $expert_details['firstname'],
                $service_details['name'],
                $customer_details['name'] . ' ' . $customer_details['last_name'],
                date('F j, Y', strtotime($appointment_data['appointment_date'])),
                date('g:i A', strtotime($appointment_data['start_time'])),
                $new_customer_address,
                '',

            );

//                $from_title = $company_detail['name'];
//                $from_email = $company_detail['company_email'];

            send_mail($to, $subject, 'expert_add_appointment', $toRepArray, $fromRepArray, $expert_details['company_id'], $ics_name);

            /*
             * end mail to expert
             */

            @unlink(ICS_PATH . $ics_name);

            // user found
            $response["error"] = FALSE;
            $response["success"] = "Advanced Appointment has been successfully added.";
            $response["appointment_id"] = $appointment_id;
            $response["public_key"] = $company_detail['stripe_api_published_key'];
        } else {

            // user found
            $response["error"] = FALSE;
            $response["success"] = "Advance appointment successfully updated.";
            $response["appointment_id"] = $appointment_id;
            $response["public_key"] = $company_detail['stripe_api_published_key'];

        }
    }    
$db->closedb();
    echo json_encode($response);
    unset($response);
    unset($db);
    cleanup();
    exit();

}


?>
