<?php

/**
 * Audio GUI class for question type plugins
 *
 * @author	Christoph Jobst <iliasplugins.christoph.jobst@outlook.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assAudioGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 * @ilctrl_calls assAudioGUI: ilFormPropertyDispatchGUI
 */
class assAudioGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable
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
	 * @return assAudio
	 */
	public function getPlugin(): ilPlugin
	{
	    return $this->plugin;
	}
	
	/**
	 * @param assAudio $plugin
	 */
	public function setPlugin(ilPlugin $plugin): void
	{
	    $this->plugin = $plugin;
	}
	
	/**
	 * Constructor
	 *
	 * @param integer $id The database id of a question object
	 * @access public
	 */
	public function __construct($id = -1)
	{
	    global $tpl;
	    parent::__construct();
	    
	    // init the plugin object
	    try {
	        global $DIC;
	        
	        /** @var ilComponentRepository $component_repository */
	        $component_repository = $DIC["component.repository"];
	        
	        $info = null;
	        $plugin_name = 'assAudio';
	        $info = $component_repository->getPluginByName($plugin_name);
	        
	        /** @var ilComponentFactory $component_factory */
	        $component_factory = $DIC["component.factory"];
	        
	        /** @var ilQuestionsPlugin $plugin_obj */
	        $plugin_obj = $component_factory->getPlugin($info->getId());
	        
	        if (!is_null($info) && $info->isActive()) {
	            $this->setPlugin($plugin_obj);
	        } else {
	            throw new ilPluginException($plugin_name . ' plugin is not active');
	        }
	    } catch (ilPluginException $e) {
	        global $tpl;
	        $tpl->setOnScreenMessage('failure', $e->getMessage(), true);
	    }
	    
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
	 * @param bool $is_save_cmd
	 * @return bool
	 */
	public function editQuestion(
	    bool $checkonly = false,
	    ?bool $is_save_cmd = null
	    ): bool 
	{
	    global $DIC;
	    $lng = $DIC->language();
	    
	    $save = $is_save_cmd ?? $this->isSaveCommand();
	    
		$this->getQuestionTemplate();
		$plugin = $this->object->getPlugin();
		
		$form = new ilPropertyFormGUI();
		$this->editForm = $form;
		
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->plugin->txt("edit_assAudio"));
		$form->setMultipart(TRUE);
		$form->setTableWidth("100%");
		$form->setId("Audio");

		$this->addBasicQuestionFormProperties($form);
		$this->populateQuestionSpecificFormPart($form);
		$this->populateAnswerSpecificFormPart($form);
		$this->populateTaxonomyFormSection($form);
		$this->addQuestionFormCommandButtons($form);

		$errors = false;
		
		if ($save)
		{
		    $form->setValuesByPost();
		    $errors = !$form->checkInput();
		    $form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
		    
		    if ($errors) {
		        $checkonly = false;
		    }
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
	 * @param integer $active_id			           The active user id
	 * @param integer $pass					           The test pass
	 * @param boolean $is_question_postponed           Question is postponed
	 * @param boolean $user_post_solutions	           Use post solutions
	 * @param boolean $show_specific_inline_feedback   Show a feedback
	 * @return string
	 */
	public function getTestOutput(
	    int $active_id,
	    int $pass,
	    bool $is_question_postponed = false,
	    array|bool $user_post_solutions = false,
	    bool $show_specific_inline_feedback = false
	    ): string {
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

		$template = new ilTemplate("tpl.il_as_qpl_Audio_output.html", true, true, 'public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assAudio');
		
		$template->setVariable("QUESTIONTEXT", self::prepareTextareaOutput( $this->object->getQuestion(), TRUE));
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
		$pageoutput = $this->outQuestionPage("", $is_question_postponed, $active_id, $questionoutput);
		
		return $pageoutput;
	}
	
	/**
	 * Get the output for question preview
	 * (called from ilObjQuestionPoolGUI)
	 * 
	 * @param boolean	show only the question instead of embedding page (true/false)
	 */
	public function getPreview(bool $show_question_only = false, bool $show_inline_feedback = false): string
	{
		$template = new ilTemplate("tpl.il_as_qpl_Audio_output.html", true, true, 'public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assAudio');
		$template->setVariable("QUESTIONTEXT", self::prepareTextareaOutput( $this->object->getQuestion(), TRUE));
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
	 * @param bool    $show_inline_feedback
	 * @return string solution output of the question as HTML code
	 */
	public function getSolutionOutput(
	    int $active_id,
	    ?int $pass = null,
	    bool $graphical_output = false,
	    bool $result_output = false,
	    bool $show_question_only = true,
	    bool $show_feedback = false,
	    bool $show_correct_solution = false,
	    bool $show_manual_scoring = false,
	    bool $show_question_text = true,
	    bool $show_inline_feedback = true
	    ): string 
	{
        if (($active_id > 0) && (!$show_correct_solution))
        {
            // get the solutions of a user
            $solution = $this->object->getSolutionStored($active_id, $pass, null);
            
            if (!is_array($solution))
            {
                $solution = array();
            }
        } else {
            $solution = array();
        }
        
        return $this->renderSolutionOutput(
            $solution,
            $active_id,
            $pass,
            $graphical_output,
            $result_output,
            $show_question_only,
            $show_feedback,
            $show_correct_solution,
            $show_manual_scoring,
            $show_question_text,
            false, // $show_autosave_title
            false, // $show_inline_feedback
        );
	}
	
	public function renderSolutionOutput(
	    mixed $user_solutions,
	    int $active_id,
	    ?int $pass,
	    bool $graphical_output = false,
	    bool $result_output = false,
	    bool $show_question_only = true,
	    bool $show_feedback = false,
	    bool $show_correct_solution = false,
	    bool $show_manual_scoring = false,
	    bool $show_question_text = true,
	    bool $show_autosave_title = false,
	    bool $show_inline_feedback = false,
	    ): ?string
    {
        
        if ($show_manual_scoring)
        {
            // Use the direct link to the file on the server for manual correction since scoring by question won't easily execute JS
            $solutiontemplate = new ilTemplate("tpl.il_as_qpl_Audio_solution_directlink.html", true, true, 'public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assAudio');
        } else {
            // Use the binary blob-URL for participants so they won't see the direct link to the server
            $solutiontemplate = new ilTemplate("tpl.il_as_qpl_Audio_solution.html", true, true, 'public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assAudio');
        }
        
        $solution = '';
        $resultpath = '';
        if (isset($user_solutions["value1"]))
        {
            $path = $this->object->getFileUploadPath($active_id);
            $content = file_get_contents ($path . $user_solutions["value1"]);
            $resultpath = $this->object->getFilePathWeb($active_id) . $user_solutions["value1"];
            $solution = base64_encode($content);
        }
        
        $solutiontemplate->setVariable("ID", $this->object->getId());
        $solutiontemplate->setVariable("RESULT_OUTPUT", $solution);
        
        if ($show_manual_scoring)
        {
           $solutiontemplate->setVariable("RESULT_PATH", $resultpath);
        }
        
        if ($show_question_text == true) 
        {
            $solutiontemplate->setVariable("QUESTIONTEXT", self::prepareTextareaOutput( $this->object->getQuestion(), TRUE));
        }
        
        // Sample solution by upload or manual recording is currently not supported
        if ($show_correct_solution == true) 
        {
            $solutiontemplate->setVariable("FALLBACK", $this->plugin->txt("no_sample_solution_support"));
        }
        
        $feedback = '';
        if ($show_feedback) {
            if (!$this->isTestPresentationContext()) 
            {
                $fb = $this->getGenericFeedbackOutput((int) $active_id, $pass);
                $feedback .= strlen($fb) ? $fb : '';
            }
        }
        
        if (strlen($feedback)) {
            $cssClass = (
                $this->hasCorrectSolution($active_id, $pass) ?
                ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_CORRECT : ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_WRONG
                );
            
            $solutiontemplate->setVariable("ILC_FB_CSS_CLASS", $cssClass);
            $solutiontemplate->setVariable("FEEDBACK", ilLegacyFormElementsUtil::prepareTextareaOutput($feedback, true));
        }
        	        
        $solutionoutput = $solutiontemplate->get();
        if (!$show_question_only) {
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
        return self::prepareTextareaOutput($output, TRUE);
    }
	
    /**
     * Sets the ILIAS tabs for this question type
     * called from ilObjTestGUI and ilObjQuestionPoolGUI
     */
    public function setQuestionTabs(): void
    {
        parent::setQuestionTabs();
    }
    
    /**
     * Adds the question specific forms parts to a question property form gui.
     */
    public function populateQuestionSpecificFormPart(ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        $plugin = $this->object->getPlugin();
        
        // points
        $points = new ilNumberInputGUI($plugin->txt("points"), "points");
        $points->setSize(3);
        $points->setMinValue(0);
        $points->allowDecimals(1);
        $points->setRequired(true);
        $points->setValue($this->object->getPoints());
        $form->addItem($points);
        
        return $form;
    }
    
    /**
     * Extracts the question specific values from the request and applies them
     * to the data object.
     */
    public function writeQuestionSpecificPostData(ilPropertyFormGUI $form): void
    {
        $this->object->setPoints($this->request_data_collector->float('points'));
    }
    
    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionQuestionPostVars(): array
    {
        return [];
    }
    
    public function populateAnswerSpecificFormPart(\ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        return $form;
    }
    
    public function writeAnswerSpecificPostData(ilPropertyFormGUI $form): void
    {
        #not needed for Audio
    }
    
    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionAnswerPostVars(): array
    {
        return [];
    }
    
    public function populateCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        $this->populateQuestionSpecificFormPart($form);
    }
    
    /**
     * @param ilPropertyFormGUI $form
     */
    public function saveCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        $this->object->setPoints((float) str_replace(',', '.', $form->getInput('points')));
    }
    
    public function prepareReprintableCorrectionsForm(ilPropertyFormGUI $form): void
    {
        #not needed for Audio
    }
}
?>
