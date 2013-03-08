PageQuery Plugin
======


Overview
------
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

"http://pagequery.googlecode.com/files/pagequery%20screenshot.png"An example of a navigation page

Syntax and Usage
------

Insert the pagequery markup in a page wherever you want your list to appear. You can have more than one ''pagequery'' list on one page((works well with the [[plugin:columns|columns plugin]])).

It could be simple:
  {{pagequery>}}

Or complicated:
  {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=10;cols=2;inwords;proper}}

Or just plain ol' __too__ complicated:
  {{pagequery>[query];fulltext;sort=key:direction,key2:direction;group;limit=100;cols=6;inwords;proper;snippet=5;border=inside;nostart;case;natsort}}


Examples
------

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


