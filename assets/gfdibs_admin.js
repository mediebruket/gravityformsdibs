jQuery(document).ready(function(){
  if ( jQuery('#dibs_edit_form').length ){
    jQuery('#gf_dibs_form').change(function(){assignFormFields()});
    jQuery('#gf_dibs_type').change(function(){canCaptureNow()});
    checkForm();
    setFormFields();
  }
});


function setFormFields(){
  generateOptionElements();

  jQuery.each(feed_meta, function(index, value){
    jQuery('#'+index+ ' option[value="'+value+'"]').attr('selected', true);
  });

  canCaptureNow();
}

function canCaptureNow(){
  var dibs_type = jQuery('#gf_dibs_type').val();

  if ( dibs_type == '1' || dibs_type == '2' ){
    jQuery('#capture_now').show();
  }
  else{
    jQuery('#capture_now').hide();
    jQuery('#gf_dibs_capture_now').attr('checked', false);
  }
}


function generateOptionElements(){
  var form_id = jQuery('#gf_dibs_form').val();

  resetSelectElements();

  if ( form_id  ){
    form_index = 'form_'+form_id;

    jQuery.each(form_fields[form_index], function(index, value){
      addFormField( value );
    });
  }
}

function resetSelectElements(){
  jQuery('.form_field option').attr('selected', false);
  jQuery('.custom').remove();
}

function addFormField( value ){
  if ( typeof value.label !== 'undefined' && value.label.length){
    if ( value.inputs != null && value.inputs.length ){
      jQuery.each(value.inputs, function(input_index, input_value){
        jQuery('.form_field').append('<option value="'+input_value.id+'" class="custom">'+input_value.label+'</option>');
      });
    }
    else{
      jQuery('.form_field').append('<option value="'+value.id+'" class="'+value.type+' custom" >'+value.label+'</option>');
    }
  }
}

function assignFormFields(){
  var form_id = jQuery('#gf_dibs_form').val();

  resetSelectElements();

  if ( form_id ){
    form_index = 'form_'+form_id;

    jQuery.each(form_fields[form_index], function(index, value){
        addFormField( value );

        if ( value.type == 'phone' ){
          jQuery('#billingMobile .phone').attr('selected', true);
        }

        if ( value.type == 'email' ){
          jQuery('#billingEmail .email').attr('selected', true);
        }

        if ( value.type == 'total' ){
          jQuery('#amount .total').attr('selected', true);
        }

    });
  }
}

 function checkForm(){

  jQuery('#dibs_edit_form').submit(function(e){

    var post = true;

    if (  !jQuery('#gf_dibs_mode_production').attr('checked') && !jQuery('#gf_dibs_mode_test').attr('checked') ){
      post = false;
    }

    if ( !jQuery('#gf_dibs_type').val().length ){
      post = false;
    }

    if ( !jQuery('#gf_dibs_form').val().length ){
      post = false;
    }

    if ( !post ){
      e.preventDefault();
    }

  });
}


function DeleteSetting(id){
  jQuery("#action_argument").val(id);
  jQuery("#action").val("delete");
  jQuery("#feed_form")[0].submit();
}

