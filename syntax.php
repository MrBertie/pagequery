<?php
/**
 * PageQuery Plugin: search for and list pages, sorted/grouped by name, date, creator, etc
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author	   Symon Bent <hendrybadao@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC . 'inc/fulltext.php');
require_once(DOKU_PLUGIN . 'pagequery/inc/pagequery.php');



class syntax_plugin_pagequery extends DokuWiki_Syntax_Plugin {

    const MAX_COLS = 12;


    function getType() {
        return 'substition';
    }


    function getPType() {
        return 'block';
    }


    function getSort() {
        return 98;
    }


    function connectTo($mode) {
        // this regex allows multi-line syntax for easier composition/reading
        $this->Lexer->addSpecialPattern('\{\{pagequery>(?m).*?(?-m)\}\}', $mode, 'plugin_pagequery');
    }


    /**
     * Parses all the pagequery options:
     * Insert the pagequery markup wherever you want your list to appear. E.g:
     *
     *   {{pagequery>}}
     *
     *   {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=??;cols=?;spelldate;proper}}
     *
     * @link https://www.dokuwiki.org/plugin:pagequery See PageQuery page for full details
     */
    function handle($match, $state, $pos, &$handler) {

        $opt = array();
        $match = substr($match, 12, -2); // strip markup "{{pagequery>...}}"
        $params = explode(';', $match);
        // remove any trailing spaces due to multi-line syntax
        $params = array_map('trim', $params);

        $opt['query'] = $params[0];

        // establish some basic option defaults
        $opt['border'] = 'none';        // show borders around entire list and/or between columns
        $opt['bullet'] = 'none';        // bullet style for list items
        $opt['casesort'] = false;       // allow case sorting
        $opt['cols'] = 1;               // number of displayed columns (fixed for table layout, max for column layout
        $opt['dformat'] = "%d %b %Y";   // general display date format
        $opt['display'] = 'name';       // how page links should be displayed
        $opt['filter'] = array();       // filtering by metadata prior to sorting
        $opt['fontsize'] = '';          // base fontsize of pagequery; best to use %
        $opt['fullregex'] = false;      // power-user regex search option--file name only
        $opt['fulltext'] = false;       // search full-text; including file contents
        $opt['group'] = false;          // group the results based on sort headings
        $opt['hidejump'] = false;       // hide the jump to top link
        $opt['hidemsg'] = false;        // hide any error messages
        $opt['hidestart'] = false;      // hide start pages
        $opt['label'] = '';             // label to put at top of the list
        $opt['layout'] = 'table';       // html layout type: table (1 col = div only) or columns (html 5 only)
        $opt['limit'] = 0;              // limit results to certain number
        $opt['maxns'] = 0;              // max number of namespaces to display (i.e. ns depth)
        $opt['natsort'] = false;        // allow natural case sorting
        $opt['proper'] = 'none';        // display file names in Proper Case
        $opt['showcount'] = false;      // show the count of links found
        $opt['snippet'] = array('type' => 'none', 'count' => 0, 'extent' => '');  // show content snippets/abstracts
        $opt['sort'] = array();         // sort by various headings
        $opt['spelldate'] = false;      // spell out date headings in words where possible
        $opt['underline'] = false;      // faint underline below each link for clarity

        foreach ($params as $param) {
            list($option, $value) = explode('=', $param);
            switch ($option) {
                case 'casesort':
				case 'fullregex':
                case 'fulltext':
                case 'group':
                case 'hidejump':
                case 'hidemsg':
                case 'hidestart':
                case 'natsort':
                case 'showcount':
                case 'underline':
                    $opt[$option] = true;
                    break;
                case 'spelldate':
                    $opt['spelldate'] = true;
                    break;
                case 'limit':
                case 'maxns':
                    $opt[$option] = abs($value);
                    break;
                case 'sort':
                case 'filter':
                    $fields = explode(',', $value);
                    foreach ($fields as $field) {
                        list($key, $expr) = explode(':', $field, 2);
                        // allow for a few common naming differences
                        switch ($key) {
                            case 'pagename':
                                $key = 'name';
                                break;
                            case 'heading':
                            case 'firstheading':
                                $key = 'title';
                                break;
                            case 'pageid':
                            case 'id':
                                $key = 'id';
                                break;
                            case 'contrib':
                                $key = 'contributor';
                                break;
                        }
                        $opt[$option][$key] = $expr;
                    }
                    break;
                case 'proper':
                    switch ($value) {
                        case 'hdr':
                        case 'header':
                        case 'group':
                            $opt['proper'] = 'header';
                            break;
                        case 'name':
                        case 'page':
                            $opt['proper'] = 'name';
                            break;
                        default:
                            $opt['proper'] = 'both';
                    }
                    break;
                case 'cols':
                    $opt['cols'] = ($value > self::MAX_COLS) ? self::MAX_COLS : $value;
                    break;
                case 'border':
                    switch ($value) {
                        case 'none':
                        case 'inside':
                        case 'outside':
                        case 'both':
                            $opt['border'] = $value;
                            break;
                        default:
                            $opt['border'] = 'both';
                    }
                    break;
                case 'snippet':
                    $default = 'tooltip';
                    if (empty($value)) {
                        $opt['snippet']['type'] = $default;
                        break;
                    } else {
                        $options = explode(',', $value);
                        $type = ( ! empty($options[0])) ? $options[0] : $opt['snippet']['type'];
                        $count = ( ! empty($options[1])) ? $options[1] : $opt['snippet']['count'];
                        $extent = ( ! empty($options[2])) ? $options[2] : $opt['snippet']['extent'];

                        $valid = array('none', 'tooltip', 'inline', 'plain', 'quoted');
                        if ( ! in_array($type, $valid)) {
                            $type = $default;  // empty snippet type => tooltip
                        }
                        $opt['snippet'] = array('type' => $type, 'count' => $count, 'extent' => $extent);
                        break;
                    }
                case 'label':
                case 'bullet':
                    $opt[$option] = $value;
                    break;
                case 'display':
                    switch ($value) {
                        case 'name':
                            $opt['display'] = 'name';
                            break;
                        case 'title':
                        case 'heading':
                        case 'firstheading':
                            $opt['display'] = 'title';
                            break;
                        case 'pageid':
                        case 'id':
                            $opt['display'] = 'id';
                            break;
                        default:
                            $opt['display'] = $value;
                    }
                    break;
                case 'layout':
                    if ($value != 'table' && $value != 'column') {
                        $value = 'table';
                    }
                    $opt['layout'] = $value;
                    break;
                case 'fontsize':
                    if ( ! empty($value)) {
                        $opt['fontsize'] = $value;
                    }
                    break;
            }
        }
        return $opt;
	}


    function render($mode, &$renderer, $opt) {

        if ( ! PHP_MAJOR_VERSION >= 5 && ! PHP_MINOR_VERSION >= 3) {
            $renderer->doc .= "You must have PHP 5.3 or greater to use this pagequery plugin.  Please upgrade PHP or use an older version of the plugin";
            return false;
        }

        $incl_ns = array();
        $excl_ns = array();
        $sort_opts = array();
        $group_opts = array();
        $message = '';

        // we pass $this just to get access to the ->getLang() function...
        $pq = new PageQuery($this);
        $query = $opt['query'];

        if ($mode == 'xhtml') {

            // first get a raw list of matching results
            if ($opt['fulltext']) {
                // full text (Dokuwiki style) searching
                $results = $pq->page_search($opt['query']);

            } else {
                // search by page id only
                if ($opt['fullregex']) {
                    // allow for raw regex mode, for power users, this searches the full page id (incl. namespaces)
                    $pageonly = false;
                } else {
                    list($query, $incl_ns, $excl_ns) = $pq->parse_ns_query($query);
                    $pageonly = true;
                }

                // Allow for a lazy man's option!
                if ($query == '*') {
                    $query = '.*';
                }

                $results = $pq->page_lookup($query, $pageonly, $incl_ns, $excl_ns, $opt['hidestart'], $opt['maxns']);
            }

            $empty = false;
            if ($results === false) {
                $empty = true;
                $message = $this->getLang('regex_error');

            } elseif ( ! empty($results)) {

                // *** this section is where the essential pagequery functionality happens... ***

                // prepare the necessary sorting arrays, as per users options
                list($sort_array, $sort_opts, $group_opts) = $pq->build_sorting_array($results, $opt);

                // meta data filtering of the list is next
                $sort_array = $pq->filter_meta($sort_array, $opt['filter']);
                if (empty($sort_array)) {
                    $empty = true;
                    $message = $this->getLang("empty_filter");
                }

            } else {
                $empty = true;
            }

            // successful search...
            if ( ! $empty) {

                // now do the sorting
                $pq->msort($sort_array, $sort_opts);

                // limit the result list length if required; this can only be done after sorting!
                if ($opt['limit'] > 0) {
                    $sort_array = array_slice($sort_array, 0, $opt['limit']);
                }

                // do a link count BEFORE grouping (don't want to count headers...)
                $count = count($sort_array);

                // and finally the grouping
                $keys = array('name', 'id', 'title', 'abstract', 'display');
                if ($opt['group']) {
                    $sorted_results = $pq->mgroup($sort_array, $keys, $group_opts);
                } else {
                    $sorted_results = $pq->mgroup($sort_array, $keys);
                }

                // and out to the page
                $renderer->doc .= $pq->render_as_html($opt['layout'], $sorted_results, $opt, $count);

            // no results...
            } else {
                if ( ! $opt['hidemsg']) {
                    $renderer->doc .= $pq->render_as_empty($query, $message);
                }
            }
            return true;
        } else {
            return false;
        }
    }
}

