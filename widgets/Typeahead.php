<?php

namespace kartik\widgets;

use Yii;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use yii\base\InvalidConfigException;
use yii\web\View;
use yii\web\JsExpression;

/**
 * Typeahead widget is a Yii2 wrapper for the Twitter Typeahead.js plugin. This
 * input widget is a jQuery based replacement for text inputs providing search
 * and typeahead functionality. It is inspired by twitter.com's autocomplete search 
 * functionality and based on Twitter's typeahead.js which Twitter mentions as
 * a fast and fully-featured autocomplete library.
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @since 1.0
 * @see http://twitter.github.com/typeahead.js/examples
 */
class Typeahead extends \yii\widgets\InputWidget {

    /**
     * @var ActiveForm the form object to which this
     * input will be attached to in case you are using 
	 * the widget within an ActiveForm
     */
    public $form;

	/**
	 * @var array dataset an object that defines a set of data that hydrates suggestions. 
	 * It consists of the following special variable settings:
	 * - data: array the option data items - a linear list of values. For a simple and 
	 *   straight forward setup, you must define either [[data]] or [[action]].
	 * - action: mixed URL or a remote action, which will return json-encoded typeahead 
	 *   datasets or list of datums. For a simple and straight forward setup, you must 
	 *   define either [[data]] or [[action]].
	 * - limit: integer the max number of suggestions from the dataset to display for 
	 *   a given query. Defaults to 5.
	 * - valueKey: string the key used to access the value of the datum in the datum 
	 *   object. Defaults to 'value'.
	 * - template: string the template used to render suggestions. Can be a string or a 
	 *   pre-compiled template (i.e. a `JsExpression` function that takes a datum as input
	 *   and returns html as output). If not provided, defaults to `<p>{{value}}</p>`
	 * - engine: the template engine used to compile/render template if it is a string. 
	 *   Any engine can be used as long as it adheres to the expected API. Required if 
	 *   template is a string.	 
	 * - local: array configuration for the [[local]] list of datums. Optional.
	 *   If this is set, it will skip the setting for [[data]]	 
	 * - prefetch: array configuration for the [[prefetch]] options object. Optional.
	 *   If this is set, it will skip the setting for [[action]]
	 * - remote: array configuration for the [[remote]] options object. Optional.
	 *   If this is set, it will skip the setting for [[action]]
	 * - header: string the header rendered before suggestions in the dropdown menu. Can
	 *   be either a DOM element or HTML
	 * - footer: string the footer rendered after suggestions in the dropdown menu. Can
	 *   be either a DOM element or HTML
	 * - headerOptions: array the HTML attributes for the header container.
	 * - footerOptions: array the HTML attributes for the footer container.
	 */
	public $dataset = [];
	
    /**
     * @var array the HTML attributes for the input tag. The following options are important:
     * - multiple: boolean whether multiple or single item should be selected. Defaults to false.
     * - placeholder: string placeholder for the select item.
     */
    public $options = [];

    /**
     * @var array Typeahead JQuery events. You must define events in
     * event-name => event-function format
     * for example:
     * ~~~
     * pluginEvents = [
     * 		"change" => "function() { log("change"); }",
     * 		"open" => "function() { log("open"); }",
     * 		"typeahead-opening" => "function() { log("typeahead-opening"); }",
     * ];
     * ~~~
     */
    public $pluginEvents = [];

    /**
     * @var array Typeahead JQuery plugin options. This is autogenerated based on datasets.
     */
    private $_pluginOptions = [];
	
    /**
     * Initializes the widget
     * @throw InvalidConfigException
     */
    public function init() {
        parent::init();
        if (isset($this->form) && !($this->form instanceof \yii\widgets\ActiveForm)) {
            throw new InvalidConfigException("The 'form' property must be set and must be an object of type 'ActiveForm'.");
        }
        if (isset($this->form) && !$this->hasModel()) {
            throw new InvalidConfigException("You must set the 'model' and 'attribute' when you are using the widget with ActiveForm.");
        }		
		if (empty($this->dataset) || !is_array($this->dataset)) {
			 throw new InvalidConfigException("You must define the 'dataset' property for Typeahead which must be an array.");
		}
        $this->registerAssets();
        $this->renderInput();
    }

    /**
     * Renders the source Input for the Typeahead plugin.
     * Graceful fallback to a normal HTML  text input - in 
	 * case JQuery is not supported by the browser
     */
    protected function renderInput() {
		if (isset($this->form)) {
			echo $this->form->field($this->model, $this->attribute)->textInput($this->options);
		}
		elseif ($this->hasModel()) {
			echo Html::activeTextInput($this->model, $this->attribute, $this->options);
		}
		else {
			echo Html::textInput($this->name, $this->value, $this->options);
		}
    }
	
	/**
	 * Validates and sets plugin options
	 */
	protected function setPluginOptions() {
		$i = 1;
		$data = [];
		foreach ($this->dataset as $d) {
			if (empty($d['name'])) {
				$d['name'] = $this->options['id'] . '-ta-' . $i;
			}
			if (!empty($d['data']) && empty($d['local'])) {
				$d['local'] = $d['data'];
				unset($d['data']);
			}
			if (empty($d['remote']) && empty($d['prefetch']) && !empty($d['action'])) {
				$d['remote'] = $d['action'];
				unset($d['action']);
			}
			if (!empty($d['header'])) {
				$opt = ArrayHelper::remove($d, 'headerOptions', []);
				$d['header'] = Html::tag('div', $d['header'], $opt);
				
			}
			if (!empty($d['footer'])) {
				$opt = ArrayHelper::remove($d, 'footerOptions', []);
				$d['footer'] = Html::tag('div', $d['footer'], $opt);
			}
			$data[] = $d;
			$i++;
		}
		$this->_pluginOptions = Json::encode($data);
	}
	
    /**
     * Registers the needed assets
     */
    public function registerAssets() {
        $view = $this->getView();
        Typeahead1Asset::register($view);
        Typeahead2Asset::register($view);
        $id = '$("#' . $this->options['id'] . '")';
		$this->setPluginOptions();
        $js = "{$id}.typeahead(" . $this->_pluginOptions . ");";
        if (!empty($this->pluginEvents)) {
            $js .= "\n{$id}";
            foreach ($this->pluginEvents as $event => $function) {
                $func = new JsExpression($function);
                $js .= ".on('{$event}', {$func})\n";
            }
        }
        $view->registerJs($js);
    }

}
