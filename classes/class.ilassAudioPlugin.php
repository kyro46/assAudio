<?php
	
/**
* Question plugin Audio
*
* @author Christoph Jobst <iliasplugins.christoph.jobst@outlook.de>
* @version $Id$
* @ingroup ModulesTestQuestionPool
*/
class ilassAudioPlugin extends ilQuestionsPlugin
{
	final function getPluginName(): string
	{
	    return "assAudio";
	}
	
	final function getQuestionType(): string
	{
	    return "assAudio";
	}
	
	final function getQuestionTypeTranslation(): string
	{
	    return $this->txt($this->getQuestionType());
	}
}
?>