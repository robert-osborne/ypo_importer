<?php
/**
 * Class for interpreting an XML file from Solve360, and importing (and subsequently verifying) the data into EE4 and Group Integration addon
 * 
 */
class YP_Importer{
	/**
	 * Models Used by the importer:
	 */
	/**
	 *
	 * @var EEM_YPOGroup
	 */
	protected $_GRP;
	/**
	 *
	 * @var EEM_YPOGroup_Type
	 */
	protected $_GPT;
	/**
	 *
	 * @var EEM_YPOGroup_Role
	 */
	protected $_GRR;
	/**
	 *
	 * @var EEM_YPOAttendee
	 */
	protected $_ATT;
	/**
	 *
	 * @var EEM_Answer
	 */
	protected $_ANS;
	/**
	 *
	 * @var EEM_Question
	 */
	protected $_QST;
	
	
	
	
	
	
	/**
	 * Important model objects which are repeatedly use din the importer
	 */
	/**
	 *
	 * @var EE_YPOGroup_Type
	 */
	var $_org_group_type = null;
	/**
	 *
	 * @var EE_YPOGroup_Role
	 */
	var $_org_member_role = null;
	/**
	 *
	 * @var EE_YPOGroup_Role
	 */
	var $_spouse_role = null;
	/**
	 *
	 * @var EE_YPOGroup_Role
	 */
	var $_child_role = null;
	/**
	 *
	 * @var EE_YPOGroup_Role
	 */
	var $_associate_role = null;
	/**
	 *
	 * @var array, keys are the xml code names,values are EE_Question IDs
	 */
	var $_xml_code_name_to_question_id_mapping;
	/**
	 *
	 * @var array, keys are xml contact ids (from xml) values are EE_YPOGroup IDs
	 */
	var $_xml_contact_id_to_group_id_mapping;
	/**
	 *
	 * @var array of string which explain problems with the data
	 */
	var $_problems;
	/**
	 * constants used in a few places in the importer
	 */
	const default_group_type_and_roles_wp_option_name = 'yp_ee_group_type_and_role_ids';
	const default_group_type_name = 'Org Member and Family';
	const default_org_member_role_name = 'Org Member';
	const default_spouse_role_name = 'Spouse';
	const default_child_role_name = 'Child';
	const default_associate_role_name = 'Associate';
	
	const question_ids_wp_option_name = 'yp_ee_question_ids';
	const group_from_contact_ids_wp_option_name = 'yp_ee_groups_from_contact_ids';
	/**
	 * Constructor, accepting an XML string of Solve 360 Young Presidents org member data.
	 * Parses the XML and attempts to create EE groups, attendees, and answers pertaining to those
	 * attendees from the data.
	 * First time it is run, automatically creates a group type and roles for the YP data,
	 * and saves their IDs in a wp option, so that the group type and role names can be changed
	 * but we still know what their IDs are.
	 * @param string $xml_string
	 */
	function __construct($xml_string){
		//timing
		$mtime = microtime(); 
		$mtime = explode(" ",$mtime); 
		$mtime = $mtime[1] + $mtime[0]; 
		$starttime = $mtime; 
				
		ini_set('max_execution_time', 300);
		$this->_require_needed_files();
		$this->_ensure_group_infrastructure_setup();
		$this->_process_xml_file_for_group_data($xml_string);
		$this->_finish_up();
		$this->_show_problems();
		
		//timing	
		$mtime = microtime(); 
		$mtime = explode(" ",$mtime); 
		$mtime = $mtime[1] + $mtime[0]; 
		$endtime = $mtime; 
		$totaltime = ($endtime - $starttime); 
		echo "This import took ".$totaltime." seconds";
	}
	
	/**
	 * requires teh needed models and model objects, and keeps 
	 * an instance of each model as a property on this class for future use
	 * @return void
	 */
	protected function _require_needed_files(){
		/* require_once('EEM_Promotion.model.php');
		require_once('EEM_YPOGroup.model.php');
        require_once('EE_YPOGroup.class.php'); */
		$this->_GRP = EEM_YPOGroup::instance();
		
		/* require_once('EEM_YPOGroup_Role.model.php');
        require_once('EE_YPOGroup_Role.class.php'); */
		$this->_GRR = EEM_YPOGroup_Role::instance();
		
		/*require_once('EEM_YPOGroup_Type.model.php');
        require_once('EE_YPOGroup_Type.class.php'); */
		$this->_GPT = EEM_YPOGroup_Type::instance();
		
        /*
        require_once('EEM_Attendee.model.php');
        require_once('EE_Attendee.class.php');
         */
		$this->_ATT = EEM_Attendee::instance();

        /*
		require_once('EEM_Answer.model.php');
        require_once('EE_Attendee.class.php');
         */
		$this->_ANS = EEM_Answer::instance();
		
        /*
		require_once('EEM_Question.model.php');
        require_once('EE_Question.class.php');
         */
		$this->_QST = EEM_Question::instance();
	}
	
	/**
	 * Ensures there is a group type (originally called Org Member and Family),
	 * and roles (Org member, Spouse, Child, and Associate)
	 */
	protected function _ensure_group_infrastructure_setup(){
		$yp_group_type_and_roles_IDs = get_option(YP_Importer::default_group_type_and_roles_wp_option_name,null);
		//if the option hasnt already been set, then we must create the group type and roles
		//and then save the option for future use
		if ( ! $yp_group_type_and_roles_IDs){
			$this->_org_group_type = new EE_YPOGroup_Type(YP_Importer::default_group_type_name, 'Default Group Type for a YP Organization Member and family and associates');
			$this->_org_group_type->save();
			
			$this->_org_member_role = new EE_YPOGroup_Role(YP_Importer::default_org_member_role_name, 'The YP Member', array('register_self','register_others'), $this->_org_group_type->ID());
			$this->_org_member_role->save();
			
			$this->_spouse_role = new EE_YPOGroup_Role(YP_Importer::default_spouse_role_name, 'The Spouse of the YP Member', array('register_self'), $this->_org_group_type->ID());
			$this->_spouse_role->save();
			
			$this->_child_role = new EE_YPOGroup_Role(YP_Importer::default_child_role_name, 'Family (especially children) of the YP Member', array(), $this->_org_group_type->ID());
			$this->_child_role->save();
			
			$this->_associate_role = new EE_YPOGroup_Role(YP_Importer::default_associate_role_name, 'Associate of the YP Member ', array(), $this->_org_group_type->ID());
			$this->_associate_role->save();
			$yp_group_type_and_roles_IDs = array(
				YP_Importer::default_group_type_name => $this->_org_group_type->ID(),
				YP_Importer::default_org_member_role_name => $this->_org_member_role->ID(),
				YP_Importer::default_spouse_role_name => $this->_spouse_role->ID(),
				YP_Importer::default_child_role_name => $this->_child_role->ID(),
				YP_Importer::default_associate_role_name => $this->_associate_role->ID()
				);
				update_option(YP_Importer::default_group_type_and_roles_wp_option_name, $yp_group_type_and_roles_IDs);
		}else{
			//use the IDs in the wp option array to find the correct group type and roles (as the names of
			//the roles may have changed names, but their IDs should be the same)
			$group_type_id = $yp_group_type_and_roles_IDs[YP_Importer::default_group_type_name];
			$this->_org_group_type = $this->_GPT->get_one_by_ID($group_type_id);
			
			$org_member_role = $yp_group_type_and_roles_IDs[YP_Importer::default_org_member_role_name];
			$this->_org_member_role = $this->_GRR->get_one_by_ID($org_member_role);
			
			$spouse_role = $yp_group_type_and_roles_IDs[YP_Importer::default_spouse_role_name];
			$this->_spouse_role = $this->_GRR->get_one_by_ID($spouse_role);
			
			$child_role = $yp_group_type_and_roles_IDs[YP_Importer::default_child_role_name];
			$this->_child_role = $this->_GRR->get_one_by_ID($child_role);
			
			$associate_role = $yp_group_type_and_roles_IDs[YP_Importer::default_associate_role_name];
			$this->_associate_role = $this->_GRR->get_one_by_ID($associate_role);
		}
		
		//ensure proper questions exist, which are stored in an option too (as an array. keys
		//are the xml node names, values are actual question objects)
		$xml_node_name_to_question_id_mapping = get_option(YP_Importer::question_ids_wp_option_name);
		//if no yp questions option exists, we'll create it
		if( ! $xml_node_name_to_question_id_mapping){
			//keys should be the XML node's name, keys are the question's ID
			//there are a few we know off the bat
			$xml_node_name_to_question_id_mapping['firstname']= EEM_Attendee::fname_question_id;
			$xml_node_name_to_question_id_mapping['lastname']= EEM_Attendee::lname_question_id;
			$xml_node_name_to_question_id_mapping['businessemail'] = EEM_Attendee::email_question_id;
			
			//...and other we'll add as we discover as we parse the XML
		}
		$this->_xml_code_name_to_question_id_mapping = $xml_node_name_to_question_id_mapping;
		//when we're done, during the _finish_up function, we'll update the wp option
		//which stores the mapping from xml node names to Question IDs
		
		$xml_contact_id_to_group_ids = get_option(YP_Importer::group_from_contact_ids_wp_option_name);
		if ( ! $xml_contact_id_to_group_ids ){
			$this->_xml_contact_id_to_group_id_mapping = array();
		}else{
			$this->_xml_contact_id_to_group_id_mapping = $xml_contact_id_to_group_ids;
		}
		//when we're done, during the _finish_up function, we'll update the option YP_Importer::group_from_contact_ids_wp_option_name
		//with the newly created groups
	}
	
	/**
	 * Gets the question id given the xml node name (first checks this class' property which cahces them,
	 * then checks teh db for it, and finally creates it if it dosn't already exist)
	 * @param type $node_name
	 * @return int ID of the question
	 */
	protected function _get_question_from_node_name($node_name){
		//first: check if the node name already has a question that we've fetched
		
		if( ! array_key_exists($node_name,$this->_xml_code_name_to_question_id_mapping)){
			$question_obj = new EE_Question($node_name, $node_name, null, 'TEXT', false, null, 10, 1, 1, false);
			$question_obj->save();
			$this->_xml_code_name_to_question_id_mapping[$node_name] = $question_obj->ID();
			unset($question_obj);
		}
		return $this->_xml_code_name_to_question_id_mapping[$node_name] ;
	}
	
	/**
	 * Given the xml node "contact"'s child node's "id"'s value, retrieves it from our cached
	 * list of groups, indexed by contact ids. If there is no group for that contact id
	 * 
	 * @param type $contact_id
	 */
	protected function _get_group_from_contact_id($contact_id,$primary_member_name){
		//check if we have have a group for that contact id
		
		if( ! array_key_exists($contact_id, $this->_xml_contact_id_to_group_id_mapping) ){
			//create a new group
			$group = new EE_YPOGroup($primary_member_name. ", Family, and Associates", 'Automatically created group', $this->_org_group_type->ID());
			$group->save();
			$this->_xml_contact_id_to_group_id_mapping[$contact_id] = $group->ID();
			unset($group);
		}
		return $this->_xml_contact_id_to_group_id_mapping[$contact_id];
	}
	
	/**
	 * Takes care of any cleanup tasks which should be done after processing data from teh xml
	 */
	protected function _finish_up(){
		//save an option for questions, 
		//mapping xml node names (eg <bob>) to question IDs
		update_option(YP_Importer::question_ids_wp_option_name,$this->_xml_code_name_to_question_id_mapping);
		
		//save an option to groups
		//mapping xml contact node values (eg <contact><id>124124</id>...</contact>) to grou pids
		update_option(YP_Importer::group_from_contact_ids_wp_option_name, $this->_xml_contact_id_to_group_id_mapping);
	}
	
	
	protected function _show_problems(){
		if ($this->_problems){?>
			<h2>Problems Importing Data</h2>
			<ol>
				<?php foreach($this->_problems as $problem){
					echo "<li>$problem</li>";
				}?>
			</ol>
			<p>Please fix the problems in data, and re-upload this file. <a href=''>Ok</a></p>
			<?php
		}else{?>
			<h2>No Problems Detected</h2>
			<p>The pull of data from Solve 360 XML data was successful</p>
		<?php }
		
	}
	
	/**
	 * Given an xml-like string (ie, Solve 360 exports invalid XML. Eg, it has random HTML tags in the middle of XML tags, like <br>)
	 * @param string $xml_string looking
	 */
	protected function _process_xml_file_for_group_data($xml_string){
		$domdoc = new DOMDocument();
		//because the XML file exported from solve360 is totally invalid XML (it has HTML in the middle of XML for example, with no
		//CDATA[[ escapings. So if we pretend its HTML, we can parse it.
		$success = @$domdoc->loadHTML($xml_string);
		$xpath = new DOMXPath($domdoc);
		//get all the 'contact' elements
		$contact_xml_nodes = $xpath->query('//export/contact');
		foreach($contact_xml_nodes as $contact_xml_node){
			$this->_process_contact_node($contact_xml_node);
		}
	}
	
	/**
	 * Ensures there is an attendee in the system given an XMl node like mentioned.
	 * So it will either create a new EE_Attendee, and call otehr functions to set
	 * answers
	 * @param DOMNode $contact_node from invalid XML like teh following:
	 * <contact id="58533355"> 
		<id>58533355</id> 
		<parent/> 
		<name>Aaron Beck</name> 
		<starred>N</starred> 
		<createdGMT>2012-09-17 04:05:42</createdGMT> 
		<updatedGMT>2012-09-29 04:11:01</updatedGMT> 
		<viewedGMT>2012-12-05 20:09:17</viewedGMT> 
		<personalemail/> 
		<businessemail/> 
		<custom9496468>WPO Ontario</custom9496468> 
		<firstname>Aaron </firstname> 
		<custom9496865>Male</custom9496865> 
		<lastname>Beck</lastname> 
		<custom9723235>Child</custom9723235> 
		<custom9496471>1993-09-01</custom9496471> 
		<related> 
			<item id="58517158" note="Member"> 
				<id>58517158</id> 
				<parent>58533355</parent> Harvey Beck 
			</item> 
		</related> 
		<categories> 
			<category id="55283748"> 
				<id>55283748</id> 
				<parent>58533355</parent> Type-Child 
			</category> 
			<category id="58463444"> 
				<id>58463444</id> 
				<parent>58533355</parent> WPO Ontario 
			</category> 
			<category id="58759041"> 
				<id>58759041</id> 
				<parent>58533355</parent> Status-Active 
			</category> 
		</categories> 
	</contact> 
	 *  
	 */
	protected function _process_contact_node(DOMNode $contact_node){
		//echo "<b>person ID:".$contact_node->attributes->item(0)->nodeValue."</b>";
		$will_create_wp_user_for_attendee = false;
		
		$id_question = $this->_get_question_from_node_name('id');
		
		$contact_id = $this->_get_value_of_first_child_node_by_name_on_node('id', $contact_node);
		
		
		$answer_to_id_question_with_this_id = $this->_ANS->get_one(array('QST_ID'=>$id_question,'ANS_value'=>$contact_id));
		
		//if an answer exists, then the attendee on that answer is the one we want. Don't creat ea new attendee
		if($answer_to_id_question_with_this_id){
			$attendee = $answer_to_id_question_with_this_id->attendee();
		}else{
			//otherwise: there is no attendee with an answer to the id question that matches this $contact_node's, so create a new attendee
			$attendee = new EE_Attendee();
			//ok so we don't know anything about this attendee yet, but we do want to start saving answers for them
			//so we need the attendee to have an ID, so we must save it
			$attendee->save();
		}
		//for each child node of $contact_node which has no children, treat it as a question
		//(nodes with child nodes will need to be handled specially)
		foreach($contact_node->childNodes as $child_node){
			//skip nodes which aren't in this list (Harvey doesn't want them)
			if( ! in_array($child_node->nodeName,array('id', 'firstname','lastname','businessemail','personalemail','custom9723235' /*relatinoship to org member */,'custom9496468' /*organization */))){
				continue;
			}
			
			
			//we only add a question and answer for nodes like <something>VALUE</something>, 
			//NOT <something><somethingelse>VALUE</somethingelse></something>
			
			if( count($child_node->childNodes) <= 1 ){
				//no child nodes. Treat it as a question, and possibly a property on the attendee
				$question_id_for_this_node = $this->_get_question_from_node_name($child_node->nodeName);
				//check: do we have an answer for this question with this value?
				$existing_answer = $this->_ANS->get_one(array('QST_ID'=>$question_id_for_this_node,'ANS_value'=>$child_node->nodeValue,'ATT_ID'=>$attendee->ID()));
				if( ! $existing_answer){
					$answer_to_this_question = new EE_Answer(array('QST_ID'=>$question_id_for_this_node, 'ANS_value'=>$child_node->nodeValue,'ATT_ID'=>$attendee->ID()));
//					var_dump($answer_to_this_question);
//					die;
					$answer_to_this_question->save();
					//try and save memory
					unset($answer_to_this_question);
				}
				
			}
			//handle special nodes
			switch($child_node->nodeName){
					case 'firstname':
						$attendee->set_fname($child_node->nodeValue);
					break;
					case 'lastname':
						$attendee->set_lname($child_node->nodeValue);
					break;
					case 'businessemail':
						if( ! $attendee->email() && $child_node->nodeValue){
							$attendee->set_email($child_node->nodeValue);
						}
					break;
					case 'personalemail':
						if ( ! $attendee->email() && $child_node->nodeValue){
							$attendee->set_email($child_node->nodeValue);
						}
					break;
					case 'custom9723235'://ie ROLE, eg 'Member','Spouse','Child', 'Assistant' and others
						switch($child_node->nodeValue){
							case 'Member':
							case 'member':
								//if they're a member, let's create a group for them
								//first thing is verify we have
								$contact_name = $this->_get_value_of_first_child_node_by_name_on_node('name',$contact_node);
								if ( ! $contact_name ){
									$contact_name = "Unknown $contact_id";
									$this->_add_problem("Contact with id '".$contact_id."' has no name!");
								}
								$member_group_id = $this->_get_group_from_contact_id($contact_id, $contact_name);
								//and add as a member
								$attendee->add_role_in_group($this->_org_member_role,$member_group_id);
								$will_create_wp_user_for_attendee = true;
								break;
							case 'Spouse':
							case 'spouse':
								$org_member_contact_id = $this->_get_org_member_contact_id_for_related_contact($contact_node);
								$org_member_name = $this->_get_org_member_name_for_related_contact($contact_node);
								$member_group_id = $this->_get_group_from_contact_id($org_member_contact_id, $org_member_name);
								$attendee->add_role_in_group($this->_spouse_role,$member_group_id);
								
								$will_create_wp_user_for_attendee = true;
								break;
							case 'Child':
							case 'child':
								$org_member_contact_id = $this->_get_org_member_contact_id_for_related_contact($contact_node);
								$org_member_name = $this->_get_org_member_name_for_related_contact($contact_node);
								$member_group_id = $this->_get_group_from_contact_id($org_member_contact_id, $org_member_name);
								$attendee->add_role_in_group($this->_child_role,$member_group_id);
								break;
							default:
								$org_member_contact_id = $this->_get_org_member_contact_id_for_related_contact($contact_node);
								$org_member_name = $this->_get_org_member_name_for_related_contact($contact_node);
								$member_group_id = $this->_get_group_from_contact_id($org_member_contact_id, $org_member_name);
								$attendee->add_role_in_group($this->_associate_role,$member_group_id);
								break;
						}
					break;
					//handle organization in which they are a member
				}
		}
		//maybe createa  wp user for them, depending on their role
		if ( $will_create_wp_user_for_attendee ){
			//catch exception if we can't add a user for teh attendee
			try{
				$attendee->ensure_has_wp_user_attached();
			}catch(EE_Error $e){
				$this->_add_problem($e->getMessage());
			}
		}
		//we've probably set the attendee's first and last name and email, so save it
		$attendee->save();
		
		//try and save memory
		unset($attendee);
		return;
	}
	
	/**
	 * Given an XML DOMNode $node, finds the first child node of the specified name
	 * @param string $child_node_name eg contact, firstname, relation, etc.
	 * @param DOMNode $node
	 * @return DOMNode
	 */
	protected function _get_first_child_node_by_name_on_node($child_node_name, DOMNode $node){
		$child_nodes_with_tag_name = $node->getElementsByTagName($child_node_name);
		if( $child_nodes_with_tag_name instanceof DOMNodeList ){
			$first_matching_child_node = $child_nodes_with_tag_name->item(0);
		}else{
			$first_matching_child_node = $child_nodes_with_tag_name;
		}
		return $first_matching_child_node;
	}
	
	/**
	 * Gets the child node of teh given name from $node, and retrieves its value.
	 * If the node doesn't exist, returns null
	 * @param string $child_node_name
	 * @param DOMNode $node
	 * @return string
	 */
	protected function _get_value_of_first_child_node_by_name_on_node($child_node_name, DOMNode $node){
		$node =  $this->_get_first_child_node_by_name_on_node($child_node_name, $node);
		if( $node ){
			return $node->nodeValue;
		}else{
			return null;
		}
	}
	
	/**
	 * You would call this when you know the contact node isn't an org member, but you want
	 * to find who the org member is who's related to thsi contact.
	 * @param DOMNode $contact_node from xml like
	 * <related> 
			<item id="63190288" note="member"> 
				<id>63190288</id> 
				<parent>63190477</parent> Jesse Hawthorne 
			</item> 
		</related> 
	 * @return string for the contact ID of the ORG member related to the contact indicated by $contact_node
	 * 
	 */
	protected function _get_org_member_contact_id_for_related_contact(DOMNode $contact_node){
		$related_child_node = $this->_get_first_child_node_by_name_on_node('related', $contact_node);
		if ( ! $related_child_node ){
			$name = $this->_get_value_of_first_child_node_by_name_on_node('name',$contact_node);
			$this->_add_problem("Contact named '$name' has no relation to an Organization Member");
			return "no-relation";
		}
		$item_node =$this->_get_first_child_node_by_name_on_node('item', $related_child_node);//so that's the <item...> node
		$contact_id = $this->_get_value_of_first_child_node_by_name_on_node('id', $item_node);//so that's <id>...</id>'s value
		return $contact_id;
	}
	
	/**
	 * You would call this when you know the contact node isn't an org member, but you want
	 * to get their name
	 * @param DOMNode $contact_node from xml like
	 * <related> 
			<item id="63190288" note="member"> 
				<id>63190288</id> 
				<parent>63190477</parent> Jesse Hawthorne 
			</item> 
		</related> 
	 * @return string for the contact ID of the ORG member related to the contact indicated by $contact_node
	 * 
	 */
	protected function _get_org_member_name_for_related_contact(DOMNode $contact_node){
		$related_child_node = $this->_get_first_child_node_by_name_on_node('related', $contact_node);
		if ( ! $related_child_node ){
			return "no-relation";
		}
		$item_node =$this->_get_first_child_node_by_name_on_node('item', $related_child_node);//so that's the <item...> node
		$contact_id = $this->_get_value_of_first_child_node_by_name_on_node('id', $item_node);//so that's <id>...</id>'s value
		$parent_id = $this->_get_value_of_first_child_node_by_name_on_node('parent', $item_node);
		$whole_item_value = $item_node->nodeValue;
		//so $whole_item_value, from the above example would be "63190288 63190477 Jesse Hawthorne", but we just want the "Jesse Hawthorne" part
		//so we'll remove the first two parts we can easily grab
		$org_member_name = trim(str_replace(array($contact_id, $parent_id),array('',''), $whole_item_value));
		return $org_member_name;
	}
	
	
	/**
	 * adds a new problem for displaying
	 * @param string $problem_description
	 * @return void
	 */
	protected function _add_problem($problem_description){
		$this->_problems[]=$problem_description;
	}
}
