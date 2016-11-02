<?php
namespace pinfirestudios\yii1pjax;

use CClientScript;

/**
 * Override CClientScript so we can reset CSS without script (needed for Pjax backport)
 */
class PjaxClientScript extends CClientScript
{
	public function resetCssFiles()
	{
		$this->cssFiles = [];
	}
}
