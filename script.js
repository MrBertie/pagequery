/**
 * Button action for the floating dialog
 *
 * @param  DOMElement btn   Button element to add the action to
 * @param  array      props Associative array of button properties
 * @param  string     edid  ID of the editor textarea
 */
function tb_dialog(btn, props, edid) {
    var list = props['list'];
    var text = '<div id="pq-dialog" title="PageQuery Cheatsheet" style="font-size:75%;">';

    jQuery.each(list, function(index, value) {
        var tab = '';
        var item = value.split("\t", 2);
        if (item[0].indexOf('-') === 0) {
            item[0] = item[0].substring(2);
            tab = "&nbsp;&nbsp;&nbsp;&nbsp;";
        }
        text += tab + '<b>' + item[0] + '</b>&nbsp; ' + item[1] + '</br>';
    });

    text += '</div>';

    jQuery(text).dialog({
        autoOpen: false,
        modal: false,
        width: 475,
        height: 295
    }).dialog("open");
    return false;
}


