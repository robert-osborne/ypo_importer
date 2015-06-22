<?php

class YP_Email_Convert{
	/**
	 * question concerning emails
	 * @var EE_Question
	 */
	protected $_email_question = null;
	
	/**
	 *
	 * @var EEM_Attendee
	 */
	protected $_ATT = null;
	
	/**
	 *
	 * @var EEM_Answer
	 */
	protected $_ANS = null;
	
	/**
	 * the name of hte wp option used to store the count of how many emails have been converted
	 * in order to avoid duplicates
	 */
	const email_convert_count_wp_option_name = 'ypo_email_conversion_count';
	const email_default = 'no-email@ypowpo.ca';
	function __construct(){
		require_once('EEM_Question.model.php');
		require_once('EE_Question.class.php');
		$this->_email_question = EEM_Question::instance()->get_one(array('QST_system'=>'email'));
		require_once('EEM_Attendee.model.php');
		require_once('EE_Attendee.class.php');
		$this->_ATT = EEM_Attendee::instance();
		
		require_once('EEM_Answer.model.php');
		require_once('EE_Answer.class.php');
		$this->_ANS = EEM_Answer::instance();
	}
	/**
	 * main funciton that runs through the entire db and udpates all attendees with no email
	 */
	function convert_empty_emails(){
		$all_attendees_with_no_email = $this->_ATT->get_all_where(array('ATT_email'=>''));
		foreach($all_attendees_with_no_email as $attendee){
			$this->convert_attendee_email($attendee);
		}
	}
	
	/**
	 * Changes the attendee's email and all email answers for this attendee
	 * @param EE_Attendee $attendee
	 */
	protected function convert_attendee_email(EE_Attendee $attendee){
		$option_val = get_option(YP_Email_Convert::email_convert_count_wp_option_name);
		$count = isset($option_val) ? intval($option_val) : 1;
		$new_email = str_replace("@","+".$count."@",  YP_Email_Convert::email_default);
		$attendee->save(array('ATT_email'=>$new_email));
		$this->_ANS->update(array('ANS_value'=>$new_email), array('QST_ID'=>$this->_email_question->ID(),'ATT_ID'=>$attendee->ID()));
		update_option(YP_Email_Convert::email_convert_count_wp_option_name, ++$count);
	}
}