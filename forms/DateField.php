<?php
require_once 'Zend/Date.php';

/**
 * Form field to display an editable date string,
 * either in a single `<input type="text">` field,
 * or in three separate fields for day, month and year.
 * 
 * # Configuration
 * 
 * - 'showcalendar' (boolean): Determines if a calendar picker is shown.
 *    By default, "DHTML Calendar" is used,see http://www.dynarch.com/projects/calendar.
 *    CAUTION: Only works in NZ date format, see calendar-setup.js
 * - 'dmyfields' (boolean): Show three input fields for day, month and year separately.
 *    CAUTION: Might not be useable in combination with 'showcalendar', depending on the used javascript library
 * - 'dateformat' (string): Date format compatible with Zend_Date.
 *    Usually set to default format for {@link locale}
 *    through {@link Zend_Locale_Format::getDateFormat()}.
 * - 'datavalueformat' (string): Internal ISO format string used by {@link dataValue()} to save the
 *    date to a database.
 * - 'min' (string): Minimum allowed date value (in ISO format, or strtotime() compatible).
 *    Example: '2010-03-31', or '-7 days'
 * - 'max' (string): Maximum allowed date value (in ISO format, or strtotime() compatible).
 *    Example: '2010-03-31', or '1 year'
 * 
 * # Localization
 * 
 * The field will get its default locale from {@link i18n::get_locale()}, and set the `dateformat`
 * configuration accordingly. Changing the locale through {@link setLocale()} will not update the 
 * `dateformat` configuration automatically.
 * 
 * # Usage
 * 
 * ## Example: German dates with separate fields for day, month, year
 * 
 * 	$f = new DateField('MyDate');
 * 	$f->setLocale('de_DE');
 * 	$f->setConfig('dmyfields');
 * 
 * @package forms
 * @subpackage fields-datetime
 */
class DateField extends TextField {
	
	/**
	 * @var array
	 */
	protected $config = array(
		'showcalendar' => false,
		'dmyfields' => false,
		'dmyseparator' => '&nbsp;<span class="separator">/</span>&nbsp;',
		'dateformat' => null,
		'datavalueformat' => 'yyyy-MM-dd',
		'min' => null,
		'max' => null
	);
		
	/**
	 * @var String
	 */
	protected $locale = null;
	
	/**
	 * @var Zend_Date Just set if the date is valid.
	 * {@link $value} will always be set to aid validation,
	 * and might contain invalid values.
	 */
	protected $valueObj = null;
	
	function __construct($name, $title = null, $value = null, $form = null, $rightTitle = null) {
		if(!$this->locale) {
			$this->locale = i18n::get_locale();
		}
		
		if(!$this->getConfig('dateformat')) {
			$this->setConfig('dateformat', Zend_Locale_Format::getDateFormat($this->locale));
		}

		parent::__construct($name, $title, $value, $form, $rightTitle);
	}

	function FieldHolder() {
		
		
		return parent::FieldHolder();
	}

	function Field() {
		// Three separate fields for day, month and year
		if($this->getConfig('dmyfields')) {
			// values
			$valArr = ($this->valueObj) ? $this->valueObj->toArray() : null;

			// fields
			$fieldDay = new NumericField($this->name . '[day]', false, ($valArr) ? $valArr['day'] : null);
			$fieldDay->addExtraClass('day');
			$fieldDay->setMaxLength(2);
			$fieldMonth = new NumericField($this->name . '[month]', false, ($valArr) ? $valArr['month'] : null);
			$fieldMonth->addExtraClass('month');
			$fieldMonth->setMaxLength(2);
			$fieldYear = new NumericField($this->name . '[year]', false, ($valArr) ? $valArr['year'] : null);
			$fieldYear->addExtraClass('year');
			$fieldYear->setMaxLength(4);
			
			// order fields depending on format
			$sep = $this->getConfig('dmyseparator');
			$format = $this->getConfig('dateformat');
			$fields = array();
			$fields[stripos($format, 'd')] = $fieldDay->Field();
			$fields[stripos($format, 'm')] = $fieldMonth->Field();
			$fields[stripos($format, 'y')] = $fieldYear->Field();
			ksort($fields);
			$html = implode($sep, $fields);
		} 
		// Default text input field
		else {
			$html = parent::Field();
		}
		
		$html = $this->FieldDriver($html);
		
		// wrap in additional div for legacy reasons and to apply behaviour correctly
		if($this->getConfig('showcalendar')) $html = sprintf('<div class="calendardate">%s</div>', $html);
		
		return $html;
	}
	
	/**
	 * Caution: API might change. This will evolve into a pluggable
	 * API for 'form field drivers' which can add their own
	 * markup and requirements. 
	 * 
	 * @param String $html
	 * @return $html
	 */
	protected function FieldDriver($html) {
		// Optionally add a "DHTML" calendar icon. Mainly legacy, a date picker
		// should be unobtrusively added by javascript (e.g. jQuery UI).
		// CAUTION: Only works in NZ date format, see calendar-setup.js
		if($this->getConfig('showcalendar')) {
			Requirements::javascript(THIRDPARTY_DIR . '/prototype/prototype.js');
			Requirements::javascript(THIRDPARTY_DIR . '/behaviour/behaviour.js');
			Requirements::javascript(THIRDPARTY_DIR . "/calendar/calendar.js");
			Requirements::javascript(THIRDPARTY_DIR . "/calendar/lang/calendar-en.js");
			Requirements::javascript(THIRDPARTY_DIR . "/calendar/calendar-setup.js");
			Requirements::css(SAPPHIRE_DIR . "/css/DateField.css");
			Requirements::css(THIRDPARTY_DIR . "/calendar/calendar-win2k-1.css");
			
			$html .= sprintf('<img src="sapphire/images/calendar-icon.gif" id="%s-icon" alt="Calendar icon" />', $this->id());
			$html .= sprintf('<div class="calendarpopup" id="%s-calendar"></div>', $this->id());
		}
		
		return $html;
	}
	
	/**
	 * Sets the internal value to ISO date format.
	 * 
	 * @param String|Array $val 
	 */
	function setValue($val) {
		if(empty($val)) {
			$this->value = null;
			$this->valueObj = null;
		} else {
			if($this->getConfig('dmyfields')) {
				// Setting in correct locale
				if(is_array($val) && $this->validateArrayValue($val)) {
					// set() gets confused with custom date formats when using array notation
					$this->valueObj = new Zend_Date($val, null, $this->locale);
					$this->value = $this->valueObj->toArray();
				}
				// load ISO date from database (usually through Form->loadDataForm())
				else if(!empty($val) && Zend_Date::isDate($val, $this->getConfig('datavalueformat'))) {
					$this->valueObj = new Zend_Date($val, $this->getConfig('datavalueformat'));
					$this->value = $this->valueObj->toArray();
				}
				else {
					$this->value = $val;
					$this->valueObj = null;
				}
			} else {
				// Setting in corect locale.
				// Caution: Its important to have this check *before* the ISO date fallback,
				// as some dates are falsely detected as ISO by isDate(), e.g. '03/04/03'
				// (en_NZ for 3rd of April, definetly not yyyy-MM-dd)
				if(!empty($val) && Zend_Date::isDate($val, $this->getConfig('dateformat'), $this->locale)) {
					$this->valueObj = new Zend_Date($val, $this->getConfig('dateformat'), $this->locale);
					$this->value = $this->valueObj->get($this->getConfig('dateformat'));
				}
				// load ISO date from database (usually through Form->loadDataForm())
				else if(!empty($val) && Zend_Date::isDate($val, $this->getConfig('datavalueformat'))) {
					$this->valueObj = new Zend_Date($val, $this->getConfig('datavalueformat'));
					$this->value = $this->valueObj->get($this->getConfig('dateformat'));
				}
				else {
					$this->value = $val;
					$this->valueObj = null;
				}
			}
		}
	}
	
	/**
	 * @return String ISO 8601 date, suitable for insertion into database
	 */
	function dataValue() {
		if($this->valueObj) {
			return $this->valueObj->toString($this->getConfig('datavalueformat'));
		} else {
			return null;
		}
	}
	
	function performReadonlyTransformation() {
		$field = new DateField_Disabled($this->name, $this->title, $this->valueObj);
		$field->setForm($this->form);
		$field->readonly = true;
		
		return $field;
	}
	
	function jsValidation() {
		$formID = $this->form->FormName();

		if(Validator::get_javascript_validator_handler() == 'none') return true;

		if($this->showSeparateFields) {
			$error = _t('DateField.VALIDATIONJS', 'Please enter a valid date format (DD/MM/YYYY).');
			$error = 'Please enter a valid date format (DD/MM/YYYY) from dmy.';
			$jsFunc =<<<JS
Behaviour.register({
	"#$formID": {
		validateDMYDate: function(fieldName) {
			var day_value = \$F(_CURRENT_FORM.elements[fieldName+'[day]']);
			var month_value = \$F(_CURRENT_FORM.elements[fieldName+'[month]']);
			var year_value = \$F(_CURRENT_FORM.elements[fieldName+'[year]']);

			// TODO NZ specific
			var value = day_value + '/' + month_value + '/' + year_value;
			if(value && value.length > 0 && !value.match(/^[0-9]{1,2}\/[0-9]{1,2}\/([0-9][0-9]){1,2}\$/)) {
				validationError(_CURRENT_FORM.elements[fieldName+'[day]'],"$error","validation",false);

				return false;
			}
			
			return true;
		}
	}
});
JS;
			Requirements :: customScript($jsFunc, 'func_validateDMYDate_'.$formID);

			return <<<JS
	if(\$('$formID')){
		if(typeof fromAnOnBlur != 'undefined'){
			if(fromAnOnBlur.name == '$this->name')
				\$('$formID').validateDMYDate('$this->name');
		}else{
			\$('$formID').validateDMYDate('$this->name');
		}
	}
JS;
		} else {
			$error = _t('DateField.VALIDATIONJS', 'Please enter a valid date format (DD/MM/YYYY).');
			$jsFunc =<<<JS
Behaviour.register({
	"#$formID": {
		validateDate: function(fieldName) {

			var el = _CURRENT_FORM.elements[fieldName];
			if(el)
			var value = \$F(el);

			if(Element.hasClassName(el, 'dmydate')) {
				// dmy triple field validation
				var day_value = \$F(_CURRENT_FORM.elements[fieldName+'[day]']);
				var month_value = \$F(_CURRENT_FORM.elements[fieldName+'[month]']);
				var year_value = \$F(_CURRENT_FORM.elements[fieldName+'[year]']);

				// TODO NZ specific
				var value = day_value + '/' + month_value + '/' + year_value;
				if(value && value.length > 0 && !value.match(/^[0-9]{1,2}\/[0-9]{1,2}\/([0-9][0-9]){1,2}\$/)) {
					validationError(_CURRENT_FORM.elements[fieldName+'[day]'],"$error","validation",false);
					return false;
				}
			} else {
				// single field validation
				if(value && value.length > 0 && !value.match(/^[0-9]{1,2}\/[0-9]{1,2}\/[0-90-9]{2,4}\$/)) {
					validationError(el,"$error","validation",false);
					return false;
				}
			}

			return true;
		}
	}
});
JS;
			Requirements :: customScript($jsFunc, 'func_validateDate_'.$formID);

			return <<<JS
if(\$('$formID')){
	if(typeof fromAnOnBlur != 'undefined'){
		if(fromAnOnBlur.name == '$this->name')
			\$('$formID').validateDate('$this->name');
	}else{
		\$('$formID').validateDate('$this->name');
	}
}
JS;
		}
	}

	/**
	 * Validate an array with expected keys 'day', 'month' and 'year.
	 * Used because Zend_Date::isDate() doesn't provide this.
	 * 
	 * @param Array $val
	 * @return boolean
	 */
	function validateArrayValue($val) {
		if(!is_array($val)) return false;

		return (
			array_key_exists('year', $val)  
			&& Zend_Date::isDate($val['year'], 'yyyy', $this->locale)
			&& array_key_exists('month', $val)
			&& Zend_Date::isDate($val['month'], 'MM', $this->locale)
			&& array_key_exists('day', $val)
			&& Zend_Date::isDate($val['day'], 'dd', $this->locale)
		);
	}

	/**
	 * @return Boolean
	 */
	function validate($validator) {
		$valid = true;
		
		// Don't validate empty fields
		if(empty($this->value)) return true;

		// date format
		if($this->getConfig('dmyfields')) {
			$valid = ($this->validateArrayValue($this->value));
		} else {
			$valid = (Zend_Date::isDate($this->value, $this->getConfig('dateformat'), $this->locale));
		}
		if(!$valid) {
			$validator->validationError(
				$this->name, 
				_t('DateField.VALIDDATEFORMAT', "Please enter a valid date format (DD/MM/YYYY)."), 
				"validation", 
				false
			);
			return false;
		}
		
		// min/max - Assumes that the date value was valid in the first place
		if($min = $this->getConfig('min')) {
			// ISO or strtotime()
			if(Zend_Date::isDate($min, $this->getConfig('datavalueformat'))) {
				$minDate = new Zend_Date($min, $this->getConfig('datavalueformat'));
			} else {
				$minDate = new Zend_Date(strftime('%F', strtotime($min)), $this->getConfig('datavalueformat'));
			}
			if(!$this->valueObj->isLater($minDate) && !$this->valueObj->equals($minDate)) {
				$validator->validationError(
					$this->name, 
					sprintf(
						_t('DateField.VALIDDATEMINDATE', "Your date has to be newer or matching the minimum allowed date (%s)"), 
						$minDate->toString($this->getConfig('dateformat'))
					),
					"validation", 
					false
				);
				return false;
			}
		}
		if($max = $this->getConfig('max')) {
			// ISO or strtotime()
			if(Zend_Date::isDate($min, $this->getConfig('datavalueformat'))) {
				$maxDate = new Zend_Date($max, $this->getConfig('datavalueformat'));
			} else {
				$maxDate = new Zend_Date(strftime('%F', strtotime($max)), $this->getConfig('datavalueformat'));
			}
			if(!$this->valueObj->isEarlier($maxDate) && !$this->valueObj->equals($maxDate)) {
				$validator->validationError(
					$this->name, 
					sprintf(
						_t('DateField.VALIDDATEMAXDATE', "Your date has to be older or matching the maximum allowed date (%s)"), 
						$maxDate->toString($this->getConfig('dateformat'))
					),
					"validation", 
					false
				);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @return string
	 */
	function getLocale() {
		return $this->locale;
	}
	
	/**
	 * Caution: Will not update the 'dateformat' config value.
	 * 
	 * @param String $locale
	 */
	function setLocale($locale) {
		$this->locale = $locale;
	}
	
	/**
	 * @param string $name
	 * @param mixed $val
	 */
	function setConfig($name, $val) {
		switch($name) {
			case 'min':
				$format = $this->getConfig('datavalueformat');
				if($val && !Zend_Date::isDate($val, $format) && !strtotime($val)) {
					throw new InvalidArgumentException('Date "%s" is not a valid minimum date format (%s) or strtotime() argument', $val, $format);
				}
				break;
			case 'max':
				$format = $this->getConfig('datavalueformat');
				if($val && !Zend_Date::isDate($val, $format) && !strtotime($val)) {
					throw new InvalidArgumentException('Date "%s" is not a valid maximum date format (%s) or strtotime() argument', $val, $format);
				}
				break;
		}
		
		$this->config[$name] = $val;
	}
	
	/**
	 * @param String $name
	 * @return mixed
	 */
	function getConfig($name) {
		return $this->config[$name];
	}
}

/**
 * Disabled version of {@link DateField}.
 * Allows dates to be represented in a form, by showing in a user friendly format, eg, dd/mm/yyyy.
 * @package forms
 * @subpackage fields-datetime
 */
class DateField_Disabled extends DateField {
	
	protected $disabled = true;
		
	function Field() {
		if($this->valueObj) {
			if($this->valueObj->isToday()) {
				$val = Convert::raw2xml($this->valueObj->toString($this->getConfig('dateformat')) . ' ('._t('DateField.TODAY','today').')');
			} else {
				$df = new Date($this->name);
				$df->setValue($this->dataValue());
				$val = Convert::raw2xml($this->valueObj->toString($this->getConfig('dateformat')) . ', ' . $df->Ago());
			}
		} else {
			$val = '<i>('._t('DateField.NOTSET', 'not set').')</i>';
		}
		
		return "<span class=\"readonly\" id=\"" . $this->id() . "\">$val</span>";
	}
	
	function Type() { 
		return "date_disabled readonly";
	}
	
	function jsValidation() {
		return null;
	}
	
	function validate($validator) {
		return true;	
	}
}

?>