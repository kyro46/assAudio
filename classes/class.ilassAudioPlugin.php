<?php

include_once "./Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php";
	
/**
* Question plugin Audio
*
* @author Christoph Jobst <christoph.jobst@llz.uni-halle.de>
* @version $Id$
* @ingroup ModulesTestQuestionPool
*/
class ilassAudioPlugin extends ilQuestionsPlugin
{
    final function getPluginName(): string
    {
		return "assAudio";
	}
	
	final function getQuestionType()
	{
		return "assAudio";
	}
	
	final function getQuestionTypeTranslation(): string
	{
		return $this->txt($this->getQuestionType());
	}
}
?>