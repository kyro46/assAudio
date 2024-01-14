<?php

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
    protected $plugin = null;
    

    /**
     * Constructor
     *
     * The constructor takes possible arguments and creates an instance of the question object.
     *
     * @param string $title A title string to describe the question
     * @param string $comment A comment string to describe the question
     * @param string $author A string containing the name of the questions author
     * @param integer $owner A numerical ID to identify the owner/creator
     * @param string $question Question text
     * @access public
     *
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
     * Returns the question type of the question
     *
     * @return string The question type of the question
     */
    public function getQuestionType() : string
    {
        return "assAudio";
    }
    
    /**
     * Returns the names of the additional question data tables
     *
     * All tables must have a 'question_fi' column.
     * Data from these tables will be deleted if a question is deleted
     *
     * @return mixed 	the name(s) of the additional tables (array or string)
     */
    public function getAdditionalTableName()
    {
        return '';
    }
    
    /**
     * Collects all texts in the question which could contain media objects
     * which were created with the Rich Text Editor
     */
    protected function getRTETextWithMediaObjects(): string
    {
        $text = parent::getRTETextWithMediaObjects();
        
        // eventually add the content of question type specific text fields
        // ..
        
        return (string) $text;
    }
    
    /**
     * Get the plugin object
     *
     * @return object The plugin object
     */
    public function getPlugin()
    {
        global $DIC;
        
        if ($this->plugin == null)
        {
            /** @var ilComponentFactory $component_factory */
            $component_factory = $DIC["component.factory"];
            $this->plugin = $component_factory->getPlugin('assAudio');
        }
        return $this->plugin;
    }

    /**
     * Returns true, if the question is complete
     *
     * @return boolean True, if the question is complete for use, otherwise false
     */
    public function isComplete(): bool
    {
        // Please add here your own check for question completeness
        // The parent function will always return false
        if(!empty($this->title) && !empty($this->author) && !empty($this->question) && $this->getMaximumPoints() >= 0)
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
     * @param	string		$original_id
     * @access 	public
     * @see assQuestion::saveToDb()
     */
    function saveToDb($original_id = ''): void
    {
        
        // save the basic data (implemented in parent)
        // a new question is created if the id is -1
        // afterwards the new id is set
        if ($original_id == '') {
            $this->saveQuestionDataToDb();
        } else {
            $this->saveQuestionDataToDb($original_id);
        }
        
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
    public function loadFromDb($question_id) : void
    {
        global $DIC;
        $ilDB = $DIC->database();
        
        // load the basic question data
        $result = $ilDB->query("SELECT qpl_questions.* FROM qpl_questions WHERE question_id = "
            . $ilDB->quote($question_id, 'integer'));
        
        if ($result->numRows() > 0) {
            $data = $ilDB->fetchAssoc($result);
            $this->setId($question_id);
            $this->setObjId($data['obj_fi']);
            $this->setOriginalId($data['original_id']);
            $this->setOwner($data['owner']);
            $this->setTitle((string) $data['title']);
            $this->setAuthor($data['author']);
            $this->setPoints($data['points']);
            $this->setComment((string) $data['description']);
            //$this->setSuggestedSolution((string) $data["solution_hint"]);
         
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc((string) $data['question_text'], 1));
            try {
                $this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
                $this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
            }
            
            // now you can load additional data
            // ...
            
            try
            {
                $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
            }
            catch(ilTestQuestionPoolException $e)
            {
            }
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
    function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null) : int
    {
        if ($this->getId() <= 0)
        {
            // The question has not been saved. It cannot be duplicated
            return 0;
        }
        
        // make a real clone to keep the object unchanged
        $clone = clone $this;
        
        $original_id = assQuestion::_getOriginalId($this->getId());
        $clone->setId(-1);
        
        if( (int) $testObjId > 0 )
        {
            $clone->setObjId($testObjId);
        }
        
        if (!empty($title))
        {
            $clone->setTitle($title);
        }
        if (!empty($author))
        {
            $clone->setAuthor($author);
        }
        if (!empty($owner))
        {
            $clone->setOwner($owner);
        }
        
        if ($for_test)
        {
            $clone->saveToDb($original_id);
        }
        else
        {
            $clone->saveToDb();
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
     * @param integer	$target_questionpool_id
     * @param string	$title
     *
     * @return void|integer Id of the clone or nothing.
     */
    function copyObject($target_questionpool_id, $title = '')
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
        if (!empty($title))
        {
            $clone->setTitle($title);
        }
        
        // save the clone data
        $clone->saveToDb();
        
        // copy question page content
        $clone->copyPageOfQuestion($original_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($original_id);
        
        // call the event handler for copy
        $clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());
        
        return $clone->getId();
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
     * Synchronize a question with its original
     * You need to extend this function if a question has additional data that needs to be synchronized
     *
     * @access public
     */
    function syncWithOriginal() : void
    {
        parent::syncWithOriginal();
    }
    
    /**
     * Get a submitted solution array from $_POST
     *
     * In general this may return any type that can be stored in a php session
     * The return value is used by:
     * 		savePreviewData()
     * 		saveWorkingData()
     * 		calculateReachedPointsForSolution()
     *
     * @return	array	('value1' => string|null)
     */
    public function getSolutionSubmit()
    {
        $value1 = trim( ilUtil::stripSlashes($_POST['question_'. $this->getId()]));
        
        return array(
            'value1' => empty($value1)? null : (string) $value1
        );
    }
    
    /**
     * Get a stored solution for a user and test pass
     * This is a wrapper to provide the same structure as getSolutionSubmit()
     *
     * @param int 	$active_id		active_id of hte user
     * @param int	$pass			number of the test pass
     * @param bool	$authorized		get the authorized solution
     *
     * @return	array	('value1' => string|null,)
     */
    public function getSolutionStored($active_id, $pass, $authorized = null)
    {
        // This provides an array with records from tst_solution
        // The example question should only store one record per answer
        // Other question types may use multiple records with value1/value2 in a key/value style
        if (isset($authorized))
        {
            // this provides either the authorized or intermediate solution
            $solutions = $this->getSolutionValues($active_id, $pass, $authorized);
        }
        else
        {
            // this provides the solution preferring the intermediate
            // or the solution from the previous pass
            $solutions = $this->getTestOutputSolutions($active_id, $pass);
        }
        
        
        if (empty($solutions))
        {
            // no solution stored yet
            $value1 = null;
        }
        else
        {
            // If the process locker isn't activated in the Test and Assessment administration
            // then we may have multiple records due to race conditions
            // In this case the last saved record wins
            $solution = end($solutions);
            
            $value1 = $solution['value1'];
        }
        
        return array(
            'value1' => empty($value1)? null : (string) $value1,
        );
    }
    
    /**
     * Calculate the reached points for a submitted user input
     * 
     * Audio is not auto-graded, so always 0 points
     *
     * @param mixed user input (scalar, object or array)
     */
    public function calculateReachedPointsforSolution($solution)
    {
        return 0;
    }
    
    /**
     * Returns the points, a learner has reached answering the question
     * The points are calculated from the given answers.
     *
     * @param int $active_id
     * @param integer $pass The Id of the test pass
     * @param bool $authorizedSolution
     * @param boolean $returndetails (deprecated !!)
     * @return int
     *
     * @throws ilTestException
     */
    public function calculateReachedPoints($active_id, $pass = NULL, $authorizedSolution = true, $returndetails = false)
    {
        return 0;
    }


    /**
     * Saves the learners input of the question to the database.
     *
     * @param integer $active_id 	Active id of the user
     * @param integer $pass 		Test pass
     * @param boolean $authorized	The solution is authorized
     *
     * @return boolean $status
     */
    function saveWorkingData($active_id, $pass = NULL, $authorized = true): bool
    {		
        global $ilDB;
        
        if (is_null($pass))
        {
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
		ilFileUtils::makeDirParents($this->getFileUploadPath($active_id));
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
		$entered_values = FALSE;
		
		if (!empty($_POST["question".$this->getId()."audio"]))
		{
			$entered_values = TRUE;
			
		}

		// Log whether the user entered values
		if (ilObjAssessmentFolder::_enabledAssessmentLogging())
		{
		    assQuestion::logAction($this->lng->txtlng(
		        'assessment',
		        $entered_values ? 'log_user_entered_values' : 'log_user_not_entered_values',
		        ilObjAssessmentFolder::_getLogLanguage()
		        ),
		        $active_id,
		        $this->getId()
		        );
		}
		
		// submitted solution is valid
		return true;
	}


	/**
	 * Reworks the allready saved working data if neccessary
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $obligationsAnswered
	 * @param boolean $authorized
	 */
	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
	{
	    // normally nothing needs to be reworked
	}

	/**
	 * Creates an Excel worksheet for the detailed cumulated results of this question
	 *
	 * @param object $worksheet    Reference to the parent excel worksheet
	 * @param int $startrow     Startrow of the output in the excel worksheet
	 * @param int $active_id    Active id of the participant
	 * @param int $pass         Test pass
	 *
	 * @return int
	 */
	public function setExportDetailsXLS(ilAssExcelFormatHelper $worksheet, int $startrow, int $active_id, int $pass): int
	{
		parent::setExportDetailsXLS($worksheet, $startrow, $active_id, $pass);
		return $startrow + 1;
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
}
?>
