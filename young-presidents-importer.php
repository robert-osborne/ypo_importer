<?php

/*
Plugin Name: Young President XML Importer
Description: Plugin specifically designed for importing XML data for teh Young Presidents organization into Event Espresso Groups Integration Addon.
Author: Michael Nelson
Version: 1.0.0
Requries: Event Espresso 4.0.1.beta.1, Espresso Group Integration Addon 1.0.0.A
Author URI: eventespresso.com
*/
define('YPI_VERSION','1.0.0.0');
define('YPI_MAIN_FILE',__FILE__);
define('YPI_DIRPATH',dirname(YPI_MAIN_FILE));

//form constants
define('YPI_FORM_FILE','ypi_file');

//add admin page
function ypi_menu(){
	add_submenu_page('espresso_events', 'YPO XML Data Importer', 'YPO Importer', 'manage_options', 'ypimporter', 'ypi_admin_page');
}
add_action('admin_menu','ypi_menu',100);
//add uplloader on that page
function ypi_admin_page(){
	if($_SERVER['REQUEST_METHOD']=='POST'){
		if(isset($_POST['CONVERT_EMAILS'])){
			ypi_convert_emails();
			echo "<b>Successfully converted attendee emails";
		}else{
			ypi_receive_upload();
		}
	}else{?>
<h1>YPO Importer</h1>
<p>This is for easily importing data from Solve360 (using their XML export feature) into Event Espresso 4 and the Groups Integration Addon.</p>
<p>Specifically, when you upload an XML file from Solve360, the importer will:</p>
<ol>
	<li>Ensure there is the Group Type for Organization Members and their families</li>
	<li>Ensure there are 4 group roles in that group type: i.e., "Org Member", "Spouse", "Child", and "Associate"</li>
	<li>Create Questions for the XML data</li>
	<li>Create Event Espresso Attendees for each contact (person) in the XML data</li>
	<li>Add Answers to the Questions for each Attendee</li>
	<li>Create a Wordpress User for Attendees who will be able to login</li>
	<li>Remember associations between XML data and the newly created Event Espresso 'things' (Group Types, Group Roles, Questions, Attendees, Answers, etc.),
		so they can all be renamed according to the admin's pleasure. The importer can be re-run when the XML data has changed without introducing errors.</li>
	<li>Take a long time (depending on the size of the data imported, of course). But between 5-20 minutes is expected.</li>

</ol>
		<form enctype='multipart/form-data' method='post'>
			<input type='file' name='<?php echo YPI_FORM_FILE?>' id='<?php echo YPI_FORM_FILE?>'>
			<input type='submit' value='Import'>
		</form>
<h1>Convert Emails</h1>
<p>
	All attendees should have an email. The following will assign users a default email to any with no email.
</p>
<form method='POST'>
	<input type='hidden' name='CONVERT_EMAILS' value='true'>
	<input type='submit' value='Convert Emails'>
</form>
	<?php }
}
//parse it out...
function ypi_receive_upload(){
	$filepath = $_FILES[YPI_FORM_FILE]['tmp_name'];
	//echo "filepath:$filepath";
	$file_contents = file_get_contents($filepath);
	//echo "file contents:$file_contents";
	//verify they uploaded a file
	require_once(YPI_DIRPATH.'/includes/classes/YP_Importer.class.php');
	$importer = new YP_Importer($file_contents);
}
//converts emails
function  ypi_convert_emails(){
	
		require_once(YPI_DIRPATH.'/includes/classes/YP_Email_Convert.class.php');
		$converter = new YP_Email_Convert();
		$converter->convert_empty_emails();
}