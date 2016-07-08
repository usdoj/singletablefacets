# Single Table Facets

This class is intended as a simple faceted search solution for PHP applications where the data source is a single MySQL table. It does not require any joins or relationships. If you want faceted search, but you want the data source to be as simple as an Excel spreadsheet imported into a MySQL database, this class should help.

## Dependencies

* PHP/MySQL
* jQuery
* Composer

## Installation

Use composer to bring this into your PHP project. The composer.json should look like this:

```
{
    "require": {
        "usdoj/singletablefacets": "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/usdoj/singletablefacets.git"
        }
    ],
    "minimum-stability": "dev"
}
```

After creating a composer.json similar to the above, do a "composer install".

## Usage

To use the library you need to include the autoloader. For an example of this, see docs/example.index.php. The various parts of the page can be rendered individually using these methods: renderStyles(), renderJavascript(), renderFacets(), renderKeywordSearch(), renderResults(), and renderPager().

## Database table

It is up to you to create the database table that you will be using. Some notes:

1. The column names of the database should be the same as the headers (first row) that will be in the Excel/CSV source file.
2. At least one column must be set in MySQL as a unique index. If the data does not naturally have any unique columns, add an auto-increment column to the database and set it as a unique index.
3. For keyword searches you must add 2 columns to the database. These columns should be able to hold a lot of text. (Recommend using the "longtext" column type.) The names of the columns must be:
    * stf_doc_keywords
    * stf_data_keywords

## Importing source data

The library includes a command-line tool for re-importing data from a CSV file. That tool can be run with:
```
./vendor/bin/singletablefacets [path-to-config-file] [path-to-source-data]
```
Note that the source data file must be a CSV file.

Tip: You'll probably usually be getting the CSV file from an XLS file. Since Excel has a problem with special characters, a useful command-line tool is "xls2csv" from the "catdoc" library. To install:

* Linux: `apt-get install catdoc`
* Babun: `pact install catdoc`

When using xls2csv, to ensure you don't get encoding issues, specify the destination encoding like so:
```
xls2csv -d utf-8 file.xls > file-utf-8.csv
```

## Configuration

The library depends on configuration in a separate YAML file. See singletablefacets.yml.dist for an example. Here is that example config:
```
# Database credentials
database name: myDatabase
database user: myUser
database password: myPassword
database host: localhost

# Per the name of this library, we look at only a single table.
database table: myTable

# Indicate the columns that are required to have data in order for a row to
# appear in the search results. For example, if you don't want any rows to show
# up with titles, make your title column a required column here.
required columns:
    - myDatabaseColumn1

# Choose the order that you would like the columns to show in the facet list,
# and indicate the human-readable labels to display above each one.
facet labels:
    myDatabaseColumn1: Filter by something
    myDatabaseColumn2: Filter by something else

# Choose the order that you would like the fields to show in search results,
# and indicate the human-readable labels to display above each one.
search result labels:
    myDatabaseColumn2: Something
    myDatabaseColumn1: Something Else

# Choose the priority (order) and default direction for the sortable columns.
# ASC = ascending, DESC = descending
sort directions:
    myDatabaseColumn1: ASC
    myDatabaseColumn2: DESC

# List the columns that contain keywords in the database.
keywords in database:
    - myDatabaseColumn1

# List the columns that contain URLs pointing to files with keywords.
keywords in files:
    - myDatabaseColumn2

# List the columns that should be output as links, using another columns to
# get the destination URLs.
output as links:
    # Link label : Link URL
    myDatabaseColumn1: myDatabaseColumn2

# List the facet columns that should be collapsed at a certain point. Use 0 to
# collapse all items, or for example, 5 to collapse items in excess of 5.
collapse facet items:
    myDatabaseColumn1: 0
    myDatabaseColumn2: 5

# List the columns that should function as additional values for another facet.
# For example, if you have a Tag and Tag2 column, you could indicate that Tag2
# is just additional values for Tag, and they would both appear together.
# This is the ONLY way to give one item multiple values in a single facet.
columns for additional values:
    # Extra column: Main column
    myDatabaseColumn1: myDatabaseColumn2

# List the facet columns that depend on other facets. For example, if you don't
# want "Sub Category" to appear unless "Category" is active, you can set that
# here. This is the only way to imitate a hierarchical setup, and works well
# with "show dependents indented to the right" below.
dependent columns:
    # Child column: Main column
    myDatabaseColumn1: myDatabaseColumn2

# Indent dependents to the right and hide their titles. This gives the effect
# that they are being shown in a hierarchical way.
show dependents indented to the right: true

# List the HTML table columns you would like to give a minimum width (CSS).
# This can be used to cut down on undesirable text wrapping.
minimum column widths:
    myDatabaseColumn1: 75px

# Do not consider keywords shorter than this number.
minimum valid keyword length: 3

# Next to facet items, show the totals in parenthesis.
show counts next to facet items: true

# Choose the text for the keyword search button.
search button text: Search

# Choose the text for the message that shows when there are no results.
no results message: |
    <p>
        Sorry, no results could be found for those keywords.
    </p>

# Display the facet items as checkboxes instead of links.
use checkboxes for facets instead of links: true

# For each page of results, show this many results.
number of items per page: 20

# In the pager, show direct links to this many pages. (Besides the normal
# "Next" and "Previous" buttons.)
number of pager links to show: 5

# Show this blurb in an expandable section beneath the keyword search.
keyword help: |
    <ul>
        <li>Use the checkboxes on the left to refine your search, or enter new keywords to start over.</li>
        <li>Enter multiple keywords to get fewer results, eg: cat dogs</li>
        <li>Use OR to get more results, eg: cats OR dogs</li>
        <li>Put a dash (-) before a keyword to exclude it, eg: dogs -lazy</li>
        <li>Use "" (double-quotes) to match specific phrases, eg: "the quick brown fox"</li>
    </ul>

# Users will click this label to expand the help text above.
keyword help label: "Need help searching?"

# The items within a given facet are normally sorted alphabetically, but setting
# this to true will sort them by their counts, in descending order.
sort facet items by popularity: false

# When crawling remote URLs for keywords, add this prefix to any relative URLs.
# For example, if this is set to: "http://example.com/files/", then a relative
# URL of "mydoc.pdf" will be fetched from "http://example.com/files/mydoc.pdf".
prefix for relative keyword URLs: http://example.com/files/

# Normally keywords are processed to remove common words and such. This is a
# good thing in general, but if you expect your users to be searching for
# specific phrases/sentences that are in the documents, you might want to set
# this to false. For example, if this is set to false, and you are indexing a
# PDF that contains "The brown fox jumped over the lazy dog", it would have
# keyword data that is saved in exactly the same way: "The brown fox jumped over
# the lazy dog". However, if this is set to true, the keywords might be saved
# as "brown fox jumped lazy dog". So if someone were searching for that exact
# phrase, the desired result would not show up.
remove common keywords: true

# Normally when users do a keyword search, the full text (crawled) data is
# included. However if you would like to exclude the full text by default, and
# give the user the option to include it, set this to true.
allow user to exclude full text from keyword search: false

# If the environment needs to use a proxy, uncomment and fill out this section.
# proxy: 192.168.1.1:8080
# To prevent the use of the proxy for certain URLs, enter partial patterns here.
# proxy exceptions:
#    - .example.com

# If there are any special characters or phrases that need to be altered when
# importing the data from the CSV file, indicate those here. For example, to
# change all occurences of § with &#167; uncomment the lines below.
#text alterations:
#    "§": "&#167;"
```