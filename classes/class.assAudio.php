<?php

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Audio class for question type plugins
 *
 * @author	Christoph Jobst <christoph.jobst@llz.uni-halle.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 */
class assAudio extends assQuestion
{
	/**
	 * @var ilassAudioPlugin	The plugin object
	 */
	var $plugin = null;


	/**
	 * Constructor
	 *
	 * The constructor takes possible arguments and creates an instance of the question object.
	 *
	 * @access public
	 * @see assQuestion:assQuestion()
	 */
	function __construct( 
		$title = "",
		$comment = "",
		$author = "",
		$owner = -1,
		$question = ""
	)
	{
		// needed for excel export
		$this->getPlugin()->loadLanguageModule();

		parent::__construct($title, $comment, $author, $owner, $question);
	}

	/**
	 * Get the plugin object
	 *
	 * @return object The plugin object
	 */
	public function getPlugin() {
		if ($this->plugin == null)
		{
			include_once "./Services/Component/classes/class.ilPlugin.php";
			$this->plugin = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", "assAudio");
				
		}
		return $this->plugin;
	}

	/**
	 * Returns true, if the question is complete
	 *
	 * @return boolean True, if the question is complete for use, otherwise false
	 */
	public function isComplete()
	{
		// Please add here your own check for question completeness
		// The parent function will always return false
		if(($this->title) and ($this->author) and ($this->question) and ($this->getPoints >= 0))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Saves a question object to a database
	 * 
	 * @param	string		original id
	 * @access 	public
	 * @see assQuestion::saveToDb()
	 */
	function saveToDb($original_id = "")
	{

		// save the basic data (implemented in parent)
		// a new question is created if the id is -1
		// afterwards the new id is set
		$this->saveQuestionDataToDb($original_id);

		// Now you can save additional data
		// ...

		// save stuff like suggested solutions
		// update the question time stamp and completion status
		parent::saveToDb();
	}

	/**
	 * Loads a question object from a database
	 * This has to be done here (assQuestion does not load the basic data)!
	 *
	 * @param integer $question_id A unique key which defines the question in the database
	 * @see assQuestion::loadFromDb()
	 */
	public function loadFromDb($question_id)
	{
		global $ilDB;
                
		// load the basic question data
		$result = $ilDB->query("SELECT qpl_questions.* FROM qpl_questions WHERE question_id = "
				. $ilDB->quote($question_id, 'integer'));

		$data = $ilDB->fetchAssoc($result);
		$this->setId($question_id);
		$this->setTitle($data["title"]);
		$this->setComment($data["description"]);
		$this->setSuggestedSolution($data["solution_hint"]);
		$this->setOriginalId($data["original_id"]);
		$this->setObjId($data["obj_fi"]);
		$this->setAuthor($data["author"]);
		$this->setOwner($data["owner"]);
		$this->setPoints($data["points"]);

		include_once("./Services/RTE/classes/class.ilRTE.php");
		$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
		$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));

		try {
			$this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
		} catch (ilTestQuestionPoolInvalidArgumentException $e) {
			$this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
		}

		try
		{
			$this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
		}
		catch(ilTestQuestionPoolException $e)
		{
		}

		// loads additional stuff like suggested solutions
		parent::loadFromDb($question_id);
	}
	

	/**
	 * Duplicates a question
	 * This is used for copying a question to a test
	 *
	 * @access public
	 */
	function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
	{
		if ($this->getId() <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}

		// make a real clone to keep the object unchanged
		$clone = clone $this;
							
		$original_id = assQuestion::_getOriginalId($this->getId());
		$clone->setId(-1);

		if( (int) $testObjId > 0 )
		{
			$clone->setObjId($testObjId);
		}

		if ($title)
		{
			$clone->setTitle($title);
		}
		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}		
		
		if ($for_test)
		{
			$clone->saveToDb($original_id, false);
		}
		else
		{
			$clone->saveToDb('', false);
		}		

		// copy question page content
		$clone->copyPageOfQuestion($this->getId());
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($this->getId());

		// call the event handler for duplication
		$clone->onDuplicate($this->getObjId(), $this->getId(), $clone->getObjId(), $clone->getId());

		return $clone->getId();
	}

	/**
	 * Copies a question
	 * This is used when a question is copied on a question pool
	 *
	 * @access public
	 */
	function copyObject($target_questionpool_id, $title = "")
	{
		if ($this->getId() <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}

		// make a real clone to keep the object unchanged
		$clone = clone $this;
				
		$original_id = assQuestion::_getOriginalId($this->getId());
		$source_questionpool_id = $this->getObjId();
		$clone->setId(-1);
		$clone->setObjId($target_questionpool_id);
		if ($title)
		{
			$clone->setTitle($title);
		}
				
		// save the clone data
		$clone->saveToDb('', false);

		// copy question page content
		$clone->copyPageOfQuestion($original_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($original_id);

		// call the event handler for copy
		$clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());

		return $clone->getId();
	}

	/**
	 * Synchronize a question with its original
	 * You need to extend this function if a question has additional data that needs to be synchronized
	 * 
	 * @access public
	 */
	function syncWithOriginal()
	{
		parent::syncWithOriginal();
	}
	

	/**
	 * Returns the points, a learner has reached answering the question
	 * The points are calculated from the given answers.
	 *
	 * @param integer $active 	The Id of the active learner
	 * @param integer $pass 	The Id of the test pass
	 * @param boolean $returndetails (deprecated !!)
	 * @return integer/array $points/$details (array $details is deprecated !!)
	 * @access public
	 * @see  assQuestion::calculateReachedPoints()
	 */
	function calculateReachedPoints($active_id, $pass = NULL, $authorizedSolution = true, $returndetails = false)
	{
		return 0;
	} 


	/**
	 * Saves the learners input of the question to the database
	 *
	 * @param 	integer $test_id The database id of the test containing this question
	 * @return 	boolean Indicates the save status (true if saved successful, false otherwise)
	 * @access 	public
	 * @see 	assQuestion::saveWorkingData()
	 */
	function saveWorkingData($active_id, $pass = NULL, $authorized = true)
	{
		global $ilDB;
		global $ilUser;

		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}

		$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array(
				"integer", 
				"integer",
				"integer"
			),
			array(
				$active_id,
				$this->getId(),
				$pass
			)
		);
		
		// save the answers of the learner to tst_solution table
		// this data is question type specific
		// it is used used by calculateReachedPoints() in this class

		$solution = $this->getSolutionSubmit();
		
		$path = $this->getFileUploadPath($active_id);
		$filename = "recording_" . $active_id . "_" . $pass . "_" . time() . '.webm';

		if (!@file_exists($this->getFileUploadPath($active_id)))
			ilUtil::makeDirParents($this->getFileUploadPath($active_id));
		file_put_contents($path . $filename, base64_decode($solution["value1"]));
		
		$next_id      = $ilDB->nextId('tst_solutions');
		$affectedRows = $ilDB->insert("tst_solutions", array(
			"solution_id" => array("integer", $next_id),
			"active_fi"   => array("integer", $active_id),
			"question_fi" => array("integer", $this->getId()),
			"value1" => array("clob", $filename),
			"pass"        => array("integer", $pass),
			"tstamp"      => array("integer", time()),
		));

		// Check if the user has entered something
		// Then set entered_values accordingly
		if (!empty($_POST["question".$this->getId()."audio"]))
		{
			$entered_values = TRUE;
			
		}

		
		
		if ($entered_values)
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}

		return true;
	}


	/**
	 * Reworks the allready saved working data if neccessary
	 *
	 * @access protected
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $obligationsAnswered
	 */
	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
	{
		// normally nothing needs to be reworked
	}

	/**
	 * Create a new original question in a question pool for a test question
	 * @param int $targetParentId			id of the target question pool
	 * @param string $targetQuestionTitle
	 * @return int|void
	 */
	public function createNewOriginalFromThisDuplicate($targetParentId, $targetQuestionTitle = '')
	{
	    if ($this->id <= 0)
	    {
	        // The question has not been saved. It cannot be duplicated
	        return;
	    }
	    
	    $sourceQuestionId = $this->id;
	    $sourceParentId = $this->getObjId();
	    
	    // make a real clone to keep the object unchanged
	    $clone = clone $this;
	    $clone->setId(-1);
	    
	    $clone->setObjId($targetParentId);
	    
	    if (!empty($targetQuestionTitle))
	    {
	        $clone->setTitle($targetQuestionTitle);
	    }
	    
	    $clone->saveToDb();
	    // copy question page content
	    $clone->copyPageOfQuestion($sourceQuestionId);
	    // copy XHTML media objects
	    $clone->copyXHTMLMediaObjectsOfQuestion($sourceQuestionId);
	    
	    $clone->onCopy($sourceParentId, $sourceQuestionId, $clone->getObjId(), $clone->getId());
	    
	    return $clone->getId();
	}

	/**
	 * Returns the question type of the question
	 *
	 * @return string The question type of the question
	 */
	public function getQuestionType()
	{
		return "assAudio";
	}

	/**
	 * Returns the names of the additional question data tables
	 *
	 * all tables must have a 'question_fi' column
	 * data from these tables will be deleted if a question is deleted
	 *
	 * @return mixed 	the name(s) of the additional tables (array or string)
	 */
	public function getAdditionalTableName()
	{
		return "";
	}

	
	/**
	 * Collects all text in the question which could contain media objects
	 * which were created with the Rich Text Editor
	 */
	function getRTETextWithMediaObjects()
	{
		$text = parent::getRTETextWithMediaObjects();

		// eventually add the content of question type specific text fields
		// ..

		return $text;
	}


	/**
	 * Creates an Excel worksheet for the detailed cumulated results of this question
	 *
	 * @access public
	 * @see assQuestion::setExportDetailsXLS()
	 */
	public function setExportDetailsXLS($worksheet, $startrow, $active_id, $pass)
	{
		parent::setExportDetailsXLS($worksheet, $startrow, $active_id, $pass);
				
		//not needed anymore in 5.2?
		/*
		global $lng;
				
		include_once ("./Services/Excel/classes/class.ilExcelUtils.php");
		$solutions = $this->getSolutionValues($active_id, $pass);
		
		$worksheet->writeString($startrow, 0, ilExcelUtils::_convert_text($this->plugin->txt($this->getQuestionType())), $format_title);
		$worksheet->writeString($startrow, 1, ilExcelUtils::_convert_text($this->getTitle()), $format_title);

		return $startrow + $i + 1;
		*/
		return $startrow + 1;
	}

	/**
	 * Creates a question from a QTI file
	 * Receives parameters from a QTI parser and creates a valid ILIAS question object
	 * Extension needed to get the plugin path for the import class
	 *
	 * @access public
	 * @see assQuestion::fromXML()
	 */
	function fromXML(&$item, &$questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping, array $solutionhints = [])
	{
		$this->getPlugin()->includeClass("import/qti12/class.assAudioImport.php");
		$import = new assAudioImport($this);
		$import->fromXML($item, $questionpool_id, $tst_id, $tst_object, $question_counter, $import_mapping, $solutionhints);
	}

	/**
	 * Returns a QTI xml representation of the question and sets the internal
	 * domxml variable with the DOM XML representation of the QTI xml representation
	 * Extension needed to get the plugin path for the import class
	 *
	 * @return string The QTI xml representation of the question
	 * @access public
	 * @see assQuestion::toXML()
	 */
	function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false)
	{
		$this->getPlugin()->includeClass("export/qti12/class.assAudioExport.php");
		$export = new assAudioExport($this);
		return $export->toXML($a_include_header, $a_include_binary, $a_shuffle, $test_output, $force_image_references);
	}
	
	/**
	 * Returns the filesystem path for file uploads
	 */
	public function getFileUploadPath($active_id, $question_id = null)
	{
		$test_id = $this->lookupTestId($active_id);
		if (is_null($question_id)) $question_id = $this->getId();
		return CLIENT_WEB_DIR . "/assessment/tst_$test_id/$active_id/$question_id/files/";
	}
	
	/**
	 * Get the submitted user input as a serializable value
	 *
	 * @return mixed user input (scalar, object or array)
	 */
	public function getSolutionSubmit()
	{
	    return array(
	        'value1' => ilUtil::stripSlashes($_POST['question_'. $this->getId()]),
	    );
	}
	
	/**
	 * Calculate the reached points for a submitted user input
	 *
	 * @param mixed user input (scalar, object or array)
	 */
	public function calculateReachedPointsforSolution($solution)
	{
		return 0;
	}
	
	/**
	 * Get all available expression types for a specific question
	 *
	 * @return array
	*/
	public function getExpressionTypes()
	{
		return 0;	
	}
	
	/**
	 * Get the user solution for a question by active_id and the test pass
	 *
	 * @param int $active_id
	 * @param int $pass
	 *
	 * @return ilUserQuestionResult
	*/
	public function getUserQuestionResult($active_id, $pass)
	{
		return 0;
	}
	
	/**
	 * If index is null, the function returns an array with all anwser options
	 * Else it returns the specific answer option
	 *
	 * @param null|int $index
	 *
	 * @return array|ASS_AnswerSimple
	*/
	public function getAvailableAnswerOptions($index = null)
	{
		return 0;
	}
	
	/**
	 * Get all available operations for a specific question
	 *
	 * @param string $expression
	 *
	 * @internal param string $expression_type
	 * @return array
	*/
	public function getOperators($expression)
	{
		require_once "./Modules/TestQuestionPool/classes/class.ilOperatorsExpressionMapping.php";
		return ilOperatorsExpressionMapping::getOperatorsByExpression($expression);
	}	
	
	// End of ILIAS 5.0.x compabiltiy-code
	
}
?>
