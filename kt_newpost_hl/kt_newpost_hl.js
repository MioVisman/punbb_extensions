$(document).ready(function() {
	$('[id^=forum]').each(function(index) {
    	if ($(this).is('.new')) {
			$('#visit-new').addClass('kt_newpost_hl');
		}
	});
});