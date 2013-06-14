i18nDataCollection
==================

Could we have a select box with all countries on this form? In the language currently chosen? Oh, and it must really be up-to-date, got that? How do you mean, this tiny select box is going to take you all day? 

This will be one of these days where the intern spends all of his time on 
[Wikipedia](https://en.wikipedia.org/wiki/List_of_sovereign_states), 
[CLDR](http://cldr.unicode.org/), 
the 
[CIA Factbook](https://www.cia.gov/library/publications/the-world-factbook/) 
and 
[Geonames](http://www.geonames.org/) 
to update and verify your three year old database of country names. And this time you will also need the days and the months in the letter form. Internationalized of course.

The *i18n Data Collector* addresses this problem by collecting the required data from multiple resources. It puts all in a tiny 
[SQLite](https://www.sqlite.org/) 
data base and you can take it from there. If you don't have already a manager for SQLite, the Firefox extension
[SQLite manager](https://code.google.com/p/sqlite-manager/) 
is a good choice.

This project is far from being complete but it is a good starting point. For now it does country names and continents in a variety of languages, because this is what I needed. You are more than welcome to extend it to whatever suits your needs.


## Using the code

*i18n Data Collector* is not meant to be used in a production environment. The idea is to use the generated database either directly or to build resources from the database that are suitable for your project.


## Extending the code

For now all code is one PHP class only. It had to be written quickly so I didn't think much of a fancy architecture. It doesn't have to have to stay this way, so if you want to make this a 15-file component with *namespaces*, *interfaces* and *abstracts*, *PSR-0* compatible and *composer* friendly, go for it.


## Known problems

The quality of the data depends on the quality delivered from third parties which in return themselves rely on third parties. Some queries, especially joined ones do not return a complete list of countries in certain languages. This might be in the code or in the data sources, I'm afraid you will have to figure out this by yourself.


## Requirements
You can just download the database and that's it. If you want to generate new data sets you will need the following:

- PHP 5.4 - In the current state PHP 5.3 could be supported by using `array()` instead of `[]` but I  most probably would not accept pull requests for this.
- A copy of the [CLDR repository](http://cldr.unicode.org/index/downloads)
- You must have an account at [geonames.org](http://www.geonames.org/login)
