function yms_showpage(page)
{
  load_component(['fadefilter', 'jquery', 'jquery-ui']);
  yms_destroy_float();
  
  if ( aclDisableTransitionFX )
    jQuery.fx.off = true;
  
  darken(true, 70, 'ymsmask');
  
  $('body').append('<div id="yms-float-wrapper"><div id="yms-float-body"><div id="yms-float-inner"><div class="yms-float-spinner theme-selector-spinner"></div></div></div></div>');
  $('#yms-float-wrapper')
    .css('top', String(getScrollOffset()) + 'px')
    .css('left', 0)
    .css('z-index', String( getHighestZ() + 20 ));
    
  ajaxGet(makeUrlNS('Special', 'YMS/' + page, 'noheaders'), function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var fade_time = aclDisableTransitionFX ? 0 : 500;
        $('#yms-float-body').animate({ width: 728, height: getHeight() - 200 });
        $('.yms-float-spinner').fadeOut(fade_time, function()
          {
            $('#yms-float-inner')
              .css('text-align', 'left')
              .html(ajax.responseText)
              .append('<div class="yms-float-closer"><a class="abutton abutton_green" href="#">' + $lang.get('etc_cancel') + '</a></div>');
            $('.yms-float-closer a').click(function()
              {
                yms_destroy_float();
                return false;
              });
            $('#yms-float-inner form').submit(yms_ajax_submit);
            // focus first element in the form
            $('#yms-float-inner input:first').focus();
          });
      }
      else if ( ajax.readyState == 4 && ajax.status != 200 )
      {
        yms_destroy_float();
      }
    });
}

function yms_ajax_submit(me)
{
  var form = this.tagName == 'FORM' ? this : findParentForm(me);
  var whitey = whiteOutElement(form);
  
  var qs = '';
  $('input, select, textarea', form).each(function(i, e)
    {
      var name = $(e).attr('name');
      var val = $(e).val();
      
      if ( $(e).attr('type') == 'checkbox' )
      {
        if ( !$(e).attr('checked') )
          return;
        val = 'on';
      }
      else if ( $(e).attr('type') == 'radio' )
      {
        if ( !$(e).attr('checked') )
          return;
      }
      
      if ( name )
        qs += '&' + name + '=' + ajaxEscape(val);
    });
  qs = qs.replace(/^&/, '');
  var submit_uri = $(form).attr('action');
  var separator = (/\?/).test(submit_uri) ? '&' : '?';
  submit_uri += separator + 'ajax&noheaders';
  
  var to_self = $(form).hasClass('submit_to_self');
  ajaxPost(submit_uri, qs, function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText) + '';
        if ( to_self )
        {
          // form submits to the dynamic frame, just set HTML and die
          $(whitey).remove();
          $('#yms-float-inner')
            .html(response)
            .append('<div class="yms-float-closer"><a class="abutton abutton_green" href="#">' + $lang.get('etc_cancel') + '</a></div>');
          
          $('.yms-float-closer a').click(function()
            {
              yms_destroy_float();
              return false;
            });
          $('#yms-float-inner form').submit(yms_ajax_submit);
          // focus first element in the form
          $('#yms-float-inner input:first').focus();
            
          return true;
        }
        if ( !check_json_response(response) )
        {
          // invalid JSON, gracefully report error
          whiteOutReportFailure(whitey);
          setTimeout(function()
            {
              yms_destroy_float();
              handle_invalid_json(response);
            }, 1250);
          return false;
        }
        response = parseJSON(response);
        if ( response.mode == 'success' )
        {
          $('#yms-messages').html('<div class="info-box">' + $lang.get(response.message) + '</div>');
          yms_refresh_keylist();
          whiteOutReportSuccess(whitey);
          setTimeout('yms_destroy_float();', 1250);
        }
        else if ( response.mode == 'error' )
        {
          whiteOutReportFailure(whitey);
          setTimeout(function()
            {
              $('#yms-float-inner .error-box').remove();
              $('#yms-float-inner').prepend('<div class="error-box">' + $lang.get(response.error) + '</div>');
            }, 1250);
        }
      }
      else if ( ajax.readyState == 4 && ajax.status != 200 )
      {
        whiteOutReportFailure(whitey);
        setTimeout('yms_destroy_float();', 1250);
      }
    });
  return false;
}

function yms_destroy_float()
{
  var fade_time = aclDisableTransitionFX ? 0 : 500;
  $('#yms-float-wrapper').fadeOut(fade_time, function()
    {
      $('#yms-float-wrapper').remove();
      enlighten(aclDisableTransitionFX, 'ymsmask');
    });
}

function yms_refresh_keylist()
{
  $('#yms-keylist').empty();
  ajaxGet(makeUrlNS('Special', 'YMS', 'noheaders&ajax'), function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        $('#yms-keylist').html(ajax.responseText);
      }
    });
}

function yms_toggle_state(span, id)
{
  // touch to put into closure scope
  void(span);
  var whitey = whiteOutElement(span.parentNode);
  var newstate = $(span).hasClass('yms-disabled') ? 'active' : 'inactive';
  ajaxPost(makeUrlNS('Special', 'YMS/AjaxToggleState'), 'id=' + id + '&state=' + newstate, function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( ajax.responseText != 'ok' )
        {
          whiteOutReportFailure(whitey);
          return false;
        }
        
        whiteOutReportSuccess(whitey);
        var newclass = newstate == 'active' ? 'yms-enabled' : 'yms-disabled';
        var newtext  = newstate == 'active' ? 'yms_state_active' : 'yms_state_inactive';
        $(span).removeClass('yms-disabled').removeClass('yms-enabled').addClass(newclass).text($lang.get(newtext));
      }
    });
}

function yms_show_notes(link, id)
{
  // show the box
  var offset = $(link.parentNode).offset();
  var height = $(link.parentNode).outerHeight();
  var top = offset.top + height;
  var left = ( offset.left + $(link.parentNode).outerWidth() ) - 420;
  var box = document.createElement('div');
  $(box)
    .css('background-color', 'white')
    .css('color', '#202020')
    .css('padding', 10)
    .css('position', 'absolute')
    .css('width', 400)
    .css('height', 130)
    .css('top', top)
    .css('left', left)
    .appendTo('body');
    
  box.yk_id = id;
  box.link = link;
  
  var whitey = whiteOutElement(box);
  ajaxPost(makeUrlNS('Special', 'YMS/AjaxNotes'), 'get=' + id, function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        $(whitey).remove();
        $(box).html('<p><textarea style="width: 400px; height: 80px;"></textarea></p><p><a class="abutton abutton_green save" style="font-weight: bold;"></a> <a class="abutton cancel"></a></p>');
        $('textarea', box).val(ajax.responseText);
        $('a.save', box).text($lang.get('etc_save_changes')).attr('href', '#').click(function()
          {
            var box = this.parentNode.parentNode;
            var text = $('textarea:first', box).val();
            yms_save_note(box, box.yk_id, text, box.link);
            return false;
          });
        
        $('a.cancel', box).text($lang.get('etc_cancel')).attr('href', '#').click(function()
          {
            $(this.parentNode.parentNode).remove();
            return false;
          });
      }
    });
}

function yms_save_note(box, id, text, link)
{
  var whitey = whiteOutElement(box);
  void(link);
  ajaxPost(makeUrlNS('Special', 'YMS/AjaxNotes'), 'save=' + id + '&note=' + ajaxEscape(text), function(ajax)
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( ajax.responseText != 'ok' )
        {
          whiteOutReportFailure(whitey);
          return false;
        }
        
        var newsrc   = text == '' ? scriptPath + '/plugins/yms/icons/note_delete.png' : scriptPath + '/plugins/yms/icons/note.png';
        var newtitle = text == '' ? $lang.get('yms_btn_note_create') : $lang.get('yms_btn_note_view');
        $(link).attr('title', newtitle);
        $('img:first', link).attr('src', newsrc);
        
        // remove any existing text
        while ( link.nextSibling )
          link.parentNode.removeChild(link.nextSibling);
        
        // insert text
        if ( text != '' )
        {
          var summary = ' ' + (text.length > 15 ? text.substr(0, 12) + '...' : text);
          link.parentNode.appendChild(document.createTextNode(summary));
        }
        
        whiteOutReportSuccess(whitey);
        setTimeout(function()
          {
            $(box).remove();
          }, 1250);
      }
    });
}
