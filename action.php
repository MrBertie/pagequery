<?php

if (!defined('DOKU_INC')) die();

class action_plugin_pagequery extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
    }


    function insert_button(& $event, $param) {
        $event->data[] = array (
            'type'  => 'dialog',
            'title' => $this->getLang('pagequery'),
            'icon'  => '../../plugins/pagequery/images/pagequery.png',
            'html'  => $this->_cheatsheet(),
            'block' => false
        );
    }


    private function _cheatsheet() {
        $list = file(DOKU_PLUGIN . 'pagequery/res/toolbar', FILE_IGNORE_NEW_LINES);
        $text = '<div id="pq-dialog" title="PageQuery Cheatsheet" style="font-size:75%;">' . PHP_EOL;
        foreach($list as $line) {
            $tab = '';
            $item = explode("\t", $line, 2);
            if (substr($item[0], 0, 1) == '-') {
                $item[0] = substr($item[0], 2);
                $tab = "&nbsp;&nbsp;&nbsp;&nbsp;";
            }
            $text .= $tab . '<b>' . $item[0] . '</b>&nbsp; ' . $item[1] . '</br>' . PHP_EOL;
        }
        $text .= '</div>' . PHP_EOL;

        return $text;
    }
}

