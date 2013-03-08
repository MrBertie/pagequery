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
		$this->Lexer->addSpecialPattern('\{\{pagequery>.*?\}\}', $mode, 'plugin_pagequery');
	}


    /**
     * Parses all the pagequery options:
     * Insert the pagequery markup wherever you want your list to appear. E.g:
     *
     *   {{pagequery>}}
     *
     *   {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=??;cols=?;inwords;proper}}
     *
     * @link https://www.dokuwiki.org/plugin:pagequery See PageQuery page for full details
     */
	function handle($match, $state, $pos, &$handler) {

        $opt = array();

		$match = substr($match, 12, -2); // strip markup "{{pagequery>...}}"
		$params = explode(';', $match);
        $opt['query'] = $params[0];

        // establish some basic option defaults
        $opt['sort'] = array();         // sort by various headings
        $opt['filter'] = array();       // filtering by metadata prior to sorting
        $opt['fulltext'] = false;       // search full-text; including file contents
        $opt['fullregex'] = false;      // power-user regex search option--file name only
        $opt['group'] = false;          // group the results based on sort headings
        $opt['limit'] = 0;              // limit results to certain number
        $opt['maxns'] = 0;              // max number of namespaces to display (i.e. ns depth)
        $opt['hidestart'] = false;      // hide start pages
        $opt['spelldate'] = false;      // spell out date headings in words where possible
        $opt['cols'] = 1;               // number of displayed columns (fixed for table layout, max for column layout
        $opt['proper'] = 'none';        // display file names in Proper Case
        $opt['border'] = 'none';        // show borders around entire list and/or between columns
        $opt['snippet'] = array('type' => 'none');  // show content snippets/abstracts
        $opt['display'] = 'name';       // how page links should be displayed
        $opt['casesort'] = false;       // allow case sorting
        $opt['natsort'] = false;        // allow natural case sorting
        $opt['underline'] = false;      // faint underline below each link for clarity
        $opt['hidemsg'] = false;        // hide any error messages
        $opt['label'] = '';             // label to put at top of the list
        $opt['showcount'] = false;      // show the count of links found
        $opt['hidejump'] = false;       // hide the jump to top link
        $opt['dformat'] = "%d %b %Y";   // general dislay date format
        $opt['layout'] = 'table';       // html layout type: table (1 col = div only) or columns (html 5 only)

        foreach ($params as $param) {
            list($option, $value) = explode('=', $param);
            switch ($option) {
                case 'fulltext':
				case 'fullregex':
                case 'group':
                case 'hidestart':
                case 'casesort':
                case 'natsort':
                case 'underline':
                case 'hidemsg':
                case 'hidejump':
                case 'showcount':
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
                        list($key, $expr) = explode(':', $field);
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
                    $options = explode(',', $value);
                    $type = ( ! empty($options[0])) ? $options[0] : 'tooltip';
                    $valid = array('none', 'tooltip', 'inline', 'plain', 'quoted');
                    if ( ! in_array($type, $valid)) $type = 'tooltip';  // always valid!
                    $count = ( ! empty($options[1])) ? $options[1] : 0;
                    $extent = ( ! empty($options[2])) ? $options[2] : '';
                    $opt['snippet'] = array('type' => $type, 'count' => $count, 'extent' => $extent);
                    break;
                case 'label':
                    $opt['label'] = $value;
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
            }
        }
		return $opt;
	}


    function render($mode, &$renderer, $opt) {

        if ( ! PHP_MAJOR_VERSION >= 5 && ! PHP_MINOR_VERSION >= 3) {
            $renderer->doc .= "You must have PHP 5.3 or greater to use this pagequery plugin.  Please upgrade PHP or use an older version of the plugin";
            return false;
        }

        $query = $opt['query'];

        $incl_ns = array();
        $excl_ns = array();

        if ($mode == 'xhtml') {

            // first get a raw list of matching results
            if ($opt['fulltext']) {
                // full text (Dokuwiki style) searching
                $results = array_keys(ft_pageSearch($opt['query'], $highlight));

            } else {
                // search by page id only
                if ($opt['fullregex']) {
                    // allow for raw regex mode, for power users, this searches the full page id (incl. namespaces)
                    $pageonly = false;
                } else {
                    list($query, $incl_ns, $excl_ns) = $this->_parse_ns_query($query);
                    $pageonly = true;
                }

                if ($query == '*') {
                    $query = '.*';   // a lazy man's option!
                }

                $results = $this->_page_lookup($query, $pageonly, $incl_ns, $excl_ns, $opt['hidestart'], $opt['maxns']);
            }

            if ($results === false) {
                $empty = true;
                $message = $this->getLang('regex_error');

            } elseif ( ! empty($results)) {
                // *** this section is where the essential pagequery functionality happens... ***
                //
                // prepare the necessary sorting arrays, as per users options
                $get_abstract = ($opt['snippet']['type'] != 'none');
                list($sort_array, $sort_opts, $group_opts) = $this->_build_sorting_array($results, $get_abstract, $opt);

                // meta data filtering of the list is next
                $sort_array = $this->_filter_meta($sort_array, $opt['filter']);
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
                $this->msort($sort_array, $sort_opts);
                // limit the result list length if required; this can only be done after sorting!
                if ($opt['limit'] > 0) {
                    $count = $opt['limit'];
                    $sort_array = array_slice($sort_array, 0, $count);
                } else {
                    $count = count($sort_array);
                }

                // and finally the grouping
                $keys = array('name', 'id', 'title', 'abstract', 'display');
                if ($opt['group']) {
                    $sorted_results = $this->mgroup($sort_array, $keys, $group_opts);
                } else {
                    $sorted_results = $this->mgroup($sort_array, $keys);
                }

                $renderer->doc .= $this->_render_as_html($opt['layout'], $sorted_results, $opt, $count);

            // no results...
            } else {
                if ( ! $opt['hidemsg']) {
                    $renderer->doc .= $this->_render_as_empty($query, $message);
                }
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Render a simple "no results" message
     *
     * @param string $query => original query
     * @return string
     */
    private function _render_as_empty($query, $error = '') {
        $render = '<div class="pagequery noborder">' . DOKU_LF;
        $render .= '<p class="noresults"><span>pagequery</span>' . sprintf($this->getLang("no_results"),
                                  '<strong>' . $query . '</strong>') . '</p>' . DOKU_LF;
        if ( ! empty($error)) {
            $render .= '<p class="noresults">' . $error . '</p>' . DOKU_LF;
        }
        $render .= '</div>' . DOKU_LF;
        return $render;
    }


    private function _render_as_html($layout, $sorted_results, $opt, $count) {
        $render_type = '_render_as_html_' . $layout;
        return $this->$render_type($sorted_results, $opt, $count);
    }


    /**
     * Render the final pagequery results list as HTML, indented and in columns as required
     *
     * @param array  $sorted_results
     * @param array  $opt
     * @param int    $count => count of results
     *
     * @return string => HTML rendered list
     */
    protected function _render_as_html_table($sorted_results, $opt, $count) {

        $ratios = array(.80, 1.3, 1.17, 1.1, 1.03, .96, .90);   // height ratios: link, h1, h2, h3, h4, h5, h6
        $render = '';
        $prev_was_heading = false;
        $can_start_col = true;
        $cont_level = 1;
        $col = 0;
        $multi_col = $opt['cols'] > 1;  // single columns are displayed without tables (better for TOC)
        $col_height = $this->_adjusted_height($sorted_results, $ratios) / $opt['cols'];
        $cur_height = 0;
        $width = floor(100 / $opt['cols']);
        $is_first = true;

        // basic result page markup (always needed)
        $outer_border = ($opt['border'] == 'outside' || $opt['border'] == 'both') ? '' : ' noborder';
        $no_table = ( ! $multi_col) ? ' notable' : '';
        $top_id = 'top-' . mt_rand();   // fixed anchor point to jump back to at top of the table
        $render .= '<div class="pagequery' . $outer_border . $no_table . '" id="' . $top_id . '">' . DOKU_LF;
        if ($opt['showcount'] == true) {
            $render .= '<div class="count">' . $count . '</div>' . DOKU_LF;
        }
        if ($opt['label'] != '') {
            $render .= '<h1 class="title">' . $opt['label'] . '</h1>' . DOKU_LF;
        }
        if ($multi_col) {
            $render .= '<table><tbody><tr>' . DOKU_LF;
        }

        $inner_border = ($opt['border'] == 'inside' || $opt['border'] == 'both') ? '' : ' class="noborder" ';

        // now render the pagequery list
        foreach ($sorted_results as $line) {

            list($level, $name, $id, $title, $abstract, $display) = $line;

            $is_heading = ($level > 0);
            if ($is_heading) {
                $heading = $name;
            }

            // is it time to start a new column?
            if ($can_start_col === false && $col < $opt['cols'] && $cur_height >= $col_height) {
                $can_start_col = true;
                $col++;
            }

            // no need for indenting if there is no grouping
            if ($opt['group'] === false) {
                $indent_style = ' class="nogroup"';
            } else {
                $indent = ($is_heading) ? $level - 1 : $cont_level - 1;
                $indent_style = ' style="margin-left:' . $indent * 10 . 'px"';
            }

            // Begin new column if: 1) we are at the start, 2) last item was not a heading or 3) if there is no grouping
            if ($can_start_col && ! $prev_was_heading) {
                $jump_tip = sprintf($this->getLang('jump_section'), $heading);
                // close the previous column if necessary; also adds a 'jump to anchor'
                $col_close = ( ! $is_heading) ? '<a title="'. $jump_tip . '" href="#' .
                                $top_id . '">' . "<h$cont_level>§... </h$cont_level></a>" : '';
                $col_close = ( ! $is_first) ? $col_close . '</ul></td>' . DOKU_LF : '';
                $col_open = ( ! $is_first && ! $is_heading) ? "<h$cont_level$indent_style>" . "$heading...</h$cont_level>" : '';
                $td = ($multi_col) ? '<td' . $inner_border . ' valign="top" width="' . $width . '%">' : '';
                $render .= $col_close . $td . $col_open . DOKU_LF;
                $can_start_col = false;
                // needed to correctly style page link lists <ul>...
                $prev_was_heading = true;
                $cur_height = 0;
            }

            // finally display the appropriate heading or page link(s)
            if ($is_heading) {
                // close previous sub list if necessary
                if ( ! $prev_was_heading) {
                    $render .= '</ul>' . DOKU_LF;
                }
                if ($opt['proper'] == 'header' || $opt['proper'] == 'both') {
                    $heading = $this->_proper($heading);
                }
                if ( ! empty($id)) {
                    $heading = $this->_html_wikilink($id, $heading, 0, '', $opt, false, true);
                }
                $render .= "<h$level$indent_style>$heading</h$level>" . DOKU_LF;
                $prev_was_heading = true;
                $cont_level = $level + 1;
            } else {
                // open a new sub list if necessary
                if ($prev_was_heading || $is_first) {
                    $render .= "<ul$indent_style>";
                }
                // deal with normal page links
                if ($opt['proper'] == 'name' || $opt['proper'] == 'both') {
                    $display = $this->_proper($display);
                }
                $link = $this->_html_wikilink($id, $display, $abstract, $opt);
                $render .= $link;
                $prev_was_heading = false;
            }
            $cur_height += $ratios[$level];
            $is_first = false;
        }
        $render .= '</ul>' . DOKU_LF;
        if ($multi_col) {
            $render .= '</td></tr></tbody></table>' . DOKU_LF;
        }
        if ($opt['hidejump'] == false) {
            $render .= '<a class="top" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>' . DOKU_LF;
        }
        $render .= '</div>' . DOKU_LF;

        return $render;
    }



     /**
     * Render the final pagequery results list as HTML, indented and in columns as required
     *
     * @param array  $sorted_results
     * @param array  $opt
     * @param int    $count => count of results
     *
     * @return string => HTML rendered list
     */
    protected function _render_as_html_column($sorted_results, $opt, $count) {

        $render = '';
        $prev_was_heading = false;
        $cont_level = 1;
        $is_first = true;

        $outer_border = '';
        $inner_border = '';
        $show_count = '';
        $label = '';

        // fixed anchor to jump back to at top
        $top_id = 'top-' . mt_rand();

        if ($opt['border'] == 'outside' || $opt['border'] == 'both') {
            $outer_border = ' noborder';
        }
        if ($opt['border'] == 'inside' || $opt['border'] == 'both') {
            $inner_border =  '.noinnerborder" ';
        }
        if ($opt['showcount'] == true) {
            $show_count = '<div class="count">' . $count . '</div>' . DOKU_LF;
        }
        if ($opt['label'] != '') {
            $label = '<h1 class="title">' . $opt['label'] . '</h1>' . DOKU_LF;
        }

        $render .= '<div class="pagequery' . $outer_border . '" id="' . $top_id . '">' . DOKU_LF . $show_count . $label;
        $render .= '<div class="inner ' . $inner_border . '">';

        // now render the pagequery list
        foreach ($sorted_results as $line) {

            // TODO: what is title still used for
            list($level, $name, $id, $title, $abstract, $display) = $line;

            $is_heading = ($level > 0);
            if ($is_heading) {
                $heading = $name;
            }

            // no need for indenting if there is no grouping
            if ($opt['group'] === false) {
                $indent_style = ' class="nogroup"';
            } else {
                $indent = ($is_heading) ? $level - 1 : $cont_level - 1;
                $indent_style = ' style="margin-left:' . $indent * 10 . 'px"';
            }

            // finally display the appropriate heading or page link(s)
            if ($is_heading) {

                // close previous sub list if necessary
                if ( ! $prev_was_heading) {
                    $render .= '</ul>' . DOKU_LF;
                }
                if ($opt['proper'] == 'header' || $opt['proper'] == 'both') {
                    $heading = $this->_proper($heading);
                }
                if ( ! empty($id)) {
                    $heading = $this->_html_wikilink($id, $heading, 0, '', $opt, false, true);
                }
                $render .= "<h$level$indent_style>$heading</h$level>" . DOKU_LF;
                $prev_was_heading = true;
                $cont_level = $level + 1;

            } else {
                // open a new sub list if necessary
                if ($prev_was_heading || $is_first) {
                    $render .= "<ul$indent_style>";
                }
                // deal with normal page links
                if ($opt['proper'] == 'name' || $opt['proper'] == 'both') {
                    $display = $this->_proper($display);
                }
                $link = $this->_html_wikilink($id, $display, $abstract, $opt);
                $render .= $link;
                $prev_was_heading = false;
            }
            $is_first = false;
        }

        $render .= '</ul>' . DOKU_LF;

        if ($opt['hidejump'] === false) {
            $render .= '<a class="top" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>' . DOKU_LF;
        }

        $render .= '</div></div>' . DOKU_LF;

        return $render;
    }



    private function _adjusted_height($sorted_results, $ratios) {
        // ratio of different heading heights (%), to ensure more even use of columns (h1 -> h6)
        $adjusted_height = 0;
        foreach ($sorted_results as $row) {
            $adjusted_height += $ratios[$row[0]];
        }
        return $adjusted_height;
    }


    /**
     * Renders the page link, plus tooltip, abstract, casing, etc...
     * @param string $id
     * @param bool  $display
     * @param string $abstract
     * @param bool  $opt
     * @param bool  $track_snippets
     * @param bool  $raw => unformatted (no html)
     */
    private function _html_wikilink($id, $display,  $abstract, $opt, $track_snippets = true, $raw = false) {
        static $snippet_cnt = 0;

        if ($track_snippets) {
            $snippet_cnt++;
        }
        $id = (strpos($id, ':') === false) ? ':' . $id : $id;   // : needed for root pages (root level)

        $type = $opt['snippet']['type'];
        $count = $opt['snippet']['count'];
        $after= '';
        $inline = '';

        if ($type == 'none') {
            // Plain old wikilink
            $link = html_wikilink($id, $display);
        } else {
            $short = $this->_shorten($abstract, $opt['snippet']['extent']);   // shorten BEFORE replacing html entities!
            $short = htmlentities($short, ENT_QUOTES, 'UTF-8');
            $abstract = str_replace("\n\n", "\n", $abstract);
            $abstract = htmlentities($abstract, ENT_QUOTES, 'UTF-8');
            $link = html_wikilink($id, $display);
            $no_snippet = ($count > 0 && $snippet_cnt >= $count);
            if ($type == 'tooltip' || $no_snippet) {
                $link = $this->_add_tooltip($link, $abstract);
            } elseif ($type == 'quoted' || $type == 'plain') {
                $more = html_wikilink($id, 'more');
                $after = trim($short);
                $after = str_replace("\n\n", "\n", $after);
                $after = str_replace("\n", '<br/>', $after);
                $after = '<div class="' . $type . '">' . $after . $more . '</div>' . DOKU_LF;
            } elseif ($type == 'inline') {
                $inline .= '<span class=inline>' . $short . '</span>';
            }
        }
        $noborder = ($opt['underline']) ? '' : ' class="noborder"';
        if ($raw) {
            $wikilink = $link . $inline;
        } else {
            $wikilink = "<li$noborder>" . $link . $inline . '</li>' . DOKU_LF . $after;
        }
        return $wikilink;
    }


    /**
     * Swap normal link title (popup) for a more useful preview
     *
     * @param string $id    page id
     * @param string $name  display name
     * @return complete href link
     */
    private function _add_tooltip($link, $tooltip) {
        $tooltip = str_replace("\n", '  ', $tooltip);
        $link = preg_replace('/title=\".+?\"/', 'title="' . $tooltip . '"', $link, 1);
        return $link;
    }


    /**
     * return the first part of the $text according to the $amount given
     * @param type $text
     * @param type $amount  c? = ? chars, w? = ? words, l? = ? lines, ~? = search up to text/char/symbol
     */
    private function _shorten($text, $extent, $more = '... ') {
        $elem = $extent[0];
        $cnt = substr($extent, 1);
        switch ($elem) {
            case 'c':
                $result = substr($text, 0, $cnt);
                if ($cnt > 0 && strlen($result) < strlen($text)) $result .= $more;
                break;
            case 'w':
                $words = str_word_count($text, 1, '.');
                $result = implode(' ', array_slice($words, 0, $cnt));
                if ($cnt > 0 && $cnt <= count($words) && $words[$cnt - 1] != '.') $result .= $more;
                break;
            case 'l':
                $lines = explode("\n", $text);
                $lines = array_filter($lines);  // remove blank lines
                $result = implode("\n", array_slice($lines, 0, $cnt));
                if ($cnt > 0 && $cnt < count($lines)) $result .= $more;
                break;
            case "~":
                $result = strstr($text, $cnt, true);
                break;
            default:
                $result = $text;
        }
        return $result;
    }


    /**
     * Changes a wiki page id into proper case (allowing for :'s etc...)
     * @param string    $id    page id
     * @return string
     */
    private function _proper($id) {
         $id = str_replace(':', ': ', $id); // make a little whitespace before words so ucwords can work!
         $id = str_replace('_', ' ', $id);
         $id = ucwords($id);
         $id = str_replace(': ', ':', $id);
         return $id;
    }


    /**
     * Parse out the namespace, and convert to a regex for array search
     *
     * @param  string $query user page query
     * @return string        processed query with necessary regex markup for namespace recognition
     */
    private function _parse_ns_query($query) {
        global $INFO;

        $cur_ns = $INFO['namespace'];
        $incl_ns = array();
        $excl_ns = array();
        $page_qry = '';
        $tokens = explode(' ', trim($query));
        if (count($tokens) == 1) {
            $page_qry = $query;
        } else {
            foreach ($tokens as $token) {
                if (preg_match('/^(?:\^|-ns:)(.+)$/u', $token, $matches)) {
                    $excl_ns[] = resolve_id($cur_ns, $matches[1]);  // also resolve relative and parent ns
                } elseif (preg_match('/^(?:@|ns:)(.+)$/u', $token, $matches)) {
                    $incl_ns[] = resolve_id($cur_ns, $matches[1]);
                } else {
                    $page_qry .= ' ' . $token;
                }
            }
        }
        $page_qry = trim($page_qry);
        return array($page_qry, $incl_ns, $excl_ns);
    }


    /**
     * Builds the sorting array: array of arrays (0 = id, 1 = name, 2 = abstract, 3 = ... , etc)
     *
     * @param array     $ids            array of page ids to be sorted
     * @param array     $get_abstract   use page abstract
     * @param bool      $opt            all user options/settings
     *
     * @return array    $sort_array array of array(one value for each key to be sorted)
     *                   $sort_opts  sorting options for the msort function
     *                   $group_opts grouping options for the mgroup function
     */
    private function _build_sorting_array($ids, $get_abstract, $opt) {
        global $conf;

        $sort_array = array();
        $sort_opts = array();
        $group_opts = array();

        $dformat = array();
        $wformat = array();
        $extrakeys = array();

        $row = 0;

        // look for 'abc' by title instead of name ('abc' by pageid makes little sense)
        // title takes precedence over name (should you try to sort by both...why?)
        $from_title = (isset($opt['sort']['title'])) ? true : false;

        // add any extra columns needed for filtering!
        $extrakeys = array_diff_key($opt['filter'], $opt['sort']);
        $col_keys = array_merge($opt['sort'], $extrakeys);

        foreach ($ids as $id) {
            // getting metadata is very time-consuming, hence ONCE per displayed row
            $meta = p_get_metadata ($id, false, true);

            if ( ! isset($meta['date']['modified'])) {
                $meta['date']['modified'] = $meta['date']['created'];
            }
            // establish page name (without namespace)
            $name = noNS($id);

            // first column is the basic page id
            $sort_array[$row]['id'] = $id;

            // second column is the display 'name' (used when sorting by 'name')
            // this also avoids rebuilding the display name when building links later (DRY)
            $sort_array[$row]['name'] = $name;

            // third column: cache the display name; taken from metadata => 1st heading
            // used when sorting by 'title'
            $title = (isset($meta['title']) && ! empty($meta['title'])) ? $meta['title'] : $name;
            $sort_array[$row]['title'] = $title;

            // fourth column: cache the page abstract if needed; this saves a lot of time later
            // and avoids repeated slow metadata retrievals (v. slow!)
            $abstract = ($get_abstract) ? $meta['description']['abstract'] : '';
            $sort_array[$row]['abstract'] = $abstract;

            // fifth column is the displayed text for links; set below
            $sort_array[$row]['display'] = '';

            // cache of full date for this row
            $real_date = 0;

            foreach ($col_keys as $key => $void) {
                switch ($key) {
                    case 'a':
                    case 'ab':
                    case 'abc':
                        $abc = ($from_title) ? $title : $name;
                        $value = $this->_first(strtolower($abc), strlen($key));
                        break;
                    case 'name':
                    case 'title':
                        // name/title columns already exists by default (col 1,2)
                        // save a few microseconds by just moving on to the next key
                        continue 2;
                    case 'id':
                        $value = $id;
                        break;
                    case 'ns':
                        $value = getNS($id);
                        if (empty($value)) {
                            $value = '[' . $conf['start'] . ']';
                        }
                        break;
                    case 'creator':
                        $value = $meta['creator'];
                        break;
                    case 'contributor':
                        $value = implode(';', $meta['contributor']);
                        break;
                    case 'mdate':
                        $value = $meta['date']['modified'];
                        break;
                    case 'cdate':
                        $value = $meta['date']['created'];
                        break;
                    default:
                        // date sorting types (groupable)
                        $dtype = $key[0];
                        if ($dtype == 'c' || $dtype == 'm') {
                            // we only set real date once per id (needed for grouping)
                            // not per sort column--the date should remain same across all columns
                            // this is always the last column!
                            if ($real_date == 0) {
                                if ($dtype == 'c') {
                                    $real_date = $meta['date']['created'];
                                } else {
                                    $real_date = $meta['date']['modified'];
                                }
                                $sort_array[$row][self::MGROUP_REALDATE] = $real_date;
                            }
                            // only set date formats once per sort column/key (not per id!), i.e. on first row
                            if ($row == 0) {
                                $dformat[$key] = $this->_date_format($key);
                                // collect date in word format for potential use in grouping
                                if ($opt['spelldate']) {
                                    $wformat[$key] = $this->_date_format_words($dformat[$key]);
                                } else {
                                    $wformat[$key] = '';
                                }
                            }
                            // create a string date used for sorting only
                            // (we cannot just use the real date otherwise it would not group correctly)
                            $value = strftime($dformat[$key], $real_date);
                        }
                }
                $sort_array[$row][$key] = $value;
            }

            // allow for custom display formatting
            $key = '';
            $matches = array();
            $display = $opt['display'];
            $matched = preg_match_all('/\{(.+?)\}/', $display, $matches, PREG_SET_ORDER);
            if ($matched > 0) {
                foreach ($matches as $match) {
                    $key = $match[1];
                    $value = null;
                    if (isset($sort_array[$row][$key])) {
                        $value = $sort_array[$row][$key];
                    } elseif (isset($meta[$key])) {
                        $value = $meta[$key];
                    // allow for nested meta keys (e.g. date:created)
                    } elseif (strpos($key, ':') !== false) {
                        $keys = explode(':', $key);
                        if (isset($meta[$keys[0]][$keys[1]])) {
                            $value = $meta[$keys[0]][$keys[1]];
                        }
                    } elseif ($key == 'mdate') {
                        $value = $meta['date']['modified'];
                    } elseif ($key == 'cdate') {
                        $value = $meta['date']['created'];
                    }
                    if ( ! is_null($value)) {
                        if (strpos($key, 'date') !== false) {
                            $value = strftime($opt['dformat'], $value);
                        }
                        $display = str_replace($match[0], $value, $display);
                    }
                }

            // try to match any metadata field
            } elseif (isset($sort_array[$row][$display])) {
                $display = $sort_array[$row][$display];
            } else {
                $display = $sort_array[$row]['name'];
            }
            $sort_array[$row]['display'] = $display;

            $row++;
        }

        $idx = 0;
        foreach ($opt['sort'] as $key => $value) {

            $sort_opts['key'][] = $key;

            // now the sort direction
            switch ($value) {
                case 'a':
                case 'asc':
                    $dir = self::MSORT_ASC;
                    break;
                case 'd':
                case 'desc':
                    $dir = self::MSORT_DESC;
                    break;
                default:
                    switch ($key) {
                    // sort dates descending by default; text ascending
                        case 'a':
                        case 'ab':
                        case 'abc':
                        case 'name':
                        case 'title':
                        case 'id':
                        case 'ns':
                        case 'creator':
                        case 'contributor':
                            $dir = self::MSORT_ASC;
                            break;
                        default:
                            $dir = self::MSORT_DESC;
                            break;
                    }
            }
            $sort_opts['dir'][] = $dir;

            // set the sort array's data type
            switch ($key) {
                case 'mdate':
                case 'cdate':
                    $type = self::MSORT_NUMERIC;
                    break;
                default:
                    if ($opt['casesort']) {
                        // case sensitive: a-z then A-Z
                        $type = ($opt['natsort']) ? self::MSORT_NAT : self::MSORT_STRING;
                    } else {
                        // case-insensitive
                        $type = ($opt['natsort']) ? self::MSORT_NAT_CASE : self::MSORT_STRING_CASE;
                    }
            }
            $sort_opts['type'][] = $type;

            // now establish grouping options
            switch ($key) {
                // name strings and full dates cannot be meaningfully grouped (no duplicates!)
                case 'mdate':
                case 'cdate':
                case 'name':
                case 'title':
                case 'id':
                    $group_by = self::MGROUP_NONE;
                    break;
                case 'ns':
                    $group_by = self::MGROUP_NAMESPACE;
                    break;
                default:
                    $group_by = self::MGROUP_HEADING;
            }
            if ($group_by != self::MGROUP_NONE) {
                $group_opts['key'][$idx] = $key;
                $group_opts['type'][$idx] = $group_by;
                $group_opts['dformat'][$idx] = $wformat[$key];
                $idx++;
            }
        }

        return array($sort_array, $sort_opts, $group_opts);
    }


    // returns first $count letters from $text
    private function _first($text, $count) {
        if ($count > 0) {
            return utf8_substr($text, 0, $count);
        }
    }


    /**
     * Parse the c|m-year-month-day option; used for sorting/grouping
     *
     * @param string  $key
     * @return string
     */
    private function _date_format($key) {
        if (strpos($key, 'year') !== false) $dkey[] = '%Y';
        if (strpos($key, 'month') !== false) $dkey[] = '%m';
        if (strpos($key, 'day') !== false) $dkey[] = '%d';
        $dformat = implode('-', $dkey);
        return $dformat;
    }


    /**
     * Provide month and day format in real words if required
     * used for display only ($dformat is used for sorting/grouping)
     *
     * @param string $dformat
     * @return string
     */
    private function _date_format_words($dformat) {
        $wformat = '';
        switch ($dformat) {
            case '%m':
                $wformat = "%B";
                break;
            case '%d':
                $wformat = "%#d–%A ";
                break;
            case '%Y-%m':
                $wformat = "%B %Y";
                break;
            case '%m-%d':
                $wformat= "%B %#d, %A ";
                break;
            case '%Y-%m-%d':
                $wformat = "%A, %B %#d, %Y";
                break;
        }
        return $wformat;
    }


    /**
     * A heavily customised version of _ft_pageLookup in inc/fulltext.php
     * no sorting!
     */
    private function _page_lookup($query, $pageonly, $incl_ns, $excl_ns, $nostart = true, $maxns = 0) {
        global $conf;

        $query = trim($query);
        $pages = file($conf['indexdir'] . '/page.idx');

        // first deal with excluded namespaces, then included
        $pages = $this->_filter_ns($pages, $excl_ns, true);

        // now include ONLY the selected namespaces if provided
        $pages = $this->_filter_ns($pages, $incl_ns, false);

        $cnt = count($pages);
        for ($i = 0; $i < $cnt; $i++) {
            $page = $pages[$i];
            if ( ! page_exists($page) || isHiddenPage($page)) {
                unset($pages[$i]);
                continue;
            }
            if ($pageonly) $page = noNS($page);
            /*
             * This is the actual "search" expression!
             * Note: preg_grep cannot be used because of the pageonly option above
             *       (needs to allow for "^" syntax)
             * The @ prevents problems with invalid queries!
             */
            $matched = @preg_match('/' . $query . '/i', $page);
            if ($matched === false) {
                return false;
            } elseif ($matched == 0) {
                unset($pages[$i]);
            }
        }
        if ( ! count($pages)) return array();

        $pages = array_map('trim',$pages);

        // check ACL permissions and remove any 'start' pages if req'd
        $start = $conf['start'];
        $pos = strlen($start);
        foreach($pages as $idx => $name) {
            if ($nostart && substr($name, -$pos) == $start) {
                unset($pages[$idx]);
            } elseif ($maxns > 0 && (substr_count($name,':')) > $maxns) {
                unset($pages[$idx]);
            // TODO: this function is one of slowest in the plugin; solutions?
            } elseif(auth_quickaclcheck($pages[$idx]) < AUTH_READ) {
                unset($pages[$idx]);
            }
        }
        return $pages;
    }


    /**
     * Include/Exclude specific namespaces from a list of pages
     * @param type $pages   a list of wiki page ids
     * @param type $ns_qry  namespace(s) to include/exclude
     * @param type $exclude true = exclude
     * @return array
     */
    private function _filter_ns($pages, $ns_qry, $exclude) {
        $invert = ($exclude) ? PREG_GREP_INVERT : 0;
        foreach ($ns_qry as $ns) {
            //  we only look for namespace from beginning of the id
            $regexes[] = '^' . $ns . ':.*';
        }
        if ( ! empty($regexes)) {
            $regex = '/(' . implode('|', $regexes) . ')/';
            $result = array_values(preg_grep($regex, $pages, $invert));
        } else {
            $result = $pages;
        }
        return $result;
    }


    /**
     * filter array of pages by specific meta data keys (or columns)
     *
     * @param type $sort_array  full sorting array, all meta columns included
     * @param type $filter  meta-data filter: <meta key>:<query>
     */
    private function _filter_meta($sort_array, $filter) {
        foreach ($filter as $metakey => $expr) {
            // allow for exclusion matches (put ^ or ! in front of meta key)
            $exclude = false;
            if ($metakey[0] == '^' || $metakey[0] == '!') {
                $exclude = true;
                $metakey = substr($metakey, 1);
            }
            $that = $this;
            $sort_array = array_filter($sort_array, function($row) use ($metakey, $expr, $exclude, $that) {
                if ( ! isset($row[$metakey])) return false;
                if (strpos($metakey, 'date') !== false) {
                    $match = $that->_filter_by_date($expr, $row[$metakey]);
                } else {
                    $match = preg_match('`' . $expr . '`', $row[$metakey]) > 0;
                }
                if ($exclude) $match = ! $match;
                return $match;
            });
        }
        return $sort_array;
    }


    function _filter_by_date($filter, $date) {
        $filter = str_replace('/', '.', $filter);  // allow for Euro style date formats
        $filters = explode('->', $filter);
        $begin = (empty($filters[0]) ? null : strtotime($filters[0]));
        $end   = (empty($filters[1]) ? null : strtotime($filters[1]));

        $matched = false;
        if ($begin !== null && $end !== null) {
            $matched = ($date >= $begin && $date <= $end);
        } elseif ($begin !== null) {
            $matched = ($date >= $begin);
        } elseif ($end !== null) {
            $matched = ($date <= $end);
        }
        return $matched;
    }


    /****************************
     * SORTING HELPER FUNCTIONS *
     ****************************
     *
     */

    /* msort options
     *
     */

    // keep key associations
    const MSORT_KEEP_ASSOC = 'msort01';

    // additional sorting type
    const MSORT_NUMERIC = 'msort02';
    const MSORT_REGULAR = 'msort03';
    const MSORT_STRING = 'msort04';
    const MSORT_STRING_CASE = 'msort05'; // case insensitive
    const MSORT_NAT = 'msort06';         // natural sorting
    const MSORT_NAT_CASE = 'msort07';    // natural sorting, case insensitive

    const MSORT_ASC = 'msort08';
    const MSORT_DESC = 'msort09';

    const MSORT_DEFAULT_DIRECTION = MSORT_ASC;
    const MSORT_DEFAULT_TYPE = MSORT_STRING;

    /**
     * A replacement for array_mulitsort which permits natural and caseless sorting
     * This function will sort an 'array of rows' only (not array of 'columns')
     *
     * @param array $sort_array  : multi-dimensional array of arrays, where the first index refers to the row number
     *                             and the second to the column number (e.g. $array[row_number][column_number])
     *                             i.e. = array(
     *                                          array('name1', 'job1', 'start_date1', 'rank1'),
     *                                          array('name2', 'job2', 'start_date2', 'rank2'),
     *                                          ...
     *                                          );
     *
     * @param mixed $sort_opts   : options for how the array should be sorted
     *                    :AS ARRAY
     *                             $sort_opts['key'][<column>] = 'key'
     *                             $sort_opts['type'][<column>] = 'type'
     *                             $sort_opts['dir'][<column>] = 'dir'
     *                             $sort_opts['assoc'][<column>] = MSORT_KEEP_ASSOC | true
     * @return boolean
     */
    private function msort(&$sort_array, $sort_opts) {

        // if a full sort_opts array was passed
        if (is_array($sort_opts) && ! empty($sort_opts)) {
            if (isset($sort_opts['assoc'])) {
                $keep_assoc = true;
            }
        } else {
            return false;
        }

        // Determine which u..sort function (with or without associations).
        $sort_func = ($keep_assoc) ? 'uasort' : 'usort';

        $keys = $sort_opts['key'];

        // HACK: self:: does not work inside a closure so...
        $self = __CLASS__;

        // Sort the data and get the result.
        $result = $sort_func (
            $sort_array,
            function(array &$left, array &$right) use(&$sort_opts, $keys, $self) {

                // Assume that the entries are the same.
                $cmp = 0;

                // Work through each sort column
                foreach($keys as $idx => $key) {
                    // Handle the different sort types.
                    switch ($sort_opts['type'][$idx]) {
                        case $self::MSORT_NUMERIC:
                            $key_cmp = ((intval($left[$key]) == intval($right[$key])) ? 0 :
                                       ((intval($left[$key]) < intval($right[$key])) ? -1 : 1 ) );
                            break;

                        case $self::MSORT_STRING:
                            $key_cmp = strcmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case $self::MSORT_STRING_CASE: //case-insensitive
                            $key_cmp = strcasecmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case $self::MSORT_NAT:
                            $key_cmp = strnatcmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case $self::MSORT_NAT_CASE:    //case-insensitive
                            $key_cmp = strnatcasecmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case $self::MSORT_REGULAR:
                        default :
                            $key_cmp = (($left[$key] == $right[$key]) ? 0 :
                                       (($left[$key] < $right[$key]) ? -1 : 1 ) );
                        break;
                    }

                    // Is the column in the two arrays the same?
                    if ($key_cmp == 0) {
                        continue;
                    }

                    // Are we sorting descending?
                    $cmp = $key_cmp * (($sort_opts['dir'][$idx] == $self::MSORT_DESC) ? -1 : 1);

                    // no need for remaining keys as there was a difference
                    break;
                }
                return $cmp;
            }
        );
        return $result;
    }

    // grouping types
    const MGROUP_NONE = 'mgrp00';
    const MGROUP_HEADING = 'mgrp01';
    const MGROUP_NAMESPACE = 'mgrp02';

    // real date column
    const MGROUP_REALDATE = '__realdate__';


    /**
     * group a multi-dimensional array by each level heading
     * @param array $sort_array : array to be grouped (result of 'msort' function)
     *                             __realdate__' column should contain real dates if you need dates in words
     * @param array $keys       : which keys (columns) should be returned in results array? (as keys)
     * @param mixed $group_opts :  AS ARRAY:
     *                             $group_opts['key'][<order>] = column key to group by
     *                             $group_opts['type'][<order>] = grouping type [MGROUP...]
     *                             $group_opts['dformat'][<order>] = date formatting string
     *
     * @return array $results   : array of arrays: (level, name, page_id, title), e.g. array(1, 'Main Title')
     *                              array(0, '...') =>  0 = normal row item (not heading)
     */
    private function mgroup(&$sort_array, $keys, $group_opts = array()) {
        $level = count($group_opts['key']) - 1;
        $prevs = array();
        $results = array();
        $idx = 0;

        if (empty($sort_array)) {
            $results =  array();
        } elseif (empty($group_opts)) {
            foreach ($sort_array as $row) {
                $result = array(0);
                foreach ($keys as $key) {
                    $result[] = $row[$key];
                }
                $results[] = $result;
            }
        } else {
            foreach($sort_array as $row) {
                $this->_add_heading($results, $sort_array, $group_opts, $level, $idx, $prevs);
                $result = array(0); // basic item (page link) is level 0
                for ($i = 0; $i < count($keys); $i++) {
                    $result[] = $row[$keys[$i]];
                }
                $results[] = $result;
                $idx++;
            }
        }
        return $results;
    }


    /**
     * private function used by mgroup only!
     */
    private function _add_heading(&$results, &$sort_array, &$group_opts, $level, $idx, &$prevs) {
        global $conf;

        // recurse to find all parent headings
        if ($level > 0) {
            $this->_add_heading($results, $sort_array, $group_opts, $level - 1, $idx, $prevs);
        }
        $group_type = $group_opts['type'][$level];

        $prev = (isset($prevs[$level])) ? $prevs[$level] : '';
        $key = $group_opts['key'][$level];
        $cur = $sort_array[$idx][$key];
        if ($cur != $prev) {
            $prevs[$level] = $cur;

            if ($group_type === self::MGROUP_HEADING) {
                $date_format = $group_opts['dformat'][$level];
                if ( ! empty($date_format)) {
                    // the real date is always the the '__realdate__' column (MGROUP_REALDATE)
                    $cur = strftime($date_format, $sort_array[$idx][self::MGROUP_REALDATE]);
                }
                $results[] = array($level + 1, $cur, '');

            } elseif ($group_type === self::MGROUP_NAMESPACE) {
                $cur_ns = explode(':', $cur);
                $prev_ns = explode(':', $prev);
                // only show namespaces that are different from the previous heading
                for ($i = 0; $i < count($cur_ns); $i++) {
                    if ($cur_ns[$i] != $prev_ns[$i]) {
                        $hl = $level + $i + 1;
                        $id = implode(':', array_slice($cur_ns, 0, $i + 1)) . ':' . $conf['start'];
                        $ns_start = (page_exists($id)) ? $id : '';
                        $results[] = array($hl , $cur_ns[$i], $ns_start, '', '');
                    }
                }
            }
        }
    }
}
?>