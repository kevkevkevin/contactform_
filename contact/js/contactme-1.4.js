/**
 *
 * ContactMe
 * https://www.21tools.it
 *
 */

var lang;

// Read languages
jQuery.getJSON(cm_lang_path, function(data) {
	lang = data;
});

(function($) {
	// More then one form allowed
	$('.contactMe').each(function(index) {

		var $form        = $(this),
		    $msg         = $form.find('.msg'),
		    $submitBtn	 = $form.find('button[type=submit]');

		// Handle Form Submit
		$form.submit(function(e) {
			// prevent form submit
			e.preventDefault();

			// Prevent double submission by disabling the submit button
			$submitBtn.html($submitBtn.data('sending')).attr('disabled', 'disabled');

			// Hide previous messages
			$msg.fadeOut(0);

			// Validate the Form Data
			var validate = validateForm($form);
			if (!validate.success)
			{
				if (validate.errors.length > 0)
				{
					showMessage($msg, errorsArrayToHtml(validate.errors), 'error');
			    	// Re-enable submit button
			    	$submitBtn.html($submitBtn.data('text')).removeAttr('disabled');
			    	return null;
			    }
			}

			if (typeof(grecaptcha) != 'undefined' && $form.find('.re-captcha').length > 0 && $form.find('.re-captcha').hasClass('invisible'))
			{
				if(!grecaptcha.execute($form.find('.re-captcha').data('grecaptcha')))
				{
					// Re-enable submit button
					$submitBtn.html($submitBtn.data('text')).removeAttr('disabled');
					return null;
				}
			}
			else {
				// If reCAPTCHA V2 is enabled, check also if is checked
				if (typeof(grecaptcha) != 'undefined' && $form.find('.re-captcha').length > 0 && !$form.find('.re-captcha').hasClass('invisible'))
				{
					if (grecaptcha.getResponse($form.find('.re-captcha').data('grecaptcha')) == "")
					{
						showMessage($msg, lang.recaptcha.empty, 'error');
						// Re-enable submit button
						$submitBtn.html($submitBtn.data('text')).removeAttr('disabled');
						return null;
					}
				}

				submitAjaxForm($form);
			}
		});

		$submitBtn.attr('data-text', $submitBtn.html());
	});

	var count = 0;
	$('.contactMe .form-row.file').each(function(index) {
		$(this).find('input').attr('id', 'cm_upload_' + count);
		$(this).find('label').attr('for', 'cm_upload_' + count);

		$(this).on('change', function(e)
		{
			var fileName = '',
				labelHtml = '<i></i>' + $(e.target).attr('placeholder');

			if(e.target.files && e.target.files.length > 1)
				fileName = (e.target.getAttribute('data-multiple-label') || '').replace('{count}', e.target.files.length);
			else if(e.target.value)
				fileName = e.target.value.split('\\').pop();

			if(fileName)
				$(e.target).find('+ label').html('<i></i>' + fileName).addClass('selected');
			else
				$(e.target).find('+ label').html(labelHtml).removeClass('selected');
		});

		count++;
	});

	// convert all select dropdowns into "Select2" dropdowns
	if(typeof($.fn.select2) != 'undefined') {
		$('.contactMe select').each(function(index) {
			var placeholder = $(this).attr('placeholder');
			$(this).select2({
				minimumResultsForSearch:-1,
				placeholder:placeholder,
				language:cm_dropdown_lang,
				allowClear:true
			});
		});
	}

	if(typeof($.fn.datepicker) != 'undefined') {
		$('.contactMe .cm-date').attr('readonly', 'true').datepicker({
			format: 'dd-mm-yy',
		    maxViewMode: 2,
		    clearBtn: true,
		    language: cm_datepicker_lang,
		    orientation: 'bottom auto',
		    keyboardNavigation: false,
		    autoclose: true,
		    todayHighlight: true,
		    enableOnReadonly: true
		}).on('hide', function(e) {
	        $(this).blur();
	    });

	    $('.contactMe .cm-date[data-idconnecteddateend], .contactMe .cm-date[data-idconnecteddatestart]').on('changeDate', function(e) {
	        updateConnectedDatePickers($(this));
	    });
	}
	if(typeof($.fn.timepicker) != 'undefined') {
		$('.contactMe .cm-time').timepicker({
			timeFormat:'h:i A',
			step:30,
			scrollDefault:'now',
			disableTouchKeyboard:true,
			stopScrollPropagation:true
		});
	}
}(jQuery));

/* Functions */

/* Update Connected Date Pickers Start->End */
function updateConnectedDatePickers(obj) {
	var startDate = null;
	var endDate = null;
	if(typeof obj.data('idconnecteddateend') != 'undefined') {
		startDate = obj;
		endDate = jQuery('#' + obj.data('idconnecteddateend'));
		
		if(startDate.val()) {
	    	var formattedStartDate = new Date(startDate.datepicker("getDate"));
			var d = formattedStartDate.getDate();
			if(d < 10) {
				d = "0" + d;
			}
			var m =  formattedStartDate.getMonth() + 1;
			if(m < 10) {
				m = "0" + m;
			}
			var y = formattedStartDate.getFullYear();

	        var tmpEndDate = endDate.datepicker("getDate");
	        endDate.datepicker("setStartDate", d + "/" + m + "/" + y);
	        endDate.datepicker("update", tmpEndDate);

	        if(endDate.val() && formattedStartDate > endDate.datepicker("getDate")) {
	        	endDate.datepicker("update", "");
	        }
	    }
	    else {
	    	var tmpEndDate = endDate.datepicker("getDate");
	    	endDate.datepicker("setStartDate", "");
	    	endDate.datepicker("update", tmpEndDate);
	    }
	}
	else {
		startDate = jQuery('#' + obj.data('idconnecteddatestart'));
		endDate = obj;
	}
}

/* Validate a form */
function validateForm($form)
{
	var $msg = $form.find('.msg'),
		errors = [],
		success = true;

	// Loop through fields
	$form.find('.field').each(function(index) {
		var err = validateField(jQuery(this));
		if(err != null) { errors.push(err); }
	});

	// Check if there're errors
	if (errors.length > 0) {
		success = false;
	}

	return {success:success, errors:errors};
}

/* Validate a single field */
function validateField($field)
{
	var type = $field.prop('type'),
	    displayName = $field.data('displayname'),
	    value  = $field.val(),
	    msg = null;

	// Check if is required
	if($field.prop('required') && $field.val() === '') {
		msg = paramsIntoString(lang.required_field, [displayName]);
		return msg;
	}
	else if($field.val() === '') {
		return null;
	}

	// Check the type
	switch(type) {
		case 'email':
			var atIndex = value.indexOf("@"),
			    dotIndex = value.lastIndexOf(".");

			if (atIndex < 1 || dotIndex < 1 || dotIndex < atIndex || dotIndex >= value.length ) {
				msg = paramsIntoString(lang.email_invalid, [displayName]);
				return msg;
			}
			break;
		case 'number':
		case 'tel':
			if (!jQuery.isNumeric(value)) {
				msg = paramsIntoString(lang.number_invalid, [displayName]);
				return msg;
			}
			break;
		case 'file':
			if (typeof $field.data('allowedtypes') != 'undefined' && $field[0].files.length > 0) {
				var arrTypes = jQuery.map($field.data('allowedtypes').split(','), function(value){
				  return value.trim().toLowerCase();
				});
				jQuery.each($field[0].files, function(index, value){
					var tmpType = value.name.trim().toLowerCase().split('.').pop();
					if (jQuery.inArray(tmpType, arrTypes) == -1) {
						msg = paramsIntoString(lang.file.type, [value.name]);
						return;
					}
				});
				if (msg != null) {
					return msg;
				}
			}
			break;
	}

	return null;
}

/* Replace params into string */
function paramsIntoString(string, params)
{
	var i;
	for (i = 0; i < params.length; i++) {
		string = string.replace("{{" + (i+1) + "}}", params[i]);
	}
	return string;
}

/* Create html from errors array */
function errorsArrayToHtml(errors)
{
	var resultHtml = "";

	for (var i = 0; i < errors.length; i++) {
		resultHtml += errors[i] + "<br />";
	}

	return resultHtml;
}

/* Show Form Messages */
function showMessage($msg, html, type)
{
	$msg.html(html).removeClass('error success').addClass(type).fadeIn(400);
}

/* Reset a Form */
function resetForm($form, time)
{
	var time_anim = time > 0 ? 400 : 0;

	$form[0].reset();
	if(typeof(jQuery.fn.select2) != 'undefined') {
		$form.find('select').select2('val', {});
	}
	$form.find('.form-row.file input').trigger('change');
	if(typeof(grecaptcha) != 'undefined' && $form.find('.re-captcha').length > 0) {
		grecaptcha.reset($form.find('.re-captcha').data('grecaptcha'));
	}
	setTimeout(function() {
		$form.find('.msg').fadeOut(time_anim).html('').removeClass('error success');
	}, time);
}

/* Google reCAPTCHA functions  */
function initRecaptchas()
{
	jQuery('.contactMe .re-captcha').each(function(index) {
		var thisElem = jQuery(this)[0];
		var sitekey = jQuery(this).data('sitekey');

		if(jQuery(this).hasClass('invisible')) {
	        jQuery(this).data('grecaptcha',
				grecaptcha.render(thisElem, {
	        		'sitekey': sitekey,
	        		'badge': 'inline',
	        		'size': 'invisible',
	        		'callback': 'callbackRecaptcha'
	        	})
	        );
	    }
	    else {
			jQuery(this).data('grecaptcha',
				grecaptcha.render(thisElem, {
	        		'sitekey': sitekey,
	        		'theme': 'light',
	        		'size': 'normal'
	        	})
	        );
	    }
	});
}
function callbackRecaptcha(token)
{
	var $recaptcha = null;
	jQuery('.g-recaptcha-response').each(function(index) {
		if(jQuery(this).val() == token) {
			$recaptcha = jQuery(this);
		}
		return ($recaptcha == null);
	});

	if($recaptcha != null) {
		submitAjaxForm($recaptcha.closest('form'));
	}
}

/* Submit the Form */
function submitAjaxForm($form)
{
	var $msg 		 = $form.find('.msg'),
	    $submitBtn	 = $form.find('button[type=submit]'),
	    submitBtnTxt = $submitBtn.data('text'),
	    action 		 = $form.prop('action'),
		formData;

	// Prevent double submission by disabling the submit button
	$submitBtn.html($submitBtn.data('sending')).attr('disabled', 'disabled');

	formData = new FormData($form[0]);
	// Send informations
	jQuery.ajax({
		url: action,
		type: 'POST',
		dataType: 'json',
		data: formData,
		processData: false,
		contentType: false,
		success: function(data)
        {
			if(data.errors) {
				// Show errors
				showMessage($msg, errorsArrayToHtml(data.errors), 'error');
	            // Re-enable submit button
				$submitBtn.html(submitBtnTxt).removeAttr('disabled');
				return null;
			}
			else if(data.success) {
				// Show success message
				showMessage($msg, data.success, 'success');
				resetForm($form, 6000);
				// Re-enable submit button
				$submitBtn.html(submitBtnTxt).removeAttr('disabled');
			}
			else if(data.redirectUrl) {
				window.location.href = data.redirectUrl;
			}
			else {
				window.location.reload();
			}
        },
        error: function(data)
        {
        	// Something went wrong
			showMessage($msg, lang.something_wrong, 'error');
			if(typeof(grecaptcha) != 'undefined' && $form.find('.re-captcha').length > 0) {
				grecaptcha.reset($form.find('.re-captcha').data('grecaptcha'));
			}
			// Re-enable submit button
			$submitBtn.html(submitBtnTxt).removeAttr('disabled');
        }
	});
}
