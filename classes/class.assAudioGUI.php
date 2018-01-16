<?php

include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Audio GUI class for question type plugins
 *
 * @author	Christoph Jobst <christoph.jobst@llz.uni-halle.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assAudioGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 * @ilCtrl_Calls assAudioGUI: ilFormPropertyDispatchGUI
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
	var $object = null;
	
	/**
	* Constructor
	*
	* @param integer $id The database id of a question object
	* @access public
	*/
	public function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Services/Component/classes/class.ilPlugin.php";
		$this->plugin = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", "assAudio");
		$this->plugin->includeClass("class.assAudio.php");
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
		global $lng;

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
	public function writePostData($always = false)
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
	public function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
	    // get the solution of the user for the active pass or from the last pass if allowed
	    $user_solution = array();
	    if ($active_id)
	    {
	        include_once "./Modules/Test/classes/class.ilObjTest.php";
	        if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
	        {
	            if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
	        }
	        $user_solution =& $this->object->getSolutionValues($active_id, $pass);
	        if (!is_array($user_solution))
	        {
	            $user_solution = array();
	        }
			$template = $this->plugin->getTemplate("tpl.il_as_qpl_Audio_output.html");
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput( $this->object->getQuestion(), TRUE));
			$template->setVariable("ID", $this->object->getId());
				
			if ($user_solution[0]["value1"])
			{
				$path = $this->object->getFileUploadPath($active_id);
				$content = file_get_contents ($path . $user_solution[0]["value1"]);
				$template->setVariable("SOLUTION", ilUtil::prepareFormOutput(base64_encode($content)));
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
			
		}

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
	 * @return The solution output of the question as HTML code
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
	) {
        	// get the solution of the user for the active pass or from the last pass if allowed
        	$user_solution = array();
        	if (($active_id > 0) && (!$show_correct_solution))
        	{
        	    // get the solutions of a user
        	    $user_solution =& $this->object->getSolutionValues($active_id, $pass);
        	    if (!is_array($user_solution))
        	    {
        	        $user_solution = array();
        	    }
        	} else {
        	    $user_solution = array();
        	}
        	
        	if ($user_solution[0]["value1"])
        	{
        		$path = $this->object->getFileUploadPath($active_id);
        		$content = file_get_contents ($path . $user_solution[0]["value1"]);
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
	 * @param integer $active_id Active ID of the user
	 * @param integer $pass Active pass
	 * @return string HTML Code with the answer specific feedback
	 * @access public
	 */
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		// By default no answer specific feedback is defined
		$output = "";
		return $this->object->prepareTextareaOutput($output, TRUE);
	}
	
	
	/**
	* Sets the ILIAS tabs for this question type
	* called from ilObjTestGUI and ilObjQuestionPoolGUI
	*/
	public function setQuestionTabs()
	{
		global $rbacsystem, $ilTabs;
		
		$this->ctrl->setParameterByClass("ilpageobjectgui", "q_id", $_GET["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$q_type = $this->object->getQuestionType();

		if (strlen($q_type))
		{
			$classname = $q_type . "GUI";
			$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
			$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
		}

		if ($_GET["q_id"])
		{
			if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
			{
				// edit page
				$ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", $force_active);
			}
	
			// edit page
			$ilTabs->addTarget("preview",
				$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"),
				array("preview"),
				"ilAssQuestionPageGUI", "", $force_active);
		}

		$force_active = false;
		if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
		{
			$url = "";

			if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			$commands = $_POST["cmd"];

			// edit question properties
			$ilTabs->addTarget("edit_properties",
				$url,
				array("editQuestion", "save", "cancel", "cancelExplorer", "linkChilds", 
				"parseQuestion", "saveEdit"),
				$classname, "", $force_active);
		}

		// add tab for question feedback within common class assQuestionGUI
		$this->addTab_QuestionFeedback($ilTabs);

		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($ilTabs);

		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("solution_hint",
				$this->ctrl->getLinkTargetByClass($classname, "suggestedsolution"),
				array("suggestedsolution", "saveSuggestedSolution", "outSolutionExplorer", "cancel",
					"addSuggestedSolution","cancelExplorer", "linkChilds", "removeSuggestedSolution"
				),
				$classname,
				""
			);
		}

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}
		
		if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0))
		{
			$ref_id = $_GET["calling_test"];
			if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];
			$ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
		}
		else
		{
			$ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
		}
	}
}
?>
