<?php

/**
 * Audio class for question type plugins
 *
 * @author	Christoph Jobst <iliasplugins.christoph.jobst@outlook.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 */

use ILIAS\Test\Logging\AdditionalInformationGenerator;
use ILIAS\Test\Participants\ParticipantRepository;
use ILIAS\TestQuestionPool\QuestionPoolDIC;
use ILIAS\TestQuestionPool\Questions\QuestionAutosaveable;

class assAudio extends assQuestion implements ilObjQuestionScoringAdjustable, QuestionAutosaveable
{
	/**
	 * @var ilassAudioPlugin	The plugin object
	 */
    private ilPlugin $plugin;
    
    private ParticipantRepository $participant_repository;
    
    public function getPlugin(): ilPlugin
    {
        return $this->plugin;
    }
    
    public function setPlugin(ilPlugin $plugin): void
    {
        $this->plugin = $plugin;
    }
    
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
        parent::__construct($title, $comment, $author, $owner, $question);
        
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
        
        // needed for excel export
        $this->getPlugin()->loadLanguageModule();
        
        $local_dic = QuestionPoolDIC::dic();
        $this->participant_repository = $local_dic['participant_repository'];
        
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
    public function getAdditionalTableName(): string
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
    public function duplicate(
        bool $for_test = true,
        string $title = '',
        string $author = '',
        int $owner = -1,
        $test_obj_id = null
        ): int {
            if ($this->id <= 0) {
                // The question has not been saved. It cannot be duplicated
                return -1;
            }
            
            $clone = clone $this;
            $clone->id = -1;
            
            if ((int) $test_obj_id > 0) {
                $clone->setObjId($test_obj_id);
            }
            
            if ($title) {
                $clone->setTitle($title);
            }
            if ($author) {
                $clone->setAuthor($author);
            }
            if ($owner) {
                $clone->setOwner($owner);
            }
            if ($for_test) {
                $clone->saveToDb($this->id);
            } else {
                $clone->saveToDb();
            }
            
            $clone->clonePageOfQuestion($this->getId());
            $clone->cloneXHTMLMediaObjectsOfQuestion($this->getId());
            
            $clone = $this->cloneQuestionTypeSpecificProperties($clone);
            
            $clone->onDuplicate($this->getObjId(), $this->getId(), $clone->getObjId(), $clone->getId());
            
            return $clone->id;
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
    public function calculateReachedPoints(int $active_id, ?int $pass = null, bool $authorized_solution = true): float
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
    public function saveWorkingData(
        int $active_id,
        ?int $pass = null,
        bool $authorized = true
        ): bool {
            if ($pass === null) {
                $pass = ilObjTest::_getPass($active_id);
            }
            
            $answer = $this->getSolutionSubmit();
            $path = $this->getFileUploadPath($active_id);
            $filename = "recording_" . $active_id . "_" . $pass . "_" . time() . '.webm';
            
            if (!@file_exists($this->getFileUploadPath($active_id)))
                ilFileUtils::makeDirParents($this->getFileUploadPath($active_id));
                file_put_contents($path . $filename, base64_decode($answer["value1"]));
                
            
            $this->getProcessLocker()->executeUserSolutionUpdateLockOperation(
                function () use ($filename, $active_id, $pass, $authorized) {
                    $this->removeCurrentSolution($active_id, $pass, $authorized);
                    
                    if ($filename !== '') {
                        $this->saveCurrentSolution($active_id, $pass, $filename, null, $authorized);
                    }
                }
                );
            
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
	 * Returns the name of the answer table in the database
	 *
	 * @return array|string The answer table name
	 * @access public
	 */
	function getAnswerTableName() : array|string
	{
	    return "";
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
	public function setExportDetailsXLSX(ilAssExcelFormatHelper $worksheet, int $startrow, int $col, int $active_id, int $pass) : int
	{
	    parent::setExportDetailsXLSX($worksheet, $startrow, $col, $active_id, $pass);
	    return $startrow + 1;
	}

	// Generic log
	public function toLog(AdditionalInformationGenerator $additional_info) : array
	{
	    return [
	        AdditionalInformationGenerator::KEY_QUESTION_TYPE => (string) $this->getQuestionType(),
	        AdditionalInformationGenerator::KEY_QUESTION_TITLE => $this->getTitleForHTMLOutput(),
	        AdditionalInformationGenerator::KEY_QUESTION_TEXT => $this->formatSAQuestion($this->getQuestion()),
	        AdditionalInformationGenerator::KEY_QUESTION_REACHABLE_POINTS => $this->getPoints(),
	        AdditionalInformationGenerator::KEY_FEEDBACK => [
	            AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_INCOMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
	            AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_COMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
	        ]
	    ];
	}
	
	// FilePath as log entry
	protected function solutionValuesToLog(
	    AdditionalInformationGenerator $additional_info,
	    array $solution_values
	    ): string {
	        if (!array_key_exists(0, $solution_values)
	            || !array_key_exists('value1', $solution_values[0])) {
	                return '';
	            }
	            return $this->refinery->string()->stripTags()->transform(
	                html_entity_decode($solution_values[0]['value1'])
	                );
	}
	
	// FilePath as log entry
	public function solutionValuesToText(array $solution_values) : string
	{
	    if (!array_key_exists(0, $solution_values)
	        || !array_key_exists('value1', $solution_values[0])) {
	            return '';
	        }
	        return $solution_values[0]['value1'];
	}
	
	/**
	 * Returns the filesystem path for file uploads for the current test, user, pass and question
	 * 
	 * Path results in e.g. /var/www/ILIAS/public/data/myilias/assessment/tst_4/5/19/files/recording_5_0_1735915846.webm
	 * The filename consists of active_id + pass (starting with 0 for the first pass) + timestamp
	 */
	public function getFileUploadPath($active_id, $question_id = null)
	{
	    $test_id = $this->participant_repository->lookupTestIdByActiveId($active_id);
		if (is_null($question_id)) $question_id = $this->getId();
		return CLIENT_WEB_DIR . "/assessment/tst_$test_id/$active_id/$question_id/files/";
	}
	
	/**
	 * Returns the web path for web accessable files of a question.
	 * The audio path is under the web accessable data dir in assessment/tst_REFERENCE_ID_OF_TEST/ID_OF_PARTICIPANT/ID_OF_QUESTION/files
	 */
	public function getFilePathWeb($active_id, $question_id = null): string
	{
	    $test_id = $this->participant_repository->lookupTestIdByActiveId($active_id);
	    if (is_null($question_id)) $question_id = $this->getId();
	    
        $webdir = ilFileUtils::removeTrailingPathSeparators(CLIENT_WEB_DIR)
        . "/assessment/tst_$test_id/$active_id/$question_id/files/";
        return str_replace(
            ilFileUtils::removeTrailingPathSeparators(ILIAS_ABSOLUTE_PATH . '/public'),
            ilFileUtils::removeTrailingPathSeparators(ILIAS_HTTP_PATH),
            $webdir
            );
	}
	
	/**
	 * Saves a record to the question types additional data table.
	 *
	 * @return mixed
	 */
	public function saveAdditionalQuestionDataToDb()
	{
	    // nothing to save for Audio
	    return 0;
	}
}
?>
