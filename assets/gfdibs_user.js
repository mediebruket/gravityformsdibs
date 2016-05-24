jQuery(document).ready(function(){
  jQuery(document).bind('gform_confirmation_loaded', function(){
    if ( jQuery('#send_to_dibs').length ){
      document.dibs_post_form.submit();
    }
	});
}
);