====== pagequery plugin ======


===== Overview =====
There are many different page listing / navigation plugins on the [[:plugin]] page, all trying to serve different needs.  Pagequery tries to unify many of the ideas into a compact all-in-one method of listing your wiki pages, by name, title, a-z index, by date, by year, month, day, by namespace or by creator; ...and do it quickly.

On the surface it may appear to fill a similar role to the really excellent [[plugin:indexmenu|IndexMenu]], however pagequery's aspirations are not as lofty or elaborate.  IndexMenu focuses on displaying namespaces, and the pages within.  allowing you to sort the namespaces and files by many options, and actively navigate and manipulate this index.  Pagequery's purpose is __displaying lists of pages__: yes, you can sort by namespace if you wish, however the main goal is to search for and list pages in any order, and then neatly display them in groups (A-Z, by year, by month, etc...) and in addition in columns — to make best use of space.  If you try both of them you'll quickly see that they //"scratch different itches."//

**Features**
  * Create all sorts of indexes for your wiki: A-Z, recent, created by, authored by, start pages
  * Search all the pages in your wiki
  * Or limit your search to a specific namespace
  * Search for words in the page content as well--fulltext!
  * Search by page name using //regular expressions//
  * Sort lists by A-Z, by name, by year, by month, by day, by author, and many other options
  * Split your list into groups, with headers above each one: A..Z, 2010...2009, Jan..Dec, author's name
  * Display links based on the page's title instead of page name
  * See a useful content preview tool-tip when you hover over each page link
  * OR create a great blog-like start page, where links are followed by a neat snippet of the page content
  * Make full use of space: display your list of links in up to six columns
  * Put borders around the columns for clarity

Nothing worthwhile is built in isolation: pagequery has been built on the shoulders of giants — i.e. the DokuWiki core functions.  Under the skin it makes shamefaced use of the excellent built-in page and full-text [[:search]] functions available in DokuWiki, so there's no new search syntax to learn.  However it does bring a little something new to the mix by providing powerful sorting and grouping options.

Some possible uses:
  * Listing all the pages in your wiki in a nice A-Z index, or a By Year/Month index
  * A recently modified list of pages
  * Listing pages that discuss a particular subject (via full-text search)
  * Listing all pages created by a certain user

Here's an example of all the pages in a namespace listed by date modified, and grouped by year and month:

{{http://pagequery.googlecode.com/files/pagequery%20screenshot.png|An example of a navigation page}}

===== Syntax and Usage =====
Insert the pagequery markup in a page wherever you want your list to appear. You can have more than one ''pagequery'' list on one page((works well with the [[plugin:columns|columns plugin]])).

It could be simple:
  {{pagequery>}}

Or complicated:
  {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=10;cols=2;inwords;proper}}

Or just plain ol' __too__ complicated:
  {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=100;cols=6;inwords;proper;snippet=5;border=inside;nostart;case;natsort}}

Yes, the list below is ridiculously long and looks thoroughly intimidating---hopefully if you persevere and start learning how to use it you'll soon be asking if it can do this, or do that, so that you can adapt ''pagequery'' to suit your particular needs...as many people have on the [[plugin:pagequery:discussion|discussion]] page.  Well, it is pretty flexible, but all these options need to be set somewhere, hence the long list below.

The different options are separated by semi-colons (;), values (e.g. within the sort) are separated by comma (,); multi-part values by colon (:).

==== Complete Syntax Table ====

^Option  ^Description  ^Syntax Examples  ^Default  ^
^query  |Search expression go directly after the ''>'', e.g. %%{{ pagequery>help;... }}%% \\ By default it searches only the page names (or page ID); to search within the text of wiki pages use the ''fulltext'' option below  |* \\ help \\ test |  //all pages//  |
|       |You can use //regular expressions//((AKA: regex; just Google for 'help regular expressions' if you are interested in using this powerful feature))in page name queries. To see all pages use * or .* , or just leave it blank.((as long as you are not filtering by namespaces: see next note!)) |''^p.+'' \\ [words starting with ''p'']  |  |
|       |//**Note:** ''*'' by itself is just a convenience, in any other regex you'll have to stick to the regex rules.//  |  |  |
^namespaces  |Page name search accepts the same namespace options as [[:search|fulltext search]], that is: ''^ or -ns:'' means exclude, ''@ or ns:'' means include  |''^:work @:home:games *''  |  //none//  |
|       |Relative (.) and parent (..) [[:namespaces]] are also supported((from ver.0.7.3 onwards, follow the syntax presented on that page from now on.))  |||
|       |//**Note:** if you use the namespace option then you must provide a page name query also (at least ''*''), because the regex function cannot distinguish between ''^work'' (namespace), and ''^work'' (page names beginning with "work") //  |  |  |
^fulltext  |Use a full-text search, allowing all DokuWiki [[:search]] options.  This option allows you to do all sorts of elaborate searches: check out the [[:search]] page for details  |  |  |
^sort  |Keys to sort by, in order of sorting. Each key can be followed by a preferred sorting order, ''sort=key:direction,key2:direction''  |''sort=a:asc,name:asc'' \\ ''sort=cyear,cmonth,name''  |  //none//  |
|sort **keys**:     ||||
|a, ab, abc         |By 1st letter, 2-letters, or 3-letters  |''sort=a'' \\ ''sort=ab''  | |
|name               |By page name((without namespace)) or by page title if enabled((see ''title'' option below)) //[not grouped]//  |''sort=name''  |  //page name if title is missing//  |
|page, id           |By full page id, including namespace //[not grouped]//  |''sort=id:asc''  |  |
|ns                 |By namespace (without page name) |''sort=ns''
|mdate, cdate       |By modified/created dates (full) //[not grouped]//|''sort=mdate''  |  |
|m[year][month][day]|By [m]odified [year][month][day]; any combination accepted  |''sort=myearmonthday'' \\ ''sort=mmonthday''  |  |
|c[year][month][day]|By [c]reated [year][month][day]; any combination accepted  |''sort=cyearmonthday''  |  |
|creator            |By page author|''sort=creator''  |  |
|contrib[utor]        |By page contributor(s).  Note: only first name affects sort order((not so useful, but handy for use in the ''filter'' option below))|''sort=contributor:asc \\ sort=contrib''  |  |
|sort **directions:**  ||||
|asc, a             |Ascending, e.g. a -> z, 1 -> 10 |''sort=name:asc''  |  //asc//  |
|desc, d	        |Descending, e.g. z -> a, 10 -> 1 |''sort=mdate:desc''  |  |
|        |//Note: dates default to a descending sort (most recent date at top), text to ascending sort (A - Z)//|  |  |
^filter  |Filter the result list by any of the above sort **keys**, using regular expressions  |  |  |
|        |Syntax is similar to the ''sort'' above: ''filter=<key>:<expression>,<key2>:<expression2>''  |''filter=creator:harry'' \\ ''filter=contrib:.*(harry|sally).*'' |  |
^group   |Show a group header for each change in sort keys.((For a more detailed explanation of grouping see the note after this table))  |''group''  |  //not grouped//  |
|        |For example, if you sorted by [myear] (i.e. modified year) then a group header will \\ be inserted every time the year changes (2010...2009...2008...etc...)  |||
|        |Namespaces are grouped by all sub-namespaces up to the ''maxns'' limit((see below))  |||
|        |Note: keys that are all unique cannot be grouped (i.e. name, page/id, mdate, cdate)  |||
^limit   |Maximum number of results to return.  0 = return all (default)  |''limit=10''|  //all//  |
^inwords |Use real text month and day names instead of numeric dates  |''inwords''  |  //numeric dates//  |
^cols    |Number of columns in displayed list (max = 6)  |''cols=3''  |  1  |
^proper  |Display page names and/or namespaces in Proper Case (i.e. every word capitalised, and no _'s). |  |  //none//  |
|        |Display page names in proper-case  |''proper=name''  |  |
|        |Display group headers in proper-case  |''proper=header'' \\ ''proper=hdr''  |  |
|        |Both the above options!  |''proper=both'' \\ ''proper''  |  |
|        |//Note: this is different from the ''UseHeading'' option in DokuWiki and many other plugins (see ''title'' option below)//|  |  |
^border  |Show a border around table columns |  |  //none//  |
|none    |do not show any borders (default)  |''border=none''  |  |
|inside  |show borders between the columns only  |''border=inside''  |  |
|outside |show a border around the whole pagequery table  |''border=outside''  |  |
|both    |show borders around both table and columns  |''border=both'' \\ ''border''  |  |
^nostart |Ignore any default //start// pages in the namespace(s).  |''nostart''  |  //show start pages//  |
|        |//Note: start pages **must** be named as per the start setting in your configuration for this option to work correctly!//  |||
^fullregex  |Allows you to search the full page id (i.e. including its namespace) using regular expressions.  This is a //raw// power-user mode...  |''fullregex''  |  |
^useheading((was ''title'', which will continue to work (for now).  Useheading is the standard Dokuwiki description hence more obvious))   |Sort by and display page's 1st heading rather than its name(relies on Dokuwiki's [[config:useheading]] option; also known as its 'title')   |''useheading''  |  //page name//  |
|        |//Note: this only works well if you have put a title heading on every page; where this is missing the proper-case page name will be used instead//  |  |  |
^snippet((was ''abstract'', which will continue to work for the next few releases))|Controls how the page snippet (preview) is displayed:  |  |  //tooltip//  |
|tooltip |As a pop-up/tool-tip on each page link  |''snippet=tooltip'' \\ ''snippet''  |  |
|        |The next three options use the following syntax: <format>,<count>,<extent> |||
|        |<format>: one of the formats listed below: //inline, plain, quoted//  |||
|        |<count>: to show 1st <count> items in list with an preview  ||  //all//  |
|        |<extent>: can be choice of chars, words, lines, or find (c? w? l? ~????)  ||  //all//  |
|inline  |show in-line with the link  |''snippet=inline,5,c20''  |  |
|plain   |show as simple text below the link (mimimal formatting)  |''snippet=plain,10,w30''  |  |
|quoted  |show in tidy quotation box  |''snippet=quoted,3,l3''  |  |
^maxns   |The number of namespace levels to display.  |  |  //show all//  |
|        |Display no more than 3 namespace levels, e.g. one:two:three  |''maxns=3''  |  |
|        |''maxns=0'' => show all levels (default)  |  |  |
^case    |Honour case when sorting, i.e. a..z then A..Z  |''case''  |  //case insensitive//  |
^natsort |Use PHP's natural sorting functions, e.g. ''1,2,10,12'' rather than ''1,10,12,2''  |''natsort''  |  //normal sorting//  |
^underline  |show a faint underline between each link for clarity  |  ''underline''  |  //none//  |
^label   |A label to be displayed at the top of the PageQuery list/table  |''label=A-Z of All Pages''  |  //nothing//  |
^  //Note: All options are optional, and left to its own devices the plugin will default to \\ a long, boring, 1-column list... so you might want to take charge!//  ||||

==== Sorting and Grouping ====
A few pointers about sorting and grouping successfully.  Pagequery offers many sorting options, most of which are designed to be grouped.  So if you intend to group your list by its main headers then it makes the most sense to sort from the broadest category to the narrowest.

For example: ''year => month => name''.  The sorting algorithm will first sort the list of pages by ''year'', then the pages within //each// year will be sorted by ''month'', and finally the pages within //each// month will be sorted by ''name''.  Sorting the other way around would not make much sense, as all names are unique: hence there would be nothing to "group"!

If you grouped the above sort it would result in the following arrangement:
  * Year
    * Month
      * Name
      * Name
    * Month2
      * Name
      * Name
      * etc...

The basic rule is: __start with the least specific and work your way to the most specific options.__


===== FAQ and Tips =====
:?: I've added new pages but they do not show up in my nice new pagequery list.  Why?
  * You need to turn off page caching for the page containing the list.  Put  <code> ~~NOCACHE~~ </code>  somewhere on the page and you should then see instant updates.

:?: I have many default 'start' pages in my wiki and I don't want to see them when I list the contents of namespaces; how can I make them disappear?
  * Just add the ''nostart'' option.  Make sure that the //start// option in your configuration corresponds to the start page name you are using!

:?: I would much rather see the page title instead of the page name.  How can I enable that?
  * Put the 'title' option somewhere in your pagequery markup
===== Examples =====
For example, if you want to list all the pages in a certain namespace by A-Z, the following should do the trick:
  {{pagequery>@namespace;fulltext;sort=a,name;group}}           [fulltext version]
Or:
  {{pagequery>@namespace *;sort=a,name;group;proper;cols=2}}    [pagename version: allows regex's]

This would retrieve results from @namespace only (as there is no other search query you would get all the pages), and the list would be sorted by the //first letter// ('sort=a') then alphabetically ('sort=name') within each letter.  The //group// option will then cause the list to be grouped by the first letter only (you cannot group by name as each one is unique).

If you wanted to see the results in 3 columns and to have the links in "Sentence Case" with no underscores, then add this:

  {{pagequery>@namespace *;sort=a,name;group;cols=3;proper}

Another example, grouping by //year created//, then //month created//, then by //name//, in 2 columns, and displaying the real month name, plus having the links in "Sentence Case" with no underscores, and to top it off: a border around the table columns:

  {{pagequery>@namespace *;sort=cyear,cmonth,name;group;inwords;proper;cols=2;border}}

The same query, but now searching for pages contenting the word "help" (NOTE: ''fulltext'' means search in the page "content" not just its "name":

  {{pagequery>@namespace help;fulltext;sort=cyear,cmonth,name;group;inwords;proper;cols=2}}

**Update:** Namespaces are now supported when searching by pagename (pageid) only.  Use the same syntax as fulltext [[:search]], i.e. @namespace|^namespace.  I haven't provide support for relative namespaces yet.  In addition, you can use regular expressions when searching by pagename.

E.g. Search for all pages in the "drafts" namespace, listing only files beginning with a number, sorted by name:

  {{pagequery>@drafts [0-9]+.*;sort=name}}

Or, all files in the "happy:go:lucky" namespace, sorted by year, then date created, in proper case, in 2 columns, and use the page title, not the name in the listing:

  {{pagequery>@happy:go:lucky *;sort=cyear,cdate;group;proper;cols=2;title}}

Hopefully these examples will help to understand the workings of pagequery.


