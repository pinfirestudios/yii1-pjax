<?php
namespace pinfirestudios\yii1pjax;

use Yii;
use CClientScript;
use CHtml;
use CJavaScript;
use CWidget;

/**
 * Backport of Pjax widget from Yii2 to Yii-1
 *
 * Pjax is a widget integrating the [pjax](https://github.com/yiisoft/jquery-pjax) jQuery plugin.
 *
 * Pjax only deals with the content enclosed between its [[beginWidget()]] and [[endWidget()]] calls, called the *body content* of the widget.
 * By default, any link click or form submission (for those forms with `data-pjax` attribute) within the body content
 * will trigger an AJAX request. In responding to the AJAX request, Pjax will send the updated body content (based
 * on the AJAX request) to the client which will replace the old content with the new one. The browser's URL will then
 * be updated using pushState. The whole process requires no reloading of the layout or resources (js, css).
 *
 * You may configure [[linkSelector]] to specify which links should trigger pjax, and configure [[formSelector]]
 * to specify which form submission may trigger pjax.
 *
 * You may disable pjax for a specific link inside the container by adding `data-pjax="0"` attribute to this link.
 *
 * The following example shows how to use Pjax with the [[CGridView]] widget so that the grid pagination,
 * sorting and filtering can be done via pjax:
 */
class Pjax extends CWidget
{
    /**
     * @var array the HTML attributes for the widget container tag. The following special options are recognized:
     *
     * - `tag`: string, the tag name for the container. Defaults to `div`
     *   This option is available since version 2.0.7.
     *   See also [[\yii\helpers\Html::tag()]].
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];
    /**
     * @var string|false the jQuery selector of the links that should trigger pjax requests.
     * If not set, all links within the enclosed content of Pjax will trigger pjax requests.
     * If set to false, no code will be registered to handle links.
     * Note that if the response to the pjax request is a full page, a normal request will be sent again.
     */
    public $linkSelector;
    /**
     * @var string|false the jQuery selector of the forms whose submissions should trigger pjax requests.
     * If not set, all forms with `data-pjax` attribute within the enclosed content of Pjax will trigger pjax requests.
     * If set to false, no code will be registered to handle forms.
     * Note that if the response to the pjax request is a full page, a normal request will be sent again.
     */
    public $formSelector;
    /**
     * @var string The jQuery event that will trigger form handler. Defaults to "submit".
     * @since 2.0.9
     */
    public $submitEvent = 'submit';
    /**
     * @var boolean whether to enable push state.
     */
    public $enablePushState = true;
    /**
     * @var boolean whether to enable replace state.
     */
    public $enableReplaceState = false;
    /**
     * @var integer pjax timeout setting (in milliseconds). This timeout is used when making AJAX requests.
     * Use a bigger number if your server is slow. If the server does not respond within the timeout,
     * a full page load will be triggered.
     */
    public $timeout = 1000;
    /**
     * @var boolean|integer how to scroll the page when pjax response is received. If false, no page scroll will be made.
     * Use a number if you want to scroll to a particular place.
     */
    public $scrollTo = false;
    /**
     * @var array additional options to be passed to the pjax JS plugin. Please refer to the
     * [pjax project page](https://github.com/yiisoft/jquery-pjax) for available options.
     */
    public $clientOptions;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }

        if ($this->requiresPjax()) {
            ob_start();
			ob_implicit_flush(false);

			$controller = $this->getController();

			// We need to be able to reset CSS files, so we use our own clientscript subclass to do so
			$cs = new PjaxClientScript;
			Yii::app()->setComponent('clientScript', $cs, false);

			$cs->reset();
            if ($controller->title !== null) {
                echo CHtml::tag('title', [], CHtml::encode($controller->title));
            }
        } else {
			$options = $this->options;
			
			$tag = 'div';
			if (array_key_exists('tag', $options))
			{
				$tag = $options['tag'];
				unset($options['tag']);
			}

            echo CHtml::openTag($tag, array_merge([
                'data-pjax-container' => '',
                'data-pjax-push-state' => $this->enablePushState,
                'data-pjax-replace-state' => $this->enableReplaceState,
                'data-pjax-timeout' => $this->timeout,
                'data-pjax-scrollto' => $this->scrollTo,
            ], $options), false);
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        if (!$this->requiresPjax()) {			
			$tag = 'div';
			if (array_key_exists('tag', $this->options))
			{
				$tag = $this->options['tag'];
			}

            echo CHtml::closeTag($tag);
            $this->registerClientScript();

            return;
        }

		$cs = Yii::app()->getClientScript();

        // Do not re-send css files as it may override the css files that were loaded after them.
        // This is a temporary fix for https://github.com/yiisoft/yii2/issues/2310
        // It should be removed once pjax supports loading only missing css files
        $cs->resetCssFiles();

		// only need the content enclosed within this widget
		$content = ob_get_clean();
		$cs->renderHead($content);
		$cs->renderBodyBegin($content);
		$cs->renderBodyEnd($content);

        // Contents of \yii\web\Response::clearOutputBuffers()		
		// the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
		}

		http_response_code(200);
		echo $content;

        Yii::app()->end();
    }

    /**
     * @return boolean whether the current request requires pjax response from this widget
     */
    protected function requiresPjax()
	{
		$headers = getallheaders();

		// Amazon ALB seems to change the case sensitivity on headers.
		$headers = array_combine(
			array_map('strtolower', array_keys($headers)),
			array_values($headers)
		);

		if (!isset($headers['x-pjax']))
		{
			Yii::trace("No X-Pjax header", __METHOD__);
			return false;
		}

		if (!isset($headers['x-pjax-container']))
		{
			Yii::trace("No x-pjax-container header", __METHOD__);
			return false;
		}

        return explode(' ', $headers['x-pjax-container'])[0] === '#' . $this->options['id'];
    }

    /**
     * Registers the needed JavaScript.
     */
    public function registerClientScript()
    {
        $id = $this->options['id'];
        $this->clientOptions['push'] = $this->enablePushState;
        $this->clientOptions['replace'] = $this->enableReplaceState;
        $this->clientOptions['timeout'] = $this->timeout;
        $this->clientOptions['scrollTo'] = $this->scrollTo;
        if (!isset($this->clientOptions['container'])) {
            $this->clientOptions['container'] = "#$id";
        }
        $options = CJavaScript::encode($this->clientOptions);
        $js = '';
        if ($this->linkSelector !== false) {
            $linkSelector = CJavaScript::encode($this->linkSelector !== null ? $this->linkSelector : '#' . $id . ' a');
            $js .= "jQuery(document).pjax($linkSelector, $options);";
        }
        if ($this->formSelector !== false) {
            $formSelector =  CJavaScript::encode($this->formSelector !== null ? $this->formSelector : '#' . $id . ' form[data-pjax]');
            $submitEvent =  CJavaScript::encode($this->submitEvent);
            $js .= "\njQuery(document).on($submitEvent, $formSelector, function (event) {jQuery.pjax.submit(event, $options);});";
		}

		$vendor = Yii::getPathOfAlias('vendor.bower-asset.yii2-pjax');
		$assetsDir = Yii::app()->assetManager->publish($vendor);

		$cs = Yii::app()->clientScript;
		$cs->registerScriptFile($assetsDir . '/jquery.pjax.js');

        if ($js !== '') {
            $cs->registerScript(md5($js), $js, CClientScript::POS_READY);
        }
    }
}
