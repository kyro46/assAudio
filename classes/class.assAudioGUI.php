<?php

/**
 * Audio GUI class for question type plugins
 *
 * @author	Christoph Jobst <christoph.jobst@llz.uni-halle.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assAudioGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 * @ilctrl_calls assAudioGUI: ilFormPropertyDispatchGUI
 */
class assAudioGUI extends assQuestionGUI
{
	/**
	 * @var ilassAudioPlugin	The plugin object
	 */
	var $plugin = null;


	/**
	 * @var assAudio	The question object
	 */
	public assQuestion $object;
	
	/**
	 * Constructor
	 *
	 * @param integer $id The database id of a question object
	 * @access public
	 */
	public function __construct($id = -1)
	{
	    global $DIC;
	    
	    parent::__construct();
	    
	    /** @var ilComponentFactory $component_factory */
	    $component_factory = $DIC["component.factory"];
	    $this->plugin = $component_factory->getPlugin('assAudio');
	    $this->object = new assAudio();
	    if ($id >= 0)
	    {
	        $this->object->loadFromDb($id);
	    }
	}

	/**
	 * Creates an output of the edit form for the question
	 *
	 * @param bool $checkonly
	 * @return bool
	 */
	public function editQuestion($checkonly = FALSE)
	{
	    global $DIC;
	    $lng = $DIC->language();
	    
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();
		$plugin = $this->object->getPlugin();
		
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(TRUE);
		$form->setTableWidth("100%");
		$form->setId("Audio");

		$this->addBasicQuestionFormProperties($form);
		
		// points
		$points = new ilNumberInputGUI($plugin->txt("points"), "points");
		$points->setSize(3);
		$points->setMinValue(0);
		$points->allowDecimals(1);
		$points->setRequired(true);
		$points->setValue($this->object->getPoints());
		$form->addItem($points);
		
		$this->populateTaxonomyFormSection($form);
		$this->addQuestionFormCommandButtons($form);

		$errors = false;

		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
			if ($errors) $checkonly = false;
		}

		if (!$checkonly)
		{
			$this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		}
		return $errors;
	}

	/**
	 * Evaluates a posted edit form and writes the form data in the question object
	 *
	 * @param bool $always
	 * @return integer A positive value, if one of the required fields wasn't set, else 0
	 */
	protected function writePostData($always = false): int
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if (!$hasErrors)
		{
			$this->writeQuestionGenericPostData();
			$this->object->setPoints( str_replace( ",", ".", $_POST["points"] ));
			$this->saveTaxonomyAssignments();
			return 0;
		}
		return 1;
	}

	/**
	 * Get the HTML output of the question for a test
	 * (this function could be private)
	 * 
	 * @param integer $active_id			The active user id
	 * @param integer $pass					The test pass
	 * @param boolean $is_postponed			Question is postponed
	 * @param boolean $use_post_solutions	Use post solutions
	 * @param boolean $show_feedback		Show a feedback
	 * @return string
	 */
	public function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_specific_inline_feedback = FALSE): string
	{	    
	    // get the solution of the user for the active pass or from the last pass if allowed
	    if (is_null($pass))
	    {
	        $pass = ilObjTest::_getPass($active_id);
	    }
	    
	    $user_solution = $this->object->getSolutionStored($active_id, $pass, null);
	    
	    if (!is_array($user_solution))
	    {
	        $user_solution = array();
	    }

		$template = $this->plugin->getTemplate("tpl.il_as_qpl_Audio_output.html");
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput( $this->object->getQuestion(), TRUE));
		$template->setVariable("ID", $this->object->getId());
			
		if ($user_solution["value1"])
		{
			$path = $this->object->getFileUploadPath($active_id);
			$content = file_get_contents ($path . $user_solution["value1"]);
			$template->setVariable("SOLUTION", ilLegacyFormElementsUtil::prepareFormOutput(base64_encode($content)));
		}

		//language
		$template->setVariable("RECORD",$this->plugin->txt("record"));
		$template->setVariable("PAUSE",$this->plugin->txt("pause"));
		$template->setVariable("RESUME",$this->plugin->txt("resume"));
		$template->setVariable("FINISH",$this->plugin->txt("finish"));
		$template->setVariable("OVERRIDEWARN",$this->plugin->txt("overridewarn"));
		$template->setVariable("CANCEL",$this->plugin->txt("cancel"));
		$template->setVariable("STARTRECORDING",$this->plugin->txt("startrecording"));
		$template->setVariable("MSG_RECORDINGSTARTED",$this->plugin->txt("msg_recordingstarted"));
		$template->setVariable("MSG_CURRENTLYNORECORDING",$this->plugin->txt("msg_currentlynorecording"));
		$template->setVariable("MSG_EXISTING_DURATION_P1",$this->plugin->txt("msg_existing_duration_p1"));
		$template->setVariable("MSG_EXISTING_DURATION_P2",$this->plugin->txt("msg_existing_duration_p2"));
		$template->setVariable("MSG_EXISTING_ALTERNATE",$this->plugin->txt("msg_existing_alternate"));
		
		

		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}

	
	/**
	 * Get the output for question preview
	 * (called from ilObjQuestionPoolGUI)
	 * 
	 * @param boolean	show only the question instead of embedding page (true/false)
	 */
	public function getPreview($show_question_only = FALSE, $showInlineFeedback = false)
	{
		$template = $this->plugin->getTemplate("tpl.il_as_qpl_Audio_output.html");
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput( $this->object->getQuestion(), TRUE));
		$template->setVariable("ID", $this->object->getId());
	
		//language
		$template->setVariable("RECORD",$this->plugin->txt("record"));
		$template->setVariable("PAUSE",$this->plugin->txt("pause"));
		$template->setVariable("RESUME",$this->plugin->txt("resume"));
		$template->setVariable("FINISH",$this->plugin->txt("finish"));
		$template->setVariable("OVERRIDEWARN",$this->plugin->txt("overridewarn"));
		$template->setVariable("CANCEL",$this->plugin->txt("cancel"));
		$template->setVariable("STARTRECORDING",$this->plugin->txt("startrecording"));
		$template->setVariable("MSG_RECORDINGSTARTED",$this->plugin->txt("msg_recordingstarted"));
		$template->setVariable("MSG_CURRENTLYNORECORDING",$this->plugin->txt("msg_currentlynorecording"));
		$template->setVariable("MSG_EXISTING_DURATION_P1",$this->plugin->txt("msg_existing_duration_p1"));
		$template->setVariable("MSG_EXISTING_DURATION_P2",$this->plugin->txt("msg_existing_duration_p2"));
		$template->setVariable("MSG_EXISTING_ALTERNATE",$this->plugin->txt("msg_existing_alternate"));
		
		$questionoutput = $template->get();
		if(!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	/**
	 * Get the question solution output
	 * @param integer $active_id             The active user id
	 * @param integer $pass                  The test pass
	 * @param boolean $graphicalOutput       Show visual feedback for right/wrong answers
	 * @param boolean $result_output         Show the reached points for parts of the question
	 * @param boolean $show_question_only    Show the question without the ILIAS content around
	 * @param boolean $show_feedback         Show the question feedback
	 * @param boolean $show_correct_solution Show the correct solution instead of the user solution
	 * @param boolean $show_manual_scoring   Show specific information for the manual scoring output
	 * @param bool    $show_question_text
	 
	 * @return string solution output of the question as HTML code
	 */
	function getSolutionOutput(
	    $active_id,
	    $pass = NULL,
	    $graphicalOutput = FALSE,
	    $result_output = FALSE,
	    $show_question_only = TRUE,
	    $show_feedback = FALSE,
	    $show_correct_solution = FALSE,
	    $show_manual_scoring = FALSE,
	    $show_question_text = TRUE
	    ): string
	{
        	// get the solution of the user for the active pass or from the last pass if allowed
        	$user_solution = array();
        	if (($active_id > 0) && (!$show_correct_solution))
        	{
        	    // get the solutions of a user
        	    $user_solution = $this->object->getSolutionStored($active_id, $pass, null);
        	    
        	    if (!is_array($user_solution))
        	    {
        	        $user_solution = array();
        	    }
        	} else {
        	    $user_solution = array();
        	}

        	$value1 = '';
        	if (isset($user_solution["value1"]))
        	{
        		$path = $this->object->getFileUploadPath($active_id);
        		$content = file_get_contents ($path . $user_solution["value1"]);
        		$value1 = base64_encode($content);
        	}
        	
        	// generate the question output
        	$plugin       = $this->object->getPlugin();
        	$solutiontemplate = $plugin->getTemplate("tpl.il_as_qpl_Audio_solution.html");
        	$solutiontemplate->setVariable("ID", $this->object->getId());
        	
        	if ($show_correct_solution)
        	{
        	    //TODO Not yet conceptualized.
        	    //$solutiontemplate->setVariable("FALLBACK", 'Sample solution not supported at the moment.');
        	    
        	    return $solutiontemplate->get();
        	    // hier nur die Musterlösung anzeigen, da wir uns im test beim drücken von check befinden ;)
        	}

        	$solutiontemplate->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput( $this->object->getQuestion(), TRUE));
        	$solutiontemplate->setVariable("RESULT_OUTPUT", $value1);
        	$questionoutput = $solutiontemplate->get();
        	
        	if ($show_manual_scoring)
        	{
        	    $scoringtemplate = $plugin->getTemplate("tpl.il_as_qpl_Audio_solution.html");
        	    $scoringtemplate->setVariable("ID", $this->object->getId());
        	    $solutiontemplate->setVariable("FALLBACK", 'Sample solution not supported at the moment.');
        	    
        	    $questionoutput .= "<br>" . $scoringtemplate->get();
        	}
        	
        	// add the feedback
        	$feedback = ($show_feedback) ? $this->getAnswerFeedbackOutput($active_id, $pass) : "";
        	if (strlen($feedback))
        	{
        	    $solutiontemplate->setVariable("FEEDBACK", $feedback);
        	}
        	        	
        	$solutionoutput = $solutiontemplate->get();
        	
        	if (!$show_question_only)
        	{
        	    // get page object output
        	    $solutionoutput = $this->getILIASPage($solutionoutput);
        	}
        	
        	return $solutionoutput;
    }

    /**
     * Returns the answer specific feedback for the question
     *
     * @param array $userSolution Array with the user solutions
     * @return string HTML Code with the answer specific feedback
     * @access public
     */
    public function getSpecificFeedbackOutput($userSolution): string
    {
        // By default no answer specific feedback is defined
        $output = '';
        return $this->object->prepareTextareaOutput($output, TRUE);
    }
	
    /**
     * Sets the ILIAS tabs for this question type
     * called from ilObjTestGUI and ilObjQuestionPoolGUI
     */
    public function setQuestionTabs(): void
    {
        parent::setQuestionTabs();
    }
}
?>
