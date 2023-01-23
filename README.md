# DataFeedParser

This is a small datafeed parser project I was tinkering with while working at my last job that involved a legacy parser system.

Here I was testing various methods to parse large XML's quickly while performing various functions like:
  - Each feed folder has its own config.json which the following features:
    - SkipEmptyFields
    - SkipFields[]
    - RenameFields[{key, value}]
  - Callback function support, attach an anonymous callback function to a specific node in the XML. This allows custom functions to be carried out
  per xml feed, with the idea to eventually tie into a DB class to perform update/compare functions to stored data.
