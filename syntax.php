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
     * Parameters as follows:
     * 1. query:    any expression directly after the >; can use all Dokuwiki search options (see manual)
     * 2. fulltext: use a full-text search, instead of page_id only [default]
     * 3. sort:     keys to sort by, in order of sorting. Each key can be followed by prefered sorting order
     *              available keys:
     *                  a, ab, abc          by 1st letter, 2 letters, or 3 letters
     *                  name                by page name (no namespace) or 1st heading [not grouped]
     *                  page|id             by full page id, including namespace [not grouped]
     *                  ns                  by namespace (without page name)
     *                  mdate, cdate        by modified|created dates (full) [not grouped]
     *                  m[year][month][day] by modified [year][month][day]; any combination accepted
     *                  c[year][month][day] by created [year][month][day]; any combination accepted
     *                  creator             by page author
     *              date sort default to descending, string sorts to ascending
     * 4. group:    show group headers for each change in sort keys
     *              Note: keys with no duplicate cannot be grouped (i.e. name, page|id, mdate, cdate)
     * 5. limit:    maximum number of results to return
     * 6. inwords:  use real month and day names instead of numeric dates
     * 7. cols:     number of columns in displayed list (max = 6)
     * 8. proper:   display page names and namespace in Proper Case (i.e. no _'s and Capitalised)
     *              header/hdr = group headers only, name = page name only, both = both!
     * 9. border:   turn on borders. 'inside' = between columns; 'outside' => border around table;
     *              'both' => in and out; 'none' => neither
     *10. fullregex:only useful on page name searches; allows a raw regex mode on the full page id
     *11. nostart:  ignore any 'start' pages in namespace (based on "config:start")
     *12. maxns:    maximum namespace level to be displayed; e.g. maxns=3 => one:two:three
     *13. useheading:    show 1st page heading instead of page name ("title" also accepted)
     *14. snippet:  should an excerpt of the wikipage be shown:
     *              use :tooltip to show as a pop-up only
     *              use :<inline|plain|quoted>, <count>, <extent> to show 1st <count> items in list with an abstract
     *                  extent always choice of chars, words, lines, or find (c? w? l? ~????)
     *15. natsort:  use natural sorting order (good for words beginning with numbers)
     *16. case:     respect case when sorting, i.e. a != A when sorting.  a-z then A-Z (opp. to PHP term, easier on average users)
     *17. underline:show a faint underline between each link for clarity
     *18. label:    title/label to be added at top of the list
     *
     * All options are optional, and the list will default to a boring long 1-column list...
     */
	function handle($match, $state, $pos, &$handler) {

        $opt = array();

		$match = substr($match, 12, -2); // strip markup "{{pagequery>...}}"
		$params = explode(';', $match);
        $opt['query'] = $params[0];

        // establish some basic option defaults
        $opt['sort'] = array();
        $opt['filter'] = array();
        $opt['fulltext'] = false;
        $opt['fullregex'] = false;
        $opt['group'] = false;
        $opt['limit'] = 0;
        $opt['maxns'] = 0;
        $opt['nostart'] = false;
        $opt['cols'] = 1;
        $opt['proper'] = 'none';
        $opt['border'] = 'none';
        $opt['snippet'] = array('type' => 'none');
        $opt['useheading'] = false;
        $opt['case'] = false;
        $opt['natsort'] = false;
        $opt['underline'] = false;
        $opt['nomsg'] = false;
        $opt['label'] = '';

        foreach ($params as $param) {
            list($option, $value) = explode('=', $param);
            switch ($option) {
                case 'fulltext':
				case 'fullregex':
                case 'group':
                case 'inwords':
                case 'nostart':
                case 'case':
                case 'natsort':
                case 'underline':
                case 'nomsg':
                    $opt[$option] = true;
                    break;
                case 'title':   // old syntax: could be confusing, use standard Dokuwiki name instead
                case 'useheading':
                    $opt['useheading'] = true;
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
                case 'abstract':    // old syntax, to be deprecated (2011-03-15)
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
            }
        }
		return $opt;
	}

    function render($mode, &$renderer, $opt) {
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

                if ($query == '*') $query = '.*';   // a lazy man's option!
                $results = $this->_page_lookup($query, $pageonly, $incl_ns, $excl_ns, $opt['nostart'], $opt['maxns']);
            }

            if ($results === false) {
                $empty = true;
                $message = $this->getLang('regex_error');
            } elseif ( ! empty($results)) {
                // *** this section is where the essential pagequery functionality happens... ***

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

            if ( ! $empty) {
                // now do the sorting
                $this->msort($sort_array, $sort_opts);
                // limit the result list length if required; this can only be done after sorting!
                $sort_array = ($opt['limit'] > 0) ? array_slice($sort_array, 0, $opt['limit']) : $sort_array;

                // and finally the grouping
                $keys = array('name', 'id', 'title', 'abstract');
                if ($opt['group']) {
                    $sorted_results = $this->mgroup($sort_array, $keys, $group_opts);
                } else {
                    $sorted_results = $this->mgroup($sort_array, $keys);
                }
                $renderer->doc .= $this->_render_list($sorted_results, $opt);
            } else {
                if ( ! $opt['nomsg']) {
                    $renderer->doc .= $this->_render_no_list($query, $message);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    private function _adjusted_height($sorted_results, $ratios) {
        // ratio of different heading heights (%), to ensure more even use of columns (h1 -> h6)
        foreach ($sorted_results as $row) {
            $adjusted_height += $ratios[$row[0]];
        }
        return $adjusted_height;
    }

    /**
     * Render a simple "no results" message
     *
     * @param string $query => original query
     * @return string
     */
    private function _render_no_list($query, $error = '') {
        $render = '<div class="pagequery noborder">' . DOKU_LF;
        $render .= '<p class="noresults"><span>pagequery</span>' . sprintf($this->getLang("no_results"),
                                  '<strong>' . $query . '</strong>') . '</p>' . DOKU_LF;
        if ( ! empty($error)) {
            $render .= '<p class="noresults">' . $error . '</p>' . DOKU_LF;
        }
        $render .= '</div>' . DOKU_LF;
        return $render;
    }

    /**
     * Render the final pagequery results list as HTML, indented and in columns as required
     *
     * @param array  $sorted_results
     * @param int    $cols
     * @param bool   $proper
     * @param string $snippet
     * @param string $border
     * @return string => HTML rendered list
     */
    private function _render_list($sorted_results, $opt) {
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
        $snippet_cnt = 0;    // needed by the snippet section for tracking
        $is_first = true;

        // basic result page markup (always needed)
        $outer_border = ($opt['border'] == 'outside' || $opt['border'] == 'both') ? '' : ' noborder';
        $no_table = ( ! $multi_col) ? ' notable' : '';
        $top_id = 'top-' . mt_rand();   // fixed anchor point to jump back to at top of the table
        $render .= '<div class="pagequery' . $outer_border . $no_table . '" id="' . $top_id . '">' . DOKU_LF;
        if ($opt['label'] != '') $render .= '<h1 class="title">' . $opt['label'] . '</h1>' . DOKU_LF;
        if ($multi_col) $render .= '<table><tbody><tr>' . DOKU_LF;

        $inner_border = ($opt['border'] == 'inside' || $opt['border'] == 'both') ? '' : ' class="noborder" ';

        // now render the pagequery list
        foreach ($sorted_results as $line) {
            list($level, $name, $id, $title, $abstract) = $line;
            $display = ($opt['useheading']) ? $title : $name;

            $is_heading = ($level > 0);
            if ($is_heading) $heading = $name;

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
                $prev_was_heading = true;    // needed to correctly style page link lists <ul>...
                $cur_height = 0;
            }
            // finally display the appropriate heading or page link(s)
            if ($is_heading) {
                // close previous sub list if necessary
                if ( ! $prev_was_heading) {
                    $render .= '</ul>' . DOKU_LF;
                }
                if ($opt['proper'] == 'header' || $opt['proper'] == 'both') $heading = $this->_proper($heading);
                $render .= "<h$level$indent_style>$heading</h$level>" . DOKU_LF;
                $prev_was_heading = true;
                $cont_level = $level + 1;
            } else {
                // open a new sub list if necessary
                if ($prev_was_heading || $is_first) {
                    $render .= "<ul$indent_style>";
                }
                // deal with normal page links
                if ($opt['proper'] == 'name' || $opt['proper'] == 'both') $display = $this->_proper($display);
                $link = $this->_html_wikilink($id, $display, $snippet_cnt, $abstract, $opt);
                $snippet_cnt++;
                $render .= $link;
                $prev_was_heading = false;
            }
            $cur_height += $ratios[$level];
            $is_first = false;
        }
        $render .= '</ul>' . DOKU_LF;
        if ($multi_col) $render .= '</td></tr></tbody></table>' . DOKU_LF;
        $render .= '<a class="top" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>' . DOKU_LF;
        $render .= '</div>' . DOKU_LF;

        return $render;
    }

    /**
     * Renders the page link, plus tooltip, abstract, casing, etc...
     * @param string $id
     * @param bool  $proper
     * @param bool  $title
     * @param mixed $snippet
     * @param int   $snipet_cnt
     * @param bool  $underline
     */
    private function _html_wikilink($id, $display, $snippet_cnt, $abstract, $opt) {

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
        return "<li$noborder>" . $link . $inline . '</li>' . DOKU_LF . $after;
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
     * @param array     $ids        array of page ids to be sorted
     * @param array     $sortkeys   list of array keys to sort by
     * @param bool      $title      use page heading instead of name for sorting
     * @param bool      $proper     use proper case where possible
     * @param bool      $inwords    show dates in words where possible
     * @param bool      $case       honour case when sorting
     * @param bool      $natsort    natural sorting, the human way
     *
     * @return array    $sort_array array of array(one value for each key to be sorted)
     *                   $sort_opts  sorting options for the msort function
     *                   $group_opts grouping options for the mgroup function
     */
    private function _build_sorting_array($ids, $get_abstract, $opt) {
        global $conf;

        $sort_opts = array();
        $group_opts = array();

        $dformat = array();
        $wformat = array();
        $extrakeys = array();

        $row = 0;

        // any extra columns needed for filtering are added also!
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
            $sort_array[$row]['title'] = ($opt['useheading'] && isset($meta['title'])) ? $meta['title'] : $name;

            // fourth column: cache the page abstract if needed; this saves a lot of time later
            // and avoids repeated slow metadata retrievals (v. slow!)
            $sort_array[$row]['abstract'] = ($get_abstract) ? $meta['description']['abstract'] : '';

            // cache of full date for this row
            $real_date = 0;

            foreach ($col_keys as $key => $_void) {
                switch ($key) {
                    case 'a':
                    case 'ab':
                    case 'abc':
                        $value = $this->_first($name, strlen($key));
                        break;
                    case 'name':
                    case 'title':
                        // a name/title column already exists by default (col 1)
                        continue 2; // move on to the next key
                    case 'id':
                    case 'page':
                        $value = $id;
                        break;
                    case 'ns':
                        $value = getNS($id);
                        if (empty($value)) $value = '[' . $conf['start'] . ']';
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
                                if ($opt['inwords']) {
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
                        case 'page':
                        case 'id':
                        case 'name':
                        case 'title':
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
            $is_ns = false;
            switch ($key) {
                case 'mdate':
                case 'cdate':
                    $type = self::MSORT_NUMERIC;
                    break;
                default:
                    if ($case) {
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
                case 'page':
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
            $regexes[] = '.*' . $ns . ':.*';
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
            $sort_array = array_filter($sort_array, function($row) use ($metakey, $expr) {
                return preg_match('`' . $expr . '`', $row[$metakey]) > 0;
            });
        }
        return $sort_array;
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

        // Sort the data and get the result.
        $result = $sort_func (
            $sort_array,
            function(array &$left, array &$right) use(&$sort_opts, $keys) {

                // Assume that the entries are the same.
                $cmp = 0;

                // Work through each sort column
                foreach($keys as $idx => $key) {
                    // Handle the different sort types.
                    switch ($sort_opts['type'][$idx]) {
                        case MSORT_NUMERIC:
                            $key_cmp = ((intval($left[$key]) == intval($right[$key])) ? 0 :
                                       ((intval($left[$key]) < intval($right[$key])) ? -1 : 1 ) );
                            break;

                        case MSORT_STRING:
                            $key_cmp = strcmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case MSORT_STRING_CASE: //case-insensitive
                            $key_cmp = strcasecmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case MSORT_NAT:
                            $key_cmp = strnatcmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case MSORT_NAT_CASE:    //case-insensitive
                            $key_cmp = strnatcasecmp((string)$left[$key], (string)$right[$key]);
                            break;

                        case MSORT_REGULAR:
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
                    $cmp = $key_cmp * (($sort_opts['dir'][$idx] == MSORT_DESC) ? -1 : 1);

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
                for ($i= 0; $i < count($cur_ns); $i++) {
                    if ($cur_ns[$i] != $prev_ns[$i]) {
                        $hl = $level + $i + 1;
                        $results[] = array($hl , $cur_ns[$i], '');
                    }
                }
            }
        }
    }
}
?>
