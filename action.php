<?php

if (!defined('DOKU_INC')) die();

class action_plugin_pagequery extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, '_purgecache');
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


    /**
     * Check for pages changes and eventually purge cache.
     *
     * @author Samuele Tognini <samuele@samuele.netsons.org>
     *
     * @param Doku_Event $event
     * @param mixed      $param not defined
     */
    function _purgecache(&$event, $param) {
        global $ID;
        global $conf;
        /** @var cache_parser $cache */
        $cache = &$event->data;

        if(!isset($cache->page)) return;
        //purge only xhtml cache
        if($cache->mode != "xhtml") return;
        //Check if it is an pagequery page
        if(!p_get_metadata($ID, 'pagequery')) return;
        $aclcache = $this->getConf('aclcache');
        if($conf['useacl']) {
            $newkey = false;
            if($aclcache == 'user') {
                //Cache per user
                if($_SERVER['REMOTE_USER']) $newkey = $_SERVER['REMOTE_USER'];
            } else if($aclcache == 'groups') {
                //Cache per groups
                global $INFO;
                if($INFO['userinfo']['grps']) $newkey = implode('#', $INFO['userinfo']['grps']);
            }
            if($newkey) {
                $cache->key .= "#".$newkey;
                $cache->cache = getCacheName($cache->key, $cache->ext);
            }
        }
        //Check if a page is more recent than purgefile.
        if(@filemtime($cache->cache) < @filemtime($conf['cachedir'].'/purgefile')) {
            $event->preventDefault();
            $event->stopPropagation();
            $event->result = false;
        }
    }
}

