<?php

if (!defined('DOKU_INC')) die();

class action_plugin_pagequery extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers
     */
    function register(&$controller) {
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
    }


    function insert_button(& $event, $param) {
        $event->data[] = array (
            'type'   => 'dialog',
            'title'  => $this->getLang('pagequery'),
            'icon'   => '../../plugins/pagequery/images/pagequery.png',
            'list'   => $this->_syntax_list(),
            'block'  => false
        );
    }


    private function _syntax_list() {
        $list = file(DOKU_PLUGIN . 'pagequery/toolbar', FILE_IGNORE_NEW_LINES);
        return $list;
    }
}

