/**
 * Button action for the floating dialog
 *
 * @param  DOMElement btn   Button element to add the action to
 * @param  array      props Associative array of button properties
 * @param  string     edid  ID of the editor textarea
 */
function tb_dialog(btn, props, edid) {
    var content = props['html'];

    jQuery(content).dialog({
        autoOpen: false,
        modal: false,
        width: 475,
        height: 295
    }).dialog("open");
    return false;
}


