function bbnotify_replaceName(paragraph) {
	var textbox = bbnotify_get_textarea();
	var pos = $(textbox).caret();
	var start = textbox.value.substr(0, pos);
	var end = textbox.value.substr(pos);
	var atPos = start.lastIndexOf('@');
	textbox.value = start.substr(0, atPos) + '[notify]' + paragraph.innerHTML + '[/notify]' + end;
	$(textbox).caret(textbox.value.length - end.length);
	bbnotify_hideSuggestion();
}

function bbnotify_hideSuggestion() {
	document.getElementById('bbnotify_suggestions').style.display = 'none';
	bbnotifySelectedItem = -1;
}

function bbnotify_search() {
	var textbox = bbnotify_get_textarea();
	var pos = $(textbox).caret();
	var search = '';
	var isValid = false;
	for (var i = pos-1; i >= 0; i--) {
		var char = textbox.value.charAt(i).toLowerCase();
		if (char.match(/[a-z0-9\-]/)) {
			search = char + search;
		}
		else {
			if (char == '@') {
				// only use @ at beginning of text, not within (like email address)
				if (i == 0 || textbox.value.charAt(i-1).match(/\s/)) {
					isValid = true;
				}
			}
			break;
		}
	}

	if (isValid && search.length > 0) {
		$.getScript(bbnotify_path+'/bbnotify_search.php?fid='+bbnotify_fid+'&search='+search)
			.done(function() {
				bbnotify_showSuggestion(bbnotify_usernames);
			})
			.fail(function(jqxhr, settings, exception) {
				bbnotify_hideSuggestion();
			});
	}
	else {
		bbnotify_hideSuggestion();
	}
}

function bbnotify_showSuggestion(searchResults) {
	if (searchResults.length == 0) {
		bbnotify_hideSuggestion();
		return;
	}

	var text = '';
	for (var i = 0; i < searchResults.length; i++) {
		text += '<p onmousedown="bbnotify_replaceName(this);return false;" onmouseover="bbnotify_selectItem('+i+');" id="bbnotifyp'+i+'">' + searchResults[i] + '</p>';
	}

	var textbox = bbnotify_get_textarea();
	var pos = $(textbox).caret();
	var textboxPosition = $(textbox).offset();
	var caretPosition = getCaretCoordinates(textbox, pos);
	var elem = document.getElementById('bbnotify_suggestions');
	elem.style.top = (caretPosition.top + textboxPosition.top - $(textbox).scrollTop() + 20) + 'px';
	elem.style.left = (caretPosition.left + textboxPosition.left + 10) + 'px';
	elem.innerHTML = text;
	elem.style.display = 'inline-block';
	bbnotify_selectItem(0);
}

function bbnotify_isShowing() {
	return (document.getElementById('bbnotify_suggestions').style.display == 'inline-block');
}

function bbnotify_keyup(evt) {
	bbnotify_disableKeys(evt);
	
	var code = evt.keyCode ? evt.keyCode : evt.which;
    if (code == 27)
		bbnotify_hideSuggestion();
	else if (!bbnotify_isShowing() || (code != 38 && code != 40 && (code != 13 || bbnotifySelectedItem == -1)))
		bbnotify_search();
}

var bbnotifySelectedItem = -1;
function bbnotify_keydown(evt) {
	if (!bbnotify_isShowing()) return;
	
	bbnotify_disableKeys(evt);
	
	var newSelection = bbnotifySelectedItem;
	
	var code = evt.keyCode ? evt.keyCode : evt.which;
    switch (code) {
        case 38:
			// arrow up
            newSelection--;
            break;
        case 40:
			// arrow down
            newSelection++;
            break;
		case 13:
			// enter
			$('#bbnotifyp'+bbnotifySelectedItem).mousedown();
			return;
		default:
			// nothing to do
			return;
    }
	
	var pList = $('#bbnotify_suggestions').find('p');
	if (newSelection < 0) {
		newSelection = pList.length - 1;
	}
	else if (newSelection >= pList.length) {
		newSelection = 0;
	}
	
	bbnotify_selectItem(newSelection);
}

function bbnotify_keypress(evt) {
	bbnotify_disableKeys(evt);
}

function bbnotify_disableKeys(evt) {
	if (!bbnotify_isShowing()) return;
	
	var code = evt.keyCode ? evt.keyCode : evt.which;
    switch (code) {
		case 13: // enter
			if (bbnotifySelectedItem == -1) return;
		case 27: // esc
        case 38: // arrow up
        case 40: // arrow down
			evt.preventDefault();
    }
}

function bbnotify_selectItem(selectedItem) {
	if ($('#bbnotifyp'+bbnotifySelectedItem).length) {
		$('#bbnotifyp'+bbnotifySelectedItem).removeClass('bbnotify-hover');
	}
	
	if ($('#bbnotifyp'+selectedItem).length) {
		$('#bbnotifyp'+selectedItem).addClass('bbnotify-hover');
		bbnotifySelectedItem = selectedItem;
	}
	else {
		bbnotifySelectedItem = -1;
	}
}

function bbnotify_get_textarea() {
	return (document.all) ? document.all.req_message : ((document.getElementById('afocus')!==null) ? (document.getElementById('afocus').req_message) : (document.getElementsByName('req_message')[0]));
}

var bbnotify_initialize = function() {
	var $div = $('<div />').appendTo('body').attr('id', 'bbnotify_suggestions');
	var textbox = bbnotify_get_textarea();
	$(textbox).keyup(bbnotify_keyup);
	$(textbox).keydown(bbnotify_keydown);
	$(textbox).keypress(bbnotify_keypress);
	$(textbox).click(bbnotify_search);
	$(textbox).blur(bbnotify_search);
}

PUNBB.common.addDOMReadyEvent(bbnotify_initialize);